<?php
/**
 * PHPUnit bootstrap.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post stub for unit tests.
	 */
	class WP_Post {
		public int $ID = 0;
		public string $post_type = 'post';
		public string $post_status = 'publish';

		/**
		 * @param array<string, mixed> $props Post properties.
		 */
		public function __construct( array $props = [] ) {
			foreach ( $props as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	/**
	 * Minimal WP_Term stub for unit tests.
	 */
	class WP_Term {
		public int $term_id = 0;
		public string $slug = '';

		/**
		 * @param array<string, mixed> $props Term properties.
		 */
		public function __construct( array $props = [] ) {
			foreach ( $props as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}
