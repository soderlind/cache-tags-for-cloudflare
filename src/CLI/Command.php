<?php
/**
 * WP-CLI commands for cache-tag purges and token verification.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\CLI;

use Soderlind\CacheTagsForCloudflare\Purging\CloudflareClient;
use Soderlind\CacheTagsForCloudflare\Purging\Purger;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Cloudflare cache tags.
 */
final class Command {

	public function __construct(
		private readonly CloudflareClient $client,
		private readonly Purger $purger
	) {
	}

	/**
	 * Purge cache tags from Cloudflare.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Purge the current site's `b{site}` tag, invalidating every tagged response for this site.
	 * On multisite this stays scoped to the current subsite; use `--tags=content` to purge every
	 * site sharing the zone.
	 *
	 * [--post-type=<post_type>]
	 * : Purge a whole post type (e.g. `page`).
	 *
	 * [--taxonomy=<taxonomy>]
	 * : Purge terms in a taxonomy. Requires --terms.
	 *
	 * [--terms=<slugs>]
	 * : Comma-separated term slugs to purge within --taxonomy (e.g. `news,sport`).
	 *
	 * [--post=<id>]
	 * : Purge a single post by ID.
	 *
	 * [--tags=<tags>]
	 * : Comma-separated list of already-formed tags to purge (e.g. `b1-t5,content`).
	 *
	 * [--urls=<urls>]
	 * : Comma-separated list of absolute URLs to purge by URL (e.g. a cached 404 that
	 * has since become a real page). URLs must match the cached request exactly
	 * (scheme, host, trailing slash, query). Note: `--url` is reserved by WP-CLI for
	 * multisite site selection, so this flag is `--urls`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cache-tags purge --all
	 *     wp cache-tags purge --post-type=page
	 *     wp cache-tags purge --taxonomy=category --terms=news,sport
	 *     wp cache-tags purge --post=124
	 *     wp cache-tags purge --tags=b1-t5,content
	 *     wp cache-tags purge --urls=https://example.com/hello-world/
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function purge( array $args, array $assoc_args ): void {
		$result = match ( true ) {
			isset( $assoc_args['all'] )       => $this->purger->purgeEverything(),
			isset( $assoc_args['post-type'] ) => $this->purger->purgePostType( (string) $assoc_args['post-type'] ),
			isset( $assoc_args['post'] )      => $this->purger->purgePost( (int) $assoc_args['post'] ),
			isset( $assoc_args['taxonomy'] )  => $this->purger->purgeTerms(
				(string) $assoc_args['taxonomy'],
				array_filter( array_map( 'trim', explode( ',', (string) ( $assoc_args['terms'] ?? '' ) ) ) )
			),
			! empty( $assoc_args['tags'] )    => $this->purger->purgeTags( (string) $assoc_args['tags'] ),
			! empty( $assoc_args['urls'] )    => $this->purger->purgeUrls( (string) $assoc_args['urls'] ),
			default                           => null,
		};

		if ( null === $result ) {
			WP_CLI::error( 'Provide one of --all, --post-type, --taxonomy (with --terms), --post, --tags, or --urls.' );
		}

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
