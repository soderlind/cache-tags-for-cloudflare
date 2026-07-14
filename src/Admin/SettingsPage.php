<?php
/**
 * Registers the settings menu and mounts the React admin app.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Settings → Cache Tags and enqueues the built React application. All data
 * flows through the `cache-tags-for-cloudflare/v1` REST routes.
 */
final class SettingsPage {

	private const MENU_SLUG     = 'cache-tags-for-cloudflare';
	private const CAPABILITY    = 'manage_options';
	private const SCRIPT_HANDLE = 'cache-tags-for-cloudflare-admin';

	private string $hook_suffix = '';

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'addMenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Add the options page under Settings.
	 */
	public function addMenu(): void {
		$hook = add_options_page(
			__( 'Cache Tags for Cloudflare', 'cache-tags-for-cloudflare' ),
			__( 'Cache Tags', 'cache-tags-for-cloudflare' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'renderRoot' ]
		);

		$this->hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Enqueue the built app on our settings page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( '' === $this->hook_suffix || $hook !== $this->hook_suffix ) {
			return;
		}

		$asset_path  = CACHE_TAGS_FOR_CLOUDFLARE_DIR . 'build/index.asset.php';
		$script_path = CACHE_TAGS_FOR_CLOUDFLARE_DIR . 'build/index.js';

		if ( ! is_readable( $asset_path ) || ! is_readable( $script_path ) ) {
			add_action( 'admin_notices', [ $this, 'renderMissingBuildNotice' ] );

			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CACHE_TAGS_FOR_CLOUDFLARE_URL . 'build/index.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? null,
			true
		);

		wp_set_script_translations( self::SCRIPT_HANDLE, 'cache-tags-for-cloudflare' );

		wp_enqueue_style( 'wp-components' );

		$style_path = CACHE_TAGS_FOR_CLOUDFLARE_DIR . 'build/style-index.css';

		if ( is_readable( $style_path ) ) {
			wp_enqueue_style(
				self::SCRIPT_HANDLE,
				CACHE_TAGS_FOR_CLOUDFLARE_URL . 'build/style-index.css',
				[ 'wp-components' ],
				$asset['version'] ?? null
			);
			wp_style_add_data( self::SCRIPT_HANDLE, 'rtl', 'replace' );
		}
	}

	/**
	 * Render the mount point for the React app.
	 */
	public function renderRoot(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap"><div id="cache-tags-for-cloudflare-app"></div></div>';
	}

	/**
	 * Notice shown when the compiled assets are missing.
	 */
	public function renderMissingBuildNotice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Cache Tags for Cloudflare: the admin assets are missing. Run "npm install && npm run build".', 'cache-tags-for-cloudflare' )
		);
	}
}
