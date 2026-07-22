<?php
/**
 * Thin Cloudflare API client for purge-by-tag and token verification.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Purging;

use Soderlind\CacheTagsForCloudflare\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps the WordPress HTTP API to talk to Cloudflare. No third-party runtime deps.
 */
final class CloudflareClient implements PurgeClient {

	private const API_BASE = 'https://api.cloudflare.com/client/v4';

	/**
	 * Cloudflare's maximum number of tags accepted per purge request.
	 */
	public const MAX_TAGS_PER_REQUEST = 30;

	/**
	 * Cloudflare's maximum number of URLs accepted per purge request (non-Enterprise).
	 */
	public const MAX_URLS_PER_REQUEST = 30;

	public function __construct(
		private readonly Credentials $credentials,
		private readonly Logger $logger
	) {
	}

	/**
	 * Purge the given tags, splitting into Cloudflare-sized batches.
	 *
	 * @param array<int, string> $tags Normalized tags to invalidate.
	 */
	public function purge( array $tags ): PurgeResult {
		$tags = array_values( array_unique( array_filter( $tags, static fn ( $tag ): bool => '' !== $tag ) ) );

		if ( [] === $tags ) {
			return PurgeResult::success();
		}

		if ( ! $this->credentials->isConfigured() ) {
			return PurgeResult::failure( __( 'Cloudflare API token or zone ID is not configured.', 'cache-tags-for-cloudflare' ) );
		}

		foreach ( array_chunk( $tags, self::MAX_TAGS_PER_REQUEST ) as $batch ) {
			$result = $this->purgeBatch( [ 'tags' => array_values( $batch ) ] );

			if ( ! $result->success ) {
				return $result;
			}
		}

		return PurgeResult::success(
			sprintf(
				/* translators: %d: number of purged cache tags. */
				_n( 'Purged %d cache tag.', 'Purged %d cache tags.', count( $tags ), 'cache-tags-for-cloudflare' ),
				count( $tags )
			)
		);
	}

	/**
	 * Purge the given URLs, splitting into Cloudflare-sized batches.
	 *
	 * @param array<int, string> $urls Absolute URLs to invalidate.
	 */
	public function purgeUrls( array $urls ): PurgeResult {
		$urls = array_values( array_unique( array_filter( $urls, static fn ( $url ): bool => '' !== $url ) ) );

		if ( [] === $urls ) {
			return PurgeResult::success();
		}

		if ( ! $this->credentials->isConfigured() ) {
			return PurgeResult::failure( __( 'Cloudflare API token or zone ID is not configured.', 'cache-tags-for-cloudflare' ) );
		}

		foreach ( array_chunk( $urls, self::MAX_URLS_PER_REQUEST ) as $batch ) {
			$result = $this->purgeBatch( [ 'files' => array_values( $batch ) ] );

			if ( ! $result->success ) {
				return $result;
			}
		}

		return PurgeResult::success(
			sprintf(
				/* translators: %d: number of purged URLs. */
				_n( 'Purged %d URL.', 'Purged %d URLs.', count( $urls ), 'cache-tags-for-cloudflare' ),
				count( $urls )
			)
		);
	}

	/**
	 * Verify the configured API token against Cloudflare.
	 */
	public function verify(): PurgeResult {
		if ( '' === $this->credentials->apiToken() ) {
			return PurgeResult::failure( __( 'No Cloudflare API token configured.', 'cache-tags-for-cloudflare' ) );
		}

		$response = wp_remote_get(
			self::API_BASE . '/user/tokens/verify',
			[
				'timeout' => 10,
				'headers' => [
					'Authorization' => 'Bearer ' . $this->credentials->apiToken(),
					'Content-Type'  => 'application/json',
				],
			]
		);

		return $this->interpret( $response, __( 'API token is valid.', 'cache-tags-for-cloudflare' ) );
	}

	/**
	 * Purge a single batch, given the Cloudflare request payload.
	 *
	 * @param array<string, array<int, string>> $payload Purge body, e.g. `[ 'tags' => [...] ]` or `[ 'files' => [...] ]`.
	 */
	private function purgeBatch( array $payload ): PurgeResult {
		$response = wp_remote_post(
			self::API_BASE . '/zones/' . rawurlencode( $this->credentials->zoneId() ) . '/purge_cache',
			[
				'timeout' => 10,
				'headers' => [
					'Authorization' => 'Bearer ' . $this->credentials->apiToken(),
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode( $payload ),
			]
		);

		return $this->interpret( $response, __( 'Cache purged.', 'cache-tags-for-cloudflare' ) );
	}

	/**
	 * Interpret a WordPress HTTP response as a PurgeResult.
	 *
	 * @param array<string, mixed>|\WP_Error $response WordPress HTTP response.
	 * @param string                         $success_message Message on success.
	 */
	private function interpret( $response, string $success_message ): PurgeResult {
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			$this->logger->log( 'HTTP error: ' . $message );

			return PurgeResult::failure( $message );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) && ! empty( $body['success'] ) ) {
			return PurgeResult::success( $success_message );
		}

		$message = $this->extractError( $body, $code );
		$this->logger->log( 'API error: ' . $message );

		return PurgeResult::failure( $message );
	}

	/**
	 * Pull a readable error message out of a Cloudflare error response.
	 *
	 * @param mixed $body Decoded response body.
	 * @param int   $code HTTP status code.
	 */
	private function extractError( $body, int $code ): string {
		if ( is_array( $body ) && ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
			$messages = [];

			foreach ( $body['errors'] as $error ) {
				if ( is_array( $error ) && isset( $error['message'] ) ) {
					$messages[] = (string) $error['message'];
				}
			}

			if ( [] !== $messages ) {
				return implode( '; ', $messages );
			}
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'Cloudflare returned an unexpected response (HTTP %d).', 'cache-tags-for-cloudflare' ),
			$code
		);
	}
}
