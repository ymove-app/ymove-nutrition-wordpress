<?php
/**
 * Outgoing mail through the site's own wp_mail. Messages are structured
 * (heading, intro, rows, button) so another transport can be added later.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailer {

	const LOG_OPTION = 'ymove_nutrition_mail_log';

	private static string $wp_error = '';

	/**
	 * Send one message via wp_mail. $m keys: to, subject, heading, intro (plain text),
	 * rows (list of [label, value, highlight]), cta ([label, url]), note,
	 * reply_to, context ('lead' | 'owner' | 'test').
	 *
	 * @return array{ok: bool, via: string, error: string}
	 */
	public static function send( array $m ): array {
		$result = self::via_wordpress( $m );
		self::log( $m, $result );
		return $result;
	}

	private static function via_wordpress( array $m ): array {
		self::$wp_error = '';
		$catch          = function ( $error ) {
			self::$wp_error = is_wp_error( $error ) ? $error->get_error_message() : 'wp_mail failed';
		};
		add_action( 'wp_mail_failed', $catch );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $m['reply_to'] ) && is_email( $m['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . $m['reply_to'];
		}
		$sent = wp_mail( $m['to'], $m['subject'], self::render_html( $m ), $headers );
		remove_action( 'wp_mail_failed', $catch );

		return array(
			'ok'    => (bool) $sent,
			'via'   => 'wordpress',
			'error' => $sent ? '' : ( self::$wp_error ?: __( 'wp_mail returned false.', 'ymove-nutrition' ) ),
		);
	}

	/**
	 * Site logo from the theme's Custom Logo, '' when none.
	 */
	public static function logo_url(): string {
		$id  = (int) get_theme_mod( 'custom_logo' );
		$src = $id ? wp_get_attachment_image_src( $id, 'medium' ) : false;
		return $src ? (string) $src[0] : '';
	}

	/**
	 * HTML body for a structured message.
	 */
	public static function render_html( array $m ): string {
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$logo  = self::logo_url();
		$logo  = $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site ) . '" style="max-height:60px;max-width:220px;height:auto;display:block;margin:0 0 20px">' : '';
		$intro = trim( (string) ( $m['intro'] ?? '' ) );
		$intro = $intro ? wpautop( esc_html( $intro ) ) : '';

		$table = '';
		foreach ( (array) ( $m['rows'] ?? array() ) as $row ) {
			$style  = ! empty( $row[2] ) ? 'font-size:22px;font-weight:700;' : 'font-size:16px;';
			$table .= '<tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#4b5563;font-size:14px">' . esc_html( $row[0] ) . '</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;' . $style . '">' . esc_html( $row[1] ) . '</td></tr>';
		}
		$table = $table ? '<table role="presentation" style="width:100%;border-collapse:collapse;margin-top:12px">' . $table . '</table>' : '';

		$cta = '';
		if ( ! empty( $m['cta'][0] ) && ! empty( $m['cta'][1] ) ) {
			$cta = '<p style="margin:28px 0 0"><a href="' . esc_url( $m['cta'][1] ) . '" style="display:inline-block;text-decoration:none;font-weight:600;padding:12px 22px;' . self::button_style() . '">' . esc_html( $m['cta'][0] ) . '</a></p>';
		}
		$sections = '';
		foreach ( (array) ( $m['sections'] ?? array() ) as $sec ) {
			$sections .= self::render_section( (array) $sec );
		}
		$note = ! empty( $m['note'] ) ? '<p style="margin:28px 0 0;font-size:12px;color:#9ca3af">' . esc_html( $m['note'] ) . '</p>' : '';
		if ( Settings::get( 'credit_link' ) && 'owner' !== ( $m['context'] ?? '' ) ) {
			$note .= '<p style="margin:8px 0 0;font-size:12px;color:#9ca3af">' . esc_html__( 'Sent with', 'ymove-nutrition' ) . ' <a href="' . esc_url( ymove_url( 'nutrition-api/wordpress-plugin/', array( 'utm_medium' => 'email-footer' ) ) ) . '" style="color:#9ca3af">' . esc_html__( 'Your Move Nutrition for WordPress', 'ymove-nutrition' ) . '</a></p>';
		}

		$html = '<div style="background:#f3f4f6;padding:32px 16px;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#111827">
			<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:32px">'
				. $logo
				. '<h1 style="font-size:22px;margin:0 0 12px">' . esc_html( $m['heading'] ?? $site ) . '</h1>'
				. $intro . $table . $sections . $cta . $note
			. '</div></div>';

		/**
		 * Filter the HTML of an email sent by the plugin.
		 *
		 * @param string $html
		 * @param array  $message The structured message.
		 */
		return (string) apply_filters( 'ymove_email_html', $html, $m );
	}

	/**
	 * One content block: an optional day heading, or a recipe with image,
	 * meta line, description and lists (ingredients, method).
	 */
	private static function render_section( array $s ): string {
		if ( empty( $s['title'] ) && ! empty( $s['heading'] ) ) {
			return '<h2 style="font-size:18px;margin:32px 0 4px;padding-top:16px;border-top:2px solid #111827">' . esc_html( $s['heading'] ) . '</h2>'
				. ( ! empty( $s['meta'] ) ? '<p style="margin:0;color:#6b7280;font-size:13px">' . esc_html( $s['meta'] ) . '</p>' : '' );
		}
		$out = '<div style="margin:24px 0 0;padding-top:20px;border-top:1px solid #e5e7eb">';
		if ( ! empty( $s['image'] ) ) {
			$out .= '<img src="' . esc_url( $s['image'] ) . '" alt="" width="456" style="width:100%;max-width:456px;height:auto;border-radius:12px;display:block;margin:0 0 12px">';
		}
		if ( ! empty( $s['kicker'] ) ) {
			$out .= '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b7280">' . esc_html( $s['kicker'] ) . '</div>';
		}
		$out .= '<h3 style="font-size:17px;margin:2px 0 4px">' . esc_html( (string) ( $s['title'] ?? '' ) ) . '</h3>';
		if ( ! empty( $s['meta'] ) ) {
			$out .= '<p style="margin:0 0 8px;color:#6b7280;font-size:13px">' . esc_html( $s['meta'] ) . '</p>';
		}
		if ( ! empty( $s['text'] ) ) {
			$out .= '<p style="margin:0 0 8px;font-size:14px;line-height:1.5">' . esc_html( $s['text'] ) . '</p>';
		}
		foreach ( (array) ( $s['lists'] ?? array() ) as $list ) {
			$tag  = ! empty( $list['ordered'] ) ? 'ol' : 'ul';
			$out .= '<p style="margin:12px 0 4px;font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#6b7280">' . esc_html( (string) ( $list['heading'] ?? '' ) ) . '</p>';
			$out .= '<' . $tag . ' style="margin:0;padding-left:20px;font-size:14px;line-height:1.55">';
			foreach ( (array) ( $list['items'] ?? array() ) as $item ) {
				$out .= '<li style="margin:0 0 4px">' . esc_html( (string) $item ) . '</li>';
			}
			$out .= '</' . $tag . '>';
		}
		return $out . '</div>';
	}

	/**
	 * Inline CSS for the email button, following the site-wide calculator
	 * style and accent colour. Gradients get a solid fallback for Outlook.
	 */
	private static function button_style(): string {
		$theme  = resolve_theme( '' );
		$accent = sanitize_hex_color( (string) Settings::get( 'accent_color', '' ) );
		switch ( $theme ) {
			case 'minimal':
				return 'background:#111;color:#fff;border-radius:0;letter-spacing:.08em;text-transform:uppercase;font-size:13px';
			case 'gradient':
				$pals = gradient_palettes();
				$pal  = $pals[ (string) Settings::get( 'gradient_palette', 'glacier' ) ] ?? $pals['glacier'];
				return 'background:' . $pal[1] . ';background-image:linear-gradient(90deg,' . $pal[1] . ',' . $pal[2] . ',' . $pal[3] . ');color:#fff;border-radius:999px';
			case 'brutalist':
				$bg = $accent ?: '#c6ff3d';
				return 'background:' . $bg . ';color:' . ink_for( $bg ) . ';border:3px solid #111;border-radius:0;box-shadow:4px 4px 0 #111;text-transform:uppercase';
			case 'ios':
				$bg = $accent ?: '#007aff';
				return 'background:' . $bg . ';color:' . ink_for( $bg ) . ';border-radius:12px';
			default:
				$bg = $accent ?: '#2563eb';
				return 'background:' . $bg . ';color:' . ink_for( $bg ) . ';border-radius:999px';
		}
	}

	/* ------------------------------------------------------------------ Log */

	/**
	 * Keep the last 20 sends (no message bodies) for the settings screen.
	 */
	private static function log( array $m, array $result ): void {
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array(
			'time'    => time(),
			'context' => (string) ( $m['context'] ?? '' ),
			'to'      => self::mask( (string) $m['to'] ),
			'via'     => $result['via'],
			'ok'      => $result['ok'] ? 1 : 0,
			'error'   => substr( (string) $result['error'], 0, 300 ),
		) );
		update_option( self::LOG_OPTION, array_slice( $log, 0, 20 ), false );
	}

	public static function recent(): array {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	private static function mask( string $email ): string {
		$at = strpos( $email, '@' );
		return false === $at ? '' : substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}
}
