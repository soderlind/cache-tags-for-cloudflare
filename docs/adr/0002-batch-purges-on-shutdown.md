# Batch purges on `shutdown` instead of inline or via cron

A single editor action fires many hooks (`save_post`, `transition_post_status`, term
edits) for the same post. Purging inline in each hook means several redundant Cloudflare
calls and puts API latency/failure on the editor's critical path; a persistent wp-cron
queue adds delay and moving parts we don't yet need. We instead collect and deduplicate
tags during the request and send **one** purge on the `shutdown` hook, split into batches of
30 tags (Cloudflare's per-call limit). Trade-off: a failed purge is not automatically
retried (no persistent queue) — we log it, fire success/failure action hooks, and surface a
dismissible admin notice after repeated failures. The collector sits behind an interface so
a cron-backed queue can replace it later without touching the triggers.
