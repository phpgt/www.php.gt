const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const {setImmediate: tick} = require('node:timers/promises');

const origin = 'https://www.php.gt';
const workerSource = readFileSync('script/service-worker/sw.js', 'utf8');

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

test('waits for fonts and five seconds before checking the current page and preloading links', async () => {
	const handlers = {};
	const timers = [];
	const messages = [];
	const registration = {active: {postMessage: (message, ports) => {
		messages.push(message);
		if(message.type === 'check-page') ports[0].reply({changed: false});
	}}};
	class MessageChannel {
		constructor() {
			this.port1 = {close() {}};
			this.port2 = {reply: data => queueMicrotask(() => this.port1.onmessage({data}))};
		}
	}
	let finishFonts;
	const fontsReady = new Promise(resolve => finishFonts = resolve);
	const document = {
		querySelector: () => null, images: [], fonts: {ready: fontsReady}, addEventListener: (type, handler) => handlers[type] = handler,
		referrer: '', readyState: 'loading', body: {dataset: {version: 'test'}},
		querySelectorAll: selector => selector === 'a[href]'
			? [{href: origin + '/help/'}, {href: origin + '/help/'}, {href: 'https://example.com/'}]
			: [{src: origin + '/script.js'}],
	};
	vm.runInNewContext(readFileSync('script/service-worker/runtime.es6', 'utf8'), {
		window: {isSecureContext: true, addEventListener: (type, handler) => handlers[type] = handler},
		navigator: {onLine: true, serviceWorker: {register: async () => registration, ready: Promise.resolve()}},
		document, location: new URL(origin), performance: {getEntriesByType: () => [{type: "navigate"}]}, URL, Date, Set, console, clearTimeout, MessageChannel,
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
	await timers[0].callback();
	assert.equal(messages[0].type, 'check-page');
	assert.equal(messages[1].type, 'preload');
	assert.deepEqual(Array.from(messages[1].pages), [origin + '/', origin + '/help/']);
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

test('page checks cache live responses and reset the hourly cycle only after a confirmed static change', async () => {
	const handlers = {};
	const entries = new Map();
	const url = origin + '/help/';
	const metadata = origin + '/__offline-refresh-started';
	entries.set(url, new Response('<main>Old content</main>'));
	entries.set(metadata, new Response(JSON.stringify({startedAt: Date.now(), completed: true, refreshed: []})));
	let live = '<main>New content</main>';
	let fail = false;
	vm.runInNewContext(workerSource, {
		URL, Request, Response, Headers, AbortController, Promise, Set,
		self: {location: new URL(origin + '/service-worker.js?v=test'), addEventListener: (type, handler) => handlers[type] = handler},
		caches: {open: async () => ({
			match: async key => entries.get(key)?.clone(),
			put: async (key, value) => entries.set(key, value.clone()),
		})},
		fetch: async request => {
			assert.equal(request.cache, 'no-cache');
			if(fail) throw new Error('Offline');
			const response = new Response(live, {headers: {'Content-Type': 'text/html', 'Cache-Control': 'public'}});
			Object.defineProperty(response, 'url', {value: url});
			return response;
		},
	});
	async function check(type = "check-page") {
		let completed, result;
		handlers.message({data: {type, url}, ports: [{postMessage: value => result = value}], waitUntil: promise => completed = promise});
		await completed;
		return result;
	}
	const checked = await check();
	assert.equal(checked.previousHTML, '<main>Old content</main>');
	assert.equal(checked.html, live);
	assert.equal((await entries.get(metadata).clone().json()).completed, true);
	assert.equal((await check('page-changed')).ok, true);
	assert.equal(await entries.get(url).clone().text(), live);
	const state = await entries.get(metadata).clone().json();
	assert.equal(state.completed, false);
	assert.deepEqual(state.refreshed, [url]);
	entries.set(metadata, new Response(JSON.stringify({...state, completed: true})));
	const unchanged = await check();
	assert.equal(unchanged.previousHTML, unchanged.html);
	assert.equal((await entries.get(metadata).clone().json()).completed, true);
	entries.set(url, new Response('<main><input data-live placeholder="Cached name"></main>'));
	live = '<main><input data-live placeholder="Live name"></main>';
	const liveResponse = await check();
	assert.notEqual(liveResponse.previousHTML, liveResponse.html);
	assert.equal(liveResponse.html, live);
	assert.equal(await entries.get(url).clone().text(), live);
	assert.equal((await entries.get(metadata).clone().json()).completed, true, 'Live content does not reset the hourly cycle');
	fail = true;
	assert.equal((await check()).changed, false);
	assert.equal(await entries.get(url).clone().text(), live, 'An offline check retains the cached document');
});

test('each Flux visit gets a fresh five-second check and abandoned visits never check', async () => {
	const handlers = {}, timers = [], messages = [];
	const location = new URL(origin + '/first/');
	let view = {};
	const worker = {postMessage(message, ports) {
		messages.push(message);
		if(message.type === 'check-page') ports[0].reply({changed: false});
	}};
	class MessageChannel {
		constructor() {
			this.port1 = {close() {}};
			this.port2 = {reply: data => queueMicrotask(() => this.port1.onmessage({data}))};
		}
	}
	vm.runInNewContext(readFileSync('script/service-worker/runtime.es6', 'utf8'), {
		window: {isSecureContext: true, addEventListener: (type, callback) => handlers[type] = callback},
		document: {
			readyState: 'loading', referrer: origin + '/previous/', images: [], fonts: {ready: Promise.resolve()},
			body: {dataset: {version: 'test'}},
			querySelector: selector => selector === 'main' ? {firstElementChild: view} : null,
			querySelectorAll: () => [], addEventListener: (type, callback) => handlers[type] = callback,
		},
		navigator: {onLine: true, serviceWorker: {controller: worker, register: async () => ({active: worker}), ready: Promise.resolve()}},
		location, URL, Date, Set, console, MessageChannel,
		setTimeout: (callback, milliseconds) => { timers.push({callback, milliseconds}); return timers.length; },
		clearTimeout: () => {},
	});
	await handlers.load();
	const first = timers.at(-1);
	handlers['flux:before-request']();
	location.href = origin + '/second/'; view = {};
	await handlers['flux:after-render']();
	const second = timers.at(-1);
	handlers['flux:before-request']();
	location.href = origin + '/third/'; view = {};
	await handlers['flux:after-render']();
	const third = timers.at(-1);
	assert.ok(third.milliseconds > 4900 && third.milliseconds <= 5000);
	await first.callback(); await second.callback();
	assert.equal(messages.filter(message => message.type === 'check-page').length, 0);
	await third.callback();
	assert.deepEqual(messages.filter(message => message.type === 'check-page').map(message => message.url), [origin + '/third/']);
	// A same-page widget render should not re-check an already checked view.
	await handlers['flux:after-render']();
	await timers.at(-1).callback();
	assert.equal(messages.filter(message => message.type === 'check-page').length, 1);
});
