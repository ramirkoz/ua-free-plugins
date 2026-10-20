<?php
/**
 * Read-only WordPress abilities (Abilities API, WordPress 6.9+).
 *
 * Every ability requires manage_options and runs the same code as the REST
 * endpoint, without an API key. The MCP Adapter can expose them to AI clients.
 *
 * @package KOZ_Site_Bridge
 */

namespace ramirkz\kozbridge;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZBRIDGE_Abilities {
	private const CATEGORY = 'koz-site-bridge';

	public static function init(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! KOZBRIDGE_Admin::settings()['abilities'] ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	public static function register_category(): void {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::CATEGORY,
				array(
					'label'       => 'KOZ Site Bridge',
					'description' => 'Read-only site diagnostics.',
				)
			);
		}
	}

	/** Ability name => [callback method, label, description, input properties]. */
	private static function definitions(): array {
		$path    = array( 'type' => 'string', 'description' => 'Public path beginning with /.', 'default' => '/' );
		$profile = array( 'type' => 'string', 'enum' => array( 'default', 'googlebot', 'adsbot-google', 'adsbot-google-mobile' ), 'default' => 'default' );
		return array(
			'kozbridge/site-overview' => array( 'overview', 'Site overview', 'WordPress, PHP, theme, language and permalink summary of this site.', array() ),
			'kozbridge/diagnostics'   => array( 'diagnostics', 'Site diagnostics', 'Checks the home page, robots.txt, sitemaps, llms.txt, AI manifest and 404 status. Returns issues with severity.', array() ),
			'kozbridge/page-audit'    => array( 'page_audit', 'Page SEO audit', 'Fetches one public page and checks title, description, canonical, H1, image alt text, hreflang, schema and links.', array( 'path' => $path, 'profile' => $profile ) ),
			'kozbridge/sitemap-audit' => array( 'rendered_audit', 'Sitemap audit', 'Audits a small batch of URLs from the site sitemaps, including language versions, and lists issues per URL.', array( 'page' => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'default' => 3 ), 'profile' => $profile ) ),
			'kozbridge/404-log'       => array( 'error_404_log', '404 log', 'Most frequent 404, 410 and redirect entries from KOZ 404 Guard. No IP address or User-Agent.', array( 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ) ) ),
			'kozbridge/plugins'       => array( 'plugins', 'Plugin inventory', 'Installed plugins with versions and activation state, without file paths.', array() ),
		);
	}

	public static function register_abilities(): void {
		foreach ( self::definitions() as $name => $definition ) {
			list( $method, $label, $description, $properties ) = $definition;
			$args = array(
				'label'               => $label,
				'description'         => $description,
				'category'            => self::CATEGORY,
				'output_schema'       => array( 'type' => 'object', 'additionalProperties' => true ),
				'execute_callback'    => static fn( $input = null ) => self::execute( $method, $input, $properties ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			);
			if ( ! empty( $properties ) ) {
				$args['input_schema'] = array(
					'type'                 => 'object',
					'properties'           => $properties,
					'additionalProperties' => false,
					'default'              => array(),
				);
			}
			wp_register_ability( $name, $args );
		}
	}

	/** @return array|WP_Error */
	private static function execute( string $method, $input, array $properties ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'kozbridge_forbidden', 'Administrator access is required.', array( 'status' => 403 ) );
		}
		$input = is_array( $input ) ? $input : array();
		foreach ( $properties as $name => $property ) {
			if ( ! array_key_exists( $name, $input ) && array_key_exists( 'default', $property ) ) {
				$input[ $name ] = $property['default'];
			}
		}
		$result = KOZBRIDGE_Bridge::run( $method, $input );
		if ( $result['status'] >= 400 ) {
			$data = is_array( $result['data'] ) ? $result['data'] : array();
			return new WP_Error( 'kozbridge_ability_failed', (string) ( $data['error'] ?? $data['message'] ?? 'Request failed.' ), array( 'status' => $result['status'] ) );
		}
		return is_array( $result['data'] ) ? $result['data'] : array( 'result' => $result['data'] );
	}
}
