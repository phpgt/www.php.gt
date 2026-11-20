// Run against a built local site: NODE_PATH=/path/to/playwright/node_modules node test/browser/offline.cjs
const assert = require('node:assert/strict');
const playwright = require('playwright');
const engine = process.env.PHPGT_TEST_BROWSER || 'chromium';
const {setTimeout: delay} = require('node:timers/promises');
async function until(page, predicate) {
	const deadline = Date.now() + 120000;
	while(!await page.evaluate(predicate)) {
		if(Date.now() > deadline) throw new Error('Timed out: ' + predicate);
		await delay(100);
	}
}

(async () => {
	const origin = process.env.PHPGT_TEST_ORIGIN || 'http://127.0.0.1:8080';
	const browser = await playwright[engine].launch(engine === 'chromium' ? {executablePath: process.env.PHPGT_CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox']} : {headless: true});
	try {
		console.log(engine, browser.version(), origin);
		const context = await browser.newContext();
		const page = await context.newPage();
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		await page.goto(origin);
		await page.evaluate(() => navigator.serviceWorker.ready);
		await page.evaluate(() => { const a=document.createElement('a'); a.href='/docs/DOM/Home/'; a.textContent='Offline alias'; a.id='offline-alias'; a.dataset.flux='link'; document.querySelector('site-nav nav').append(a); });
		await until(page, () => navigator.serviceWorker.controller);
		await until(page, async () => {
			const names = await caches.keys();
			const name = names.find(name => name.startsWith('phpgt-offline-'));
			if(!name) return false;
			const cache = await caches.open(name);
			return Boolean(await cache.match(location.origin + '/docs/DOM/Home/'))
				&& Boolean(await cache.match(location.origin + '/'));
		});
		console.log('Initial pages preloaded');
		await page.locator('#example-name').fill('Offline test');
		const post = page.waitForResponse(response => response.request().method() === 'POST');
		await page.locator('#example-output button').click();
		assert.equal((await post).fromServiceWorker(), false, 'POST must reach the server');
		await until(page, () => document.querySelector('#example-output h2').textContent.includes('Offline test'));
		const cacheState = await page.evaluate(async () => {
			const cache = await caches.open((await caches.keys()).find(name => name.startsWith('phpgt-offline-')));
			const home = await cache.match(location.origin + '/');
			if(!home) throw new Error(JSON.stringify({names:await caches.keys(),keys:(await cache.keys()).map(r=>r.url)}));
			return {html: await home.text(), keys: (await cache.keys()).map(request => request.url)};
		});
		assert.ok(!cacheState.html.includes('Hello, <span data-bind:text="name">Offline test'));
		assert.ok(!cacheState.keys.some(url => new URL(url).pathname === '/search/'));
		await until(page, async () => {
			const cache = await caches.open((await caches.keys()).find(name => name.startsWith('phpgt-offline-')));
			const keys = (await cache.keys()).map(request => request.url);
			return keys.some(url => url.includes('/asset/font/')) && keys.some(url => url.includes('/style.css'));
		});
		await until(page, async () => { const c=await caches.open((await caches.keys()).find(n=>n.startsWith('phpgt-offline-'))); return Boolean(await c.match(location.origin+'/help/')); });
		console.log('POST and asset caching checked');
		await context.setOffline(true);
		const documentMarker = await page.evaluate(() => window.offlineTestMarker = Math.random());
		const fluxResponse = page.waitForResponse(r => new URL(r.url()).pathname.replace(/\/$/, '') === '/help');
		await page.locator('site-nav a[href="/help/"]').click({force:true});
		assert.equal((await fluxResponse).fromServiceWorker(), true);
		await until(page, () => location.pathname.replace(/\/$/, '') === '/help');
		assert.equal(await page.evaluate(() => window.offlineTestMarker), documentMarker, 'Flux navigation must keep the document');
		const docsLink = page.locator('site-nav .repo-switcher > a');
		await docsLink.evaluate(link => link.href = '/docs/DOM/Home/');
		const aliasResponse = page.waitForResponse(response => new URL(response.url()).pathname === '/docs/DOM/Home/');
		await docsLink.click({force: true});
		assert.equal((await aliasResponse).fromServiceWorker(), true);
		await until(page, () => location.pathname.includes('/docs/DOM/Home'));
		assert.equal(await page.evaluate(() => window.offlineTestMarker), documentMarker);
		for(const path of ['/docs/DOM/Home/', '/']) {
			const response = await page.goto(origin + path);
			assert.equal(response.status(), 200);
			assert.equal(response.fromServiceWorker(), true);
			await page.evaluate(() => document.fonts.ready);
			assert.ok(await page.locator('main').innerText());
		}
		assert.deepEqual(errors, []);
		console.log('Offline navigation checked');
		await context.setOffline(false);
		// A deployment activates a fresh cache and removes the previous generation.
		await page.goto(origin + '/asset/hex.svg');
		await page.evaluate(async () => {
			const registration = await navigator.serviceWorker.register('/service-worker.js?v=offline-test-next');
			await new Promise(resolve => {
				if(registration.active?.scriptURL.endsWith('offline-test-next')) return resolve();
				navigator.serviceWorker.addEventListener('controllerchange', resolve, {once: true});
			});
		});
		await until(page, async () => !(await caches.keys()).some(name => name.startsWith('phpgt-offline-') && name !== 'phpgt-offline-v3-offline-test-next'));
		console.log('Passed: background preload, offline pages/assets, network-only POST, cache isolation and deployment cleanup.');
	}
	finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
