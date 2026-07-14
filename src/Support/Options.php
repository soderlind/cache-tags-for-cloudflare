<?php
/**
 * Typed access to the plugin's stored settings.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the single plugin option, applying defaults and sanitization.
 *
 * On multisite each site stores its own settings (per-site zone), so this reads
 * from the current site's options table.
 */
final class Options {

	public const OPTION_KEY = 'cache_tags_for_cloudflare_settings';

	/**
	 * Default settings.
	 *
	 * @var array<string, scalar>
	 */
	private const DEFAULTS = [
		'header_enabled' => true,
		'purge_enabled'  => true,
		'api_token'      => '',
		'zone_id'        => '',
		'debug'          => false,
	];

	/**
	 * Return all settings merged with defaults.
	 *
	 * @return array<string, scalar>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return array_merge( self::DEFAULTS, $stored );
	}

	/**
	 * Return a single boolean setting.
	 */
	public function boolean( string $key ): bool {
		return (bool) ( $this->all()[ $key ] ?? false );
	}

	/**
	 * Return a single string setting.
	 */
	public function string( string $key ): string {
		return (string) ( $this->all()[ $key ] ?? '' );
	}

	/**
	 * Persist a sanitized settings array.
	 *
	 * @param array<string, mixed> $input Raw input, typically from $_POST.
	 *
	 * @return array<string, scalar>
	 */
	public function save( array $input ): array {
		$clean = $this->sanitize( $input );
		update_option( self::OPTION_KEY, $clean, false );

		return $clean;
	}

	/**
	 * Sanitize a raw settings array.
	 *
	 * @param array<string, mixed> $input Raw input.
	 *
	 * @return array<string, scalar>
	 */
	public function sanitize( array $input ): array {
		return [
			'header_enabled' => ! empty( $input['header_enabled'] ),
			'purge_enabled'  => ! empty( $input['purge_enabled'] ),
			'api_token'      => isset( $input['api_token'] ) ? sanitize_text_field( (string) $input['api_token'] ) : '',
			'zone_id'        => isset( $input['zone_id'] ) ? sanitize_text_field( (string) $input['zone_id'] ) : '',
			'debug'          => ! empty( $input['debug'] ),
		];
	}
}
