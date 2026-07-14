<?php
/**
 * Minimal PSR-4 autoloader used when Composer's autoloader is unavailable.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback autoloader for the plugin namespace.
 */
final class Autoloader {

	private const PREFIX = 'Soderlind\\CacheTagsForCloudflare\\';

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	/**
	 * Load a class within the plugin namespace.
	 *
	 * @param string $class_name Fully-qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( ! str_starts_with( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$path     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
}
