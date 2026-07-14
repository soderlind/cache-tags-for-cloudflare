<?php
/**
 * Tests for Credentials.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tests\Purging;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Soderlind\CacheTagsForCloudflare\Purging\Credentials;
use Soderlind\CacheTagsForCloudflare\Support\Options;
use Soderlind\CacheTagsForCloudflare\Tests\TestCase;

final class CredentialsTest extends TestCase {

	public function test_reads_from_options_when_no_constants(): void {
		Functions\when( 'get_option' )->justReturn(
			[ 'api_token' => 'opt-token', 'zone_id' => 'opt-zone' ]
		);

		$credentials = new Credentials( new Options() );

		$this->assertSame( 'opt-token', $credentials->apiToken() );
		$this->assertSame( 'opt-zone', $credentials->zoneId() );
		$this->assertFalse( $credentials->isTokenFromConstant() );
		$this->assertTrue( $credentials->isConfigured() );
	}

	public function test_not_configured_when_empty(): void {
		Functions\when( 'get_option' )->justReturn( [ 'api_token' => '', 'zone_id' => '' ] );

		$this->assertFalse( ( new Credentials( new Options() ) )->isConfigured() );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_constant_takes_precedence_over_option(): void {
		define( 'CACHE_TAGS_CF_API_TOKEN', 'const-token' );
		define( 'CACHE_TAGS_CF_ZONE_ID', 'const-zone' );

		Functions\when( 'get_option' )->justReturn(
			[ 'api_token' => 'opt-token', 'zone_id' => 'opt-zone' ]
		);

		$credentials = new Credentials( new Options() );

		$this->assertSame( 'const-token', $credentials->apiToken() );
		$this->assertSame( 'const-zone', $credentials->zoneId() );
		$this->assertTrue( $credentials->isTokenFromConstant() );
		$this->assertTrue( $credentials->isZoneFromConstant() );
	}
}
