import("@phpgt/flux");
import("./page/_common.es6");
import("./page/index.es6");
import("./page/_component/docs-search.es6");
import("./page/_component/site-nav.es6");

if("serviceWorker" in navigator && window.isSecureContext) {
	let timer;
	let generation = 0;
	let registration;

	function pause() {
		generation++;
		clearTimeout(timer);
		navigator.serviceWorker.controller?.postMessage({type: "pause-preload"});
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
			const version = document.body.dataset.version || "development";
			registration ||= navigator.serviceWorker.register(
				`/service-worker.js?v=${encodeURIComponent(version)}`,
				{scope: "/", updateViaCache: "none"},
			);
			const active = await registration;
			await navigator.serviceWorker.ready;
			if(current !== generation) return;
			timer = setTimeout(() => {
				if(current !== generation || navigator.connection?.saveData || !navigator.onLine) return;
				const pages = [...new Set([location.href, ...[...document.querySelectorAll("a[href]")]
					.map(anchor => anchor.href)
					.filter(href => new URL(href).origin === location.origin)])];
				const assets = [...document.querySelectorAll('script[src], link[href], img[src]')]
					.map(element => element.src || element.href);
				(active.active || navigator.serviceWorker.controller)?.postMessage({type: "preload", pages, assets});
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

function showOffline(offline) {
	const notice = document.getElementById("offline-notice");
	if(notice) notice.hidden = !offline;
}

showOffline(!navigator.onLine);
window.addEventListener("offline", () => showOffline(true));
window.addEventListener("online", () => showOffline(false));
navigator.serviceWorker?.addEventListener("message", event => {
	if(event.data?.type === "connection-status") {
		showOffline(!navigator.onLine || event.data.offline);
	}
});
