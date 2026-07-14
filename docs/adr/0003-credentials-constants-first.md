# Cloudflare credentials: constants-first, settings-UI fallback

Cloudflare purge requires an API token and zone ID. Storing secrets in the options table is
convenient but leaks them into DB backups, exports, and staging syncs. We therefore read
`CACHE_TAGS_CF_API_TOKEN` and `CACHE_TAGS_CF_ZONE_ID` from `wp-config.php` constants when
defined — the settings-UI fields are then shown read-only — and fall back to option storage
only when the constants are absent. We require a **scoped** API Token (Cache Purge
permission), never the Global API Key. Trade-off: constant-based config can't be edited by
non-developers in wp-admin, which we accept as the secure default.
