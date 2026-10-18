<?php
/**
 * Redirect and 410 rules: storage, validation, matching, compiled index and CSV.
 *
 * Full rules live in two non-autoloaded options. A compact index of the enabled rules
 * is autoloaded, so front-end requests need no extra database query.
 *
 * @package KOZ_404_Guard
 */

namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Rules {
	public const INDEX_OPTION = 'koz404_rules_index';

	private const REDIRECT_STATUSES = array( 301, 302, 307, 308 );
	private const REDIRECT_TYPES    = array( 'exact', 'prefix' );
	private const GONE_TYPES        = array( 'exact_path', 'path_prefix', 'query_key', 'query_pair' );
	private const MAX_RULES         = 5000;

	/* ------------------------------------------------------------------
	 * Reading
	 * ---------------------------------------------------------------- */

	public static function redirects(): array {
		$value  = get_option( KOZ404_Guard::REDIRECT_OPTION, array() );
		$result = array();
		foreach ( is_array( $value ) ? $value : array() as $row ) {
			$rule = self::normalise_redirect( $row );
			if ( null !== $rule ) {
				$result[] = $rule;
			}
		}
		return $result;
	}

	public static function gone_rules(): array {
		$value  = get_option( KOZ404_Guard::GONE_OPTION, array() );
		$result = array();
		foreach ( is_array( $value ) ? $value : array() as $row ) {
			$rule = self::normalise_gone( $row );
			if ( null !== $rule ) {
				$result[] = $rule;
			}
		}
		return $result;
	}

	public static function redirect( string $id ): ?array {
		foreach ( self::redirects() as $rule ) {
			if ( $rule['id'] === $id ) {
				return $rule;
			}
		}
		return null;
	}

	private static function target_path( string $target ): string {
		return KOZ404_Guard::normalise_path( (string) wp_parse_url( $target, PHP_URL_PATH ) );
	}

	private static function normalise_redirect( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$id     = sanitize_text_field( KOZ404_Guard::scalar_string( $row['id'] ?? '' ) );
		$type   = sanitize_key( KOZ404_Guard::scalar_string( $row['type'] ?? 'exact' ) );
		$type   = in_array( $type, self::REDIRECT_TYPES, true ) ? $type : 'exact';
		$source = KOZ404_Guard::normalise_path( KOZ404_Guard::scalar_string( $row['source'] ?? '' ) );
		$target = KOZ404_Guard::normalise_same_site_url( $row['target'] ?? '' );
		$status = absint( KOZ404_Guard::scalar_string( $row['status'] ?? 301 ) );
		if ( '' === $id || KOZ404_Guard::is_protected_path( $source ) || '' === $target || ! in_array( $status, self::REDIRECT_STATUSES, true ) ) {
			return null;
		}
		$target_path = self::target_path( $target );
		if ( hash_equals( $source, $target_path ) || ( 'prefix' === $type && str_starts_with( $target_path, $source . '/' ) ) ) {
			return null;
		}
		$origin = sanitize_key( KOZ404_Guard::scalar_string( $row['origin'] ?? 'manual' ) );
		return array(
			'id'             => KOZ404_Guard::truncate( $id, 100 ),
			'type'           => $type,
			'source'         => $source,
			'target'         => $target,
			'status'         => $status,
			'preserve_query' => KOZ404_Guard::strict_boolean( $row['preserve_query'] ?? 0, 0 ),
			'enabled'        => KOZ404_Guard::strict_boolean( $row['enabled'] ?? 1, 1 ),
			'origin'         => in_array( $origin, array( 'manual', 'log', 'permalink', 'import' ), true ) ? $origin : 'manual',
			'created'        => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', KOZ404_Guard::scalar_string( $row['created'] ?? '' ) ) ? (string) $row['created'] : '',
		);
	}

	private static function normalise_gone( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$id    = sanitize_text_field( KOZ404_Guard::scalar_string( $row['id'] ?? '' ) );
		$type  = sanitize_key( KOZ404_Guard::scalar_string( $row['type'] ?? '' ) );
		$value = self::normalise_gone_value( $type, $row['value'] ?? '' );
		if ( '' === $id || ! in_array( $type, self::GONE_TYPES, true ) || '' === $value ) {
			return null;
		}
		return array(
			'id'      => KOZ404_Guard::truncate( $id, 100 ),
			'type'    => $type,
			'value'   => $value,
			'enabled' => KOZ404_Guard::strict_boolean( $row['enabled'] ?? 1, 1 ),
			'created' => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', KOZ404_Guard::scalar_string( $row['created'] ?? '' ) ) ? (string) $row['created'] : '',
		);
	}

	public static function normalise_gone_value( string $type, $value ): string {
		$value = KOZ404_Guard::scalar_string( $value );
		if ( in_array( $type, array( 'exact_path', 'path_prefix' ), true ) ) {
			$path = KOZ404_Guard::normalise_path( $value );
			return KOZ404_Guard::is_protected_path( $path ) ? '' : $path;
		}
		if ( 'query_key' === $type ) {
			return KOZ404_Guard::normalise_query_key( $value );
		}
		if ( 'query_pair' === $type ) {
			$pair = self::split_query_rule( $value );
			return null === $pair ? '' : $pair['key'] . '=' . $pair['value'];
		}
		return '';
	}

	private static function split_query_rule( string $value ): ?array {
		$parts = explode( '=', $value, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		$key   = KOZ404_Guard::normalise_query_key( $parts[0] );
		$right = rawurldecode( str_replace( '+', ' ', trim( $parts[1] ) ) );
		$right = KOZ404_Guard::truncate( preg_replace( '/[\x00-\x1F\x7F]/u', '', $right ) ?? '', 300 );
		if ( '' === $key || '' === $right ) {
			return null;
		}
		return array( 'key' => $key, 'value' => $right );
	}

	/* ------------------------------------------------------------------
	 * Index and matching (front end)
	 * ---------------------------------------------------------------- */

	public static function rebuild_index(): void {
		$exact  = array();
		$prefix = array();
		foreach ( self::redirects() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$entry = array(
				'id'     => $rule['id'],
				'source' => $rule['source'],
				'target' => $rule['target'],
				'status' => $rule['status'],
				'q'      => $rule['preserve_query'],
			);
			if ( 'prefix' === $rule['type'] ) {
				$prefix[] = $entry;
			} else {
				$exact[ $rule['source'] ] = $entry;
			}
		}
		usort( $prefix, static fn( array $a, array $b ): int => strlen( $b['source'] ) <=> strlen( $a['source'] ) );
		$gone = array();
		foreach ( self::gone_rules() as $rule ) {
			if ( ! empty( $rule['enabled'] ) ) {
				$gone[] = array( 'id' => $rule['id'], 'type' => $rule['type'], 'value' => $rule['value'] );
			}
		}
		$index = array(
			'exact'  => $exact,
			'prefix' => $prefix,
			'gone'   => $gone,
		);
		if ( false === get_option( self::INDEX_OPTION, false ) ) {
			add_option( self::INDEX_OPTION, $index, '', true );
		} else {
			update_option( self::INDEX_OPTION, $index, true );
		}
	}

	private static function index(): array {
		$index = get_option( self::INDEX_OPTION, null );
		if ( null === $index ) {
			// First request after an update from 2.1.x: build the index from the stored rules.
			self::rebuild_index();
			$index = get_option( self::INDEX_OPTION, null );
		}
		if ( ! is_array( $index ) ) {
			return array( 'exact' => array(), 'prefix' => array(), 'gone' => array() );
		}
		return wp_parse_args( $index, array( 'exact' => array(), 'prefix' => array(), 'gone' => array() ) );
	}

	public static function has_active_rules(): bool {
		$index = self::index();
		return ! empty( $index['exact'] ) || ! empty( $index['prefix'] ) || ! empty( $index['gone'] );
	}

	/**
	 * Redirect for a path, with the final target URL already resolved.
	 *
	 * @return array{id:string,status:int,url:string}|null
	 */
	public static function match_redirect( string $path, string $query ): ?array {
		if ( KOZ404_Guard::is_protected_path( $path ) ) {
			return null;
		}
		$index = self::index();
		$entry = null;
		$rest  = '';
		if ( isset( $index['exact'][ $path ] ) && is_array( $index['exact'][ $path ] ) ) {
			$entry = $index['exact'][ $path ];
		} else {
			foreach ( (array) $index['prefix'] as $candidate ) {
				$source = (string) ( $candidate['source'] ?? '' );
				if ( '' !== $source && ( $path === $source || str_starts_with( $path, $source . '/' ) ) ) {
					$entry = $candidate;
					$rest  = substr( $path, strlen( $source ) );
					break;
				}
			}
		}
		if ( null === $entry ) {
			return null;
		}
		$target = (string) $entry['target'];
		if ( '' !== $rest ) {
			$parts       = wp_parse_url( $target );
			$target_path = rtrim( (string) ( $parts['path'] ?? '/' ), '/' ) . $rest;
			$target      = KOZ404_Guard::path_url( KOZ404_Guard::normalise_path( $target_path ) )
				. ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		}
		if ( ! empty( $entry['q'] ) && '' !== $query ) {
			$target .= ( str_contains( $target, '?' ) ? '&' : '?' ) . $query;
		}
		if ( hash_equals( $path, self::target_path( $target ) ) ) {
			return null;
		}
		return array(
			'id'     => (string) $entry['id'],
			'status' => (int) $entry['status'],
			'url'    => $target,
		);
	}

	public static function match_gone( string $path, array $pairs ): ?string {
		// Query rules also apply to the home page (?pagelayer-template=…); system routes never match.
		if ( KOZ404_Guard::is_system_path( $path ) ) {
			return null;
		}
		foreach ( (array) self::index()['gone'] as $rule ) {
			$type  = (string) ( $rule['type'] ?? '' );
			$value = (string) ( $rule['value'] ?? '' );
			$id    = (string) ( $rule['id'] ?? '' );
			if ( 'exact_path' === $type && hash_equals( $value, $path ) ) {
				return $id;
			}
			if ( 'path_prefix' === $type && ( hash_equals( $value, $path ) || str_starts_with( $path, $value . '/' ) ) ) {
				return $id;
			}
			if ( 'query_key' === $type ) {
				foreach ( $pairs as $pair ) {
					if ( hash_equals( $value, $pair['key'] ) ) {
						return $id;
					}
				}
			}
			if ( 'query_pair' === $type ) {
				$expected = self::split_query_rule( $value );
				if ( null === $expected ) {
					continue;
				}
				foreach ( $pairs as $pair ) {
					if ( hash_equals( $expected['key'], $pair['key'] ) && hash_equals( $expected['value'], $pair['value'] ) ) {
						return $id;
					}
				}
			}
		}
		return null;
	}

	/** What a rule does with a path, for the link audit. */
	public static function describe_path( string $path ): string {
		if ( null !== self::match_gone( $path, array() ) ) {
			return 'gone';
		}
		return null !== self::match_redirect( $path, '' ) ? 'redirect' : '';
	}

	/* ------------------------------------------------------------------
	 * Writing
	 * ---------------------------------------------------------------- */

	private static function store_redirects( array $rules ): void {
		update_option( KOZ404_Guard::REDIRECT_OPTION, array_values( $rules ), false );
		self::rebuild_index();
	}

	private static function store_gone( array $rules ): void {
		update_option( KOZ404_Guard::GONE_OPTION, array_values( $rules ), false );
		self::rebuild_index();
	}

	private static function would_create_cycle( array $rules, string $source, string $target ): bool {
		$map = array();
		foreach ( $rules as $rule ) {
			if ( 'exact' === $rule['type'] && ! empty( $rule['enabled'] ) ) {
				$map[ $rule['source'] ] = self::target_path( $rule['target'] );
			}
		}
		$map[ $source ] = self::target_path( $target );
		$seen    = array();
		$current = $source;
		for ( $i = 0, $max = count( $map ) + 1; $i < $max; $i++ ) {
			if ( isset( $seen[ $current ] ) ) {
				return true;
			}
			$seen[ $current ] = true;
			if ( ! isset( $map[ $current ] ) ) {
				return false;
			}
			$current = $map[ $current ];
		}
		return true;
	}

	/**
	 * Adds or updates a redirect rule.
	 *
	 * @return string Empty on success, otherwise an error code.
	 */
	public static function save_redirect( array $data, string $id = '', array $rules = array(), bool $store = true ): string {
		$rules  = $store ? self::redirects() : $rules;
		$source = KOZ404_Guard::normalise_path( KOZ404_Guard::scalar_string( $data['source'] ?? '' ) );
		$target = KOZ404_Guard::normalise_same_site_url( $data['target'] ?? '' );
		$status = absint( KOZ404_Guard::scalar_string( $data['status'] ?? 301 ) );
		$type   = sanitize_key( KOZ404_Guard::scalar_string( $data['type'] ?? 'exact' ) );
		$type   = in_array( $type, self::REDIRECT_TYPES, true ) ? $type : 'exact';
		if ( KOZ404_Guard::is_protected_path( $source ) || '' === $target || ! in_array( $status, self::REDIRECT_STATUSES, true ) ) {
			return 'invalid_redirect';
		}
		$target_path = self::target_path( $target );
		if ( hash_equals( $source, $target_path ) || ( 'prefix' === $type && str_starts_with( $target_path, $source . '/' ) ) ) {
			return 'redirect_loop';
		}
		$existing = null;
		foreach ( $rules as $key => $rule ) {
			if ( '' !== $id && $rule['id'] === $id ) {
				$existing = $key;
				continue;
			}
			if ( $rule['type'] === $type && hash_equals( $rule['source'], $source ) ) {
				return 'duplicate_redirect';
			}
		}
		if ( '' !== $id && null === $existing ) {
			return 'rule_not_found';
		}
		$others = $rules;
		if ( null !== $existing ) {
			unset( $others[ $existing ] );
		}
		if ( 'exact' === $type && self::would_create_cycle( $others, $source, $target ) ) {
			return 'redirect_loop';
		}
		if ( null === $existing && count( $rules ) >= self::MAX_RULES ) {
			return 'too_many_rules';
		}
		$origin = sanitize_key( KOZ404_Guard::scalar_string( $data['origin'] ?? 'manual' ) );
		$rule   = array(
			'id'             => null !== $existing ? $rules[ $existing ]['id'] : wp_generate_uuid4(),
			'type'           => $type,
			'source'         => $source,
			'target'         => $target,
			'status'         => $status,
			'preserve_query' => KOZ404_Guard::strict_boolean( $data['preserve_query'] ?? 0, 0 ),
			'enabled'        => null !== $existing ? $rules[ $existing ]['enabled'] : 1,
			'origin'         => null !== $existing ? $rules[ $existing ]['origin'] : $origin,
			'created'        => null !== $existing ? $rules[ $existing ]['created'] : current_time( 'mysql' ),
		);
		if ( null !== $existing ) {
			$rules[ $existing ] = $rule;
		} else {
			$rules[] = $rule;
		}
		if ( $store ) {
			self::store_redirects( $rules );
		} else {
			self::$pending = $rules;
		}
		return '';
	}

	/** Rules collected by save_redirect() with $store = false (used by the CSV import). */
	private static array $pending = array();

	public static function delete_redirect( string $id ): void {
		self::store_redirects( array_filter( self::redirects(), static fn( array $rule ): bool => $rule['id'] !== $id ) );
	}

	public static function toggle_redirect( string $id ): string {
		$rules = self::redirects();
		foreach ( $rules as $key => $rule ) {
			if ( $rule['id'] !== $id ) {
				continue;
			}
			if ( empty( $rule['enabled'] ) && 'exact' === $rule['type'] ) {
				$others = $rules;
				unset( $others[ $key ] );
				if ( self::would_create_cycle( $others, $rule['source'], $rule['target'] ) ) {
					return 'redirect_loop';
				}
			}
			$rules[ $key ]['enabled'] = empty( $rule['enabled'] ) ? 1 : 0;
			self::store_redirects( $rules );
			return '';
		}
		return 'rule_not_found';
	}

	public static function save_gone( string $type, string $value_raw, string $id = '' ): string {
		$type  = sanitize_key( $type );
		$value = self::normalise_gone_value( $type, $value_raw );
		if ( '' === $value || ! in_array( $type, self::GONE_TYPES, true ) ) {
			return 'invalid_gone';
		}
		$rules    = self::gone_rules();
		$existing = null;
		foreach ( $rules as $key => $rule ) {
			if ( '' !== $id && $rule['id'] === $id ) {
				$existing = $key;
				continue;
			}
			if ( $rule['type'] === $type && hash_equals( $rule['value'], $value ) ) {
				return 'duplicate_gone';
			}
		}
		if ( '' !== $id && null === $existing ) {
			return 'rule_not_found';
		}
		$rule = array(
			'id'      => null !== $existing ? $rules[ $existing ]['id'] : wp_generate_uuid4(),
			'type'    => $type,
			'value'   => $value,
			'enabled' => null !== $existing ? $rules[ $existing ]['enabled'] : 1,
			'created' => null !== $existing ? $rules[ $existing ]['created'] : current_time( 'mysql' ),
		);
		if ( null !== $existing ) {
			$rules[ $existing ] = $rule;
		} else {
			$rules[] = $rule;
		}
		self::store_gone( $rules );
		return '';
	}

	public static function delete_gone( string $id ): void {
		self::store_gone( array_filter( self::gone_rules(), static fn( array $rule ): bool => $rule['id'] !== $id ) );
	}

	public static function toggle_gone( string $id ): string {
		$rules = self::gone_rules();
		foreach ( $rules as $key => $rule ) {
			if ( $rule['id'] === $id ) {
				$rules[ $key ]['enabled'] = empty( $rule['enabled'] ) ? 1 : 0;
				self::store_gone( $rules );
				return '';
			}
		}
		return 'rule_not_found';
	}

	/** Removes an automatic (permalink) redirect whose source is the given path. */
	public static function delete_permalink_redirect_from( string $path ): void {
		$rules = self::redirects();
		$kept  = array_filter( $rules, static fn( array $rule ): bool => ! ( 'exact' === $rule['type'] && 'permalink' === $rule['origin'] && hash_equals( $rule['source'], $path ) ) );
		if ( count( $kept ) !== count( $rules ) ) {
			self::store_redirects( $kept );
		}
	}

	public static function has_redirect_source( string $source ): bool {
		$source = KOZ404_Guard::normalise_path( $source );
		foreach ( self::redirects() as $rule ) {
			if ( 'exact' === $rule['type'] && hash_equals( $source, $rule['source'] ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * CSV
	 * ---------------------------------------------------------------- */

	/** CSV columns: source,target,status,type,preserve_query,enabled. */
	public static function export_csv(): string {
		$lines = array( 'source,target,status,type,preserve_query,enabled' );
		foreach ( self::redirects() as $rule ) {
			$lines[] = implode(
				',',
				array_map(
					static fn( $cell ): string => '"' . str_replace( '"', '""', (string) $cell ) . '"',
					array( $rule['source'], $rule['target'], $rule['status'], $rule['type'], $rule['preserve_query'], $rule['enabled'] )
				)
			);
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Imports redirects from CSV text. Invalid, duplicate and looping rows are skipped.
	 *
	 * @return array{added:int,skipped:int}
	 */
	public static function import_csv( string $csv ): array {
		$added   = 0;
		$skipped = 0;
		$rules   = self::redirects();
		$lines   = preg_split( '/\r\n|\r|\n/', $csv ) ?: array();
		foreach ( array_slice( $lines, 0, self::MAX_RULES + 1 ) as $number => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = str_getcsv( $line, ',', '"', '' );
			if ( 0 === $number && isset( $cells[0] ) && 'source' === strtolower( trim( (string) $cells[0] ) ) ) {
				continue;
			}
			$data = array(
				'source'         => (string) ( $cells[0] ?? '' ),
				'target'         => (string) ( $cells[1] ?? '' ),
				'status'         => '' !== trim( (string) ( $cells[2] ?? '' ) ) ? (string) $cells[2] : '301',
				'type'           => '' !== trim( (string) ( $cells[3] ?? '' ) ) ? (string) $cells[3] : 'exact',
				'preserve_query' => (string) ( $cells[4] ?? '0' ),
				'origin'         => 'import',
			);
			self::$pending = $rules;
			if ( '' === self::save_redirect( $data, '', $rules, false ) ) {
				$rules = self::$pending;
				if ( isset( $cells[5] ) && '0' === trim( (string) $cells[5] ) ) {
					$last                       = array_key_last( $rules );
					$rules[ $last ]['enabled']  = 0;
				}
				$added++;
			} else {
				$skipped++;
			}
		}
		if ( $added > 0 ) {
			self::store_redirects( $rules );
		}
		self::$pending = array();
		return array( 'added' => $added, 'skipped' => $skipped );
	}

	/* ------------------------------------------------------------------
	 * Privacy-safe export
	 * ---------------------------------------------------------------- */

	public static function export_redirects(): array {
		$result = array();
		foreach ( self::redirects() as $rule ) {
			$result[] = array(
				'rule_fingerprint'   => KOZ404_Guard::fingerprint( $rule['id'], 'rule' ),
				'type'               => $rule['type'],
				'status'             => $rule['status'],
				'enabled'            => (bool) $rule['enabled'],
				'preserve_query'     => (bool) $rule['preserve_query'],
				'origin'             => $rule['origin'],
				'source_fingerprint' => KOZ404_Guard::fingerprint( $rule['source'], 'path' ),
				'target_fingerprint' => KOZ404_Guard::fingerprint( $rule['target'], 'target' ),
			);
		}
		return $result;
	}

	public static function export_gone_rules(): array {
		$result = array();
		foreach ( self::gone_rules() as $rule ) {
			$result[] = array(
				'rule_fingerprint'  => KOZ404_Guard::fingerprint( $rule['id'], 'rule' ),
				'type'              => $rule['type'],
				'enabled'           => (bool) $rule['enabled'],
				'value_fingerprint' => KOZ404_Guard::fingerprint( $rule['value'], 'gone' ),
			);
		}
		return $result;
	}
}
