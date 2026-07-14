=== Cache Tags for Cloudflare ===
Contributors: PerS
Tags: cloudflare, cache, cache-tag, purge, cdn
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Cache-Tag HTTP response headers for singular WordPress content and purges Cloudflare by tag when content changes.

== Description ==

Cache Tags for Cloudflare does two things:

1. **Tagging** — adds a `Cache-Tag` HTTP response header to singular posts, pages, and custom post types so Cloudflare can invalidate them by tag.
2. **Purging** — calls the Cloudflare API to purge the relevant tags when content changes (post publish/update/trash/delete and taxonomy term edits).

The two features can be enabled independently.

**Requires a Cloudflare Enterprise plan.** The `Cache-Tag` header and purge-by-tag are Enterprise-only Cloudflare features.

= Default tags =

For singular content the plugin emits:

* `content`
* `post-id-{ID}`
* `post-type-{post_type}`
* `{taxonomy}-{slug}` for every public taxonomy the post belongs to
* `site-id-{blog_id}` on multisite

Example header:

`Cache-Tag: content,post-id-42,post-type-post,category-news`

= Credentials =

Provide a scoped Cloudflare API token (Zone → Cache Purge permission) and a Zone ID. Define them in `wp-config.php` for best security:

`define( 'CACHE_TAGS_CF_API_TOKEN', 'your-scoped-token' );`
`define( 'CACHE_TAGS_CF_ZONE_ID', 'your-zone-id' );`

When these constants are defined they take precedence and the settings fields become read-only. Otherwise enter them under **Settings → Cache Tags**.

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

= WP-CLI =

`wp cache-tags purge --tags=post-id-42,category-news`
`wp cache-tags purge --all`
`wp cache-tags verify`

== Installation ==

1. Upload the plugin to `/wp-content/plugins/cache-tags-for-cloudflare` or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Settings → Cache Tags** and add your Cloudflare API token and Zone ID (or define the constants in `wp-config.php`).

== Frequently Asked Questions ==

= Do I need a Cloudflare Enterprise plan? =

Yes. The `Cache-Tag` header and purge-by-tag are Enterprise-only Cloudflare features.

= Why don't I see the Cache-Tag header on my live site? =

When traffic is proxied through Cloudflare, Cloudflare consumes the `Cache-Tag` header and strips it before the response reaches visitors. Check it at the origin: `curl -I https://example.com/sample-post/`.

== Changelog ==

= 1.0.0 =
* Initial release: Cache-Tag headers for singular content plus Cloudflare purge-by-tag on content changes.
