const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');
async function until(predicate) {
	const deadline = Date.now() + 30000;
	while(!await predicate()) {
		if(Date.now() > deadline) throw new Error('Timed out waiting for Flux check');
		await delay(50);
	}
}
(async () => {
	const origin = process.env.PHPGT_TEST_ORIGIN || 'http://localhost:8080';
	const paths = ['/help/', '/learn/', '/contributing/'];
	const browser = await chromium.launch({executablePath: '/usr/bin/chromium', args: ['--no-sandbox']});
	try {
		const context = await browser.newContext();
		await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.fulfill({status: 200, body: ''}));
		const html = {};
		for(const path of paths) html[path] = await (await context.request.get(origin + path)).text();
		const page = await context.newPage();
		await page.goto(origin);
		await page.evaluate(() => navigator.serviceWorker.ready);
		await until(() => page.evaluate(async () => {
			const name = (await caches.keys()).find(name => name.startsWith('phpgt-offline-'));
			return name && Boolean(await (await caches.open(name)).match(location.href));
		}));
		await page.evaluate(() => navigator.serviceWorker.controller.postMessage({type: 'pause-preload'}));
		await delay(200);
		await page.evaluate(async html => {
			const cache = await caches.open((await caches.keys()).find(name => name.startsWith('phpgt-offline-')));
			for(const [path, body] of Object.entries(html)) await cache.put(location.origin + path, new Response(body, {headers: {'Content-Type': 'text/html', 'Cache-Control': 'public'}}));
			await cache.put(location.origin + '/__offline-refresh-started', new Response(JSON.stringify({startedAt: Date.now(), completed: true, refreshed: []})));
		}, html);
		const requests = [];
		for(const path of paths) await context.route(origin + path, route => {
			requests.push({path, at: Date.now()});
			return route.continue();
		});
		for(const path of paths) {
			await page.locator(`site-nav a[href="${path}"]`).click({force: true});
			await until(() => page.evaluate(path => location.pathname === path, path));
			if(path !== paths.at(-1)) await delay(1000);
		}
		const arrived = Date.now();
		await delay(4000);
		assert.deepEqual(requests, [], 'Rapidly abandoned pages must not be checked');
		await until(() => requests.length > 0);
		assert.deepEqual(requests.map(request => request.path), ['/contributing/']);
		assert.ok(requests[0].at - arrived >= 4800, 'The final page needs five seconds of dwell time');
		console.log('Passed: rapid Flux navigation skips abandoned pages; only the final page makes a live request after five seconds.');
	}
	finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
