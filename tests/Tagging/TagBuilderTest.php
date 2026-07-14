<?php
/**
 * Tests for TagBuilder.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tests\Tagging;

use Brain\Monkey\Functions;
use Soderlind\CacheTagsForCloudflare\Tagging\TagBuilder;
use Soderlind\CacheTagsForCloudflare\Tests\TestCase;
use WP_Post;
use WP_Term;

final class TagBuilderTest extends TestCase {

	public function test_builds_default_tags_for_single_site(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_object_taxonomies' )->justReturn( [ 'category' ] );
		Functions\when( 'is_taxonomy_viewable' )->justReturn( true );
		Functions\when( 'get_the_terms' )->justReturn(
			[ new WP_Term( [ 'slug' => 'news' ] ), new WP_Term( [ 'slug' => 'sport' ] ) ]
		);

		$post = new WP_Post( [ 'ID' => 42, 'post_type' => 'post' ] );

		$this->assertSame(
			[ 'content', 'post-id-42', 'post-type-post', 'category-news', 'category-sport' ],
			( new TagBuilder() )->forPost( $post )
		);
	}

	public function test_adds_site_id_on_multisite(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_current_blog_id' )->justReturn( 3 );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );

		$post = new WP_Post( [ 'ID' => 7, 'post_type' => 'page' ] );

		$this->assertSame(
			[ 'content', 'post-id-7', 'post-type-page', 'site-id-3' ],
			( new TagBuilder() )->forPost( $post )
		);
	}

	public function test_skips_non_viewable_taxonomies(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_object_taxonomies' )->justReturn( [ 'category', 'wp_pattern_category' ] );
		Functions\when( 'is_taxonomy_viewable' )->alias(
			static fn ( string $taxonomy ): bool => 'category' === $taxonomy
		);
		Functions\when( 'get_the_terms' )->justReturn( [ new WP_Term( [ 'slug' => 'news' ] ) ] );

		$post = new WP_Post( [ 'ID' => 1, 'post_type' => 'post' ] );

		$this->assertSame(
			[ 'content', 'post-id-1', 'post-type-post', 'category-news' ],
			( new TagBuilder() )->forPost( $post )
		);
	}
}
