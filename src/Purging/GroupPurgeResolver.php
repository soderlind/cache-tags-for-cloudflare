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

		return '' === $post_type ? [] : [ 'b' . $this->siteId() . '-pt-' . $post_type ];
	}

	/**
	 * Tags for a single post by ID.
	 *
	 * @return array<int, string>
	 */
	public function forPost( int $post_id ): array {
		return $post_id > 0 ? [ 'b' . $this->siteId() . '-p' . (string) $post_id ] : [];
	}

	/**
	 * Tags for one or more terms in a taxonomy.
	 *
	 * Slugs are resolved to term IDs so the produced tags (`b{site}-t{term_id}`)
	 * match the tags emitted on responses. Unknown slugs are skipped.
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

		$site = $this->siteId();
		$tags = [];

		foreach ( $slugs as $slug ) {
			$slug = trim( (string) $slug );

			if ( '' === $slug ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, $taxonomy );

			if ( $term instanceof \WP_Term ) {
				$tags[] = 'b' . $site . '-t' . (string) $term->term_id;
			}
		}

		return $tags;
	}

	/**
	 * The site identifier used to scope tags: the blog ID on multisite, `1` otherwise.
	 */
	private function siteId(): string {
		return is_multisite() ? (string) get_current_blog_id() : '1';
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
