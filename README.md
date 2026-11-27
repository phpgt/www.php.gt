The www.php.gt website.
=======================

This repository holds the source code for the PHP.GT website and documentation, and itself is a WebEngine application.

This README acts as a guide to understanding how the website is put together and how it takes advantage of some interesting PHP.GT functionality, including:

- Routing pages vs. APIs
- Managing content from a third-party source
- Using dynamic paths
- Managing project secrets and config variables

## Installation

// TODO: Getting started to make changes locally.

## Project outline

// TODO: Describe directory layout and what each thing does. 

// TODO: Content managed on individual Github wikis.

## Routing pages vs. APIs

// TODO: Most requests will be pages via a web browser, but APIs can be handled too.

// TODO: Github webhook to know when there's a change to the content in one of the wikis.

## Managing content from a third-party source

// TODO: The best content management system is the one you already use.

// TODO: Github wikis are where the original documentation is stored.

## Using dynamic paths

// TODO: Can't have an individual page for each repo and each page within the repo's wiki.

// TODO: Dynamic pages and routing with the `@` character

## Managing project secrets and config variables

// TODO: Github webhook could be forged by anyone - how should this be protected?

// TODO: Deploying to a server.

# Proudly sponsored by

[JetBrains Open Source sponsorship program](https://www.jetbrains.com/community/opensource/)

[![JetBrains logo.](https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.svg)](https://www.jetbrains.com/community/opensource/)

## Offline bundle

The `build-static-bundle` cron task runs hourly at ten past the hour. It fetches
`https://www.php.gt/sitemap.xml`, then crawls public same-origin HTML and its linked
CSS, JavaScript, images and fonts using `phpgt/Fetch`. Every HTTP request, including
redirect destinations, sends `X-PHPGT: Static Bundler`. Configure nginx's rate-limit
policy for this header before enabling the task. The header is a label, not an
authentication credential; restrict any exemption to the server's source IP too.

Requests run concurrently in groups of up to 10, including assets and redirect
destinations. Set a positive integer in `config.ini` (or an environment override)
to change the limit:

```ini
[offline]
concurrency=10
```

Run an initial build from the application directory with:

```sh
vendor/bin/cron --now build-static-bundle
```

HTML snapshots are saved under `data/static-html` with SHA-256 URL filenames.
A lock prevents overlapping builds. Server and network errors are retried twice;
failed URLs are reported and skipped. Sitemap or publication failures preserve
the previous bundle. Successful builds atomically replace
`www/static-bundle.json` with a versioned JSON snapshot. Bodies are base64 encoded
to preserve binary assets; redirects retain their destination URLs. External
resources, search, API requests and arbitrary action queries are excluded.
Serve the JSON file with HTTP compression to reduce download size.

Five seconds after page load, the client retains its existing live content check
and asks the worker to import the bundle (unless data saving is enabled). The
worker makes one bundle request, then writes each response locally into Cache
Storage. After a page's live content check detects a change, the updated page
loads, then waits 15 seconds after its assets load before requesting a new bundle.
There is no hourly browser download. Navigation does not cancel an import,
and simultaneous tabs share it. Failed imports retry on a later visit without
marking completion. Cached live responses newer than the build's start time take
precedence over the snapshot. The bundle is a snapshot of public content only;
third-party resources are available offline only if separately provided locally.

Regression checks:

```sh
php test/offline/bundler.php
node test/offline/concurrency.cjs
node test/browser/static-bundle.cjs
```
