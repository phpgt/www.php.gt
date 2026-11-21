const assert = require('node:assert/strict');
const playwright = require('playwright');
const {setTimeout: delay} = require('node:timers/promises');
(async () => {
	for(const engine of ['chromium', 'firefox']) {
		const browser = await playwright[engine].launch(engine === 'chromium' ? {executablePath: '/usr/bin/chromium', args: ['--no-sandbox']} : {headless: true});
		try {
			const context = await browser.newContext();
			await context.route('**/*', route => new URL(route.request().url()).hostname === 'localhost' ? route.continue() : route.fulfill({status: 200, body: ''}));
			const page = await context.newPage();
			let navigations = 0, changes = 0;
			page.on('framenavigated', frame => { if(frame === page.mainFrame()) navigations++; });
			page.on('console', message => { if(message.text() === 'CHANGED CONTENT!') changes++; });
			for(const path of ['/', '/docs/WebEngine/Home/']) {
				await page.goto('http://localhost:8080' + path);
				await page.evaluate(() => navigator.serviceWorker.ready);
				await delay(6000);
				const before = navigations;
				await page.reload();
				await delay(3000);
				assert.equal(navigations, before + 1, 'Only the requested reload should navigate');
				assert.equal(changes, 0, 'Fresh CSRF tokens must not count as changed content');
			}
			console.log(engine, 'passed: real homepage and documentation responses do not reload for changing CSRF tokens.');
		}
		finally { await browser.close(); }
	}
})().catch(error => { console.error(error); process.exitCode = 1; });
