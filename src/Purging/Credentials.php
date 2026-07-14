<?php
/**
 * Resolves Cloudflare API credentials, preferring wp-config constants.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

use Soderlind\CacheTagsForCloudflare\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the scoped API token and zone ID from constants first, then settings.
 *
 * When a constant is defined it wins, and the corresponding settings field is
 * treated as read-only so secrets can live in wp-config.php rather than the DB.
 */
final class Credentials {

	public const TOKEN_CONSTANT = 'CACHE_TAGS_CF_API_TOKEN';
	public const ZONE_CONSTANT  = 'CACHE_TAGS_CF_ZONE_ID';

	public function __construct( private readonly Options $options ) {
	}

	/**
	 * The Cloudflare API token, constant-first.
	 */
	public function apiToken(): string {
		if ( $this->isTokenFromConstant() ) {
			return (string) constant( self::TOKEN_CONSTANT );
		}

		return $this->options->string( 'api_token' );
	}

	/**
	 * The Cloudflare zone ID, constant-first.
	 */
	public function zoneId(): string {
		if ( $this->isZoneFromConstant() ) {
			return (string) constant( self::ZONE_CONSTANT );
		}

		return $this->options->string( 'zone_id' );
	}

	/**
	 * Whether the API token is provided by a constant.
	 */
	public function isTokenFromConstant(): bool {
		return defined( self::TOKEN_CONSTANT ) && '' !== (string) constant( self::TOKEN_CONSTANT );
	}

	/**
	 * Whether the zone ID is provided by a constant.
	 */
	public function isZoneFromConstant(): bool {
		return defined( self::ZONE_CONSTANT ) && '' !== (string) constant( self::ZONE_CONSTANT );
	}

	/**
	 * Whether both a token and zone are available.
	 */
	public function isConfigured(): bool {
		return '' !== $this->apiToken() && '' !== $this->zoneId();
	}
}
