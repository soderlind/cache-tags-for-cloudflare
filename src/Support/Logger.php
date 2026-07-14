<?php
/**
 * Lightweight logger gated on the plugin's debug setting or WP_DEBUG.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes namespaced messages to the PHP error log when logging is enabled.
 */
final class Logger {

	public function __construct( private readonly Options $options ) {
	}

	/**
	 * Whether logging is currently enabled.
	 */
	public function enabled(): bool {
		return $this->options->boolean( 'debug' ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	}

	/**
	 * Log a message when logging is enabled.
	 */
	public function log( string $message ): void {
		if ( ! $this->enabled() ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Cache Tags for Cloudflare: ' . $message );
	}
}
