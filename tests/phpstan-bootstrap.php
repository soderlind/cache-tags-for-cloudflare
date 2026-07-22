<?php
/**
 * PHPStan bootstrap. Defines plugin constants so analysis of the main file passes.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

define( 'CACHE_TAGS_FOR_CLOUDFLARE_FILE', dirname( __DIR__ ) . '/cache-tags-for-cloudflare.php' );
define( 'CACHE_TAGS_FOR_CLOUDFLARE_DIR', dirname( __DIR__ ) . '/' );
define( 'CACHE_TAGS_FOR_CLOUDFLARE_URL', '' );
