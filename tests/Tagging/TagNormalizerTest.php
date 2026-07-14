<?php
/**
 * Tests for TagNormalizer.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tests\Tagging;

use Brain\Monkey\Functions;
use Soderlind\CacheTagsForCloudflare\Tagging\TagNormalizer;
use Soderlind\CacheTagsForCloudflare\Tests\TestCase;

final class TagNormalizerTest extends TestCase {

	private TagNormalizer $normalizer;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'remove_accents' )->returnArg( 1 );
		$this->normalizer = new TagNormalizer();
	}

	public function test_lowercases_and_hyphenates_whitespace(): void {
		$this->assertSame(
			[ 'landing-page' ],
			$this->normalizer->normalize( [ 'Landing Page' ] )
		);
	}

	public function test_strips_unsupported_characters(): void {
		$this->assertSame(
			[ 'post-id-42', 'category-news' ],
			$this->normalizer->normalize( [ 'post-id-42!', 'Category #News' ] )
		);
	}

	public function test_removes_duplicates_and_empty(): void {
		$this->assertSame(
			[ 'content' ],
			$this->normalizer->normalize( [ 'content', 'content', '   ', '!!!', null, [ 'x' ] ] )
		);
	}

	public function test_preserves_allowed_symbols(): void {
		$this->assertSame(
			[ 'a_b.c:d-e' ],
			$this->normalizer->normalize( [ 'a_b.c:d-e' ] )
		);
	}

	public function test_caps_to_header_size_stops_at_first_overflow(): void {
		$long = str_repeat( 'a', 10000 );
		$tags = [ $long, $long . 'b', 'small' ];

		// First tag (10000) fits; the second (10001 + comma) exceeds 16384, so
		// capping stops there and everything after it is dropped as well.
		$result = $this->normalizer->capToHeaderSize( $tags );

		$this->assertSame( [ $long ], $result );
	}

	public function test_caps_returns_all_when_under_limit(): void {
		$tags = [ 'a', 'b', 'c' ];
		$this->assertSame( $tags, $this->normalizer->capToHeaderSize( $tags ) );
	}
}
