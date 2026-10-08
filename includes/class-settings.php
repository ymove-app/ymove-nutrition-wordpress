<?php
/**
 * Options storage. One serialized option, plus the API key kept separately
 * and obfuscated so it is not readable in a plain database dump.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const OPTION     = 'ymove_nutrition_settings';
	const KEY_OPTION = 'ymove_nutrition_api_key';

	/**
	 * Default activity multipliers (Mifflin / Harris convention).
	 */
	public static function default_activities(): array {
		return array(
			array( 'label' => __( 'Sedentary (desk job, little exercise)', 'ymove-nutrition' ), 'factor' => 1.2 ),
			array( 'label' => __( 'Lightly active (1-3 workouts a week)', 'ymove-nutrition' ), 'factor' => 1.375 ),
			array( 'label' => __( 'Moderately active (3-5 workouts a week)', 'ymove-nutrition' ), 'factor' => 1.55 ),
			array( 'label' => __( 'Very active (6-7 workouts a week)', 'ymove-nutrition' ), 'factor' => 1.725 ),
			array( 'label' => __( 'Extremely active (physical job or twice daily training)', 'ymove-nutrition' ), 'factor' => 1.9 ),
		);
	}

	/**
	 * Default goals: kcal/day added to TDEE.
	 */
	public static function default_goals(): array {
		return array(
			array( 'key' => 'lose', 'label' => __( 'Lose weight (about 0.5 kg / 1 lb a week)', 'ymove-nutrition' ), 'delta' => -500, 'enabled' => 1 ),
			array( 'key' => 'lose_slow', 'label' => __( 'Lose weight slowly (about 0.25 kg / 0.5 lb a week)', 'ymove-nutrition' ), 'delta' => -250, 'enabled' => 1 ),
			array( 'key' => 'maintain', 'label' => __( 'Maintain weight', 'ymove-nutrition' ), 'delta' => 0, 'enabled' => 1 ),
			array( 'key' => 'gain_slow', 'label' => __( 'Gain weight slowly', 'ymove-nutrition' ), 'delta' => 250, 'enabled' => 1 ),
			array( 'key' => 'gain', 'label' => __( 'Gain weight', 'ymove-nutrition' ), 'delta' => 500, 'enabled' => 1 ),
		);
	}

	public static function activities(): array {
		$saved = self::get( 'activity_levels' );
		return is_array( $saved ) && $saved ? $saved : self::default_activities();
	}

	public static function goals(): array {
		$saved = self::get( 'goals' );
		return is_array( $saved ) && $saved ? $saved : self::default_goals();
	}

	/**
	 * Defaults for every setting the plugin reads.
	 */
	public static function defaults(): array {
		return array(
			'activity_levels'      => array(),
			'goals'                => array(),
			'captcha_provider'     => '',
			'captcha_site_key'     => '',
			'captcha_secret'       => '',
			'country'              => '',
			'units'                => '',
			'widget_theme'         => 'classic',
			'theme_fonts'          => 1, // Bundled locally, so on by default.
			'gradient_palette'     => 'glacier',
			'accent_color'         => '',
			'source_links'       => 0, // Opt-in: wordpress.org guideline 10.
			'credit_link'          => 0,
			'tracker_access'       => 'logged_in', // logged_in | roles
			'tracker_roles'        => array(),
			'limit_search_hour'    => 60,
			'limit_barcode_hour'   => 30,
			'limit_photo_day'      => 10,
			'limit_text_day'       => 20,
			'limit_mealplan_day'   => 5,
			'mealplan_public'      => 0,
			'limit_mealplan_ip_day' => 3,
			'mealplan_delivery'    => 'both',
			'recipes_public'       => 1,
			'limit_recipes_ip_day' => 200,
			'lead_notify_email'    => '',
			'lead_subject'         => '',
			'lead_intro'           => '',
			'lead_cta_label'       => '',
			'lead_cta_url'         => '',
			'lead_consent_text'    => '',
			'lead_webhook'         => '',
			'retention_days'       => 0,
			'delete_on_uninstall'  => 0,
		);
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	public static function get( string $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}


	/**
	 * Sanitize the settings array coming from the admin form.
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = self::all();

		$country        = strtoupper( sanitize_text_field( $input['country'] ?? '' ) );
		$out['country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';

		$units        = sanitize_text_field( $input['units'] ?? '' );
		$out['units'] = in_array( $units, array( 'metric', 'imperial' ), true ) ? $units : '';

		$theme               = sanitize_key( $input['widget_theme'] ?? '' );
		$out['widget_theme'] = array_key_exists( $theme, widget_themes() ) ? $theme : 'classic';
		$out['theme_fonts']  = empty( $input['theme_fonts'] ) ? 0 : 1;

		$palette                 = sanitize_key( $input['gradient_palette'] ?? '' );
		$out['gradient_palette'] = array_key_exists( $palette, gradient_palettes() ) ? $palette : 'glacier';
		$out['accent_color']     = (string) sanitize_hex_color( (string) ( $input['accent_color'] ?? '' ) );

		$out['source_links']    = empty( $input['source_links'] ) ? 0 : 1;
		$out['mealplan_public'] = empty( $input['mealplan_public'] ) ? 0 : 1;
		$out['recipes_public']  = empty( $input['recipes_public'] ) ? 0 : 1;
		$delivery                 = sanitize_key( $input['mealplan_delivery'] ?? 'both' );
		$out['mealplan_delivery'] = in_array( $delivery, array( 'off', 'email', 'pdf', 'both' ), true ) ? $delivery : 'both';
		$out['credit_link']  = empty( $input['credit_link'] ) ? 0 : 1;

		$access                = sanitize_text_field( $input['tracker_access'] ?? 'logged_in' );
		$out['tracker_access'] = in_array( $access, array( 'logged_in', 'roles' ), true ) ? $access : 'logged_in';

		$roles = isset( $input['tracker_roles'] ) && is_array( $input['tracker_roles'] ) ? $input['tracker_roles'] : array();
		$valid = array_keys( wp_roles()->roles );
		$out['tracker_roles'] = array_values( array_intersect( array_map( 'sanitize_key', $roles ), $valid ) );

		foreach ( array( 'limit_search_hour', 'limit_barcode_hour', 'limit_photo_day', 'limit_text_day', 'limit_mealplan_day', 'limit_mealplan_ip_day', 'limit_recipes_ip_day', 'retention_days' ) as $k ) {
			$out[ $k ] = max( 0, (int) ( $input[ $k ] ?? $out[ $k ] ) );
		}

		$out['lead_notify_email']   = sanitize_email( $input['lead_notify_email'] ?? '' );
		$out['lead_subject']        = sanitize_text_field( $input['lead_subject'] ?? '' );
		$out['lead_intro']          = sanitize_textarea_field( $input['lead_intro'] ?? '' );
		$out['lead_cta_label']      = sanitize_text_field( $input['lead_cta_label'] ?? '' );
		$out['lead_cta_url']        = esc_url_raw( $input['lead_cta_url'] ?? '' );
		$out['lead_consent_text']   = sanitize_text_field( $input['lead_consent_text'] ?? '' );
		$out['lead_webhook']        = esc_url_raw( $input['lead_webhook'] ?? '' );

		// Activity multipliers: keep rows with a label and a sane factor.
		$acts = array();
		foreach ( (array) ( $input['activity_levels'] ?? array() ) as $row ) {
			$label  = sanitize_text_field( $row['label'] ?? '' );
			$factor = (float) ( $row['factor'] ?? 0 );
			if ( '' !== $label && $factor >= 1 && $factor <= 2.5 ) {
				$acts[] = array( 'label' => $label, 'factor' => round( $factor, 3 ) );
			}
		}
		$out['activity_levels'] = $acts ?: array();

		// Goals: fixed keys, editable label / delta / enabled.
		$goals = array();
		foreach ( self::default_goals() as $i => $def ) {
			$row     = (array) ( $input['goals'][ $i ] ?? array() );
			$goals[] = array(
				'key'     => $def['key'],
				'label'   => sanitize_text_field( $row['label'] ?? '' ) ?: $def['label'],
				'delta'   => max( -1500, min( 1500, (int) ( $row['delta'] ?? $def['delta'] ) ) ),
				'enabled' => empty( $row['enabled'] ) ? 0 : 1,
			);
		}
		if ( ! array_filter( array_column( $goals, 'enabled' ) ) ) {
			$goals[2]['enabled'] = 1; // Never let the owner disable every goal.
		}
		$out['goals'] = $goals;

		$provider                 = sanitize_key( $input['captcha_provider'] ?? '' );
		$out['captcha_provider']  = in_array( $provider, array( 'recaptcha', 'turnstile' ), true ) ? $provider : '';
		$out['captcha_site_key']  = sanitize_text_field( $input['captcha_site_key'] ?? '' );
		if ( isset( $input['captcha_secret'] ) && '' !== trim( (string) $input['captcha_secret'] ) ) {
			$out['captcha_secret'] = sanitize_text_field( $input['captcha_secret'] );
		}
		$out['delete_on_uninstall'] = empty( $input['delete_on_uninstall'] ) ? 0 : 1;

		// API key travels in the same form but lives in its own option.
		if ( isset( $input['api_key'] ) ) {
			$key = trim( sanitize_text_field( $input['api_key'] ) );
			if ( '' === $key ) {
				// Empty field keeps the existing key; use the explicit clear box to remove.
			} elseif ( false === strpos( $key, '****' ) ) {
				self::set_api_key( $key );
			}
		}
		if ( ! empty( $input['clear_api_key'] ) ) {
			delete_option( self::KEY_OPTION );
			delete_transient( 'ymove_nutrition_usage' );
		}

		return $out;
	}

	/**
	 * The API key: wp-config constant wins, then the stored (obfuscated) option.
	 */
	public static function api_key(): string {
		if ( defined( 'YMOVE_API_KEY' ) && YMOVE_API_KEY ) {
			return (string) YMOVE_API_KEY;
		}
		$stored = get_option( self::KEY_OPTION, '' );
		if ( ! $stored ) {
			return '';
		}
		return self::unobfuscate( (string) $stored );
	}

	public static function has_constant_key(): bool {
		return defined( 'YMOVE_API_KEY' ) && (bool) YMOVE_API_KEY;
	}

	public static function set_api_key( string $key ): void {
		update_option( self::KEY_OPTION, self::obfuscate( $key ), false );
		delete_transient( 'ymove_nutrition_usage' );
	}

	/**
	 * Masked key for display: first 6 and last 4 characters.
	 */
	public static function masked_key(): string {
		$key = self::api_key();
		if ( strlen( $key ) < 12 ) {
			return $key ? '****' : '';
		}
		return substr( $key, 0, 6 ) . '****' . substr( $key, -4 );
	}

	/**
	 * Symmetric obfuscation keyed on the site's auth salt. This is not a
	 * substitute for server security; it only keeps the key out of casual
	 * reads of the options table.
	 */
	private static function obfuscate( string $plain ): string {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$key = hash( 'sha256', wp_salt( 'auth' ), true );
			$iv  = random_bytes( 16 );
			$enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $enc ) {
				return 'enc:' . base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}
		return 'b64:' . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private static function unobfuscate( string $stored ): string {
		if ( 0 === strpos( $stored, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) < 17 ) {
				return '';
			}
			$key = hash( 'sha256', wp_salt( 'auth' ), true );
			$dec = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
			return false === $dec ? '' : $dec;
		}
		if ( 0 === strpos( $stored, 'b64:' ) ) {
			$dec = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return false === $dec ? '' : $dec;
		}
		return $stored;
	}
}
