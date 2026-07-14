<?php
/**
 * Settings screen, connection test, and manual purge action.
 *
 * @package Soderlind\CacheTagsForCloudflare
 */

declare(strict_types=1);

namespace Soderlind\CacheTagsForCloudflare\Admin;

use Soderlind\CacheTagsForCloudflare\Purging\CloudflareClient;
use Soderlind\CacheTagsForCloudflare\Purging\Credentials;
use Soderlind\CacheTagsForCloudflare\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders Settings → Cache Tags and handles the test/purge admin-post actions.
 */
final class SettingsPage {

	private const MENU_SLUG   = 'cache-tags-for-cloudflare';
	private const NOTICE_KEY  = 'cache_tags_for_cloudflare_settings_notice';
	public const TEST_ACTION  = 'cache_tags_for_cloudflare_test';
	public const PURGE_ACTION = 'cache_tags_for_cloudflare_purge_all';
	private const CAPABILITY  = 'manage_options';

	public function __construct(
		private readonly Options $options,
		private readonly Credentials $credentials,
		private readonly CloudflareClient $client
	) {
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'addMenu' ] );
		add_action( 'admin_init', [ $this, 'registerSettings' ] );
		add_action( 'admin_post_' . self::TEST_ACTION, [ $this, 'handleTest' ] );
		add_action( 'admin_post_' . self::PURGE_ACTION, [ $this, 'handlePurgeAll' ] );
		add_action( 'admin_notices', [ $this, 'renderActionNotice' ] );
	}

	/**
	 * Add the options page under Settings.
	 */
	public function addMenu(): void {
		add_options_page(
			__( 'Cache Tags for Cloudflare', 'cache-tags-for-cloudflare' ),
			__( 'Cache Tags', 'cache-tags-for-cloudflare' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'renderPage' ]
		);
	}

	/**
	 * Register the setting and its sanitizer.
	 */
	public function registerSettings(): void {
		register_setting(
			self::MENU_SLUG,
			Options::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this->options, 'sanitize' ],
				'default'           => [],
			]
		);
	}

	/**
	 * Render the settings screen.
	 */
	public function renderPage(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings     = $this->options->all();
		$token_locked = $this->credentials->isTokenFromConstant();
		$zone_locked  = $this->credentials->isZoneFromConstant();
		$action_url   = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Cache Tags for Cloudflare', 'cache-tags-for-cloudflare' ); ?></h1>
			<p><?php echo esc_html__( 'Cloudflare Cache-Tag headers and purge-by-tag require a Cloudflare Enterprise plan.', 'cache-tags-for-cloudflare' ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( self::MENU_SLUG ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Emit Cache-Tag headers', 'cache-tags-for-cloudflare' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[header_enabled]" value="1" <?php checked( (bool) $settings['header_enabled'] ); ?> />
								<?php echo esc_html__( 'Add Cache-Tag headers to singular content.', 'cache-tags-for-cloudflare' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Auto-purge on changes', 'cache-tags-for-cloudflare' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[purge_enabled]" value="1" <?php checked( (bool) $settings['purge_enabled'] ); ?> />
								<?php echo esc_html__( 'Purge Cloudflare when content changes.', 'cache-tags-for-cloudflare' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ctcf-token"><?php echo esc_html__( 'API token', 'cache-tags-for-cloudflare' ); ?></label></th>
						<td>
							<?php if ( $token_locked ) : ?>
								<input type="text" id="ctcf-token" class="regular-text" value="<?php echo esc_attr__( 'Defined in wp-config.php', 'cache-tags-for-cloudflare' ); ?>" readonly disabled />
							<?php else : ?>
								<input type="password" id="ctcf-token" class="regular-text" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[api_token]" value="<?php echo esc_attr( (string) $settings['api_token'] ); ?>" autocomplete="off" />
							<?php endif; ?>
							<p class="description"><?php echo esc_html__( 'A scoped token with the Zone → Cache Purge permission.', 'cache-tags-for-cloudflare' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ctcf-zone"><?php echo esc_html__( 'Zone ID', 'cache-tags-for-cloudflare' ); ?></label></th>
						<td>
							<?php if ( $zone_locked ) : ?>
								<input type="text" id="ctcf-zone" class="regular-text" value="<?php echo esc_attr__( 'Defined in wp-config.php', 'cache-tags-for-cloudflare' ); ?>" readonly disabled />
							<?php else : ?>
								<input type="text" id="ctcf-zone" class="regular-text" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[zone_id]" value="<?php echo esc_attr( (string) $settings['zone_id'] ); ?>" />
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Debug logging', 'cache-tags-for-cloudflare' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[debug]" value="1" <?php checked( (bool) $settings['debug'] ); ?> />
								<?php echo esc_html__( 'Log purge activity and errors to the PHP error log.', 'cache-tags-for-cloudflare' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php echo esc_html__( 'Tools', 'cache-tags-for-cloudflare' ); ?></h2>
			<p>
				<form action="<?php echo $action_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" method="post" style="display:inline">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>" />
					<?php wp_nonce_field( self::TEST_ACTION ); ?>
					<?php submit_button( __( 'Test connection', 'cache-tags-for-cloudflare' ), 'secondary', 'submit', false ); ?>
				</form>
				<form action="<?php echo $action_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" method="post" style="display:inline">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::PURGE_ACTION ); ?>" />
					<?php wp_nonce_field( self::PURGE_ACTION ); ?>
					<?php submit_button( __( 'Purge everything', 'cache-tags-for-cloudflare' ), 'delete', 'submit', false ); ?>
				</form>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the "Test connection" action.
	 */
	public function handleTest(): void {
		$this->authorize( self::TEST_ACTION );

		$result = $this->client->verify();
		$this->storeNotice( $result->success ? 'success' : 'error', $result->message );
		$this->redirectBack();
	}

	/**
	 * Handle the "Purge everything" action (purges the `content` tag).
	 */
	public function handlePurgeAll(): void {
		$this->authorize( self::PURGE_ACTION );

		$result = $this->client->purge( [ 'content' ] );
		$this->storeNotice( $result->success ? 'success' : 'error', $result->message );
		$this->redirectBack();
	}

	/**
	 * Render the transient notice produced by a test/purge action.
	 */
	public function renderActionNotice(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$notice = get_transient( self::NOTICE_KEY );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( self::NOTICE_KEY );
		$class = 'error' === ( $notice['type'] ?? 'error' ) ? 'notice-error' : 'notice-success';
		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( (string) $notice['message'] )
		);
	}

	/**
	 * Verify capability and nonce for an admin-post action.
	 */
	private function authorize( string $action ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cache-tags-for-cloudflare' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Store a one-shot admin notice.
	 */
	private function storeNotice( string $type, string $message ): void {
		set_transient(
			self::NOTICE_KEY,
			[
				'type'    => $type,
				'message' => $message,
			],
			60
		);
	}

	/**
	 * Redirect back to the settings screen.
	 */
	private function redirectBack(): void {
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) );
		exit;
	}
}
