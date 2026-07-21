<?php
/**
 * Tests for GroupPurgeResolver.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tests\Purging;

use Brain\Monkey\Functions;
use Soderlind\CacheTagsForCloudflare\Purging\GroupPurgeResolver;
use Soderlind\CacheTagsForCloudflare\Tests\TestCase;

final class GroupPurgeResolverTest extends TestCase {

	private GroupPurgeResolver $resolver;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'is_multisite' )->justReturn( false );
		$this->resolver = new GroupPurgeResolver();
	}

	public function test_post_type(): void {
		$this->assertSame( [ 'b1-pt-page' ], $this->resolver->forPostType( 'page' ) );
	}

	public function test_empty_post_type(): void {
		$this->assertSame( [], $this->resolver->forPostType( '  ' ) );
	}

	public function test_post(): void {
		$this->assertSame( [ 'b1-p124' ], $this->resolver->forPost( 124 ) );
		$this->assertSame( [], $this->resolver->forPost( 0 ) );
	}

	public function test_taxonomy_terms(): void {
		Functions\when( 'get_term_by' )->alias(
			static function ( string $field, string $slug, string $taxonomy ) {
				$map = [ 'news' => 5, 'sport' => 8 ];

				return isset( $map[ $slug ] ) ? new \WP_Term( [ 'term_id' => $map[ $slug ], 'slug' => $slug ] ) : false;
			}
		);

		$this->assertSame(
			[ 'b1-t5', 'b1-t8' ],
			$this->resolver->forTaxonomyTerms( 'category', [ 'news', ' sport ', '', 'unknown' ] )
		);
	}

	public function test_taxonomy_requires_taxonomy(): void {
		$this->assertSame( [], $this->resolver->forTaxonomyTerms( '', [ 'news' ] ) );
	}

	public function test_everything(): void {
		$this->assertSame( [ 'content' ], $this->resolver->everything() );
	}

	public function test_raw_tags(): void {
		$this->assertSame(
			[ 'post-id-42', 'category-news' ],
			$this->resolver->forRawTags( ' post-id-42 , category-news ,, ' )
		);
	}
}
