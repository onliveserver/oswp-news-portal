<?php
/**
 * Rich HTML sanitizer for AI-generated and editor-authored content.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes the HTML tags allowed in generated post content.
 */
class Html_Sanitizer {
	/**
	 * Get allowed HTML tags for rich post content.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['h1']      = [];
		$allowed['h2']      = [];
		$allowed['h3']      = [];
		$allowed['h4']      = [];
		$allowed['h5']      = [];
		$allowed['h6']      = [];
		$allowed['table']   = [
			'class' => true,
		];
		$allowed['thead']   = [];
		$allowed['tbody']   = [];
		$allowed['tfoot']   = [];
		$allowed['tr']      = [];
		$allowed['th']      = [
			'colspan' => true,
			'rowspan' => true,
			'scope'   => true,
		];
		$allowed['td']      = [
			'colspan' => true,
			'rowspan' => true,
		];
		$allowed['hr']      = [];
		$allowed['section'] = [
			'class' => true,
		];
		$allowed['div']     = [
			'class' => true,
		];
		$allowed['span']    = [
			'class' => true,
		];

		return $allowed;
	}

	/**
	 * Sanitize a rich HTML fragment.
	 *
	 * @param string $html HTML fragment.
	 * @return string
	 */
	public static function sanitize_fragment( $html ) {
		return trim( wp_kses( (string) $html, self::allowed_html() ) );
	}

	/**
	 * Determine whether an HTML fragment contains a tag.
	 *
	 * @param string $html HTML fragment.
	 * @param string $tag  Tag name.
	 * @return bool
	 */
	public static function contains_tag( $html, $tag ) {
		$tag = preg_quote( strtolower( (string) $tag ), '/' );

		return 1 === preg_match( '/<' . $tag . '\b/i', (string) $html );
	}
}