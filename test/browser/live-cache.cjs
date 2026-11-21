const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');
async function until(predicate) {
	const deadline = Date.now() + 30000;
	while(!await predicate()) {
		if(Date.now() > deadline) throw new Error('Timed out waiting for live cache');
		await delay(50);
	}
}
(async () => {
	const origin = 'http://127.0.0.1:8080';
	const browser = await chromium.launch({executablePath: '/usr/bin/chromium', args: ['--no-sandbox']});
	try {
		const context = await browser.newContext();
		await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.fulfill({status: 200, body: ''}));
		const html = await (await context.request.get(origin + '/')).text();
		const help = await (await context.request.get(origin + '/help/')).text();
		const withName = name => html.replace(/placeholder="e\.g\.[^"]*"/, `placeholder="${name}"`);
		assert.ok(html.includes('data-live'));
		const page = await context.newPage();
		await page.goto(origin);
		await page.evaluate(() => navigator.serviceWorker.ready);
		const cached = () => page.evaluate(async () => {
			const name = (await caches.keys()).find(name => name.startsWith('phpgt-offline-'));
			const response = name && await (await caches.open(name)).match(location.origin + '/');
			return response ? response.text() : '';
		});
		await until(async () => Boolean(await cached()));
		await page.evaluate(async ({home, help}) => {
			const cache = await caches.open((await caches.keys()).find(name => name.startsWith('phpgt-offline-')));
			const headers = {'Content-Type': 'text/html', 'Cache-Control': 'public'};
			await cache.put(location.origin + '/', new Response(home, {headers}));
			await cache.put(location.origin + '/help/', new Response(help, {headers}));
			await cache.put(location.origin + '/__offline-refresh-started', new Response(JSON.stringify({startedAt: Date.now(), completed: true, refreshed: []})));
		}, {home: withName('Cached person'), help});
		let checks = 0;
		await context.route(origin + '/', route => route.fulfill({status: 200, body: withName(`Live person ${++checks}`), headers: {'Content-Type': 'text/html', 'Cache-Control': 'public'}}));
		await page.goto(origin, {referer: origin + '/help/'});
		const input = page.locator('#example-name');
		const marker = await page.evaluate(() => window.liveMarker = Math.random());
		await until(async () => (await cached()).includes('Live person 1'));
		assert.equal(await input.getAttribute('placeholder'), 'Cached person', 'A live refresh must leave the visible page alone');
		assert.equal(await page.evaluate(() => window.liveMarker), marker);
		await page.evaluate(() => document.dispatchEvent(new CustomEvent('flux:after-render')));
		await delay(200);
		assert.equal(checks, 1, 'An unrelated render must not refresh the same live view twice');
		await page.locator('site-nav a[href="/help/"]').click({force: true});
		await until(() => page.evaluate(() => location.pathname === '/help/'));
		await page.locator('site-nav a[href="/"]').click({force: true});
		await until(() => page.evaluate(() => location.pathname === '/'));
		await until(async () => (await cached()).includes('Live person 2'));
		assert.equal(await input.getAttribute('placeholder'), 'Live person 1', 'The next Flux view shows the previously refreshed copy');
		assert.equal(await page.evaluate(() => window.liveMarker), marker);
		const state = await page.evaluate(async () => {
			const cache = await caches.open((await caches.keys()).find(name => name.startsWith('phpgt-offline-')));
			return (await cache.match(location.origin + '/__offline-refresh-started')).json();
		});
		assert.equal(state.completed, true, 'Live-only updates must preserve the hourly cooldown');
		assert.equal(checks, 2);
		console.log('Passed: data-live refreshes cache on referred and Flux views; visible content stays unchanged; next visit gets new placeholder; hourly cooldown preserved.');
	}
	finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
