<?php
/**
 * REST API controller backing the React settings screen.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Rest;

use Soderlind\CacheTagsForCloudflare\Purging\CloudflareClient;
use Soderlind\CacheTagsForCloudflare\Purging\Credentials;
use Soderlind\CacheTagsForCloudflare\Purging\GroupPurgeResolver;
use Soderlind\CacheTagsForCloudflare\Purging\PurgeResult;
use Soderlind\CacheTagsForCloudflare\Support\Options;
use Soderlind\CacheTagsForCloudflare\Tagging\TagNormalizer;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `cache-tags-for-cloudflare/v1` routes used by the admin app.
 *
 * All routes require the `manage_options` capability; the REST cookie nonce
 * (`X-WP-Nonce`) is validated by WordPress automatically.
 */
final class SettingsController {

	public const NAMESPACE = 'cache-tags-for-cloudflare/v1';

	private const CAPABILITY = 'manage_options';

	public function __construct(
		private readonly Options $options,
		private readonly Credentials $credentials,
		private readonly CloudflareClient $client,
		private readonly GroupPurgeResolver $resolver,
		private readonly TagNormalizer $normalizer
	) {
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'registerRoutes' ] );
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$permission = [ $this, 'checkPermission' ];

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getSettings' ],
					'permission_callback' => $permission,
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'updateSettings' ],
					'permission_callback' => $permission,
					'args'                => $this->settingsArgs(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/groups',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getGroups' ],
				'permission_callback' => $permission,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/verify',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'verify' ],
				'permission_callback' => $permission,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/purge',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'purge' ],
				'permission_callback' => $permission,
				'args'                => $this->purgeArgs(),
			]
		);
	}

	/**
	 * Capability check for every route.
	 */
	public function checkPermission(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * GET /settings
	 */
	public function getSettings(): WP_REST_Response {
		return new WP_REST_Response( $this->settingsPayload(), 200 );
	}

	/**
	 * POST /settings
	 */
	public function updateSettings( WP_REST_Request $request ): WP_REST_Response {
		$current = $this->options->all();

		$new = [
			'header_enabled' => (bool) $request->get_param( 'header_enabled' ),
			'purge_enabled'  => (bool) $request->get_param( 'purge_enabled' ),
			'debug'          => (bool) $request->get_param( 'debug' ),
			'zone_id'        => $this->credentials->isZoneFromConstant()
				? (string) $current['zone_id']
				: (string) $request->get_param( 'zone_id' ),
			'api_token'      => (string) $current['api_token'],
		];

		// Only overwrite the token when a new, non-empty value is supplied and it
		// is not locked by a constant.
		$token = (string) $request->get_param( 'api_token' );

		if ( ! $this->credentials->isTokenFromConstant() && '' !== $token ) {
			$new['api_token'] = $token;
		}

		$this->options->save( $new );

		return new WP_REST_Response( $this->settingsPayload(), 200 );
	}

	/**
	 * GET /groups
	 */
	public function getGroups(): WP_REST_Response {
		$post_types = [];

		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
			$post_types[] = [
				'slug'  => $type->name,
				'label' => $type->labels->singular_name ?? $type->name,
			];
		}

		$taxonomies = [];

		foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $taxonomy ) {
			$rest_base = '';

			if ( $taxonomy->show_in_rest ) {
				$rest_base = '' !== (string) $taxonomy->rest_base ? (string) $taxonomy->rest_base : $taxonomy->name;
			}

			$taxonomies[] = [
				'slug'      => $taxonomy->name,
				'label'     => $taxonomy->labels->singular_name ?? $taxonomy->name,
				'rest_base' => $rest_base,
			];
		}

		return new WP_REST_Response(
			[
				'post_types' => $post_types,
				'taxonomies' => $taxonomies,
			],
			200
		);
	}

	/**
	 * POST /verify
	 */
	public function verify(): WP_REST_Response {
		return $this->resultResponse( $this->client->verify() );
	}

	/**
	 * POST /purge
	 */
	public function purge( WP_REST_Request $request ): WP_REST_Response {
		$mode = (string) $request->get_param( 'mode' );

		$tags = match ( $mode ) {
			'post_type' => $this->resolver->forPostType( (string) $request->get_param( 'post_type' ) ),
			'taxonomy'  => $this->resolver->forTaxonomyTerms(
				(string) $request->get_param( 'taxonomy' ),
				(array) $request->get_param( 'terms' )
			),
			'tags'      => $this->resolver->forRawTags( (string) $request->get_param( 'tags' ) ),
			default     => $this->resolver->everything(),
		};

		$tags = $this->normalizer->normalize( $tags );

		if ( [] === $tags ) {
			return $this->resultResponse(
				PurgeResult::failure( __( 'No valid tags to purge for that selection.', 'cache-tags-for-cloudflare' ) )
			);
		}

		return $this->resultResponse( $this->client->purge( $tags ), $tags );
	}

	/**
	 * Build the settings payload returned to the app (never includes the token).
	 *
	 * @return array<string, mixed>
	 */
	private function settingsPayload(): array {
		$settings = $this->options->all();

		return [
			'header_enabled'      => (bool) $settings['header_enabled'],
			'purge_enabled'       => (bool) $settings['purge_enabled'],
			'debug'               => (bool) $settings['debug'],
			'zone_id'             => $this->credentials->zoneId(),
			'has_token'           => '' !== $this->credentials->apiToken(),
			'token_from_constant' => $this->credentials->isTokenFromConstant(),
			'zone_from_constant'  => $this->credentials->isZoneFromConstant(),
		];
	}

	/**
	 * Convert a PurgeResult into a REST response.
	 *
	 * @param array<int, string> $tags Tags involved, when relevant.
	 */
	private function resultResponse( PurgeResult $result, array $tags = [] ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => $result->success,
				'message' => $result->message,
				'tags'    => $tags,
			],
			200
		);
	}

	/**
	 * Argument schema for the settings write route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function settingsArgs(): array {
		return [
			'header_enabled' => [ 'type' => 'boolean' ],
			'purge_enabled'  => [ 'type' => 'boolean' ],
			'debug'          => [ 'type' => 'boolean' ],
			'zone_id'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'api_token'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Argument schema for the purge route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function purgeArgs(): array {
		return [
			'mode'      => [
				'type'    => 'string',
				'enum'    => [ 'everything', 'post_type', 'taxonomy', 'tags' ],
				'default' => 'everything',
			],
			'post_type' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
			'taxonomy'  => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
			'terms'     => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'tags'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}
}
