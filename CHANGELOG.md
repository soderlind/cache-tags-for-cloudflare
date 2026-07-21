# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/soderlind/cache-tags-for-cloudflare/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/soderlind/cache-tags-for-cloudflare/releases/tag/v1.0.0
