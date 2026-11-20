const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const {setImmediate: tick} = require('node:timers/promises');

const origin = 'https://www.php.gt';
const workerSource = readFileSync('script/service-worker.js', 'utf8');

test('refreshes live linked pages first, then waits before fetching remaining sitemap pages', async () => {
	const handlers = {};
	const requests = [];
	const timers = [];
	const entries = new Map([[origin + '/help/', new Response('stale')]]);
	const cache = {
		match: async key => entries.get(typeof key === 'string' ? key : key.url)?.clone(),
		put: async (request, response) => entries.set(typeof request === 'string' ? request : request.url, response.clone()),
	};
	const responses = {
		'/help/': ['<a href="/learn/">Later</a><img src="/asset/logo.svg">', 'text/html'],
		'/learn/': ['Learning', 'text/html'],
		'/asset/logo.svg': ['SVG', 'image/svg+xml'],
		'/sitemap.xml': ['<urlset><loc>https://www.php.gt/help</loc><loc>https://www.php.gt/learn</loc></urlset>', 'text/xml'],
	};
	vm.runInNewContext(workerSource, {
		URL, Request, Response, Headers, AbortController, Promise, Set, clearTimeout,
		self: {location: new URL(origin + '/service-worker.js?v=test'), addEventListener: (type, handler) => handlers[type] = handler},
		caches: {open: async () => cache},
		setTimeout: (callback, milliseconds) => timers.push({callback, milliseconds}),
		fetch: async request => {
			const url = typeof request === 'string' ? new URL(request, origin) : new URL(request.url);
			requests.push(url.pathname);
			assert.equal(typeof request === 'string' ? undefined : request.cache, url.pathname === '/sitemap.xml' ? undefined : 'reload');
			const [body, type] = responses[url.pathname];
			return new Response(body, {headers: {'Content-Type': type}});
		},
	});
	let completed;
	handlers.message({data: {type: 'preload', pages: [origin + '/help', origin + '/help/#duplicate']}, waitUntil: promise => completed = promise});
	for(let i = 0; i < 100 && !timers.length; i++) await tick();
	assert.deepEqual(requests, ['/help/', '/asset/logo.svg']);
	assert.equal(await (await cache.match(origin + '/help/')).text(), responses['/help/'][0]);
	assert.equal(timers.length, 1);
	assert.equal(timers[0].milliseconds, 5000);
	timers[0].callback();
	await completed;
	assert.deepEqual(requests, ['/help/', '/asset/logo.svg', '/sitemap.xml', '/learn/']);
	// Ordinary cached browsing must not refresh before the scheduled pass.
	let response;
	handlers.fetch({request: new Request(origin + '/help/'), respondWith: promise => response = promise});
	assert.equal((await response).status, 200);
	assert.equal(requests.length, 4);
	let intercepted = false;
	handlers.fetch({request: new Request(origin + '/', {method: 'POST'}), respondWith: () => intercepted = true});
	assert.equal(intercepted, false);
});

test('waits for fonts after load, then delays five seconds and deduplicates links', async () => {
	const handlers = {};
	const timers = [];
	const messages = [];
	const registration = {active: {postMessage: message => messages.push(message)}};
	let finishFonts;
	const fontsReady = new Promise(resolve => finishFonts = resolve);
	const document = {
		images: [], fonts: {ready: fontsReady}, addEventListener: (type, handler) => handlers[type] = handler,
		readyState: 'loading', body: {dataset: {version: 'test'}},
		querySelectorAll: selector => selector === 'a[href]'
			? [{href: origin + '/help/'}, {href: origin + '/help/'}, {href: 'https://example.com/'}]
			: [{src: origin + '/script.js'}],
	};
	vm.runInNewContext(readFileSync('script/script.es6', 'utf8').slice(readFileSync('script/script.es6', 'utf8').indexOf('if("serviceWorker"')).split('\nlet offline =')[0], {
		window: {isSecureContext: true, addEventListener: (type, handler) => handlers[type] = handler},
		navigator: {onLine: true, serviceWorker: {register: async () => registration, ready: Promise.resolve()}},
		document, location: new URL(origin), URL, Date, Set, console, clearTimeout,
		setTimeout: (callback, milliseconds) => timers.push({callback, milliseconds}),
	});
	assert.equal(timers.length, 0);
	const loaded = handlers.load();
	await tick();
	assert.equal(timers.length, 0, "Fonts must finish before the countdown starts");
	finishFonts();
	await loaded;
	assert.ok(timers[0].milliseconds <= 5000 && timers[0].milliseconds > 4900);
	assert.equal(messages.length, 0);
	timers[0].callback();
	assert.deepEqual(Array.from(messages[0].pages), [origin + '/', origin + '/help/']);
});

test('streams foreground responses before cache writes finish and caches canonical redirects under both names', async () => {
	const handlers = {};
	const entries = new Map();
	let finishWrite;
	const writeReady = new Promise(resolve => finishWrite = resolve);
	const cache = {
		match: async () => undefined,
		put: async (key, response) => { entries.set(key, response); await writeReady; },
	};
	vm.runInNewContext(workerSource, {
		URL, Request, Response, Headers, AbortController, Promise, Set,
		self: {location: new URL(origin + '/service-worker.js?v=test'), addEventListener: (type, handler) => handlers[type] = handler},
		caches: {open: async () => cache},
		fetch: async () => {
			const response = new Response('Documentation', {headers: {'Content-Type': 'text/html'}});
			Object.defineProperties(response, {redirected: {value: true}, url: {value: origin + '/docs/WebEngine/Home'}});
			return response;
		},
	});
	let response;
	let write;
	handlers.fetch({request: new Request(origin + '/previously-unknown-alias'), respondWith: promise => response = promise, waitUntil: promise => write = promise});
	assert.equal(await (await response).text(), 'Documentation');
	assert.equal((await response).url, origin + '/docs/WebEngine/Home');
	assert.equal(entries.get(origin + '/previously-unknown-alias/').status, 302);
	assert.equal(entries.get(origin + '/previously-unknown-alias/').headers.get('Location'), origin + '/docs/WebEngine/Home/');
	assert.ok(entries.has(origin + '/docs/WebEngine/Home/'));
	finishWrite();
	await write;
});

test('pausing a running preload aborts network traffic and prevents the sitemap stage', async () => {
	const handlers = {};
	let pending;
	let aborted = false;
	vm.runInNewContext(workerSource, {
		URL, Request, Response, Headers, AbortController, Promise, Set,
		self: {location: new URL(origin + '/service-worker.js?v=test'), addEventListener: (type, handler) => handlers[type] = handler},
		caches: {open: async () => ({match: async () => undefined, put: async () => {}})},
		fetch: request => new Promise((resolve, reject) => {
			pending = request;
			request.signal.addEventListener('abort', () => { aborted = true; reject(new Error('Aborted')); });
		}),
		setTimeout: () => assert.fail('An aborted refresh must not schedule the sitemap'),
	});
	let completed;
	handlers.message({data: {type: 'preload', pages: [origin + '/help']}, waitUntil: promise => completed = promise});
	while(!pending) await tick();
	handlers.message({data: {type: 'pause-preload'}});
	await completed;
	assert.equal(aborted, true);
});

test('persists an hourly cooldown across tabs and worker restarts', async () => {
	const entries = new Map();
	let now = 10000000;
	let fetches = 0;
	function worker() {
		const handlers = {};
		vm.runInNewContext(workerSource, {
			URL, Request, Response, Headers, AbortController, Promise, Set,
			Date: {now: () => now},
			clearTimeout: () => {},
			setTimeout: callback => { queueMicrotask(callback); return 1; },
			self: {location: new URL(origin + '/service-worker.js?v=test'), addEventListener: (type, handler) => handlers[type] = handler},
			caches: {open: async () => ({
				match: async key => entries.get(key)?.clone(),
				put: async (key, value) => entries.set(key, value.clone()),
			})},
			fetch: async () => { fetches++; return new Response('<urlset/>'); },
		});
		return () => {
			let completed;
			handlers.message({data: {type: 'preload'}, waitUntil: promise => completed = promise});
			return completed;
		};
	}
	let refresh = worker();
	await refresh();
	assert.equal(fetches, 1);
	await Promise.all([refresh(), refresh()]);
	assert.equal(fetches, 1);
	refresh = worker();
	now += 60 * 60 * 1000 - 1;
	await refresh();
	assert.equal(fetches, 1, 'The cooldown survives a worker restart');
	now++;
	await refresh();
	assert.equal(fetches, 2, 'A new sweep is allowed after one hour');
});

test('resumes interrupted hourly sweeps without re-fetching already refreshed URLs', async () => {
	const handlers = {};
	const entries = new Map();
	const timers = [];
	const fetched = [];
	vm.runInNewContext(workerSource, {
		URL, Request, Response, Headers, AbortController, Promise, Set,
		clearTimeout: () => {},
		setTimeout: callback => { timers.push(callback); return timers.length; },
		self: {location: new URL(origin + '/service-worker.js?v=test'), addEventListener: (type, handler) => handlers[type] = handler},
		caches: {open: async () => ({
			match: async key => entries.get(key)?.clone(),
			put: async (key, value) => entries.set(key, value.clone()),
		})},
		fetch: async request => {
			const url = typeof request === 'string' ? request : new URL(request.url).pathname;
			fetched.push(url);
			return new Response(url === '/sitemap.xml'
				? '<loc>https://www.php.gt/help/</loc><loc>https://www.php.gt/learn/</loc>'
				: 'Page', {headers: {'Content-Type': 'text/html'}});
		},
	});
	let completed;
	const refresh = () => handlers.message({data: {type: 'preload', pages: [origin + '/help/']}, waitUntil: promise => completed = promise});
	refresh();
	while(!timers.length) await tick();
	handlers.message({data: {type: 'pause-preload'}});
	await completed;
	// Model a saved page whose asset queue was interrupted before downloading.
	entries.set(origin + '/help/', new Response('<img src="/asset/missing.svg">', {headers: {'Content-Type':'text/html'}}));
	refresh();
	while(timers.length < 2) await tick();
	timers[1]();
	await completed;
	assert.deepEqual(fetched, ['/help/', '/asset/missing.svg', '/sitemap.xml', '/learn/']);
});
