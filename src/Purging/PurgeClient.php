<?php
/**
 * Contract for something that can purge cache tags.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstraction over the purge backend so the collector doesn't depend on the
 * concrete Cloudflare client (enables a future cron-backed implementation).
 */
interface PurgeClient {

	/**
	 * Purge the given tags.
	 *
	 * @param array<int, string> $tags Normalized tags to invalidate.
	 */
	public function purge( array $tags ): PurgeResult;

	/**
	 * Purge the given URLs (Cloudflare "purge by URL").
	 *
	 * Invalidates responses that carry no cache tag — e.g. a cached 404 at a URL
	 * that has since become a real post or archive.
	 *
	 * @param array<int, string> $urls Absolute URLs to invalidate.
	 */
	public function purgeUrls( array $urls ): PurgeResult;
}
