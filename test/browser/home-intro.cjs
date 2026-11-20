// Optional dependency: Playwright, with the selected browser installed.
const assert = require('node:assert/strict');
const playwright = require('playwright');

(async () => {
	const name = process.env.PHPGT_TEST_BROWSER || 'chromium';
	assert.ok(['chromium', 'firefox'].includes(name));
	const options = name === 'chromium'
		? {executablePath: process.env.PHPGT_CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox']}
		: {};
	const browser = await playwright[name].launch({...options, headless: true});
	try {
		const page = await browser.newPage({reducedMotion: 'reduce'});
		const url = new URL(process.env.PHPGT_TEST_URL || 'http://localhost:8080/');
		await page.route('**/*', route => new URL(route.request().url()).origin === url.origin
			? route.continue()
			: route.fulfill({status: 200, body: '', headers: {'access-control-allow-origin': '*'}}));
		for(const width of [320, 390, 560, 768, 960, 1100, 1440, 1600, 1920, 2560]) {
			await page.setViewportSize({width, height: 1000});
			await page.goto(url.href);
			await page.evaluate(() => document.fonts.ready);
			const layout = await page.locator('main .intro blockquote').evaluate(card => {
				const number = value => parseFloat(value) || 0;
				const style = getComputedStyle(card);
				const list = getComputedStyle(card.querySelector('ul'));
				const canvas = document.createElement('canvas').getContext('2d');
				const rows = [...card.querySelectorAll('li')].map(row => {
					const css = getComputedStyle(row);
					canvas.font = `${css.fontWeight} ${css.fontSize} ${css.fontFamily}`;
					const text = row.textContent.trim().replace(/\s+/g, ' ');
					const naturalWidth = canvas.measureText(text).width
						+ number(getComputedStyle(row, '::before').width) + number(css.columnGap);
					const range = document.createRange();
					range.selectNodeContents(row);
					const lines = new Set([...range.getClientRects()]
						.filter(rect => rect.width > .1 && rect.height > .1)
						.map(rect => Math.round(rect.y))).size;
					return {naturalWidth, lines};
				});
				const rect = card.getBoundingClientRect();
				return {
					width: rect.width,
					right: rect.right,
					available: card.parentElement.getBoundingClientRect().width,
					naturalWidth: Math.max(...rows.map(row => row.naturalWidth))
						+ number(style.paddingLeft) + number(style.paddingRight)
						+ number(style.borderLeftWidth) + number(style.borderRightWidth)
						+ number(list.paddingLeft) + number(list.paddingRight),
					lines: rows.map(row => row.lines),
				};
			});
			assert.ok(layout.width <= layout.available + 1, `${name} ${width}: card exceeds intro`);
			assert.ok(layout.right <= width + 1, `${name} ${width}: card exceeds screen`);
			if(layout.available >= layout.naturalWidth + 1) {
				assert.ok(layout.lines.every(lines => lines === 1), `${name} ${width}: text wraps despite available space: ${JSON.stringify(layout)}`);
			}
		}
		console.log(`${name}: homepage feature text fits when space is available and stays within narrow screens.`);
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error); process.exitCode = 1; });
