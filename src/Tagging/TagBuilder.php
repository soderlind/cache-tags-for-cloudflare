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
		$site = $this->siteId();

		$tags = [
			'content',
			'b' . $site,
			'b' . $site . '-p' . (string) $post->ID,
			'b' . $site . '-pt-' . $post->post_type,
		];

		foreach ( $this->taxonomyTags( $post, $site ) as $tag ) {
			$tags[] = $tag;
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
	 * Build `{taxonomy}-{slug}` tags for every public taxonomy the post belongs to.
	 *
	 * @param WP_Post $post Queried post.
	 * @param string  $site Site identifier used to scope the tags.
	 *
	 * @return array<int, string>
	 */
	private function taxonomyTags( WP_Post $post, string $site ): array {
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
				$tags[] = 'b' . $site . '-' . $taxonomy . '-' . $term->slug;
			}
		}

		return $tags;
	}
}
