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
use Soderlind\CacheTagsForCloudflare\Purging\GroupPurgeResolver;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeCollector;
use Soderlind\CacheTagsForCloudflare\Purging\Purger;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeTriggers;
use Soderlind\CacheTagsForCloudflare\Rest\SettingsController;
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
		$resolver    = new GroupPurgeResolver();
		$normalizer  = new TagNormalizer();
		$purger      = new Purger( $client, $resolver, $normalizer );

		$this->bootTagging( $options );
		$this->bootPurging( $options, $logger, $client );
		$this->bootRest( $options, $credentials, $client, $resolver, $normalizer );
		$this->bootAdmin();
		$this->bootCli( $client, $purger );
		$this->bootHooks( $purger );
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
	 * Register the REST routes backing the admin app.
	 */
	private function bootRest( Options $options, Credentials $credentials, CloudflareClient $client, GroupPurgeResolver $resolver, TagNormalizer $normalizer ): void {
		( new SettingsController(
			$options,
			$credentials,
			$client,
			$resolver,
			$normalizer
		) )->register();
	}

	/**
	 * Wire the admin UI.
	 */
	private function bootAdmin(): void {
		if ( ! is_admin() ) {
			return;
		}

		( new SettingsPage() )->register();
		( new Notices() )->register();
	}

	/**
	 * Register WP-CLI commands.
	 */
	private function bootCli( CloudflareClient $client, Purger $purger ): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		\WP_CLI::add_command( 'cache-tags', new Command( $client, $purger ) );
	}

	/**
	 * Register the public action hooks that let integrators trigger purges.
	 *
	 * Each hook forwards to the shared {@see Purger} façade, so hooks and WP-CLI
	 * produce identical purges.
	 */
	private function bootHooks( Purger $purger ): void {
		add_action(
			'cache_tags_for_cloudflare/purge_post_type',
			static function ( $post_type ) use ( $purger ): void {
				$purger->purgePostType( (string) $post_type );
			},
			10,
			1
		);

		add_action(
			'cache_tags_for_cloudflare/purge_terms',
			static function ( $taxonomy, $slugs = [] ) use ( $purger ): void {
				$purger->purgeTerms( (string) $taxonomy, (array) $slugs );
			},
			10,
			2
		);

		add_action(
			'cache_tags_for_cloudflare/purge_post',
			static function ( $post_id ) use ( $purger ): void {
				$purger->purgePost( (int) $post_id );
			},
			10,
			1
		);

		add_action(
			'cache_tags_for_cloudflare/purge_all',
			static function () use ( $purger ): void {
				$purger->purgeEverything();
			},
			10,
			0
		);

		add_action(
			'cache_tags_for_cloudflare/purge',
			static function ( $tags ) use ( $purger ): void {
				$purger->purgeTags( is_array( $tags ) ? $tags : (string) $tags );
			},
			10,
			1
		);
	}
}
