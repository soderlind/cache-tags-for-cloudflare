<?php
/**
 * WP-CLI commands for cache-tag purges and token verification.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\CLI;

use Soderlind\CacheTagsForCloudflare\Purging\CloudflareClient;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Cloudflare cache tags.
 */
final class Command {

	public function __construct( private readonly CloudflareClient $client ) {
	}

	/**
	 * Purge cache tags from Cloudflare.
	 *
	 * ## OPTIONS
	 *
	 * [--tags=<tags>]
	 * : Comma-separated list of tags to purge.
	 *
	 * [--all]
	 * : Purge the `content` tag, invalidating every tagged response.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cache-tags purge --tags=b1-p42,b1-category-news
	 *     wp cache-tags purge --all
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function purge( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['all'] ) ) {
			$tags = [ 'content' ];
		} elseif ( ! empty( $assoc_args['tags'] ) ) {
			$tags = array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['tags'] ) ) );
		} else {
			WP_CLI::error( 'Provide --tags=<tags> or --all.' );
		}

		$result = $this->client->purge( $tags );

		if ( $result->success ) {
			WP_CLI::success( '' !== $result->message ? $result->message : 'Purged.' );

			return;
		}

		WP_CLI::error( $result->message );
	}

	/**
	 * Verify the configured Cloudflare API token.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cache-tags verify
	 */
	public function verify(): void {
		$result = $this->client->verify();

		if ( $result->success ) {
			WP_CLI::success( '' !== $result->message ? $result->message : 'Token is valid.' );

			return;
		}

		WP_CLI::error( $result->message );
	}
}
