// Isolated worker regression: real Fetch/Response objects and an in-memory Cache API.
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const origin = 'https://www.php.gt';
const listeners = {};
const data = new Map();
const cache = {
  async match(key) { return data.get(String(key))?.clone(); },
  async put(key, value) { data.set(String(key), value.clone()); },
};
let downloads = 0;
let pageRequests = 0;
let bundle = {version: 'one', generatedAt: Date.now() - 10000, entries: [
  {url: '/help/', contentType: 'text/html', body: btoa('Bundled help')},
  {url: '/help', redirect: '/help/'},
  {url: '/asset/font.woff2', contentType: 'font/woff2', body: btoa('\x00\xff\x80')},
  {url: '/old/', redirect: '/help/'},
  {url: '/', contentType: 'text/html', body: btoa('Old home')},
]};
const context = vm.createContext({
  URL, Request, Response, Headers, Uint8Array, atob, Date, console,
  self: {location: new URL(origin + '/sw.js?v=test'), addEventListener(type, listener) {listeners[type] = listener;}, clients: {async matchAll() {return [];}}},
  caches: {async open() {return cache;}},
  async fetch(request) {
    const url = typeof request === 'string' ? request : request.url;
    if(url === '/static-bundle.json') {downloads++; return Response.json(bundle);}
    pageRequests++;
    const response = new Response('Fresh home', {headers: {'Content-Type': 'text/html'}});
    Object.defineProperty(response, 'url', {value: url});
    return response;
  },
});
vm.runInContext(fs.readFileSync('script/service-worker/sw.js', 'utf8'), context);
async function message(data) {
  let pending;
  listeners.message({data, ports: [{postMessage() {}}], waitUntil(promise) {pending = promise;}});
  await pending;
}
(async () => {
  const writes = [];
  let response;
  listeners.fetch({request: new Request(origin + '/'), waitUntil(promise) {writes.push(promise);}, respondWith(promise) {response = promise;}});
  assert.equal(await (await response).text(), 'Fresh home');
  await Promise.all(writes);
  await Promise.all([message({type: 'preload'}), message({type: 'preload'})]);
  assert.equal(downloads, 1, 'Concurrent tabs share one download');
  const markerURL = origin + '/__offline-refresh-started';
  const state = await (await cache.match(markerURL)).json();
  await cache.put(markerURL, Response.json({...state, checkedAt: 0}));
  await message({type: 'preload'});
  assert.equal(downloads, 1, 'An old bundle is not downloaded on a timer');
  await message({type: 'refresh-bundle'});
  assert.equal(downloads, 2, 'A detected content change can request a fresh bundle');
  assert.equal(pageRequests, 1, 'Import never fetches individual pages');
  assert.equal(await (await cache.match(origin + '/')).text(), 'Fresh home', 'Older bundle preserves live page');
  assert.equal(await (await cache.match(origin + '/help/')).text(), 'Bundled help');
  assert.equal((await cache.match(origin + '/help')).headers.get('Location'), origin + '/help/');
  listeners.fetch({request: new Request(origin + '/help'), waitUntil() {}, respondWith(promise) {response = promise;}});
  const alias = await response;
  assert.equal(alias.status, 302);
  assert.notEqual(alias.headers.get('Location'), origin + '/help', 'Slash redirect must not point to its own cache key');
  assert.deepEqual([...new Uint8Array(await (await cache.match(origin + '/asset/font.woff2')).arrayBuffer())], [0, 255, 128]);
  assert.equal((await cache.match(origin + '/old/')).headers.get('Location'), origin + '/help/');
  listeners.fetch({request: new Request(origin + '/help/'), waitUntil() {}, respondWith(promise) {response = promise;}});
  assert.equal(await (await response).text(), 'Bundled help');
  assert.equal(pageRequests, 1, 'Cached navigation makes no network request');
  await message({type: 'check-page', url: origin + '/help/'});
  assert.equal(await (await cache.match(origin + '/help/')).text(), 'Fresh home', 'Live content check updates bundled page');
  // An invalid snapshot must leave completion unset, allowing a subsequent retry.
  data.delete(origin + '/__offline-refresh-started');
  bundle = {...bundle, version: 'bad', entries: [{url: 'https://evil.example/', body: '', contentType: 'text/html'}]};
  await message({type: 'preload'});
  assert.equal(await cache.match(origin + '/__offline-refresh-started'), undefined);
  bundle = {...bundle, version: 'retry', entries: [{url: '/retry/', body: btoa('Retried'), contentType: 'text/html'}]};
  await message({type: 'preload'});
  assert.equal(await (await cache.match(origin + '/retry/')).text(), 'Retried');
  console.log('Static bundle: single download, binary assets, redirects, live precedence and retry passed');
})().catch(error => {console.error(error); process.exitCode = 1;});
