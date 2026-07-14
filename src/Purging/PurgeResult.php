<?php
/**
 * Immutable result of a Cloudflare API interaction.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries success state and a human-readable message for a purge or verify call.
 */
final class PurgeResult {

	private function __construct(
		public readonly bool $success,
		public readonly string $message
	) {
	}

	/**
	 * Create a successful result.
	 */
	public static function success( string $message = '' ): self {
		return new self( true, $message );
	}

	/**
	 * Create a failed result.
	 */
	public static function failure( string $message ): self {
		return new self( false, $message );
	}
}
