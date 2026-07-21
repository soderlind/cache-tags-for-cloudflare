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

	public function test_taxonomy_terms(): void {
		$this->assertSame(
			[ 'b1-category-news', 'b1-category-sport' ],
			$this->resolver->forTaxonomyTerms( 'category', [ 'news', ' sport ', '' ] )
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
