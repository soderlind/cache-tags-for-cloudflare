<?php
/**
 * Plugin bootstrap: constructs and wires the two contexts.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare;

use Soderlind\CacheTagsForCloudflare\Admin\Notices;
use Soderlind\CacheTagsForCloudflare\Admin\SettingsPage;
use Soderlind\CacheTagsForCloudflare\CLI\Command;
use Soderlind\CacheTagsForCloudflare\Purging\CloudflareClient;
use Soderlind\CacheTagsForCloudflare\Purging\Credentials;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeCollector;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeTriggers;
use Soderlind\CacheTagsForCloudflare\Support\Logger;
use Soderlind\CacheTagsForCloudflare\Support\Options;
use Soderlind\CacheTagsForCloudflare\Tagging\HeaderEmitter;
use Soderlind\CacheTagsForCloudflare\Tagging\TagBuilder;
use Soderlind\CacheTagsForCloudflare\Tagging\TagNormalizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composition root. Instantiates collaborators and registers hooks.
 */
final class Plugin {

	/**
	 * Boot the plugin.
	 */
	public function boot(): void {
		$options     = new Options();
		$logger      = new Logger( $options );
		$credentials = new Credentials( $options );
		$client      = new CloudflareClient( $credentials, $logger );

		$this->bootTagging( $options );
		$this->bootPurging( $options, $logger, $client );
		$this->bootAdmin( $options, $credentials, $client );
		$this->bootCli( $client );
	}

	/**
	 * Wire the Tagging context.
	 */
	private function bootTagging( Options $options ): void {
		if ( ! $options->boolean( 'header_enabled' ) ) {
			return;
		}

		( new HeaderEmitter( new TagBuilder(), new TagNormalizer() ) )->register();
	}

	/**
	 * Wire the Purging context.
	 */
	private function bootPurging( Options $options, Logger $logger, CloudflareClient $client ): void {
		if ( ! $options->boolean( 'purge_enabled' ) ) {
			return;
		}

		$collector = new PurgeCollector( $client, $options, $logger );
		( new PurgeTriggers( $collector ) )->register();
	}

	/**
	 * Wire the admin UI.
	 */
	private function bootAdmin( Options $options, Credentials $credentials, CloudflareClient $client ): void {
		if ( ! is_admin() ) {
			return;
		}

		( new SettingsPage( $options, $credentials, $client ) )->register();
		( new Notices() )->register();
	}

	/**
	 * Register WP-CLI commands.
	 */
	private function bootCli( CloudflareClient $client ): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		\WP_CLI::add_command( 'cache-tags', new Command( $client ) );
	}
}
