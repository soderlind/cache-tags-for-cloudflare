<?php
/**
 * Tests for the Purger façade.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tests\Purging;

use Brain\Monkey\Functions;
use Soderlind\CacheTagsForCloudflare\Purging\GroupPurgeResolver;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeClient;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeResult;
use Soderlind\CacheTagsForCloudflare\Purging\Purger;
use Soderlind\CacheTagsForCloudflare\Tagging\TagNormalizer;
use Soderlind\CacheTagsForCloudflare\Tests\TestCase;

final class PurgerTest extends TestCase {

	/**
	 * Records the tags handed to the purge backend.
	 */
	private object $client;

	private Purger $purger;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'remove_accents' )->returnArg( 1 );

		$this->client = new class() implements PurgeClient {
			/**
			 * @var array<int, string>
			 */
			public array $received = [];

			/**
			 * @var array<int, string>
			 */
			public array $received_urls = [];

			public bool $called = false;

			public bool $urls_called = false;

			public function purge( array $tags ): PurgeResult {
				$this->called   = true;
				$this->received = $tags;

				return PurgeResult::success( 'ok' );
			}

			public function purgeUrls( array $urls ): PurgeResult {
				$this->urls_called   = true;
				$this->received_urls = $urls;

				return PurgeResult::success( 'ok' );
			}
		};

		$this->purger = new Purger( $this->client, new GroupPurgeResolver(), new TagNormalizer() );
	}

	public function test_purge_post_type(): void {
		$result = $this->purger->purgePostType( 'page' );

		$this->assertTrue( $result->success );
		$this->assertSame( [ 'b1-pt-page' ], $this->client->received );
	}

	public function test_purge_post(): void {
		$this->purger->purgePost( 124 );

		$this->assertSame( [ 'b1-p124' ], $this->client->received );
	}

	public function test_purge_everything(): void {
		$this->purger->purgeEverything();

		$this->assertSame( [ 'b1' ], $this->client->received );
	}

	public function test_purge_terms(): void {
		Functions\when( 'get_term_by' )->alias(
			static fn ( string $field, string $slug, string $taxonomy ) =>
				'news' === $slug ? new \WP_Term( [ 'term_id' => 5, 'slug' => 'news' ] ) : false
		);

		$this->purger->purgeTerms( 'category', [ 'news' ] );

		$this->assertSame( [ 'b1-t5' ], $this->client->received );
	}

	public function test_purge_tags_passthrough(): void {
		$this->purger->purgeTags( [ 'b1-t5', 'content' ] );

		$this->assertSame( [ 'b1-t5', 'content' ], $this->client->received );
	}

	public function test_empty_selection_does_not_call_client(): void {
		$result = $this->purger->purgePostType( '   ' );

		$this->assertFalse( $result->success );
		$this->assertFalse( $this->client->called );
	}

	public function test_purge_urls_normalizes_and_dedupes(): void {
		Functions\when( 'esc_url_raw' )->returnArg( 1 );

		$result = $this->purger->purgeUrls( ' https://example.com/a/ , https://example.com/b/ , https://example.com/a/ ' );

		$this->assertTrue( $result->success );
		$this->assertTrue( $this->client->urls_called );
		$this->assertSame( [ 'https://example.com/a/', 'https://example.com/b/' ], $this->client->received_urls );
	}

	public function test_purge_urls_empty_does_not_call_client(): void {
		Functions\when( 'esc_url_raw' )->returnArg( 1 );

		$result = $this->purger->purgeUrls( '   ,  ' );

		$this->assertFalse( $result->success );
		$this->assertFalse( $this->client->urls_called );
	}
}
