const assert = require('node:assert/strict');
const playwright = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');
(async () => {
	for(const engine of ['chromium', 'firefox']) {
		const browser = await playwright[engine].launch(engine === 'chromium' ? {executablePath: '/usr/bin/chromium', args: ['--no-sandbox']} : {headless: true});
		try {
			const context = await browser.newContext();
			const page = await context.newPage();
			let searches = 0;
			context.on('request', request => { if(new URL(request.url()).pathname.startsWith('/search')) searches++; });
			for(const width of [390, 1440]) for(const scheme of ['light', 'dark']) {
				await page.setViewportSize({width, height: 900});
				await page.emulateMedia({colorScheme: scheme, reducedMotion: 'reduce'});
				await page.goto(process.env.PHPGT_TEST_ORIGIN || 'http://127.0.0.1:8080/');
				const input = page.locator('docs-search input');
				assert.equal(await page.locator('#offline-notice').count(), 0);
				await input.fill('Saved query');
				let before = searches;
				await input.blur();
				before = searches;
				await context.setOffline(true);
				for(let i = 0; i < 40 && await input.getAttribute('placeholder') !== 'You are offline'; i++) await delay(50);
				assert.equal(await input.getAttribute('placeholder'), 'You are offline');
				assert.equal(await input.inputValue(), '');
				const collapsed = await page.locator('docs-search label').evaluate(element => {
					const style = getComputedStyle(element, '::before');
					return {mask: style.maskImage, colour: style.backgroundColor};
				});
				assert.match(collapsed.mask, /\/asset\/icon\/wifi-off\.svg/);
				assert.equal(await page.locator("docs-search").evaluate(element => element.classList.contains("offline")), true);
				await input.click();
				await input.press('Enter');
				assert.equal(searches, before, 'Offline focus/Enter must not request search');
				const colours = await input.evaluate(element => ({
					background: getComputedStyle(element).backgroundColor,
					placeholder: getComputedStyle(element, '::placeholder').color,
					icon: getComputedStyle(element.closest('label'), '::before').backgroundColor,
					readonly: element.readOnly,
				}));
				assert.equal(colours.icon, 'rgb(255, 255, 255)');
				assert.equal(colours.placeholder, 'rgb(255, 255, 255)');
				assert.equal(colours.readonly, true);
				await page.locator('docs-search').screenshot({path: `/tmp/phpgt-offline-search-${engine}-${width}-${scheme}.png`});
				await context.setOffline(false);
				for(let i = 0; i < 40 && await input.getAttribute('placeholder') === 'You are offline'; i++) await delay(50);
				assert.equal(await input.getAttribute('placeholder'), 'Search this site...');
				assert.equal(await input.inputValue(), 'Saved query');
				assert.equal(await input.evaluate(element => element.readOnly), false);
				assert.match(await page.locator('docs-search label').evaluate(element => getComputedStyle(element, '::before').maskImage), /search.svg/);
				console.log(engine, width, scheme, 'offline icon, focus, no search requests and reconnect pass');
			}
		}
		finally { await browser.close(); }
	}
})().catch(error => { console.error(error); process.exitCode = 1; });
