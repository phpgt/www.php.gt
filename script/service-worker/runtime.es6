if("serviceWorker" in navigator && window.isSecureContext) {
	let timer;
	let generation = 0;
	let registration;
	let checkedView;
	let checkedURL;
	let visitView = document.querySelector("main")?.firstElementChild;
	let visitURL = location.href;
	const refreshGuardKey = "phpgt:automatic-page-refresh";
	let automaticRefreshURL = null;
	try {
		automaticRefreshURL = sessionStorage.getItem(refreshGuardKey);
		sessionStorage.removeItem(refreshGuardKey);
	}
	catch { /* If storage is unavailable, the write below prevents automatic reload. */ }

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

	function pageContentWithoutLiveElements(html) {
		const page = new DOMParser().parseFromString(html, "text/html");
		// CSRF credentials are generated per request, rather than page content.
		for(const element of page.querySelectorAll('[data-live], meta[name="csrf-token"], input[type="hidden"][name="csrf-token"]')) {
			element.replaceWith(page.createComment("data-live"));
		}
		return page.documentElement.outerHTML;
	}

	async function checkPage(worker) {
		const result = await requestWorker(worker, {type: "check-page", url: location.href});
		const changed = result.previousHTML != null && result.html != null
			&& pageContentWithoutLiveElements(result.previousHTML) !== pageContentWithoutLiveElements(result.html);
		if(changed) await requestWorker(worker, {type: "page-changed", url: result.url});
		return {...result, changed};
	}

	function pause() {
		generation++;
		clearTimeout(timer);
		navigator.serviceWorker.controller?.postMessage({type: "pause-preload"});
	}

	async function schedule() {
		pause();
		const current = generation;
		const currentView = document.querySelector("main")?.firstElementChild;
		if(currentView !== visitView || location.href !== visitURL) {
			automaticRefreshURL = null;
			visitView = currentView;
			visitURL = location.href;
		}
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
			const version = document.body.dataset.version || "development";
			registration ||= navigator.serviceWorker.register(
				`/sw.js?v=${encodeURIComponent(version)}`,
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
							const destination = new URL(result.url);
							if(destination.href !== automaticRefreshURL) {
								console.log("CHANGED CONTENT!");
								sessionStorage.setItem(refreshGuardKey, destination.href);
								destination.hash = location.hash;
								location.replace(destination.href);
								return;
							}
						}
					}
					if(navigator.connection?.saveData) return;
					const pages = [...new Set([location.href, ...[...document.querySelectorAll("a[href]")]
						.map(anchor => anchor.href)
						.filter(href => new URL(href).origin === location.origin)])];
					const assets = [...document.querySelectorAll('script[src], link[href], img[src]')]
						.map(element => element.src || element.href);
					(active.active || navigator.serviceWorker.controller)?.postMessage({type: "preload", pages, assets});
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
