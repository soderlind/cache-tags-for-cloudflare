<?php
/**
 * Programmatic purge façade shared by WP-CLI and action hooks.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

use Soderlind\CacheTagsForCloudflare\Tagging\TagNormalizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single entry point for on-demand purges. Resolves a high-level selection
 * (post type, taxonomy terms, post, everything, or raw tags) into cache tags and
 * purges them immediately. Both the WP-CLI commands and the public action hooks
 * call this class, so the tag vocabulary is applied in exactly one place.
 */
final class Purger {

	public function __construct(
		private readonly PurgeClient $client,
		private readonly GroupPurgeResolver $resolver,
		private readonly TagNormalizer $normalizer
	) {
	}

	/**
	 * Purge a whole post type.
	 */
	public function purgePostType( string $post_type ): PurgeResult {
		return $this->run( $this->resolver->forPostType( $post_type ) );
	}

	/**
	 * Purge one or more terms in a taxonomy, identified by slug.
	 *
	 * @param array<int, string> $slugs Term slugs.
	 */
	public function purgeTerms( string $taxonomy, array $slugs ): PurgeResult {
		return $this->run( $this->resolver->forTaxonomyTerms( $taxonomy, $slugs ) );
	}

	/**
	 * Purge a single post by ID.
	 */
	public function purgePost( int $post_id ): PurgeResult {
		return $this->run( $this->resolver->forPost( $post_id ) );
	}

	/**
	 * Purge everything for the current site (the blog-scoped `b{site}` tag).
	 */
	public function purgeEverything(): PurgeResult {
		return $this->run( $this->resolver->everything() );
	}

	/**
	 * Purge an explicit list of already-formed tags.
	 *
	 * @param array<int, string>|string $tags Tag list, or a comma-separated string.
	 */
	public function purgeTags( array|string $tags ): PurgeResult {
		$list = is_array( $tags )
			? array_values( array_filter( array_map( 'trim', $tags ), static fn ( string $tag ): bool => '' !== $tag ) )
			: $this->resolver->forRawTags( $tags );

		return $this->run( $list );
	}

	/**
	 * Normalize and purge the given tags.
	 *
	 * @param array<int, string> $tags Raw tags to purge.
	 */
	private function run( array $tags ): PurgeResult {
		$tags = $this->normalizer->normalize( $tags );

		if ( [] === $tags ) {
			return PurgeResult::failure( 'No valid tags to purge for that selection.' );
		}

		return $this->client->purge( $tags );
	}
}
