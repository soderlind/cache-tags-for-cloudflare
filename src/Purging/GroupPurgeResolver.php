<?php
/**
 * Resolves group-purge selections into concrete cache tags.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a UI "group" selection (post type, taxonomy terms, everything, raw tags)
 * to the raw cache tags that should be purged. Tags are normalized downstream.
 */
final class GroupPurgeResolver {

	/**
	 * Tags for a whole post type.
	 *
	 * @return array<int, string>
	 */
	public function forPostType( string $post_type ): array {
		$post_type = trim( $post_type );

		return '' === $post_type ? [] : [ 'post-type-' . $post_type ];
	}

	/**
	 * Tags for one or more terms in a taxonomy.
	 *
	 * @param array<int, string> $slugs Term slugs.
	 *
	 * @return array<int, string>
	 */
	public function forTaxonomyTerms( string $taxonomy, array $slugs ): array {
		$taxonomy = trim( $taxonomy );

		if ( '' === $taxonomy ) {
			return [];
		}

		$tags = [];

		foreach ( $slugs as $slug ) {
			$slug = trim( (string) $slug );

			if ( '' !== $slug ) {
				$tags[] = $taxonomy . '-' . $slug;
			}
		}

		return $tags;
	}

	/**
	 * The tag that invalidates every tagged response.
	 *
	 * @return array<int, string>
	 */
	public function everything(): array {
		return [ 'content' ];
	}

	/**
	 * Raw, comma-separated tags entered by the user.
	 *
	 * @return array<int, string>
	 */
	public function forRawTags( string $tags ): array {
		$parts = array_map( 'trim', explode( ',', $tags ) );

		return array_values( array_filter( $parts, static fn ( string $tag ): bool => '' !== $tag ) );
	}
}
