<?php
/**
 * Settings screen: API keys with scopes, abilities switch and the "Try it" console.
 *
 * @package KOZ_Site_Bridge
 */

namespace ramirkz\kozbridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZBRIDGE_Admin {
	public const SETTINGS_OPTION = 'kozbridge_settings';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_kozbridge_try', array( __CLASS__, 'ajax_try' ) );
	}

	private static function t( string $text ): string {
		return KOZBRIDGE_Bridge::tr( $text );
	}

	public static function settings(): array {
		$stored = get_option( self::SETTINGS_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'abilities' => ! isset( $stored['abilities'] ) || ! empty( $stored['abilities'] ),
		);
	}

	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! current_user_can( 'manage_options' ) || ! str_contains( $hook_suffix, 'koz-site-bridge' ) ) {
			return;
		}
		wp_enqueue_style( 'kozbridge-admin', KOZBRIDGE_URL . 'assets/kozbridge-admin.css', array(), KOZBRIDGE_VERSION );
		wp_enqueue_script( 'kozbridge-admin', KOZBRIDGE_URL . 'assets/kozbridge-admin.js', array(), KOZBRIDGE_VERSION, true );
		wp_localize_script(
			'kozbridge-admin',
			'KOZBRIDGEAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'kozbridge_try' ),
				'revokeConfirm' => self::t( 'Revoke the key?' ),
				'copied'        => self::t( 'API key copied.' ),
				'running'       => self::t( 'Running…' ),
				'failed'        => self::t( 'Request failed.' ),
			)
		);
	}

	public static function admin_menu(): void {
		add_submenu_page(
			KOZBRIDGE_Suite_Registry::suite_page(),
			self::t( 'KOZ Site Bridge' ),
			self::t( 'Site Bridge' ),
			'manage_options',
			'koz-site-bridge',
			array( __CLASS__, 'admin_page' )
		);
	}

	/** Endpoint => example parameters for the console. */
	private static function console_hints(): array {
		return array(
			'ping'              => '',
			'overview'          => '',
			'plugins'           => '',
			'suite'             => '',
			'content'           => 'type=page&limit=10&search=',
			'navigation'        => '',
			'links'             => 'type=page&pages=1&max_links=10&check=0',
			'error_404_log'     => 'limit=20',
			'diagnostics'       => '',
			'security_headers'  => 'path=/',
			'consent_check'     => 'path=/',
			'performance'       => 'path=/',
			'admin_error_log'   => 'limit=20',
			'sitemap_inventory' => 'page=1&limit=50',
			'page_audit'        => 'path=/&profile=default',
			'rendered_audit'    => 'page=1&limit=3&profile=default',
			'http_probe'        => 'path=/&method=HEAD&profile=googlebot',
		);
	}

	public static function ajax_try(): void {
		check_ajax_referer( 'kozbridge_try', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'Forbidden.' ), 403 );
		}
		$method = sanitize_key( wp_unslash( $_POST['endpoint'] ?? '' ) );
		$params = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each parameter is sanitized and validated by the endpoint's own REST argument callbacks in KOZBRIDGE_Bridge::run(); unknown names are ignored.
		wp_parse_str( (string) wp_unslash( $_POST['params'] ?? '' ), $params );
		$started = microtime( true );
		$result  = KOZBRIDGE_Bridge::run( $method, is_array( $params ) ? $params : array() );
		wp_send_json(
			array(
				'status'      => $result['status'],
				'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
				'data'        => $result['data'],
			)
		);
	}

	private static function handle_post(): string {
		$plain = '';
		if ( isset( $_POST['kozbridge_create'] ) ) {
			check_admin_referer( 'kozbridge_create_action' );
			$scopes = array_map( 'sanitize_key', (array) wp_unslash( $_POST['kozbridge_scopes'] ?? array() ) );
			$days   = absint( wp_unslash( $_POST['kozbridge_expires'] ?? 0 ) );
			$days   = in_array( $days, array( 0, 30, 90, 365 ), true ) ? $days : 0;
			$plain  = KOZBRIDGE_Auth::create( sanitize_text_field( wp_unslash( $_POST['kozbridge_label'] ?? '' ) ), $scopes, $days );
			if ( '' === $plain ) {
				echo '<div class="notice notice-error"><p>' . esc_html( self::t( 'The key was not created: the limit of 10 keys is reached.' ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html( self::t( 'New API key created. Copy it now: it will not be displayed again.' ) ) . '</p></div>';
			}
		}
		if ( isset( $_POST['kozbridge_revoke'] ) ) {
			check_admin_referer( 'kozbridge_revoke_action' );
			KOZBRIDGE_Auth::revoke( sanitize_key( wp_unslash( $_POST['kozbridge_revoke'] ) ) );
			echo '<div class="notice notice-success"><p>' . esc_html( self::t( 'API key revoked.' ) ) . '</p></div>';
		}
		if ( isset( $_POST['kozbridge_save_settings'] ) ) {
			check_admin_referer( 'kozbridge_settings_action' );
			update_option( self::SETTINGS_OPTION, array( 'abilities' => ! empty( $_POST['kozbridge_abilities'] ) ), false );
			echo '<div class="notice notice-success"><p>' . esc_html( self::t( 'Settings saved.' ) ) . '</p></div>';
		}
		return $plain;
	}

	public static function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plain      = self::handle_post();
		$keys       = KOZBRIDGE_Auth::keys();
		$scopes     = KOZBRIDGE_Auth::scopes();
		$settings   = self::settings();
		$schema_url = rest_url( 'kozbridge/v1/openapi' );
		$https_ok   = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		?>
		<div class="wrap kozbridge-wrap">
			<h1>KOZ Site Bridge <small><?php echo esc_html( KOZBRIDGE_VERSION ); ?></small></h1>

			<div class="notice notice-info inline">
				<p><strong><?php echo esc_html( self::t( 'Read-only.' ) ); ?></strong>
				<?php echo esc_html( self::t( 'The plugin does not modify pages, plugins, themes, users or site settings. It stores only key digests, labels, scopes, dates and automatically expiring rate-limit counters.' ) ); ?></p>
			</div>

			<?php if ( ! $https_ok ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( self::t( 'API disabled: Home URL does not use HTTPS.' ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( '' !== $plain ) : ?>
				<section class="kozbridge-key-card">
					<h2><?php echo esc_html( self::t( 'API key is displayed once' ) ); ?></h2>
					<input type="text" readonly value="<?php echo esc_attr( $plain ); ?>" class="kozbridge-key-input" data-kozbridge-select-key>
					<div class="kozbridge-key-actions"><button type="button" class="button button-primary" data-kozbridge-copy-key><?php echo esc_html( self::t( 'Copy API key' ) ); ?></button><strong><?php echo esc_html( self::t( 'Do not send the key in chat.' ) ); ?></strong><span class="screen-reader-text" aria-live="polite" data-kozbridge-key-status></span></div>
				</section>
			<?php endif; ?>

			<h2><?php echo esc_html( self::t( 'API keys' ) ); ?></h2>
			<table class="widefat striped kozbridge-table kozbridge-keys">
				<thead><tr>
					<th><?php echo esc_html( self::t( 'Label' ) ); ?></th>
					<th><?php echo esc_html( self::t( 'Access' ) ); ?></th>
					<th><?php echo esc_html( self::t( 'Key created' ) ); ?></th>
					<th><?php echo esc_html( self::t( 'Last used' ) ); ?></th>
					<th><?php echo esc_html( self::t( 'Expires' ) ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $keys ) ) : ?>
					<tr><td colspan="6"><?php echo esc_html( self::t( 'No API keys. External access is closed.' ) ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $keys as $key ) : ?>
					<?php $expired = '' !== $key['expires'] && $key['expires'] < gmdate( 'Y-m-d' ); ?>
					<tr>
						<td><strong><?php echo esc_html( $key['label'] ); ?></strong><?php echo $key['legacy'] ? '<br><span class="description">' . esc_html( self::t( 'Created before 0.6.0; has full access.' ) ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( implode( ', ', $key['scopes'] ) ); ?></td>
						<td><?php echo esc_html( '' !== $key['created'] ? $key['created'] : '—' ); ?></td>
						<td><?php echo esc_html( '' !== $key['last_used'] ? $key['last_used'] : '—' ); ?></td>
						<td><?php echo $expired ? '<strong class="kozbridge-status kozbridge-status--missing">' . esc_html( self::t( 'expired' ) ) . '</strong> ' : ''; ?><?php echo esc_html( '' !== $key['expires'] ? $key['expires'] : self::t( 'never' ) ); ?></td>
						<td>
							<form method="post" data-kozbridge-revoke-form>
								<?php wp_nonce_field( 'kozbridge_revoke_action' ); ?>
								<button class="button button-small" name="kozbridge_revoke" value="<?php echo esc_attr( $key['id'] ); ?>"><?php echo esc_html( self::t( 'Revoke' ) ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php echo esc_html( sprintf( self::t( 'Limit: %d successfully authenticated requests per hour per key. IP addresses, User-Agent strings and request history are not stored.' ), KOZBRIDGE_Auth::REQUESTS_PER_HOUR ) ); ?>
			</p>

			<details class="kozbridge-create"<?php echo empty( $keys ) ? ' open' : ''; ?>>
				<summary class="button"><?php echo esc_html( self::t( 'Create API key' ) ); ?></summary>
				<form method="post" class="kozbridge-create-form">
					<?php wp_nonce_field( 'kozbridge_create_action' ); ?>
					<p><label><?php echo esc_html( self::t( 'Label' ) ); ?><br><input type="text" name="kozbridge_label" maxlength="60" class="regular-text" placeholder="<?php echo esc_attr( self::t( 'Private GPT' ) ); ?>"></label></p>
					<fieldset><legend><?php echo esc_html( self::t( 'Access' ) ); ?></legend>
						<?php foreach ( $scopes as $scope => $label ) : ?>
							<label class="kozbridge-scope"><input type="checkbox" name="kozbridge_scopes[]" value="<?php echo esc_attr( $scope ); ?>"<?php checked( 'error_log' !== $scope ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<p><label><?php echo esc_html( self::t( 'Expires' ) ); ?>
						<select name="kozbridge_expires">
							<option value="0"><?php echo esc_html( self::t( 'never' ) ); ?></option>
							<option value="30"><?php echo esc_html( self::t( 'in 30 days' ) ); ?></option>
							<option value="90"><?php echo esc_html( self::t( 'in 90 days' ) ); ?></option>
							<option value="365"><?php echo esc_html( self::t( 'in 1 year' ) ); ?></option>
						</select></label></p>
					<p><button class="button button-primary" name="kozbridge_create" value="1"><?php echo esc_html( self::t( 'Create API key' ) ); ?></button></p>
				</form>
			</details>

			<h2><?php echo esc_html( self::t( 'Try it' ) ); ?></h2>
			<p class="description"><?php echo esc_html( self::t( 'Runs an endpoint as the current administrator without an API key and shows the JSON a GPT would receive. Nothing is changed.' ) ); ?></p>
			<div class="kozbridge-console" data-kozbridge-console>
				<select data-kozbridge-endpoint>
					<?php foreach ( self::console_hints() as $method => $hint ) : ?>
						<?php $route = array_search( $method, KOZBRIDGE_Bridge::routes(), true ); ?>
						<option value="<?php echo esc_attr( $method ); ?>" data-hint="<?php echo esc_attr( $hint ); ?>"><?php echo esc_html( false !== $route ? (string) $route : '/' . $method ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" class="regular-text code" data-kozbridge-params placeholder="" aria-label="<?php echo esc_attr( self::t( 'Parameters' ) ); ?>">
				<button type="button" class="button button-primary" data-kozbridge-run><?php echo esc_html( self::t( 'Run' ) ); ?></button>
				<span class="kozbridge-console-status" aria-live="polite" data-kozbridge-console-status></span>
				<pre class="kozbridge-console-output" data-kozbridge-output hidden></pre>
			</div>

			<h2><?php echo esc_html( self::t( 'WordPress abilities (MCP)' ) ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'kozbridge_settings_action' ); ?>
				<p><label><input type="checkbox" name="kozbridge_abilities" value="1"<?php checked( $settings['abilities'] ); ?>> <?php echo esc_html( self::t( 'Register read-only abilities for administrators (WordPress 6.9+ Abilities API, usable by AI clients through the MCP Adapter).' ) ); ?></label></p>
				<p class="description"><?php echo esc_html( function_exists( 'wp_register_ability' ) ? self::t( 'Abilities API is available on this site.' ) : self::t( 'Abilities API is not available: WordPress 6.9 or newer is required.' ) ); ?></p>
				<p><button class="button" name="kozbridge_save_settings" value="1"><?php echo esc_html( self::t( 'Save settings' ) ); ?></button></p>
			</form>

			<h2><?php echo esc_html( self::t( 'Private GPT connection' ) ); ?></h2>
			<ol class="kozbridge-steps">
				<li><?php echo esc_html( self::t( 'Create a private GPT with “Only me” access.' ) ); ?></li>
				<li><?php echo esc_html( self::t( 'Import the OpenAPI schema from this URL:' ) ); ?> <code class="kozbridge-url"><?php echo esc_html( $schema_url ); ?></code></li>
				<li>Authentication: <strong>API key → Custom header</strong>, header <code>X-KOZ-Key</code>.</li>
				<li><?php echo esc_html( self::t( 'Legacy endpoint and X-UAFree-Key remain accepted during migration.' ) ); ?></li>
				<li><?php echo esc_html( self::t( 'Paste the key and test pingSiteBridge.' ) ); ?></li>
			</ol>

			<h2><?php echo esc_html( self::t( 'Safe HTTP profiles' ) ); ?></h2>
			<p><?php echo esc_html( self::t( 'HTTP probe accepts safe public frontend paths on the current site without a query string. WordPress administration, REST, login, executable and private system paths remain blocked. Profiles: default, googlebot, adsbot-google, adsbot-google-mobile.' ) ); ?></p>
		</div>
		<?php
	}
}
