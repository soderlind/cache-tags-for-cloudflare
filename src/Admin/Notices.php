<?php
/**
 * Admin notice for recurring purge failures.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Admin;

use Soderlind\CacheTagsForCloudflare\Purging\PurgeCollector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces the most recent background purge failure to administrators.
 */
final class Notices {

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'renderFailureNotice' ] );
	}

	/**
	 * Render a dismissible notice when a background purge has failed.
	 */
	public function renderFailureNotice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = get_transient( PurgeCollector::FAILURE_TRANSIENT );

		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Cache Tags for Cloudflare could not purge Cloudflare:', 'cache-tags-for-cloudflare' ),
			esc_html( $message )
		);
	}
}
