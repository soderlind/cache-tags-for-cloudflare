# Split Tagging and Purging into two bounded contexts

The plugin both emits `Cache-Tag` headers and calls Cloudflare's API to purge by tag.
These are separable concerns with different failure modes, credentials, and lifecycles, so
we model them as two contexts ([Tagging](../../src/Tagging/CONTEXT.md),
[Purging](../../src/Purging/CONTEXT.md)) that can be enabled independently. Tagging owns the
tag vocabulary; Purging only references tags Tagging can emit. This keeps the
credential-bearing Purging code isolated and lets a site run header-only (the original
mu-plugin's behaviour) without ever configuring an API token.
