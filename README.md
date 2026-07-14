# Cache Tags for Cloudflare

Adds `Cache-Tag` HTTP response headers for singular WordPress content and purges Cloudflare by tag when content changes.

- **Requires:** WordPress 6.8+, PHP 8.3+
- **Works on any Cloudflare plan** — the `Cache-Tag` header and purge-by-tag are available on all plans (Free, Pro, Business, Enterprise); purge API rate limits scale with your plan.

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
post-id-{ID}
post-type-{post_type}
{taxonomy}-{slug}      # for every public taxonomy the post belongs to
site-id-{blog_id}      # multisite only
```

Example: `Cache-Tag: content,post-id-42,post-type-post,category-news`

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

- **Purge** — manual, on-demand purges by group: a whole post type (`post-type-{type}`), a taxonomy term (`{taxonomy}-{slug}`), everything (`content`), or raw comma-separated tags. The purge tools stay locked until valid credentials have been saved and verified.
- **Settings** — toggles for header emission, auto-purge, and debug logging, plus the API token and Zone ID (read-only when defined via constants). **Save settings** persists the values and automatically verifies the connection; a **Test connection** button is also available.

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
wp cache-tags purge --tags=post-id-42,category-news
wp cache-tags purge --all
wp cache-tags verify
```

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
