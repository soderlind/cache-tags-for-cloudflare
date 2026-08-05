=== Cache Tags for Cloudflare ===
Contributors: PerS
Tags: cloudflare, cache, cache-tag, purge, cdn
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.5.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Precise Cloudflare cache purging for WordPress: adds Cache-Tag headers and purges only affected posts, pages, and terms.

== Description ==

Cache Tags for Cloudflare does two things:

1. **Tagging** — adds a `Cache-Tag` HTTP response header to singular posts, pages, custom post types and taxonomy terms so Cloudflare can invalidate them by tag.
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

= Hooks and programmatic purging =

The plugin exposes hooks for customizing tags and purging from your own code:

* Filters: `cache_tags_for_cloudflare/tags`, `cache_tags_for_cloudflare/purge_tags`
* Result actions: `cache_tags_for_cloudflare/purged`, `cache_tags_for_cloudflare/purge_failed`
* Programmatic purging: `cache_tags_for_cloudflare/purge_post`, `/purge_terms`, `/purge_post_type`, `/purge_all`, `/purge`

See the [Developer guide](https://github.com/soderlind/cache-tags-for-cloudflare/blob/main/docs/DEVELOPER.md) for full documentation and examples.

= WP-CLI =

`wp cache-tags purge --post-type=page`
`wp cache-tags purge --taxonomy=category --terms=news,sport`
`wp cache-tags purge --post=42`
`wp cache-tags purge --tags=b1-t5,content`
`wp cache-tags purge --all`
`wp cache-tags verify`

On multisite, `--all` purges only the current subsite (via its `b{id}` tag). To purge every site sharing the Cloudflare zone at once, purge the global `content` tag: `wp cache-tags purge --tags=content`.

= External services =

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

==  Screenshots ==

1. Purge tab: purge by post type, taxonomy term, everything, or raw tags.
2. Settings tab: add Cloudflare API token and Zone ID, enable auto-purge, and verify credentials.

== Frequently Asked Questions ==

= How do I get a Cloudflare API token and Zone ID? =

In the Cloudflare dashboard, create a scoped API token (**My Profile → API Tokens → Create Token**) with the **Zone → Cache Purge** permission, limited to the zone you want to purge. Your Zone ID is on that zone's **Overview** page. Add both under **Settings → Cache Tags**, or define `CACHE_TAGS_CF_API_TOKEN` and `CACHE_TAGS_CF_ZONE_ID` in `wp-config.php`.

= Do I need to configure anything in Cloudflare? =

No extra setup is required. Cloudflare reads the `Cache-Tag` header the plugin adds automatically, and the plugin purges by tag through the API. Just make sure your site is proxied through Cloudflare (orange-clouded) so responses are cached.

= Why don't I see the Cache-Tag header on my live site? =

When traffic is proxied through Cloudflare, Cloudflare consumes the `Cache-Tag` header and strips it before the response reaches visitors. Check it at the origin instead: `curl -I https://example.com/sample-post/`.

= Why isn't my content being purged automatically? =

Auto-purge only runs when **Auto-purge on changes** is enabled and valid credentials are saved and verified. It also only fires for public post types/taxonomies on real content changes — draft-only edits, comments, menu/widget/theme changes, and plugin/core updates are ignored. For those, use the Purge tab, WP-CLI, or a purge hook. Enable debug logging on the Settings tab to see what was purged.

= Can I purge manually or from my own code? =

Yes. Use the **Purge** tab (by post type, taxonomy term, everything, or raw tags), the `wp cache-tags purge` WP-CLI command, or the programmatic purge hooks (`cache_tags_for_cloudflare/purge_post`, `/purge_terms`, `/purge_all`, and more). See the [Developer guide](https://github.com/soderlind/cache-tags-for-cloudflare/blob/main/docs/DEVELOPER.md).

= Does it work on multisite? =

Yes. Every tag is scoped to the current blog (`b{blog_id}`), so purges on one subsite never affect another. Each subsite uses its own Cloudflare credentials. "Purge everything" (the Purge tab, `--all`, or `purge_all`) is also scoped to the current subsite; to purge every site sharing the same Cloudflare zone at once, purge the global `content` tag with `wp cache-tags purge --tags=content`.

= Does the plugin send any personal or content data to Cloudflare? =

No. Requests to Cloudflare contain only your API token, Zone ID, and the list of cache tags to purge — never post content, personal data, or visitor information. See the **External services** section above for the exact endpoints.

== Changelog ==

= 1.5.1 =
* Fixed: Release workflow no longer runs twice per release, so the WordPress.org deployment no longer reports a spurious failure.
* Fixed: Build artifacts (the staging copy and generated zip) are excluded from the distributed plugin, so the download no longer contains a nested duplicate of itself.

= 1.5.0 =
* Fixed: The Purge tab no longer locks itself on every page load. Verification state is stored server-side, so a site with saved credentials opens with the purge tools ready instead of requiring a trip to Settings → Test connection first.
* Added: Persistent connection status on the Settings tab: not configured, saved-but-untested, connected with the time of the last successful test, or the error Cloudflare returned.
* Added: Tag previews under each purge tool showing the exact cache tag that will be sent (e.g. `b1-pt-post`).
* Added: Help text and a link to Cloudflare's token screen for the API token and Zone ID fields.
* Changed: "Purge everything" now confirms in a WordPress modal that states exactly what gets purged, replacing the browser's native confirm dialog.
* Changed: Result notices moved to a fixed corner stack so feedback is visible from the purge buttons; successes auto-dismiss, errors stay.
* Changed: The settings screen opens on Settings when no credentials are stored, and on Purge otherwise.
* Changed: Debug logging and raw-tag purging moved into collapsed "Advanced" sections; credential fields no longer stretch the full window width.
* Changed: The three "Purge" buttons now have distinct accessible labels.

= 1.4.1 =
* Changed: The purge-failure admin notice now links to the Cache Tags settings page so credentials can be fixed in one click.
* Changed: Added WordPress.org as the primary install option in the README.
* Security: Bumped `fast-uri` to 3.1.4 to resolve a high-severity advisory (build-only dependency; nothing ships in the plugin).

= 1.4.0 =
* Added: Purge by URL. When a post is published (including a draft or scheduled post going live), its permalink is now purged by URL alongside its cache tags. This invalidates responses that carry no cache tag — most importantly a cached `404` at a URL that has since become a real post — which tag-based purging alone cannot reach.
* Added: `cache_tags_for_cloudflare/purge_urls` action hook to purge arbitrary URLs programmatically.
* Added: `wp cache-tags purge --urls=<urls>` WP-CLI flag (comma-separated URLs).
* Added: `cache_tags_for_cloudflare/purged_urls` and `cache_tags_for_cloudflare/purge_urls_failed` hooks fired after an automatic URL purge. URL purges are sent as a separate Cloudflare request from tag purges and must match the cached request exactly (scheme, host, trailing slash, query).

= 1.3.1 =
* Fixed: "Purge everything" (the Purge tab, `wp cache-tags purge --all`, and the `cache_tags_for_cloudflare/purge_all` hook) now purges the blog-scoped `b{id}` tag instead of the shared `content` tag. On multisite this keeps the purge scoped to the current subsite instead of clearing every site sharing the Cloudflare zone. To purge every site in the zone at once, purge the `content` tag directly (e.g. `wp cache-tags purge --tags=content`).

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
