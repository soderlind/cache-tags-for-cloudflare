<?php
/**
 * Collects tags to purge during a request and flushes them once on shutdown.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

use Soderlind\CacheTagsForCloudflare\Support\Logger;
use Soderlind\CacheTagsForCloudflare\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accumulates unique tags across a request and sends a single batched purge on
 * the `shutdown` hook. Purge failures are logged, surfaced via a transient the
 * admin notice reads, and broadcast through action hooks.
 */
final class PurgeCollector {

	public const FAILURE_TRANSIENT = 'cache_tags_for_cloudflare_purge_error';

	/**
	 * Pending tags for this request, keyed by tag for O(1) dedupe.
	 *
	 * @var array<string, true>
	 */
	private array $pending = [];

	private bool $flush_registered = false;

	public function __construct(
		private readonly PurgeClient $client,
		private readonly Options $options,
		private readonly Logger $logger
	) {
	}

	/**
	 * Queue tags to be purged when the request ends.
	 *
	 * @param array<int, string> $tags Tags to purge.
	 */
	public function add( array $tags ): void {
		if ( ! $this->options->boolean( 'purge_enabled' ) ) {
			return;
		}

		foreach ( $tags as $tag ) {
			$tag = trim( (string) $tag );

			if ( '' !== $tag ) {
				$this->pending[ $tag ] = true;
			}
		}

		$this->registerFlush();
	}

	/**
	 * Register the shutdown flush exactly once.
	 */
	private function registerFlush(): void {
		if ( $this->flush_registered ) {
			return;
		}

		$this->flush_registered = true;
		add_action( 'shutdown', [ $this, 'flush' ], 100 );
	}

	/**
	 * Send the accumulated purge as a single batched request.
	 */
	public function flush(): void {
		if ( [] === $this->pending ) {
			return;
		}

		$tags          = array_keys( $this->pending );
		$this->pending = [];

		$result = $this->client->purge( $tags );

		if ( $result->success ) {
			delete_transient( self::FAILURE_TRANSIENT );

			/**
			 * Fires after cache tags are successfully purged.
			 *
			 * @param array<int, string> $tags Purged tags.
			 */
			do_action( 'cache_tags_for_cloudflare/purged', $tags );

			return;
		}

		$this->logger->log( 'Purge failed: ' . $result->message );
		set_transient( self::FAILURE_TRANSIENT, $result->message, DAY_IN_SECONDS );

		/**
		 * Fires when a cache-tag purge fails.
		 *
		 * @param array<int, string> $tags    Tags that failed to purge.
		 * @param string             $message Error message from Cloudflare.
		 */
		do_action( 'cache_tags_for_cloudflare/purge_failed', $tags, $result->message );
	}
}
