<?php
/**
 * Builds the default cache-tag set for a WordPress post.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tagging;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces the raw (un-normalized) default tags describing a single post.
 *
 * The tag vocabulary lives here; the Purging context references these same
 * prefixes when deciding what to invalidate.
 */
final class TagBuilder {

	/**
	 * Build the default cache tags for a post/page/CPT.
	 *
	 * @param WP_Post $post Queried post.
	 *
	 * @return array<int, string>
	 */
	public function forPost( WP_Post $post ): array {
		$tags = [
			'content',
			'post-id-' . (string) $post->ID,
			'post-type-' . $post->post_type,
		];

		if ( is_multisite() ) {
			$tags[] = 'site-id-' . (string) get_current_blog_id();
		}

		foreach ( $this->taxonomyTags( $post ) as $tag ) {
			$tags[] = $tag;
		}

		return $tags;
	}

	/**
	 * Build `{taxonomy}-{slug}` tags for every public taxonomy the post belongs to.
	 *
	 * @param WP_Post $post Queried post.
	 *
	 * @return array<int, string>
	 */
	private function taxonomyTags( WP_Post $post ): array {
		$tags       = [];
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! is_taxonomy_viewable( $taxonomy ) ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy );

			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$tags[] = $taxonomy . '-' . $term->slug;
			}
		}

		return $tags;
	}
}
