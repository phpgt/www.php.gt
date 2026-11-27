if("serviceWorker" in navigator && window.isSecureContext) {
	let timer;
	let generation = 0;
	let registration;
	let checkedView;
	let checkedURL;
	let bundleTimer;
	let bundleDue = 0;
	const bundleRefreshKey = "phpgt:pending-bundle-refresh";
	try { bundleDue = Number(sessionStorage.getItem(bundleRefreshKey)) || 0; }
	catch { /* The in-memory timer still works without session storage. */ }

	function scheduleBundleRefresh(worker, changed = false) {
		if(changed) bundleDue = Date.now() + 15000;
		if(!bundleDue) return;
		clearTimeout(bundleTimer);
		try { sessionStorage.setItem(bundleRefreshKey, String(bundleDue)); }
		catch { /* Keep the in-memory deadline. */ }
		bundleTimer = setTimeout(() => {
			if(!navigator.onLine) return;
			worker.postMessage({type: "refresh-bundle"});
			bundleDue = 0;
			try { sessionStorage.removeItem(bundleRefreshKey); }
			catch { /* Storage is optional. */ }
		}, Math.max(0, bundleDue - Date.now()));
	}

	function requestWorker(worker, message) {
		return new Promise(resolve => {
			const channel = new MessageChannel();
			const timeout = setTimeout(() => { channel.port1.close(); resolve({changed: false}); }, 120000);

			channel.port1.onmessage = event => {
				clearTimeout(timeout);
				channel.port1.close();
				resolve(event.data);
			};

			worker.postMessage(message, [channel.port2]);
		});
	}

	function pageText(html) {
		const page = new DOMParser().parseFromString(html, "text/html");
		return page.body.innerText;
	}

	async function checkPage(worker) {
		const result = await requestWorker(worker, {type: "check-page", url: location.href});
		const cachedText = result.previousHTML == null ? null : pageText(result.previousHTML);
		const liveText = result.html == null ? null : pageText(result.html);
		const changed =
			cachedText !== null && liveText !== null && cachedText !== liveText;

		if(changed) {
			let offset = 0;
			while(offset < Math.min(cachedText.length, liveText.length)
				&& cachedText[offset] === liveText[offset]) offset++;
			console.group("[Offline cache] Page text changed", result.url || location.href);
			console.log("Text lengths:", {cached: cachedText.length, live: liveText.length});
			console.log("First differing character:", offset);
			const start = Math.max(0, offset - 100);
			console.log("Cached context (escaped):", JSON.stringify(cachedText.slice(start, offset + 200)));
			console.log("Live context (escaped):", JSON.stringify(liveText.slice(start, offset + 200)));
			console.log("Full cached body.innerText:", cachedText);
			console.log("Full live body.innerText:", liveText);
			console.groupEnd();
		}

		return {...result, changed};
	}

	function pause() {
		generation++;
		clearTimeout(timer);
	}

	async function schedule() {
		pause();
		const current = generation;
		try {
			// load waits for CSS and scripts; fonts and Flux-inserted images can finish later.
			await Promise.all([...document.images]
				.filter(image => !image.complete && image.loading !== "lazy")
				.map(image => new Promise(resolve => {
					image.addEventListener("load", resolve, {once: true});
					image.addEventListener("error", resolve, {once: true});
				})));
			await document.fonts.ready;
			if(current !== generation) return;
			const loadedAt = Date.now();
			registration ||= navigator.serviceWorker.register(
				"/sw.js",
				{scope: "/", updateViaCache: "none"},
			);
			const active = await registration;
			await navigator.serviceWorker.ready;
			const pending = active.installing || active.waiting;
			if(pending && pending.state !== "activated") {
				await new Promise((resolve, reject) => {
					pending.addEventListener("statechange", () => {
						if(pending.state === "activated") resolve();
						if(pending.state === "redundant") reject(new Error("Service worker update failed"));
					});
				});
			}
			if(current !== generation) return;
			scheduleBundleRefresh(active.active || navigator.serviceWorker.controller);

			timer = setTimeout(async () => {
				if(current !== generation || !navigator.onLine) return;
				try {
					const view = document.querySelector("main")?.firstElementChild;
					if(view !== checkedView || location.href !== checkedURL) {
						checkedView = view;
						checkedURL = location.href;
						const result = await checkPage(active.active || navigator.serviceWorker.controller);
						if(current !== generation) return;
						if(result.changed) {
							// The worker has already updated the cache. Leave the current
							// document, form state and scroll position untouched.
							scheduleBundleRefresh(active.active || navigator.serviceWorker.controller, true);
						}
					}
					if(navigator.connection?.saveData) return;
					(active.active || navigator.serviceWorker.controller)?.postMessage({type: "preload"});
				}
				catch(error) {
					console.warn("Offline caching unavailable:", error);
				}
			}, Math.max(0, loadedAt + 5000 - Date.now()));
		}
		catch(error) {
			registration = null;
			console.warn("Offline caching unavailable:", error);
		}
	}

	document.addEventListener("flux:before-request", pause);
	document.addEventListener("flux:after-render", schedule);
	window.addEventListener("pagehide", pause);
	window.addEventListener("online", schedule);
	if(document.readyState === "complete") schedule();
	else window.addEventListener("load", schedule, {once: true});
}
