=== Cache Tags for Cloudflare ===
Contributors: PerS
Tags: cloudflare, cache, cache-tag, purge, cdn
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Precise Cloudflare cache purging for WordPress: adds Cache-Tag headers and purges only affected posts, pages, and terms.

== Description ==

Cache Tags for Cloudflare does two things:

1. **Tagging** — adds a `Cache-Tag` HTTP response header to singular posts, pages, and custom post types so Cloudflare can invalidate them by tag.
2. **Purging** — calls the Cloudflare API to purge the relevant tags when content changes (post publish/update/trash/delete and taxonomy term edits).

The two features can be enabled independently.

**Works on any Cloudflare plan.** The `Cache-Tag` header and purge-by-tag are available on all Cloudflare plans (Free, Pro, Business, and Enterprise); purge API rate limits scale with your plan.

= Default tags =

For singular content the plugin emits:

* `content`
* `b{id}` (site scope: `b1` on single site, `b{blog_id}` on multisite)
* `b{id}-p{ID}`
* `b{id}-pt-{post_type}`
* `b{id}-t{term_id}` for every public taxonomy term the post belongs to

Example header:

`Cache-Tag: content,b1,b1-p42,b1-pt-post,b1-t5`

Term tags use the numeric term ID (`b1-t5`), so they stay stable when a term is renamed or its slug changes.

= Credentials =

Provide a scoped Cloudflare API token (Zone → Cache Purge permission) and a Zone ID. Define them in `wp-config.php` for best security:

`define( 'CACHE_TAGS_CF_API_TOKEN', 'your-scoped-token' );`
`define( 'CACHE_TAGS_CF_ZONE_ID', 'your-zone-id' );`

When these constants are defined they take precedence and the settings fields become read-only. Otherwise enter them under **Settings → Cache Tags**.

= Purge tools =

The **Settings → Cache Tags** screen has a **Purge** tab for manual, on-demand purges by group: a whole post type, a taxonomy term, everything, or raw comma-separated tags. Saving valid credentials on the **Settings** tab automatically verifies the Cloudflare connection and unlocks the purge tools.

= Automatic purging =

When **Auto-purge on changes** is enabled and valid credentials are set, the plugin purges the affected tags automatically on these events:

* Post published, updated, trashed, or untrashed (any transition to or from the published status) — purges `b{id}-p{ID}` plus the post's `b{id}-t{term_id}` tags.
* Post permanently deleted — purges `b{id}-p{ID}` and its `b{id}-t{term_id}` tags.
* Taxonomy term edited or deleted — purges `b{id}-t{term_id}`.

Only public post types and taxonomies are considered; revisions and autosaves are ignored. Tags collected during a request are de-duplicated and sent as a single batched purge after the response (in Cloudflare's 30-tags-per-request batches). Draft-only edits, comments, menu/widget/theme changes, and plugin/core updates do not trigger a purge — use the Purge tab, WP-CLI, or the `cache_tags_for_cloudflare/purge_tags` filter for those. Static files such as images are served outside WordPress and are not tagged or purged by tag.

= Extending the tags =

Filter the tags emitted on a response:

`add_filter( 'cache_tags_for_cloudflare/tags', function ( array $tags, WP_Post $post ) {
	$tags[] = 'author-' . $post->post_author;
	return $tags;
}, 10, 2 );`

Filter the tags purged on a content change:

`add_filter( 'cache_tags_for_cloudflare/purge_tags', function ( array $tags, string $context, $object ) {
	return $tags;
}, 10, 3 );`

React to purges:

`add_action( 'cache_tags_for_cloudflare/purged', function ( array $tags ) {} );`
`add_action( 'cache_tags_for_cloudflare/purge_failed', function ( array $tags, string $message ) {} );`

= Programmatic purging =

Trigger an immediate purge from your own code with these action hooks:

`do_action( 'cache_tags_for_cloudflare/purge_post_type', 'page' );`
`do_action( 'cache_tags_for_cloudflare/purge_terms', 'category', [ 'news', 'sport' ] );`
`do_action( 'cache_tags_for_cloudflare/purge_post', 42 );`
`do_action( 'cache_tags_for_cloudflare/purge_all' );`
`do_action( 'cache_tags_for_cloudflare/purge', [ 'b1-t5', 'content' ] );`

These hooks purge immediately (they are not batched on `shutdown` like auto-purge). Term slugs passed to `.../purge_terms` are resolved to their numeric term IDs automatically.

= WP-CLI =

`wp cache-tags purge --post-type=page`
`wp cache-tags purge --taxonomy=category --terms=news,sport`
`wp cache-tags purge --post=42`
`wp cache-tags purge --tags=b1-t5,content`
`wp cache-tags purge --all`
`wp cache-tags verify`

== External services ==

This plugin connects to the **Cloudflare API** (`https://api.cloudflare.com`) to verify your credentials and to purge cached content by cache tag. Cloudflare is required for the plugin's purging feature to work; the tagging feature (emitting `Cache-Tag` headers) works without contacting any external service.

The plugin contacts Cloudflare in the following cases, and only after you have provided a Cloudflare API token and Zone ID:

* **Verifying credentials** — when you save credentials on the settings screen or run `wp cache-tags verify`, the plugin calls `GET https://api.cloudflare.com/client/v4/user/tokens/verify`. It sends your API token (in the `Authorization` header) so Cloudflare can confirm the token is valid.
* **Purging cache** — when content changes and auto-purge is enabled, or when you purge manually via the settings screen or `wp cache-tags purge`, the plugin calls `POST https://api.cloudflare.com/client/v4/zones/{zone-id}/purge_cache`. It sends your API token (in the `Authorization` header), your Zone ID (in the request URL), and the list of cache tags to purge (in the request body). It does not send post content, personal data, or visitor information.

No data is sent to Cloudflare until you configure credentials, and no request is made unless one of the actions above is triggered.

Cloudflare is a third-party service provided by Cloudflare, Inc. By using this plugin's purging feature you agree to Cloudflare's terms and privacy policy:

* Terms of Service: https://www.cloudflare.com/website-terms/
* Privacy Policy: https://www.cloudflare.com/privacypolicy/

== Installation ==

1. Upload the plugin to `/wp-content/plugins/cache-tags-for-cloudflare` or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Settings → Cache Tags** and add your Cloudflare API token and Zone ID (or define the constants in `wp-config.php`).

== Frequently Asked Questions ==

= Do I need a Cloudflare Enterprise plan? =

No. The `Cache-Tag` header and purge-by-tag are available on all Cloudflare plans (Free, Pro, Business, and Enterprise). Only the purge API rate limits differ by plan.

= Why don't I see the Cache-Tag header on my live site? =

When traffic is proxied through Cloudflare, Cloudflare consumes the `Cache-Tag` header and strips it before the response reaches visitors. Check it at the origin: `curl -I https://example.com/sample-post/`.

== Changelog ==

= 1.3.0 =
* Taxonomy cache tags now use the numeric term ID: `b{id}-t{term_id}` (e.g. `b1-t5`) instead of `b{id}-{taxonomy}-{slug}`. Shorter and stable across term renames and slug changes.
* Added programmatic purging via action hooks: `cache_tags_for_cloudflare/purge_post_type`, `/purge_terms`, `/purge_post`, `/purge_all`, and `/purge` (raw tags). These purge immediately.
* Added structured WP-CLI flags: `--post-type`, `--taxonomy` with `--terms`, and `--post`, alongside the existing `--tags` and `--all`.

= 1.2.0 =
* Shortened the cache-tag vocabulary and scoped every tag to the blog. Tags are now `content`, `b{id}`, `b{id}-p{ID}`, `b{id}-pt-{post_type}`, and `b{id}-{taxonomy}-{slug}` (e.g. `b1-p42`, `b1-category-news`). On multisite the current blog ID is used; single sites use `b1`. Replaces the previous `post-id-`, `post-type-`, `{taxonomy}-`, and `site-id-` tags.

= 1.1.1 =
* Fixed alignment of the Purge buttons on the settings page.

= 1.1.0 =
* Rebuilt the settings screen as a React app with Purge and Settings tabs.
* Added purge-by-group: whole post type, taxonomy term, everything, and raw tags.
* Added a REST API (cache-tags-for-cloudflare/v1) backing the admin UI.
* Purging is locked until valid credentials are saved and verified; saving auto-verifies the connection.
* Added a Vitest JavaScript test suite.
* Made the plugin installable via Composer (composer require).
* Corrected docs: works on all Cloudflare plans (not Enterprise-only).

= 1.0.0 =
* Initial release: Cache-Tag headers for singular content plus Cloudflare purge-by-tag on content changes.
