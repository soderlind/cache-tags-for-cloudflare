<?php
/**
 * Emits the Cache-Tag response header on singular content.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tagging;

use WP;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the `wp_headers` filter and adds a normalized, size-capped Cache-Tag header.
 */
final class HeaderEmitter {

	public function __construct(
		private readonly TagBuilder $builder,
		private readonly TagNormalizer $normalizer
	) {
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_filter( 'wp_headers', [ $this, 'addHeader' ], 10, 2 );
	}

	/**
	 * Add the Cache-Tag header to singular content responses.
	 *
	 * @param array<string, string> $headers Existing response headers.
	 * @param WP                     $wp      WordPress environment object.
	 *
	 * @return array<string, string>
	 */
	public function addHeader( array $headers, WP $wp ): array {
		if ( is_admin() || ! is_singular() ) {
			return $headers;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return $headers;
		}

		$tags = $this->builder->forPost( $post );

		/**
		 * Filters the cache tags for a singular content response.
		 *
		 * Tags are normalized after this filter. Return raw strings such as
		 * "author-12" or "landing-page"; spaces and unsupported characters are
		 * converted or removed before the header is sent.
		 *
		 * @param array<int, string> $tags Default cache tags.
		 * @param WP_Post            $post Current queried post.
		 * @param WP                 $wp   WordPress environment object.
		 */
		$tags = apply_filters( 'cache_tags_for_cloudflare/tags', $tags, $post, $wp );

		$tags = $this->normalizer->normalize( (array) $tags );

		if ( [] === $tags ) {
			return $headers;
		}

		$headers['Cache-Tag'] = implode( ',', $this->normalizer->capToHeaderSize( $tags ) );

		return $headers;
	}
}
