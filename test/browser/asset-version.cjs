const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');
async function until(predicate) {
	const deadline = Date.now() + 30000;
	while(!await predicate()) {
		if(Date.now() > deadline) throw new Error('Timed out waiting for cache migration');
		await delay(50);
	}
}
(async () => {
	const origin = process.env.PHPGT_TEST_ORIGIN || 'http://localhost:8080';
	const browser = await chromium.launch({executablePath: '/usr/bin/chromium', args: ['--no-sandbox']});
	try {
		const context = await browser.newContext();
		await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.fulfill({status: 200, body: ''}));
		const page = await context.newPage();
		let debugMessages = 0;
		page.on('console', message => { if(/Event .* added to element:|storeUpdateElement completed/.test(message.text())) debugMessages++; });
		const html = await (await context.request.get(origin + '/')).text();
		const script = await (await context.request.get(origin + '/script.js')).text();
		const css = await (await context.request.get(origin + '/style.css')).text();
		await page.goto(origin);
		const version = await page.locator('body').getAttribute('data-version');
		assert.match(version, /^[a-f0-9]{40}-[a-f0-9]{16}$/);
		await page.evaluate(() => navigator.serviceWorker.ready);
		await until(() => page.evaluate(async () => {
			for(const name of await caches.keys()) {
				if(name.startsWith('phpgt-offline-') && await (await caches.open(name)).match(location.origin + '/')) return true;
			}
			return false;
		}));
		const oldVersion = version.split('-')[0];
		await page.evaluate(async ({html, script, css, oldVersion, version}) => {
			const cache = await caches.open((await caches.keys()).find(name => name.startsWith('phpgt-offline-')));
			await cache.put(location.origin + '/', new Response(html.replaceAll(version, oldVersion), {headers: {'Content-Type': 'text/html'}}));
			// Represent the old cached bundle which enabled Flux debugging on import.
			await cache.put(location.origin + '/script.js?v=' + oldVersion, new Response('globalThis.FluxConfig = {debug: true};\n' + script, {headers: {'Content-Type': 'application/javascript'}}));
			await cache.put(location.origin + '/style.css?v=' + oldVersion, new Response(css, {headers: {'Content-Type': 'text/css'}}));
		}, {html, script, css, oldVersion, version});
		debugMessages = 0;
		await page.reload();
		assert.ok(debugMessages > 0, 'An old cached script can reproduce the unwanted logging');
		await until(() => page.evaluate(async version => {
			for(const name of await caches.keys()) {
				if(!name.startsWith('phpgt-offline-')) continue;
				const response = await (await caches.open(name)).match(location.origin + '/');
				if(response && (await response.text()).includes(version)) return true;
			}
			return false;
		}, version));
		debugMessages = 0;
		await page.reload();
		assert.equal(await page.locator('body').getAttribute('data-version'), version);
		assert.equal(debugMessages, 0, 'The rebuilt asset URL must load the quiet Flux release');
		console.log('Passed: old cached bundle reproduces logging; refreshed HTML selects fingerprinted assets and the next reload is quiet.');
	}
	finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
