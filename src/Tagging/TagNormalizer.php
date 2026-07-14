<?php
/**
 * Normalizes cache tags for Cloudflare's Cache-Tag header constraints.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Tagging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure transformation from raw tag strings to a canonical, deduplicated tag set.
 */
final class TagNormalizer {

	/**
	 * Cloudflare's aggregate limit for the Cache-Tag header value, in bytes.
	 */
	public const HEADER_SIZE_LIMIT = 16384;

	/**
	 * Normalize a list of raw tags.
	 *
	 * Each tag is lowercased, stripped of accents, has whitespace converted to
	 * hyphens, and unsupported characters removed. Empty results are dropped and
	 * duplicates are collapsed.
	 *
	 * @param array<int, mixed> $tags Raw tags.
	 *
	 * @return array<int, string>
	 */
	public function normalize( array $tags ): array {
		$normalized = [];

		foreach ( $tags as $tag ) {
			if ( ! is_scalar( $tag ) ) {
				continue;
			}

			$tag = strtolower( (string) $tag );
			$tag = remove_accents( $tag );
			$tag = (string) preg_replace( '/\s+/', '-', $tag );
			$tag = (string) preg_replace( '/[^a-z0-9_.:-]/', '', $tag );
			$tag = trim( $tag, " \t\n\r\0\x0B,-" );

			if ( '' === $tag ) {
				continue;
			}

			$normalized[] = $tag;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Keep the aggregate header value below Cloudflare's Cache-Tag size limit.
	 *
	 * Tags are dropped whole once the comma-joined value would exceed the limit.
	 *
	 * @param array<int, string> $tags Normalized tags.
	 *
	 * @return array<int, string>
	 */
	public function capToHeaderSize( array $tags ): array {
		$limited = [];
		$length  = 0;

		foreach ( $tags as $tag ) {
			$next_length = $length + strlen( $tag ) + ( [] === $limited ? 0 : 1 );

			if ( $next_length > self::HEADER_SIZE_LIMIT ) {
				break;
			}

			$limited[] = $tag;
			$length    = $next_length;
		}

		return $limited;
	}
}
