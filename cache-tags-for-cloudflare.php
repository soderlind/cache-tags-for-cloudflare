<?php
/**
 * Plugin Name:       Cache Tags for Cloudflare
 * Plugin URI:        https://github.com/soderlind/cache-tags-for-cloudflare
 * Description:       Adds Cache-Tag HTTP response headers for singular WordPress content and purges Cloudflare by tag when content changes.
 * Version:           1.1.1
 * Requires at least: 6.8
 * Tested up to:      7.0
 * Requires PHP:      8.3
 * Author:            Per Søderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cache-tags-for-cloudflare
 * Domain Path:       /languages
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = '1.1.1';

define( 'CACHE_TAGS_FOR_CLOUDFLARE_FILE', __FILE__ );
define( 'CACHE_TAGS_FOR_CLOUDFLARE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CACHE_TAGS_FOR_CLOUDFLARE_URL', plugin_dir_url( __FILE__ ) );

// Prefer the Composer autoloader; fall back to a minimal PSR-4 loader so the
// plugin runs from a plain checkout without a build step.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
} else {
	require __DIR__ . '/src/Autoloader.php';
	Autoloader::register();
}

add_action(
	'plugins_loaded',
	static function (): void {
		( new Plugin() )->boot();
	}
);
