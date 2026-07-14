# Context Map

This plugin spans two bounded contexts: one that **describes** a response by attaching
cache tags, and one that **invalidates** cached responses by tag.

## Contexts

- [Tagging](./src/Tagging/CONTEXT.md) — emits the `Cache-Tag` HTTP response header on singular content.
- [Purging](./src/Purging/CONTEXT.md) — tells Cloudflare which tags to invalidate when content changes.

## Relationships

- **Tagging → Purging**: The tag vocabulary is owned by Tagging. Purging never invents
  tags; it only references tags that Tagging is capable of emitting (`post-id-{ID}`,
  `{taxonomy}-{slug}`, `content`, …). If a tag is not emitted on the response, purging it
  has no effect.
- **Shared constraint (Cloudflare)**: The `Cache-Tag` header and purge-by-tag are
  **Cloudflare Enterprise-plan-only** features. Both contexts are inert on non-Enterprise zones.
- **Independence**: The two contexts are toggled independently (a site may emit headers
  without granting purge credentials, or vice-versa).
