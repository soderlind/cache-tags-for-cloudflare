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

## Configuration

Provide a **scoped** Cloudflare API token (Zone → Cache Purge) and a Zone ID. Constants in `wp-config.php` are preferred and take precedence over the settings UI:

```php
define( 'CACHE_TAGS_CF_API_TOKEN', 'your-scoped-token' );
define( 'CACHE_TAGS_CF_ZONE_ID', 'your-zone-id' );
```

Otherwise configure them under **Settings → Cache Tags**, where you can also test the connection and purge everything.

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

## License

GPL-2.0-or-later.
