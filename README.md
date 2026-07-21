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
b{id}-t{term_id}       # for every public taxonomy term the post belongs to
```

Example: `Cache-Tag: content,b1,b1-p42,b1-pt-post,b1-t5`

Term tags use the numeric term ID (`b1-t5`), so they stay stable when a term is renamed or its slug changes.

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

- **Purge** — manual, on-demand purges by group: a whole post type (`b{id}-pt-{type}`), a taxonomy term (`b{id}-t{term_id}`), everything (`content`), or raw comma-separated tags. The purge tools stay locked until valid credentials have been saved and verified.
- **Settings** — toggles for header emission, auto-purge, and debug logging, plus the API token and Zone ID (read-only when defined via constants). **Save settings** persists the values and automatically verifies the connection; a **Test connection** button is also available.

## Automatic purging

When **Auto-purge on changes** is enabled (and valid credentials are set), the plugin purges the affected tags automatically on these events:

| Event | Purges |
| --- | --- |
| Post published, updated, trashed, or untrashed (any transition **to or from** the published status) | `b{id}-p{ID}` + the post's `b{id}-t{term_id}` tags |
| Post permanently deleted | `b{id}-p{ID}` + its `b{id}-t{term_id}` tags |
| Taxonomy term edited or deleted | `b{id}-t{term_id}` |

Details:

- Only **public** post types and taxonomies are considered; **revisions and autosaves are ignored**.
- Tags from all triggers in a request are **de-duplicated** and sent as a **single batched purge on `shutdown`** (split into Cloudflare's 30-tags-per-request batches), off the editor's critical path.
- **Not** triggered by: draft-only edits, comments, menu/widget/theme changes, or plugin/core updates. Use the **Purge** tab, WP-CLI, or the `cache_tags_for_cloudflare/purge_tags` filter for those.
- Static files (e.g. images under `wp-content/uploads/`) are served outside WordPress, so they are **not** tagged or purged by tag.

## Extensibility

The plugin exposes filters (`cache_tags_for_cloudflare/tags`, `.../purge_tags`), result actions (`.../purged`, `.../purge_failed`), and action hooks for programmatic purging (`.../purge_post`, `.../purge_terms`, `.../purge_post_type`, `.../purge_all`, `.../purge`). See the [Developer guide](docs/DEVELOPER.md) for full documentation and examples.

## WP-CLI

```bash
wp cache-tags purge --post-type=page
wp cache-tags purge --taxonomy=category --terms=news,sport
wp cache-tags purge --post=42
wp cache-tags purge --tags=b1-t5,content
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

Build tooling, hooks, programmatic purging, and how to test purging (including without a public site) are documented in the [Developer guide](docs/DEVELOPER.md).

```bash
composer install
composer check      # PHPCS + PHPStan + PHPUnit
npm install && npm run build   # rebuild the React admin UI
```

## License

GPL-2.0-or-later.
