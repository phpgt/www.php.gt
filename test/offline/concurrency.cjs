// Real overlapping requests, including streamed bodies and a low descriptor limit.
const assert = require('node:assert/strict');
const http = require('node:http');
const {spawn} = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
let active = 0, peak = 0;
const server = http.createServer((request, response) => {
  assert.equal(request.headers['x-phpgt'], 'Static Bundler');
  if(request.url === '/sitemap.xml') {
    response.setHeader('Content-Type', 'application/xml');
    response.end('<urlset>' + Array.from({length: 35}, (_, i) => `<url><loc>https://www.php.gt/page/${i}/</loc></url>`).join('') + '</urlset>');
    return;
  }
  active++; peak = Math.max(peak, active);
  response.setHeader('Content-Type', 'text/html');
  response.write('<html><body>Start');
  setTimeout(() => {active--; response.end(' End</body></html>');}, 40 + Number(request.url.split('/')[2] || 0) % 3 * 20);
});
(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const origin = `http://127.0.0.1:${server.address().port}`;
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'phpgt-concurrency-'));
  try {
    for(const limit of [10, 3, 1]) {
      peak = 0;
      const code = 'require "vendor/autoload.php"; (new GT\\Website\\Offline\\StaticBundler($argv[1], (int)$argv[2]))->build($argv[3], $argv[4]);';
      const output = path.join(directory, 'bundle.json');
      const child = spawn('bash', ['-c', 'ulimit -n 64; exec php "$@"', 'php-test', '-r', code, origin, String(limit), path.join(directory, 'html'), output]);
      let errors = '';
      child.stdout.resume(); child.stderr.on('data', chunk => errors += chunk);
      const status = await new Promise((resolve, reject) => {child.on('error', reject); child.on('close', resolve);});
      assert.equal(status, 0, errors);
      assert.equal(peak, limit, `Expected exactly ${limit} overlapping requests`);
      const bundle = JSON.parse(fs.readFileSync(output));
      assert.equal(bundle.entries.length, 36);
      for(const entry of bundle.entries) assert.equal(Buffer.from(entry.body, 'base64').toString(), '<html><body>Start End</body></html>', 'Complete streamed body must be cached');
      console.log(`Concurrency ${limit}: peak ${peak}, all 36 pages complete, 64-file limit passed`);
    }
  } finally {fs.rmSync(directory, {recursive: true, force: true}); server.close();}
})().catch(error => {console.error(error); server.close(); process.exitCode = 1;});
