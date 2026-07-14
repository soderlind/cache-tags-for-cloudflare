# Purging

Watches WordPress content changes and asks Cloudflare to invalidate the affected Cache Tags.

## Language

**Purge**:
A request to Cloudflare to invalidate all cached responses carrying one or more given
Cache Tags. The unit of invalidation.
_Avoid_: Flush, clear, bust, invalidate (as a noun).

**Purge Trigger**:
A WordPress event (post publish/update/trash/delete, term change) that produces tags to
purge. Triggers are the only source of purges; nothing purges on read.
_Avoid_: Hook, event handler.

**Purge Batch**:
The deduplicated set of tags collected across all triggers within a single request,
flushed as one Cloudflare API call on `shutdown`. Capped at Cloudflare's 30-tags-per-call
limit (overflow splits into multiple calls).
_Avoid_: Queue, job, purge list.

**Zone**:
The Cloudflare zone whose cache is being purged, identified by a Zone ID. On multisite,
each site configures its own Zone.
_Avoid_: Domain, site, account.

**API Token**:
A scoped Cloudflare credential (Cache Purge permission) used to authenticate purges.
Read from `wp-config.php` constants when present, otherwise from plugin settings.
_Avoid_: API key, secret, credential.

**Connection Test**:
A verification call against Cloudflare's token-verify endpoint confirming the API Token
is valid before relying on it.
_Avoid_: Health check, ping.
