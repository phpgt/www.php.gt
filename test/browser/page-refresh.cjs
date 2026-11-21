const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');
async function until(predicate) {
	const deadline = Date.now() + 30000;
	while(!await predicate()) {
		if(Date.now() > deadline) throw new Error('Timed out waiting for page refresh');
		await delay(50);
	}
}
(async () => {
	const origin = process.env.PHPGT_TEST_ORIGIN || 'http://127.0.0.1:8080';
	const livePage = process.env.PHPGT_TEST_LIVE_PAGE === '1';
	const alwaysChanges = process.env.PHPGT_TEST_ALWAYS_CHANGES === '1';
	const path = livePage ? '/' : '/help/';
	const browser = await chromium.launch({executablePath: '/usr/bin/chromium', args: ['--no-sandbox']});
	try {
		const context = await browser.newContext();
		await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.fulfill({status: 200, body: ''}));
		const page = await context.newPage();
		let changeLogs = 0;
		page.on('console', message => { if(message.text() === 'CHANGED CONTENT!') changeLogs++; });
		const original = await (await context.request.get(origin + path)).text();
		const withMarker = (value, name = 'Cached name') => original.replace('</main>', `<p id="refresh-proof">${value}</p></main>`).replace(/placeholder="e\.g\.[^"]*"/, `placeholder="${name}"`);
		await page.goto(origin + path);
		await page.evaluate(() => navigator.serviceWorker.ready);
		await until(() => page.evaluate(async () => {
			const name = (await caches.keys()).find(name => name.startsWith('phpgt-offline-'));
			return name && Boolean(await (await caches.open(name)).match(location.href));
		}));
		await page.evaluate(async html => {
			const cache = await caches.open((await caches.keys()).find(name => name.startsWith('phpgt-offline-')));
			await cache.put(location.href, new Response(html, {headers: {'Content-Type': 'text/html', 'Cache-Control': 'public'}}));
			await cache.put(location.origin + '/__offline-refresh-started', new Response(JSON.stringify({startedAt: Date.now(), completed: true, refreshed: []})));
		}, withMarker('Old'));
		let checkCount = 0;
		let hold = false;
		let release;
		const responseReady = new Promise(resolve => release = resolve);
		await context.route(origin + path, async route => {
			checkCount++;
			if(hold) await responseReady;
			await route.fulfill({status: 200, body: withMarker(alwaysChanges ? `New ${checkCount}` : 'New', `Live name ${checkCount}`), headers: {'Content-Type': 'text/html', 'Cache-Control': 'public'}});
		});

		hold = true;
		await page.reload();
		await delay(4000);
		assert.equal(checkCount, 0, 'The current-page check must not run in the first four seconds');
		assert.equal(await page.locator('#refresh-proof').innerText(), 'Old', 'Cached content displays while the live check is pending');
		await until(() => checkCount > 0);
		let recacheRequests = 0;
		context.on('request', request => { if(new URL(request.url()).pathname === '/news/') recacheRequests++; });
		release();
		await until(async () => await page.locator('#refresh-proof').count() > 0 && await page.locator('#refresh-proof').innerText() === (alwaysChanges ? 'New 1' : 'New'));
		await until(() => recacheRequests > 0);
		assert.equal(checkCount, 2, 'Each page view, including the refreshed document, performs one delayed check');
		assert.equal(changeLogs, 1, 'The changed-content log must fire once for a static change');
		console.log('Passed:', livePage ? 'page with data-live' : 'static page', 'shows changed content and logs once; live-only differences do not cause reload loops.');
	}
	finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
