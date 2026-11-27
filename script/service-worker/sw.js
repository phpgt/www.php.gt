const CACHE_PREFIX = "phpgt-offline-";
// Bundle imports use a fresh cache generation.
// Replaced during the asset build, independently of a cached page's version.
const CACHE_NAME = CACHE_PREFIX + "v8-__APP_BUILD__";
let cacheWrites = Promise.resolve();
let refreshCheck = Promise.resolve();
const REFRESH_TIMESTAMP = new URL("/__offline-refresh-started", self.location.origin).href;
let networkOffline = false;

async function reportConnection(offline) {
	if(networkOffline === offline) return;
	networkOffline = offline;
	for(const client of await self.clients.matchAll({type: "window"})) {
		client.postMessage({type: "connection-status", offline});
	}
}

// Serialize cache mutations so bundle imports cannot race live response writes.
function writeCache(cache, key, response, generatedAt = null) {
	const write = cacheWrites.catch(() => {
	}).then(async() => {
		if(generatedAt !== null) {
			const previous = await cache.match(key);
			if(Number(previous?.headers.get("X-PHPGT-Cached-At")) > generatedAt) return;
			// Keep live redirect responses, which have immutable headers.
			if(previous?.status === 302) return;
		}
		if(response.status !== 302) {
			const headers = new Headers(response.headers);
			headers.delete("Content-Encoding");
			headers.delete("Content-Length");
			headers.set("X-PHPGT-Cached-At", String(generatedAt ?? Date.now()));
			response = new Response(response.body, {status: response.status, headers});
		}
		await cache.put(key, response);
	});
	cacheWrites = write;
	return write;
}

function cacheURL(value, base = self.location.origin) {
	const url = new URL(value, base);
	if(url.origin !== self.location.origin) return null;
	url.hash = "";
	if(url.pathname === "/static-bundle.json" || url.pathname === "/sw.js" || url.pathname.startsWith("/__offline-")) return null;
	if(url.pathname.startsWith("/asset/") || /\.(css|js|mjs|woff2?|ttf|otf|png|jpe?g|gif|svg|webp|avif|ico|json|xml|txt|webmanifest|pdf|mp4|webm|mp3|ogg|wav)$/i.test(url.pathname)) {
		return url;
	}
	const path = url.pathname.replace(/\/$/, "") + "/";
	if(path === "/search/" || path.startsWith("/api/")
		|| ["/service-worker.js/", "/__offline-refresh-started/"].includes(path)) return null;
	if(url.search) {
		if(path !== "/releases/" || !/^\?page=\d+$/.test(url.search) || Number(url.searchParams.get("page")) > 100) return null;
	}
	// Preserve the exact path: /page and /page/ may be an HTTP redirect
	// and its destination. Sharing their key creates a cached self-redirect.
	return url;
}

async function store(cache, request, event) {
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
			response = new Response(response.clone().body, {
				status: response.status,
				statusText: response.statusText,
				headers
			});
		}
		const alias = response !== networkResponse && finalURL?.href !== key;
		const writes = [writeCache(cache, key, alias ? Response.redirect(finalURL.href, 302) : response.clone())];
		if(finalURL && finalURL.href !== key) writes.push(writeCache(cache, finalURL.href, response.clone()));
		const saved = Promise.all(writes);
		// Stream foreground HTML/CSS immediately instead of waiting for cache.put.
		if(event) event.waitUntil(saved.catch(() => {
		}));
		else await saved;
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
	await writeCache(cache, destination.href, new Response(html, {status: response.status, headers}));
	if(destination.href !== url.href) await writeCache(cache, url.href, Response.redirect(destination.href, 302));
	return {previousHTML, html, url: destination.href};
}

self.addEventListener("install", event => {
	event.waitUntil(self.skipWaiting());
});
self.addEventListener("activate", event => {
	event.waitUntil((async() => {
		const previousCaches = (await caches.keys())
			.filter(key => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME);
		await Promise.all(previousCaches.map(key => caches.delete(key)));
		await self.clients.claim();
		if(previousCaches.length) {
			// Existing clients may be running cached JavaScript without update
			// listeners. Navigation delivers the new code to those clients too.
			const clients = await self.clients.matchAll({type: "window"});
			await Promise.all(clients.map(client => client.navigate(client.url)
				.catch(error => console.warn("Page update unavailable:", error))));
		}
	})());
});

self.addEventListener("fetch", event => {
	// Never cache POSTs, live search, APIs, query-based actions, or third-party traffic.
	if(event.request.method !== "GET") return;
	const url = cacheURL(event.request.url);
	if(!url) return;
	const response = (async() => {
		const cache = await caches.open(CACHE_NAME);
		const cached = await cache.match(url.href);
		// Scheduled refreshes happen after load; a cache hit needs no network request.
		if(cached) return cached;
		// Preserve the browser's stylesheet/font request metadata and priority.
		try {
			const response = await store(cache, new Request(event.request, {cache: "no-cache"}), event);
			event.waitUntil(reportConnection(false));
			return response;
		} catch(error) {
			event.waitUntil(reportConnection(true));
			throw error;
		}
	})();
	event.respondWith(response);
});

async function importBundle(force = false) {
	const cache = await caches.open(CACHE_NAME);
	const marker = await cache.match(REFRESH_TIMESTAMP);
	const state = marker ? await marker.json() : null;
	if(state?.completed && !force) return;
	const response = await fetch("/static-bundle.json", {cache: "no-cache", credentials: "same-origin"});
	if(!response.ok) throw new Error(`Static bundle HTTP ${response.status}`);
	const bundle = await response.json();
	if(typeof bundle.version !== "string" || !Number.isFinite(bundle.generatedAt)
		|| !Array.isArray(bundle.entries) || !bundle.entries.length) throw new Error("Invalid static bundle");
	// Validate metadata before writing any entries. Bodies are base64 for binary fidelity.
	for(const entry of bundle.entries) {
		if(typeof entry.url !== "string" || !cacheURL(entry.url)) throw new Error("Invalid bundle URL");
		if(entry.redirect) {
			if(!cacheURL(entry.redirect)) throw new Error("Invalid bundle redirect");
		} else if(typeof entry.body !== "string" || typeof entry.contentType !== "string") throw new Error("Invalid bundle entry");
	}
	if(state?.version !== bundle.version || !state.completed) {
		for(const entry of bundle.entries) {
			const url = cacheURL(entry.url).href;
			const content = entry.redirect
				? Response.redirect(cacheURL(entry.redirect).href, 302)
				: new Response(Uint8Array.from(atob(entry.body), c => c.charCodeAt(0)), {
					headers: {"Content-Type": entry.contentType},
				});
			await writeCache(cache, url, content, bundle.generatedAt);
		}
	}
	await cache.put(REFRESH_TIMESTAMP, new Response(JSON.stringify({
		version: bundle.version, checkedAt: Date.now(), completed: true,
	})));
}

self.addEventListener("message", event => {
	if(event.data?.type === "check-page") {
		event.waitUntil(checkPage(event.data.url)
			.then(result => event.ports[0]?.postMessage(result))
			.catch(() => event.ports[0]?.postMessage({changed: false})));
		return;
	}
	if(!["preload", "refresh-bundle"].includes(event.data?.type)) return;
	// A single shared import survives navigation and simultaneous tabs.
	refreshCheck = refreshCheck.catch(() => {
	}).then(() => importBundle(event.data.type === "refresh-bundle"));
	event.waitUntil(refreshCheck.catch(error => console.warn("Offline bundle unavailable:", error)));
});
