<?php
/**
 * Tests for CloudflareClient.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tests\Purging;

use Brain\Monkey\Functions;
use Soderlind\CacheTagsForCloudflare\Purging\CloudflareClient;
use Soderlind\CacheTagsForCloudflare\Purging\Credentials;
use Soderlind\CacheTagsForCloudflare\Support\Logger;
use Soderlind\CacheTagsForCloudflare\Support\Options;
use Soderlind\CacheTagsForCloudflare\Tests\TestCase;

final class CloudflareClientTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn ( array $r ): int => (int) ( $r['code'] ?? 0 )
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn ( array $r ): string => (string) ( $r['body'] ?? '' )
		);
	}

	private function client( string $token = 'token', string $zone = 'zone' ): CloudflareClient {
		Functions\when( 'get_option' )->justReturn( [ 'api_token' => $token, 'zone_id' => $zone ] );
		$options = new Options();

		return new CloudflareClient( new Credentials( $options ), new Logger( $options ) );
	}

	private static function ok(): array {
		return [ 'code' => 200, 'body' => json_encode( [ 'success' => true ] ) ];
	}

	public function test_purge_success(): void {
		Functions\expect( 'wp_remote_post' )->once()->andReturn( self::ok() );

		$result = $this->client()->purge( [ 'post-id-42' ] );

		$this->assertTrue( $result->success );
	}

	public function test_purge_splits_into_batches_of_thirty(): void {
		Functions\expect( 'wp_remote_post' )->times( 3 )->andReturn( self::ok() );

		$tags   = array_map( static fn ( int $i ): string => 'tag-' . $i, range( 1, 65 ) );
		$result = $this->client()->purge( $tags );

		$this->assertTrue( $result->success );
	}

	public function test_purge_reports_cloudflare_errors(): void {
		Functions\expect( 'wp_remote_post' )->once()->andReturn(
			[ 'code' => 403, 'body' => json_encode( [ 'success' => false, 'errors' => [ [ 'message' => 'Invalid token' ] ] ] ) ]
		);

		$result = $this->client()->purge( [ 'content' ] );

		$this->assertFalse( $result->success );
		$this->assertSame( 'Invalid token', $result->message );
	}

	public function test_purge_skips_api_when_not_configured(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$result = $this->client( '', '' )->purge( [ 'content' ] );

		$this->assertFalse( $result->success );
	}

	public function test_purge_noop_for_empty_tags(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$this->assertTrue( $this->client()->purge( [] )->success );
	}

	public function test_purge_urls_success(): void {
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				\Mockery::type( 'string' ),
				\Mockery::on(
					static function ( array $args ): bool {
						$body = json_decode( (string) $args['body'], true );

						return isset( $body['files'] ) && [ 'https://example.com/a/' ] === $body['files'];
					}
				)
			)
			->andReturn( self::ok() );

		$result = $this->client()->purgeUrls( [ 'https://example.com/a/' ] );

		$this->assertTrue( $result->success );
	}

	public function test_purge_urls_splits_into_batches_of_thirty(): void {
		Functions\expect( 'wp_remote_post' )->times( 3 )->andReturn( self::ok() );

		$urls   = array_map( static fn ( int $i ): string => 'https://example.com/' . $i . '/', range( 1, 65 ) );
		$result = $this->client()->purgeUrls( $urls );

		$this->assertTrue( $result->success );
	}

	public function test_purge_urls_skips_api_when_not_configured(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$result = $this->client( '', '' )->purgeUrls( [ 'https://example.com/a/' ] );

		$this->assertFalse( $result->success );
	}

	public function test_purge_urls_noop_for_empty_list(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$this->assertTrue( $this->client()->purgeUrls( [] )->success );
	}

	public function test_verify_success(): void {
		Functions\expect( 'wp_remote_get' )->once()->andReturn( self::ok() );

		$this->assertTrue( $this->client()->verify()->success );
	}

	public function test_verify_requires_token(): void {
		Functions\expect( 'wp_remote_get' )->never();

		$this->assertFalse( $this->client( '', '' )->verify()->success );
	}
}
