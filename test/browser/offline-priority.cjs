// Cold-load regression: use localhost deliberately to catch accidental dev-only link crawlers.
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');

(async () => {
	const origin = process.env.PHPGT_TEST_ORIGIN || 'http://localhost:8080';
	const browser = await chromium.launch({executablePath: process.env.PHPGT_CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox']});
	try {
		const context = await browser.newContext();
		const page = await context.newPage();
		const cdp = await context.newCDPSession(page);
		await cdp.send('Network.enable');
		await cdp.send('Network.setBlockedURLs', {urls: ['*traffic.brightflair.com*', '*camo.githubusercontent.com*']});
		await cdp.send('Network.emulateNetworkConditions', {
			offline: false, latency: 300, downloadThroughput: 32 * 1024,
			uploadThroughput: 16 * 1024, connectionType: 'cellular2g',
		});
		const pageRequests = [];
		context.on('request', request => {
			const url = new URL(request.url());
			if(url.origin === origin && (/^\/docs\//.test(url.pathname) || ['/help/', '/learn/', '/news/'].includes(url.pathname))) {
				pageRequests.push({url: url.href, at: Date.now()});
			}
		});
		await page.goto(origin, {waitUntil: 'load', timeout: 120000});
		await page.evaluate(() => document.fonts.ready);
		const ready = Date.now();
		assert.equal(pageRequests.length, 0, 'No linked-page fetches may compete with initial assets');
		const deadline = ready + 30000;
		while(!pageRequests.length && Date.now() < deadline) await delay(100);
		assert.ok(pageRequests.length, 'The linked-page refresh should eventually start');
		assert.ok(pageRequests[0].at - ready >= 4800, JSON.stringify({delay: pageRequests[0].at - ready, first: pageRequests[0].url}));
		const stylesheet = await page.evaluate(() => [...document.styleSheets].some(sheet => sheet.href?.includes('/style.css')));
		assert.equal(stylesheet, true);
		// Reload while the first sweep is active, forcing CSS through the worker's
		// network fallback rather than letting a cached stylesheet hide contention.
		await page.evaluate(async () => {
			for(const name of await caches.keys()) {
				const cache = await caches.open(name);
				for(const request of await cache.keys()) {
					if(new URL(request.url).pathname === '/style.css') await cache.delete(request);
				}
			}
		});
		pageRequests.length = 0;
		await page.reload({waitUntil: 'load', timeout: 120000});
		await page.evaluate(() => document.fonts.ready);
		const reloaded = Date.now();
		assert.equal(pageRequests.length, 0, 'An old sweep must not compete with a controlled reload');
		await delay(6000);
		assert.ok(pageRequests.every(request => request.at - reloaded >= 4800), 'Resuming missing pages must still wait five seconds after assets load');
		console.log('Passed: 2G cold load waits for assets plus five seconds; interrupted sweeps resume only after the reloaded assets plus five seconds.');
	}
	finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
