<?php
/**
 * Tests for PurgeCollector.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tests\Purging;

use Brain\Monkey\Functions;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeClient;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeCollector;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeResult;
use Soderlind\CacheTagsForCloudflare\Support\Logger;
use Soderlind\CacheTagsForCloudflare\Support\Options;
use Soderlind\CacheTagsForCloudflare\Tests\TestCase;

final class PurgeCollectorTest extends TestCase {

	/**
	 * @param array<string, mixed> $settings Option overrides.
	 */
	private function collector( PurgeClient $client, array $settings = [] ): PurgeCollector {
		Functions\when( 'get_option' )->justReturn( array_merge( [ 'purge_enabled' => true, 'debug' => false ], $settings ) );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'delete_transient' )->justReturn( true );

		$options = new Options();

		return new PurgeCollector( $client, $options, new Logger( $options ) );
	}

	private function recordingClient(): PurgeClient {
		return new class() implements PurgeClient {
			/** @var array<int, string> */
			public array $received = [];
			/** @var array<int, string> */
			public array $received_urls = [];
			public bool $shouldSucceed = true;

			public function purge( array $tags ): PurgeResult {
				$this->received = $tags;

				return $this->shouldSucceed ? PurgeResult::success() : PurgeResult::failure( 'boom' );
			}

			public function purgeUrls( array $urls ): PurgeResult {
				$this->received_urls = $urls;

				return $this->shouldSucceed ? PurgeResult::success() : PurgeResult::failure( 'boom' );
			}
		};
	}

	public function test_dedupes_tags_and_flushes_once(): void {
		$client    = $this->recordingClient();
		$collector = $this->collector( $client );

		$collector->add( [ 'post-id-1', 'post-id-1', 'category-news' ] );
		$collector->add( [ 'category-news', 'post-id-2' ] );
		$collector->flush();

		sort( $client->received );
		$this->assertSame( [ 'category-news', 'post-id-1', 'post-id-2' ], $client->received );
	}

	public function test_does_nothing_when_purge_disabled(): void {
		$client    = $this->recordingClient();
		$collector = $this->collector( $client, [ 'purge_enabled' => false ] );

		$collector->add( [ 'post-id-1' ] );
		$collector->flush();

		$this->assertSame( [], $client->received );
	}

	public function test_flush_is_noop_without_pending_tags(): void {
		$client    = $this->recordingClient();
		$collector = $this->collector( $client );

		$collector->flush();

		$this->assertSame( [], $client->received );
	}

	public function test_urls_are_deduped_and_flushed_separately(): void {
		$client    = $this->recordingClient();
		$collector = $this->collector( $client );

		$collector->addUrls( [ 'https://example.com/a/', 'https://example.com/a/' ] );
		$collector->addUrls( [ 'https://example.com/b/' ] );
		$collector->add( [ 'post-id-1' ] );
		$collector->flush();

		$this->assertSame( [ 'post-id-1' ], $client->received );
		sort( $client->received_urls );
		$this->assertSame( [ 'https://example.com/a/', 'https://example.com/b/' ], $client->received_urls );
	}

	public function test_urls_not_collected_when_purge_disabled(): void {
		$client    = $this->recordingClient();
		$collector = $this->collector( $client, [ 'purge_enabled' => false ] );

		$collector->addUrls( [ 'https://example.com/a/' ] );
		$collector->flush();

		$this->assertSame( [], $client->received_urls );
	}

	public function test_records_failure_transient_on_error(): void {
		$client                = $this->recordingClient();
		$client->shouldSucceed = false;
		$collector             = $this->collector( $client );

		Functions\expect( 'set_transient' )
			->once()
			->with( PurgeCollector::FAILURE_TRANSIENT, 'boom', \Mockery::type( 'int' ) )
			->andReturn( true );

		$collector->add( [ 'post-id-9' ] );
		$collector->flush();

		$this->assertSame( [ 'post-id-9' ], $client->received );
	}
}
