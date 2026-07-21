# Tagging

Attaches a `Cache-Tag` HTTP response header to singular WordPress responses so that a
downstream CDN can later invalidate them by tag.

## Language

**Cache Tag**:
A short label attached to a cached response identifying content it depends on
(e.g. `b1-p42`, `b1-category-news`). The unit of both description and invalidation.
_Avoid_: Surrogate key, cache key, purge key.

**Cache-Tag header**:
The single HTTP response header whose comma-separated value is the response's normalized
cache tags. There is exactly one per response.
_Avoid_: Tag header, surrogate-key header.

**Tag Set**:
The deduplicated, normalized collection of Cache Tags emitted for one response.
_Avoid_: Tag list, tags array.

**Normalization**:
The canonicalization applied to every tag before it is emitted: lowercased, accents
removed, whitespace to hyphens, unsupported characters stripped, duplicates removed.
_Avoid_: Sanitizing, cleaning, escaping.

**Singular content**:
A response representing a single queried post/page/CPT (`is_singular()`). The only
response type Tagging acts on.
_Avoid_: Single page, permalink page, detail page.

**Header-size cap**:
Cloudflare's 16 KB aggregate limit on the `Cache-Tag` header value. Tags beyond the cap
are dropped, not truncated mid-tag.
_Avoid_: Header limit, max length.
