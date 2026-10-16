<?php
/**
 * Detection reason column and filter on the Comments screen.
 *
 * The reason is a rule key stored as comment meta. It contains no personal data.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace ramirkz\kozurlspam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Comments_UI {
	public const META_KEY = '_kozurlspam_reason';
	private const COLUMN = 'kozurlspam_reason';
	private const FILTER = 'kozurlspam_reason';

	public static function boot(): void {
		add_filter( 'manage_edit-comments_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_comments_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_action( 'restrict_manage_comments', array( __CLASS__, 'render_filter' ) );
		add_filter( 'comments_list_table_query_args', array( __CLASS__, 'filter_query' ) );
	}

	/** @param array<string,string> $columns @return array<string,string> */
	public static function add_column( array $columns ): array {
		$columns[ self::COLUMN ] = __( 'Spam reason', 'koz-url-only-comment-spam' );
		return $columns;
	}

	public static function render_column( string $column, $comment_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$rule = (string) get_comment_meta( (int) $comment_id, self::META_KEY, true );
		$labels = Maintenance::rule_labels();
		echo isset( $labels[ $rule ] ) ? esc_html( $labels[ $rule ] ) : '&mdash;';
	}

	private static function current_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$value = isset( $_GET[ self::FILTER ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::FILTER ] ) ) : '';
		return isset( Maintenance::rule_labels()[ $value ] ) || 'any' === $value ? $value : '';
	}

	public static function render_filter(): void {
		$current = self::current_filter();
		?>
		<label class="screen-reader-text" for="kozurlspam-reason-filter"><?php esc_html_e( 'Filter by spam reason', 'koz-url-only-comment-spam' ); ?></label>
		<select id="kozurlspam-reason-filter" name="<?php echo esc_attr( self::FILTER ); ?>">
			<option value=""><?php esc_html_e( 'All spam reasons', 'koz-url-only-comment-spam' ); ?></option>
			<option value="any" <?php selected( 'any', $current ); ?>><?php esc_html_e( 'Any KOZ reason', 'koz-url-only-comment-spam' ); ?></option>
			<?php foreach ( Maintenance::rule_labels() as $rule => $label ) : ?>
				<option value="<?php echo esc_attr( $rule ); ?>" <?php selected( $rule, $current ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/** @param array<string,mixed> $args @return array<string,mixed> */
	public static function filter_query( array $args ): array {
		$current = self::current_filter();
		if ( '' === $current ) {
			return $args;
		}

		$clause = 'any' === $current
			? array( 'key' => self::META_KEY, 'compare' => 'EXISTS' )
			: array( 'key' => self::META_KEY, 'value' => $current );

		$args['meta_query'] = array( $clause ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only filter on a small meta set.
		return $args;
	}
}
