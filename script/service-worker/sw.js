const CACHE_PREFIX = "phpgt-offline-";
// Retire incomplete refresh metadata from earlier implementations.
const CACHE_NAME = CACHE_PREFIX + "v5-" + (new URL(self.location).searchParams.get("v") || "development");
let refreshController;
let refreshCheck = Promise.resolve();
const REFRESH_INTERVAL = 60 * 60 * 1000;
const REFRESH_TIMESTAMP = new URL("/__offline-refresh-started", self.location.origin).href;
let networkOffline = false;

async function reportConnection(offline) {
	if(networkOffline === offline) return;
	networkOffline = offline;
	for(const client of await self.clients.matchAll({type: "window"})) {
		client.postMessage({type: "connection-status", offline});
	}
}

function pauseRefresh() {
	refreshController?.abort();
	refreshController = null;
}

function cacheURL(value, base = self.location.origin) {
	const url = new URL(value, base);
	if(url.origin !== self.location.origin) return null;
	url.hash = "";
	if(url.pathname.startsWith("/asset/") || ["/style.css", "/script.js"].includes(url.pathname)) {
		return url;
	}
	const path = url.pathname.replace(/\/$/, "") + "/";
	if(path === "/search/" || path.startsWith("/api/")
		|| ["/service-worker.js/", "/__offline-refresh-started/"].includes(path)) return null;
	if(url.search) {
		if(path !== "/releases/" || !/^\?page=\d+$/.test(url.search) || Number(url.searchParams.get("page")) > 100) return null;
	}
	url.pathname = path;
	return url;
}

async function store(cache, request, event, onStored) {
	let response = await fetch(request);
	const networkResponse = response;
	const finalURL = response.url ? cacheURL(response.url) : null;
	if(response.ok && (!response.redirected || finalURL) && response.type !== "opaque"
		&& !/no-store|private/i.test(response.headers.get("Cache-Control") || "")) {
		const key = cacheURL(request.url)?.href || request.url;
		// Keep the HTTP redirect at the alias, and the final document at its own URL.
		if(response.redirected) {
			const headers = new Headers(response.headers);
			headers.delete("Content-Encoding");
			headers.delete("Content-Length");
			response = new Response(response.clone().body, {status: response.status, statusText: response.statusText, headers});
		}
		const alias = response !== networkResponse && finalURL?.href !== key;
		const writes = [cache.put(key, alias ? Response.redirect(finalURL.href, 302) : response.clone())];
		if(finalURL && finalURL.href !== key) writes.push(cache.put(finalURL.href, response.clone()));
		const saved = Promise.all(writes);
		// Stream foreground HTML/CSS immediately instead of waiting for cache.put.
		if(event) event.waitUntil(saved.catch(() => {}));
		else await saved;
		if(finalURL) onStored?.(finalURL.href);
	}
	return event ? networkResponse : response;
}

async function checkPage(value) {
	const url = cacheURL(value);
	if(!url) return {changed: false};
	const cache = await caches.open(CACHE_NAME);
	const previous = await cache.match(url.href);
	const response = await fetch(new Request(url.href, {credentials: "same-origin", cache: "no-cache"}));
	if(!response.ok || !response.headers.get("Content-Type")?.includes("text/html")
		|| /no-store|private/i.test(response.headers.get("Cache-Control") || "")) return {changed: false};
	const destination = cacheURL(response.url);
	if(!destination) return {changed: false};
	const html = await response.text();
	const previousHTML = previous ? await previous.text() : null;
	const headers = new Headers(response.headers);
	headers.delete("Content-Encoding");
	headers.delete("Content-Length");
	await cache.put(destination.href, new Response(html, {status: response.status, headers}));
	if(destination.href !== url.href) await cache.put(url.href, Response.redirect(destination.href, 302));
	return {previousHTML, html, url: destination.href};
}

async function refreshChangedPage(value) {
	const url = cacheURL(value);
	if(!url) return {ok: false};
	pauseRefresh();
	await refreshCheck.catch(() => {});
	const cache = await caches.open(CACHE_NAME);
	// The page has compared static content, excluding its data-live elements.
	await cache.put(REFRESH_TIMESTAMP, new Response(JSON.stringify({
		startedAt: Date.now(), completed: false, refreshed: [url.href], sitemap: null,
	})));
	return {ok: true};
}

self.addEventListener("install", event => {
	event.waitUntil(self.skipWaiting());
});
self.addEventListener("activate", event => {
	event.waitUntil((async () => {
		await Promise.all((await caches.keys())
			.filter(key => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
			.map(key => caches.delete(key)));
		await self.clients.claim();
	})());
});

self.addEventListener("fetch", event => {
	// Never cache POSTs, live search, APIs, query-based actions, or third-party traffic.
	if(event.request.mode === "navigate") pauseRefresh();
	if(event.request.method !== "GET") return;
	const url = cacheURL(event.request.url);
	if(!url) return;
	const response = (async () => {
		const cache = await caches.open(CACHE_NAME);
		const cached = await cache.match(url.href);
		// Scheduled refreshes happen after load; a cache hit needs no network request.
		if(cached) return cached;
		// Preserve the browser's stylesheet/font request metadata and priority.
		try {
			const response = await store(cache, new Request(event.request, {cache: "no-cache"}), event);
			event.waitUntil(reportConnection(false));
			return response;
		}
		catch(error) {
			event.waitUntil(reportConnection(true));
			throw error;
		}
	})();
	event.respondWith(response);
});

async function preload({pages = [], assets = []}, signal, state) {
	const cache = await caches.open(CACHE_NAME);
	const queue = [];
	const refreshed = new Set(state.refreshed);
	const seen = new Set();
	async function saveProgress() {
		state.refreshed = [...refreshed];
		await cache.put(REFRESH_TIMESTAMP, new Response(JSON.stringify(state)));
	}
	function enqueue(value, base) {
		try {
			const url = cacheURL(value.replaceAll("&amp;", "&"), base);
			if(url && !seen.has(url.href)) {
				seen.add(url.href);
				queue.push(url.href);
			}
		}
		catch { /* Ignore malformed links in imported documentation. */ }
	}

	// Fetch live responses even when these URLs are already cached.
	async function drain(includePageLinks = false) {
		while(queue.length && !signal.aborted) {
			await Promise.all(queue.splice(0, 2).map(async url => {
				try {
					// Read already-refreshed pages to rediscover assets from an interrupted queue.
					let cached = refreshed.has(url) ? await cache.match(url) : null;
					let destination;
					if(cached?.status === 302) {
						destination = cached.headers.get("Location");
						cached = await cache.match(destination);
					}
					const response = cached || await store(cache, new Request(url, {credentials: "same-origin", cache: "reload", signal}), null, finalURL => {
						destination = finalURL;
						refreshed.add(finalURL);
					});
					if(!response.ok) return;
					refreshed.add(url);
					const type = response.headers.get("Content-Type") || "";
					if(type.includes("text/html")) {
						const html = await response.text();
						for(const match of html.matchAll(/\b(?:src|href)\s*=\s*["']([^"']+)["']/gi)) {
							// Release pagination has an unbounded "next" link even on empty pages.
							if(new URL(url).pathname === "/releases/" && !html.includes("/releases/tag/")) continue;
							const linked = cacheURL(match[1].replaceAll("&amp;", "&"), destination || url);
							if(linked && (includePageLinks || linked.pathname.startsWith("/asset/") || ["/style.css", "/script.js"].includes(linked.pathname))) {
								enqueue(linked.href);
							}
						}
					}
					else if(type.includes("text/css")) {
						for(const match of (await response.text()).matchAll(/url\(\s*["']?([^\s"')]+)["']?\s*\)/gi)) {
							enqueue(match[1], url);
						}
					}
				}
				catch { /* Failed downloads can be retried on a later visit. */ }
			}));
			await saveProgress();
		}
	}
	pages.concat(assets).forEach(value => enqueue(value));
	await drain();
	// Only begin the sitemap stage after all linked pages and their assets finish.
	if(signal.aborted) return;
	await new Promise(resolve => {
		const finish = () => {
			clearTimeout(timer);
			signal.removeEventListener("abort", finish);
			resolve();
		};
		const timer = setTimeout(finish, 5000);
		signal.addEventListener("abort", finish, {once: true});
	});
	if(signal.aborted) return;
	// Retry failed first-stage URLs if the sitemap or another page lists them.
	for(const url of seen) {
		if(!refreshed.has(url)) seen.delete(url);
	}
	try {
		if(!state.sitemap) {
			const sitemap = await fetch("/sitemap.xml", {cache: "reload", signal});
			if(sitemap.ok) {
				state.sitemap = await sitemap.text();
				await saveProgress();
			}
		}
		for(const match of (state.sitemap || "").matchAll(/<loc>([^<]+)<\/loc>/g)) {
			// The sitemap uses the production host; reuse its paths on previews too.
			enqueue(new URL(match[1].replaceAll("&amp;", "&")).pathname);
		}
	}
	catch { /* Keep existing cached content if the sitemap is unavailable. */ }
	await drain(true);
	if(!signal.aborted && state.sitemap) {
		state.completed = true;
		await saveProgress();
	}
}

self.addEventListener("message", event => {
	if(event.data?.type === "check-page") {
		event.waitUntil(checkPage(event.data.url)
			.then(result => event.ports[0]?.postMessage(result))
			.catch(() => event.ports[0]?.postMessage({changed: false})));
		return;
	}
	if(event.data?.type === "page-changed") {
		event.waitUntil(refreshChangedPage(event.data.url)
			.then(result => event.ports[0]?.postMessage(result))
			.catch(() => event.ports[0]?.postMessage({ok: false})));
		return;
	}
	if(event.data?.type === "pause-preload") {
		pauseRefresh();
		return;
	}
	if(event.data?.type !== "preload") return;
	// A newer page replaces the old sweep rather than building an unbounded queue.
	pauseRefresh();
	refreshController = new AbortController();
	const {signal} = refreshController;
	// Serialize the check/write so simultaneous tabs cannot both start a sweep.
	refreshCheck = refreshCheck.catch(() => {}).then(async () => {
		if(signal.aborted) return;
		const cache = await caches.open(CACHE_NAME);
		const last = await cache.match(REFRESH_TIMESTAMP);
		let state;
		try { state = last ? JSON.parse(await last.text()) : null; }
		catch { /* Replace metadata from older workers. */ }
		if(!state?.startedAt || Date.now() - state.startedAt >= REFRESH_INTERVAL) {
			state = {startedAt: Date.now(), completed: false, refreshed: [], sitemap: null};
		}
		else if(state.completed) return;
		if(signal.aborted) return;
		await cache.put(REFRESH_TIMESTAMP, new Response(JSON.stringify(state)));
		if(!signal.aborted) await preload(event.data, signal, state);
	});
	event.waitUntil(refreshCheck);
});
