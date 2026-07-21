# Cache Tags for Cloudflare

Precise Cloudflare cache purging for WordPress: adds `Cache-Tag` headers and purges only affected posts, pages, and terms.

- **Requires:** WordPress 6.8+, PHP 8.3+
- **Works on any Cloudflare plan** — the `Cache-Tag` header and purge-by-tag are available on all plans (Free, Pro, Business, Enterprise); purge API rate limits scale with your plan.

[What it does](#what-it-does) · [Default tags](#default-tags) · [Installation](#installation) · [Configuration](#configuration) · [Admin UI](#admin-ui) · [Automatic purging](#automatic-purging) · [Extensibility](#extensibility) · [WP-CLI](#wp-cli)

## What it does

The plugin has two independently toggleable parts:

| Context | Responsibility |
| --- | --- |
| **Tagging** | Emits a `Cache-Tag` response header on singular posts/pages/CPTs. |
| **Purging** | Calls the Cloudflare API to invalidate the affected tags when content changes. |

See [`CONTEXT-MAP.md`](CONTEXT-MAP.md) and the per-context glossaries for the domain model, and [`docs/adr/`](docs/adr/) for the key architectural decisions.

## Default tags

For singular content:

```text
content
b{id}                  # site scope: b1 on single site, b{blog_id} on multisite
b{id}-p{ID}
b{id}-pt-{post_type}
b{id}-{taxonomy}-{slug}      # for every public taxonomy the post belongs to
```

Example: `Cache-Tag: content,b1,b1-p42,b1-pt-post,b1-category-news`

## Installation

1. Install the plugin using one of:
   - **Release zip** — download the latest `cache-tags-for-cloudflare.zip` from the [Releases](https://github.com/soderlind/cache-tags-for-cloudflare/releases) page and upload it via **Plugins → Add New → Upload Plugin**.
   - **Composer** — for Composer-managed WordPress sites (Bedrock, etc.). The `wordpress-plugin` type installs it into your plugins directory automatically (via `composer/installers`):

     ```bash
     composer require soderlind/cache-tags-for-cloudflare
     ```

     The plugin has **no runtime dependencies** and ships its compiled `build/` assets, so no build step is needed. If it isn't on Packagist yet, add the repository first:

     ```json
     { "repositories": [ { "type": "vcs", "url": "https://github.com/soderlind/cache-tags-for-cloudflare" } ] }
     ```

   - **Git checkout** — clone into `wp-content/plugins/` (the compiled `build/` assets are committed, so no build step is required):

     ```bash
     git clone https://github.com/soderlind/cache-tags-for-cloudflare.git \
       wp-content/plugins/cache-tags-for-cloudflare
     ```

2. Activate **Cache Tags for Cloudflare** on the Plugins screen (or network-activate on multisite).
3. Add your Cloudflare API token and Zone ID — via `wp-config.php` constants or under **Settings → Cache Tags** (see [Configuration](#configuration)).

## Configuration

Provide a **scoped** Cloudflare API token (Zone → Cache Purge) and a Zone ID. Constants in `wp-config.php` are preferred and take precedence over the settings UI:

```php
define( 'CACHE_TAGS_CF_API_TOKEN', 'your-scoped-token' );
define( 'CACHE_TAGS_CF_ZONE_ID', 'your-zone-id' );
```

Otherwise configure them under **Settings → Cache Tags** (see [Admin UI](#admin-ui) below). Saving valid credentials automatically verifies the Cloudflare connection and unlocks the purge tools.

## Admin UI

**Settings → Cache Tags** is a React app (`@wordpress/components`) with two tabs:

- **Purge** — manual, on-demand purges by group: a whole post type (`b{id}-pt-{type}`), a taxonomy term (`b{id}-{taxonomy}-{slug}`), everything (`content`), or raw comma-separated tags. The purge tools stay locked until valid credentials have been saved and verified.
- **Settings** — toggles for header emission, auto-purge, and debug logging, plus the API token and Zone ID (read-only when defined via constants). **Save settings** persists the values and automatically verifies the connection; a **Test connection** button is also available.

## Automatic purging

When **Auto-purge on changes** is enabled (and valid credentials are set), the plugin purges the affected tags automatically on these events:

| Event | Purges |
| --- | --- |
| Post published, updated, trashed, or untrashed (any transition **to or from** the published status) | `b{id}-p{ID}` + the post's `b{id}-{taxonomy}-{slug}` tags |
| Post permanently deleted | `b{id}-p{ID}` + its `b{id}-{taxonomy}-{slug}` tags |
| Taxonomy term edited or deleted | `{taxonomy}-{slug}` |

Details:

- Only **public** post types and taxonomies are considered; **revisions and autosaves are ignored**.
- Tags from all triggers in a request are **de-duplicated** and sent as a **single batched purge on `shutdown`** (split into Cloudflare's 30-tags-per-request batches), off the editor's critical path.
- **Not** triggered by: draft-only edits, comments, menu/widget/theme changes, or plugin/core updates. Use the **Purge** tab, WP-CLI, or the `cache_tags_for_cloudflare/purge_tags` filter for those.
- Static files (e.g. images under `wp-content/uploads/`) are served outside WordPress, so they are **not** tagged or purged by tag.

## Extensibility

```php
// Tags emitted on a response.
add_filter( 'cache_tags_for_cloudflare/tags', function ( array $tags, WP_Post $post ) {
	$tags[] = 'author-' . $post->post_author;
	return $tags;
}, 10, 2 );

// Tags purged on a content change.
add_filter( 'cache_tags_for_cloudflare/purge_tags', function ( array $tags, string $context, $object ) {
	return $tags;
}, 10, 3 );

// React to purges.
add_action( 'cache_tags_for_cloudflare/purged', function ( array $tags ) {} );
add_action( 'cache_tags_for_cloudflare/purge_failed', function ( array $tags, string $message ) {} );
```

## WP-CLI

```bash
wp cache-tags purge --tags=b1-p42,b1-category-news
wp cache-tags purge --all
wp cache-tags verify
```

## External services

This plugin connects to the **Cloudflare API** (`https://api.cloudflare.com`) to verify your credentials and to purge cached content by cache tag. Cloudflare is required for the purging feature; the tagging feature (emitting `Cache-Tag` headers) works without contacting any external service.

Requests are made only after you provide a Cloudflare API token and Zone ID:

- **Verifying credentials** — saving credentials or running `wp cache-tags verify` calls `GET https://api.cloudflare.com/client/v4/user/tokens/verify`, sending your API token (in the `Authorization` header) so Cloudflare can confirm it is valid.
- **Purging cache** — auto-purge on content changes, manual purges, and `wp cache-tags purge` call `POST https://api.cloudflare.com/client/v4/zones/{zone-id}/purge_cache`, sending your API token (in the `Authorization` header), your Zone ID (in the URL), and the list of cache tags to purge (in the body). No post content, personal data, or visitor information is sent.

Cloudflare is a third-party service provided by Cloudflare, Inc. By using the purging feature you agree to Cloudflare's [Terms of Service](https://www.cloudflare.com/website-terms/) and [Privacy Policy](https://www.cloudflare.com/privacypolicy/).

## Development

```bash
composer install
composer lint      # PHPCS (WordPress-Extra + PHPCompatibility)
composer analyse   # PHPStan level 6
composer test      # PHPUnit + Brain Monkey
composer check     # all of the above
```

### Admin UI (JavaScript)

The compiled assets in `build/` are committed, so the plugin runs from a checkout. To rebuild or test the React app:

```bash
npm install
npm run build       # compile admin/src into build/
npm run start       # watch mode
npm run test:js     # Vitest + Testing Library
```

## License

GPL-2.0-or-later.
