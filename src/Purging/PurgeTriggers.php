<?php
/**
 * Maps WordPress content-change events to cache tags and queues them for purge.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WordPress hooks that trigger purges and resolves which tags each
 * event should invalidate. The tag decision runs through a single filterable seam
 * so integrators can extend it.
 */
final class PurgeTriggers {

	public function __construct( private readonly PurgeCollector $collector ) {
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'transition_post_status', [ $this, 'onPostTransition' ], 10, 3 );
		add_action( 'before_delete_post', [ $this, 'onPostDeleted' ], 10, 2 );
		add_action( 'edited_term', [ $this, 'onTermChanged' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'onTermChanged' ], 10, 3 );
	}

	/**
	 * Purge when a post is published, updated, or trashed.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 */
	public function onPostTransition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		$this->purgePost( $post );
	}

	/**
	 * Purge when a post is permanently deleted.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public function onPostDeleted( int $post_id, ?WP_Post $post = null ): void {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( $post instanceof WP_Post ) {
			$this->purgePost( $post );
		}
	}

	/**
	 * Purge when a term is edited or deleted.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function onTermChanged( int $term_id, int $tt_id, string $taxonomy ): void {
		$term = get_term( $term_id, $taxonomy );

		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$site = $this->siteId();

		$this->collector->add(
			$this->filterTags( [ 'b' . $site . '-t' . (string) $term->term_id ], 'term', $term )
		);
	}

	/**
	 * The site identifier used to scope tags: the blog ID on multisite, `1` otherwise.
	 */
	private function siteId(): string {
		return is_multisite() ? (string) get_current_blog_id() : '1';
	}

	/**
	 * Resolve and queue the tags for a changed post.
	 */
	private function purgePost( WP_Post $post ): void {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		$site = $this->siteId();
		$tags = [ 'b' . $site . '-p' . (string) $post->ID ];

		foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
			if ( ! is_taxonomy_viewable( $taxonomy ) ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy );

			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$tags[] = 'b' . $site . '-t' . (string) $term->term_id;
			}
		}

		$this->collector->add( $this->filterTags( $tags, 'post', $post ) );
	}

	/**
	 * Run the purge-tag list through the extensibility filter.
	 *
	 * @param array<int, string> $tags    Default tags to purge.
	 * @param string             $context Either 'post' or 'term'.
	 * @param WP_Post|WP_Term    $subject The changed object.
	 *
	 * @return array<int, string>
	 */
	private function filterTags( array $tags, string $context, WP_Post|WP_Term $subject ): array {
		/**
		 * Filters the cache tags purged for a content-change event.
		 *
		 * @param array<int, string> $tags    Default tags to purge.
		 * @param string             $context Event context: 'post' or 'term'.
		 * @param WP_Post|WP_Term    $subject The changed object.
		 */
		return (array) apply_filters( 'cache_tags_for_cloudflare/purge_tags', $tags, $context, $subject );
	}
}
