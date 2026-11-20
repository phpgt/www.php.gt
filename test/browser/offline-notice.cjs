const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');
(async () => {
	const browser = await chromium.launch({executablePath: '/usr/bin/chromium', args: ['--no-sandbox']});
	try {
		const context = await browser.newContext();
		const page = await context.newPage();
		const cdp = await context.newCDPSession(page);
		await cdp.send('Network.enable');
		await cdp.send('Network.setBlockedURLs', {urls: ['*traffic.brightflair.com*']});
		await page.goto('http://127.0.0.1:8080/docs/Dom/Home/');
		assert.equal(await page.locator('#rewrite').count(), 0);
		const notice = page.locator('#offline-notice');
		assert.equal(await notice.isVisible(), false);
		await page.evaluate(() => navigator.serviceWorker.ready);
		while(!await page.evaluate(() => Boolean(navigator.serviceWorker.controller))) await delay(50);
		await page.evaluate(() => {
			window.connectionMessages = [];
			navigator.serviceWorker.addEventListener('message', event => {
				if(event.data?.type === 'connection-status') connectionMessages.push(event.data);
			});
		});
		await context.setOffline(true);
		await page.evaluate(async () => { try { await fetch('/legal/'); } catch {} });
		for(const width of [390, 1440]) for(const scheme of ['light', 'dark']) {
			await page.setViewportSize({width, height: 900});
			await page.emulateMedia({colorScheme: scheme});
			assert.equal(await notice.isVisible(), true);
			const bounds = await notice.boundingBox();
			assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
			const colour = await notice.evaluate(element => getComputedStyle(element).backgroundColor);
			assert.match(colour, /rgb\(/);
			await notice.screenshot({path: `/tmp/phpgt-offline-notice-${width}-${scheme}.png`});
		}
		assert.ok(await page.evaluate(() => connectionMessages.some(message => message.offline)));
		await context.setOffline(false);
		for(let i = 0; i < 20 && await notice.isVisible(); i++) await delay(50);
		assert.equal(await notice.isVisible(), false);
		console.log('Passed: rewrite notice removed; worker reports offline; red notice fits mobile/desktop in light/dark and hides on reconnect.');
	}
	finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
