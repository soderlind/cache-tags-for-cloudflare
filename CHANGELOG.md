# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.2] - 2026-08-05

### Changed

- Tested up to WordPress 7.1.
- Plugin URI now points to the WordPress.org listing.
- Updated dev/build dependencies to clear security advisories (no change to the shipped plugin).

## [1.5.1] - 2026-08-05

### Fixed

- Release workflow no longer runs twice per release. It now triggers only on a published GitHub release, so the duplicate tag-push run can't race and fail the WordPress.org SVN tag commit.
- Build artifacts (the `dist/` staging copy and the generated `.zip`) are now excluded from the distributed plugin, so the WordPress.org package no longer contains a nested duplicate of itself.

## [1.5.0] - 2026-08-05

### Fixed

- The **Purge** tab no longer locks itself on every page load. Verification state is stored server-side, so a site with saved credentials opens with the purge tools ready instead of requiring a trip to **Settings → Test connection** first.

### Added

- Persistent connection status on the **Settings** tab: not configured, saved-but-untested, connected with the time of the last successful test, or the error Cloudflare returned.
- Tag previews under each purge tool showing the exact cache tag that will be sent (e.g. `b1-pt-post`).
- Help text and a link to Cloudflare's token screen for the **API token** and **Zone ID** fields.

### Changed

- "Purge everything" now confirms in a WordPress modal that states exactly what gets purged, replacing the browser's native confirm dialog.
- Result notices moved to a fixed corner stack so feedback is visible from the purge buttons; successes auto-dismiss, errors stay.
- The settings screen opens on **Settings** when no credentials are stored, and on **Purge** otherwise.
- Debug logging and raw-tag purging moved into collapsed "Advanced" sections; credential fields no longer stretch the full window width.
- The three "Purge" buttons now have distinct accessible labels.

## [1.4.1] - 2026-07-22

### Changed

- The purge-failure admin notice now links to the Cache Tags settings page so credentials can be fixed in one click.
- Added WordPress.org as the primary install option in the README.

### Security

- Bumped `fast-uri` to 3.1.4 to resolve a high-severity advisory (GHSA-v2hh-gcrm-f6hx). Build-only dependency; nothing ships in the plugin.

## [1.4.0] - 2026-07-22

### Added

- Purge by URL. When a post is published (including a draft or scheduled post going live), its permalink is now purged by URL in addition to its cache tags. This invalidates responses that carry no cache tag — most importantly a cached `404` at a URL that has since become a real post — which tag-based purging alone cannot reach.
- New `cache_tags_for_cloudflare/purge_urls` action hook for purging arbitrary URLs programmatically, e.g. `do_action( 'cache_tags_for_cloudflare/purge_urls', [ 'https://example.com/hello-world/' ] )`.
- New WP-CLI flag `wp cache-tags purge --urls=<urls>` (comma-separated) to purge one or more URLs. (`--url` is reserved by WP-CLI for multisite site selection, so the flag is `--urls`.)
- New `cache_tags_for_cloudflare/purged_urls` and `cache_tags_for_cloudflare/purge_urls_failed` action hooks fired after an automatic URL purge succeeds or fails.

### Note

- URL purges are sent as a separate Cloudflare request from tag purges (the API does not allow mixing `files` and `tags`). URLs must match the cached request exactly — scheme, host, trailing slash, and query string all matter.

## [1.3.1] - 2026-07-21

### Fixed

- "Purge everything" (the **Purge** tab, `wp cache-tags purge --all`, and the `cache_tags_for_cloudflare/purge_all` hook) now purges the blog-scoped `b{id}` tag instead of the shared `content` tag. On multisite this keeps the purge scoped to the current subsite instead of clearing every site sharing the Cloudflare zone. To purge every site in the zone at once, purge the `content` tag directly (e.g. `wp cache-tags purge --tags=content`).

## [1.3.0] - 2026-07-21

### Added

- Programmatic purging via action hooks: `cache_tags_for_cloudflare/purge_post_type`, `.../purge_terms`, `.../purge_post`, `.../purge_all`, and `.../purge` (raw tags). Backed by a shared purge façade; these purges run immediately rather than being batched on `shutdown`.
- Structured WP-CLI flags: `wp cache-tags purge --post-type=<type>`, `--taxonomy=<tax> --terms=<slugs>`, and `--post=<id>`, alongside the existing `--tags=` and `--all`.

### Changed

- Taxonomy cache tags now use the numeric term ID: `b{id}-t{term_id}` (e.g. `b1-t5`) instead of `b{id}-{taxonomy}-{slug}`. Shorter and stable across term renames and slug changes.

## [1.2.0] - 2026-07-21

- Shortened the cache-tag vocabulary and scoped every tag to the blog. Tags are now `content`, `b{id}`, `b{id}-p{ID}`, `b{id}-pt-{post_type}`, and `b{id}-{taxonomy}-{slug}` (e.g. `b1-p42`, `b1-category-news`). On multisite the current blog ID is used; single sites use `b1`. Replaces the previous `post-id-`, `post-type-`, `{taxonomy}-`, and `site-id-` tags.

## [1.1.1] - 2026-07-15

### Fixed

- Alignment of the Purge buttons on the settings page.

## [1.1.0] - 2026-07-15

### Added

- Rebuilt the settings screen as a React app with **Purge** and **Settings** tabs.
- Purge-by-group from the admin UI and REST API: a whole post type (`post-type-{type}`), a taxonomy term (`{taxonomy}-{slug}`), everything (`content`), or raw comma-separated tags.
- REST API namespace `cache-tags-for-cloudflare/v1` (`settings`, `groups`, `verify`, `purge`) backing the admin app.
- Vitest JavaScript test suite for the admin app.
- Composer installability via `composer require` (`composer/installers`); the compiled `build/` assets are committed so no build step is required.

### Changed

- Purging is locked until valid Cloudflare credentials are saved and verified; saving settings now automatically verifies the connection.
- Corrected documentation: the `Cache-Tag` header and purge-by-tag work on all Cloudflare plans (not Enterprise-only); purge API rate limits scale by plan.

## [1.0.0] - 2026-07-14

### Added

- **Tagging** — emits a `Cache-Tag` HTTP response header on singular posts, pages, and custom post types.
  - Default tags: `content`, `post-id-{ID}`, `post-type-{post_type}`, `{taxonomy}-{slug}` for every public taxonomy, and `site-id-{blog_id}` on multisite.
  - Tags are normalized (lowercased, accents removed, whitespace hyphenated, unsupported characters stripped, deduplicated) and capped to Cloudflare's 16 KB header limit.
  - `cache_tags_for_cloudflare/tags` filter to customize the emitted tags.
- **Purging** — purges Cloudflare by tag when content changes.
  - Triggers on post publish/update/trash/delete and taxonomy term edits.
  - Tags are collected and deduplicated per request and sent as a single batched purge on `shutdown`, split into Cloudflare's 30-tags-per-request limit.
  - `cache_tags_for_cloudflare/purge_tags` filter, plus `cache_tags_for_cloudflare/purged` and `cache_tags_for_cloudflare/purge_failed` actions.
- Scoped Cloudflare API token support, read from the `CACHE_TAGS_CF_API_TOKEN` and `CACHE_TAGS_CF_ZONE_ID` constants first, then plugin settings.
- Settings screen (Settings → Cache Tags) with header/purge/debug toggles, a connection test, and a "Purge everything" action.
- Dismissible admin notice surfacing recurring purge failures.
- WP-CLI commands: `wp cache-tags purge --tags=<tags>|--all` and `wp cache-tags verify`.

[1.5.2]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.5.1...1.5.2
[1.5.1]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.5.0...1.5.1
[1.5.0]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.4.1...1.5.0
[1.4.1]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.4.0...1.4.1
[1.4.0]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.1.1...1.2.0
[1.1.1]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/soderlind/cache-tags-for-cloudflare/releases/tag/1.0.0
