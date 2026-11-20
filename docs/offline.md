# Offline pages

The site registers `/service-worker.js` on HTTPS (or localhost). Bulk cache
refreshes start at most once per hour, recorded persistently per deployment so
tabs, reloads and worker restarts share the cooldown. Interrupted sweeps resume
from persisted progress; URLs already refreshed that hour are read from cache,
while their missing assets and remaining pages are fetched. Cache misses can still download individual pages normally.
A new deployment starts with a fresh cache and cooldown. After the page
and its assets (including web fonts) finish loading, it waits five seconds, deduplicates the page’s
internal links and fetches their live responses to refresh the cache. It also
refreshes their same-origin assets, including fonts and CSS images.

After that queue finishes, it waits another five seconds, fetches `/sitemap.xml`
and refreshes remaining public pages, skipping URLs already fetched in this pass.
Links found during the sitemap stage include release pagination. Downloads run
two at a time. Redirects are discovered from live responses while following internal links and
the sitemap. The original URL stores an HTTP redirect and the final URL stores the page,
so aliases such as `/webengine/` update the address bar and open offline. No redirect list
is maintained in the worker. The existing sitemap cron task supplies the documentation list.
Failed responses and external resources are skipped. Data-saving mode disables
bulk refreshing; visited public pages can still cache.

Navigating or submitting through Flux cancels any existing background sweep.
After rendering and loading the new assets, the five-second countdown restarts.
The old localhost-only eager link checker has been removed.

Public GET pages use an immediate cached response when available. Refreshes run
in the scheduled queues, even when a URL already has a cached response.
POST requests, search, APIs and query-based actions go directly to the network.
The homepage greeting therefore still needs a connection, and its POST response
never replaces the cached homepage. Pages become available offline as their
background downloads finish; the first visit still needs a connection.

Caches are named using the Git version already bound to the page. A new worker
activates immediately, claims open tabs and removes older site caches. The build
configuration emits the worker at the web root so its scope covers the site.

Browser regression check (requires Playwright, Chromium and the local server):

```sh
NODE_PATH=/path/to/playwright/node_modules node test/browser/offline.cjs
```

The test checks background downloads, offline navigation, network-only greetings,
POST cache isolation, local assets and deployment cache cleanup.

The staged refresh and duplicate handling also have a deterministic check:

```sh
node test/offline-refresh.test.cjs
```

Slow-connection regression (localhost, throttled 2G):

```sh
NODE_PATH=/path/to/playwright/node_modules node test/browser/offline-priority.cjs
```

The same offline navigation check supports Firefox:

```sh
PHPGT_TEST_BROWSER=firefox PHPGT_TEST_ORIGIN=http://localhost:8080 \
NODE_PATH=/path/to/playwright/node_modules node test/browser/offline.cjs
```
