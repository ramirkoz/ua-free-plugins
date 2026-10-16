<?php
namespace ramirkz\kozcopyactions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [koz_copy] shortcode and the "Copy button" block. Both render the same markup,
 * which the frontend script handles through KOZCOAC_Plugin::BUILTIN_SELECTORS.
 */
final class KOZCOAC_Shortcode {
	public const TAG = 'koz_copy';

	public static function init(): void {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			KOZCOAC_DIR . 'blocks/copy-button',
			array( 'render_callback' => array( __CLASS__, 'render_block' ) )
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'kozcoac-copy-button-editor-script', 'koz-copy-actions' );
		}
	}

	/**
	 * @param array<string,mixed>|string $atts
	 */
	public static function shortcode( $atts, ?string $content = null ): string {
		$atts = shortcode_atts(
			array(
				'text'    => '',
				'target'  => '',
				'label'   => '',
				'display' => 'button',
				'format'  => '',
				'key'     => '',
				'class'   => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$atts['content'] = null === $content ? '' : wp_strip_all_tags( do_shortcode( $content ) );
		return self::render( $atts );
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public static function render_block( array $attributes ): string {
		return self::render(
			array(
				'text'    => (string) ( $attributes['text'] ?? '' ),
				'target'  => (string) ( $attributes['target'] ?? '' ),
				'label'   => (string) ( $attributes['label'] ?? '' ),
				'display' => (string) ( $attributes['display'] ?? 'button' ),
				'format'  => (string) ( $attributes['format'] ?? '' ),
				'key'     => '',
				'class'   => '',
				'content' => '',
			),
			true
		);
	}

	/**
	 * @param array<string,string> $atts
	 */
	private static function render( array $atts, bool $is_block = false ): string {
		$text = (string) $atts['text'];
		$content = trim( (string) $atts['content'] );
		$target = trim( (string) $atts['target'] );
		$target = 1 === preg_match( '/^#[A-Za-z][A-Za-z0-9_\-:.]{0,99}$/', $target ) ? $target : '';
		$display = 'inline' === $atts['display'] ? 'inline' : 'button';
		$format = in_array( $atts['format'], array( 'text', 'html' ), true ) ? $atts['format'] : '';
		$key = 1 === preg_match( '/^[A-Za-z0-9_-]{1,80}$/', (string) $atts['key'] ) ? (string) $atts['key'] : '';
		$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ?: array() ) );

		$value = '' !== $text ? $text : $content;
		if ( '' === $value && '' === $target ) {
			return '';
		}

		$attributes = array();
		if ( '' !== $value ) {
			$attributes['data-copy-value'] = $value;
		} else {
			$attributes['data-copy-target'] = $target;
		}
		if ( '' !== $format ) {
			$attributes['data-copy-format'] = $format;
		}
		if ( '' !== $key ) {
			$attributes['data-copy-key'] = $key;
		}

		if ( 'inline' === $display ) {
			$label = '' !== $atts['label'] ? (string) $atts['label'] : ( '' !== $content ? $content : $value );
			$tag = 'span';
			$classes = array_merge( array( 'kozcoac-copy-inline' ), $extra_classes );
		} else {
			$label = '' !== $atts['label'] ? (string) $atts['label'] : __( 'Copy', 'koz-copy-actions' );
			$tag = 'button';
			$classes = array_merge( array( 'kozcoac-copy-button' ), $extra_classes );
			$attributes['type'] = 'button';
		}

		if ( '' === $label ) {
			return '';
		}

		KOZCOAC_Plugin::ensure_assets();

		$attributes['class'] = implode( ' ', $classes );
		$html = '';
		foreach ( $attributes as $name => $attribute_value ) {
			$html .= ' ' . $name . '="' . esc_attr( $attribute_value ) . '"';
		}

		$element = '<' . $tag . $html . '>' . esc_html( $label ) . '</' . $tag . '>';

		if ( $is_block ) {
			$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : 'class="wp-block-kozcoac-copy-button"';
			return '<div ' . $wrapper . '>' . $element . '</div>';
		}

		return $element;
	}
}
