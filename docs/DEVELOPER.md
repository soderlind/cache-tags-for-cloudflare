# Developer guide

Hooks, programmatic purging, build tooling, and how to test purging for **Cache Tags for Cloudflare**.

- [Extensibility (hooks)](#extensibility-hooks)
- [Programmatic purging](#programmatic-purging)
- [Development](#development)
- [Admin UI (JavaScript)](#admin-ui-javascript)
- [Testing purging](#testing-purging)

## Extensibility (hooks)

### `cache_tags_for_cloudflare/tags` (filter)

Filter the `Cache-Tag` header tags emitted on a singular response. Runs with the current `WP_Post`.

```php
// Add an author tag so you can purge everything by one author.
add_filter( 'cache_tags_for_cloudflare/tags', function ( array $tags, WP_Post $post ) {
	$tags[] = 'author-' . $post->post_author;
	return $tags;
}, 10, 2 );

// Drop the site-wide `content` tag on a specific post type.
add_filter( 'cache_tags_for_cloudflare/tags', function ( array $tags, WP_Post $post ) {
	if ( 'landing_page' === $post->post_type ) {
		$tags = array_values( array_diff( $tags, [ 'content' ] ) );
	}
	return $tags;
}, 10, 2 );
```

### `cache_tags_for_cloudflare/purge_tags` (filter)

Filter the tags purged on a content change. `$context` is one of `post`, `term`, `post_type`, `everything`, or `tags`; `$object` is the related object (`WP_Post`, `WP_Term`, string, or array) for that context.

```php
// Also purge the author tag whenever one of their posts changes.
add_filter( 'cache_tags_for_cloudflare/purge_tags', function ( array $tags, string $context, $object ) {
	if ( 'post' === $context && $object instanceof WP_Post ) {
		$tags[] = 'author-' . $object->post_author;
	}
	return $tags;
}, 10, 3 );

// Never purge the site-wide `content` tag automatically.
add_filter( 'cache_tags_for_cloudflare/purge_tags', function ( array $tags ) {
	return array_values( array_diff( $tags, [ 'content' ] ) );
}, 10, 3 );
```

### `cache_tags_for_cloudflare/purged` and `.../purge_failed` (actions)

React to the result of a purge — e.g. for logging or notifications.

```php
add_action( 'cache_tags_for_cloudflare/purged', function ( array $tags ) {
	error_log( 'Cloudflare purged: ' . implode( ',', $tags ) );
} );

add_action( 'cache_tags_for_cloudflare/purge_failed', function ( array $tags, string $message ) {
	error_log( "Cloudflare purge failed ({$message}): " . implode( ',', $tags ) );
}, 10, 2 );
```

## Programmatic purging

Trigger an immediate purge from your own code with these action hooks (each maps to the shared purge façade):

```php
// Purge a whole post type (e.g. after a bulk import of pages).
do_action( 'cache_tags_for_cloudflare/purge_post_type', 'page' );

// Purge one or more taxonomy terms (by slug).
do_action( 'cache_tags_for_cloudflare/purge_terms', 'category', [ 'news', 'sport' ] );

// Purge a single post by ID.
do_action( 'cache_tags_for_cloudflare/purge_post', 42 );

// Purge everything (the site-wide `content` tag).
do_action( 'cache_tags_for_cloudflare/purge_all' );

// Purge raw cache tags (array or comma-separated string).
do_action( 'cache_tags_for_cloudflare/purge', [ 'b1-t5', 'content' ] );
do_action( 'cache_tags_for_cloudflare/purge', 'b1-t5,content' );
```

Example — purge a product's page and category after a WooCommerce stock change:

```php
add_action( 'woocommerce_product_set_stock', function ( $product ) {
	do_action( 'cache_tags_for_cloudflare/purge_post', $product->get_id() );
	do_action( 'cache_tags_for_cloudflare/purge_terms', 'product_cat', wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'slugs' ] ) );
} );
```

These hooks purge **immediately** (they are not batched on `shutdown` like auto-purge), so each call results in a Cloudflare API request. Term slugs passed to `.../purge_terms` are resolved to their numeric term IDs automatically.

## Development

```bash
composer install
composer lint      # PHPCS (WordPress-Extra + PHPCompatibility)
composer analyse   # PHPStan level 6
composer test      # PHPUnit + Brain Monkey
composer check     # all of the above
```

See [`CONTEXT-MAP.md`](../CONTEXT-MAP.md) and [`docs/adr/`](adr/) for the domain model and key architectural decisions.

## Admin UI (JavaScript)

The compiled assets in `build/` are committed, so the plugin runs from a checkout. To rebuild or test the React app:

```bash
npm install
npm run build       # compile admin/src into build/
npm run start       # watch mode
npm run test:js     # Vitest + Testing Library
```

## Testing purging

You do **not** need a public site to test purging — the Cloudflare purge API never fetches your site, it just tells Cloudflare which cache tags to invalidate. Purge by cache-tag (and honoring the `Cache-Tag` response header) is available on **all Cloudflare plans**, including Free.

**1. Logic only (no Cloudflare account).** The unit tests drive the whole tag → resolve → batch pipeline with a fake client:

```bash
composer test
```

**2. Real API from a local site (no public site needed).** Add a scoped token and Zone ID (in `wp-config.php` or **Settings → Cache Tags**), then:

```bash
wp cache-tags verify                       # GET /user/tokens/verify — works on any plan
wp cache-tags purge --tags=b1-t5,content   # POST /zones/{id}/purge_cache
```

Both calls succeed from a local install; neither requires the content to be publicly reachable.

**3. Full HIT → purge → MISS.** To watch Cloudflare actually cache a tagged response and drop it on purge, proxy a hostname on a zone you own through Cloudflare — e.g. with a [Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) pointing at your local site:

```bash
brew install cloudflared
cloudflared tunnel login
cloudflared tunnel --url http://your-local-site.test   # map a proxied hostname on your zone
```

Then:

```bash
curl -sI https://test.yourzone.com/some-post   # note Cache-Tag + cf-cache-status: HIT
wp cache-tags purge --post=123                 # or trigger any purge front door
curl -sI https://test.yourzone.com/some-post   # cf-cache-status: MISS (or EXPIRED with Tiered Cache)
```

Relevant Cloudflare limits (per account, shared across same-plan zones): Free allows 5 purge requests/minute; the `Cache-Tag` header is capped at 16 KB (~1,000 tags) and individual purge tags at 1,024 characters. The plugin already caps the header at 16 KB and batches purges at 30 tags per request.
