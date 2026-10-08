<?php
/**
 * Server-side client for the Your Move Nutrition API. The API key never
 * leaves PHP; every browser request goes through our REST proxy.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Client {

	const CACHE_BARCODE = 30 * DAY_IN_SECONDS;
	const CACHE_SEARCH  = DAY_IN_SECONDS;
	const CACHE_USAGE   = 5 * MINUTE_IN_SECONDS;

	public static function is_connected(): bool {
		return '' !== Settings::api_key();
	}

	/* ------------------------------------------------------------ Endpoints */

	/**
	 * GET /foods?query=
	 */
	public static function search( string $query, array $opts = array() ) {
		$query = trim( $query );
		if ( strlen( $query ) < 2 ) {
			return array( 'data' => array(), 'pagination' => array() );
		}
		$params = array(
			'query'    => $query,
			'country'  => $opts['country'] ?? default_country(),
			'page'     => max( 1, (int) ( $opts['page'] ?? 1 ) ),
			'pageSize' => min( 50, max( 1, (int) ( $opts['pageSize'] ?? 20 ) ) ),
		);
		if ( ! empty( $opts['usdaOnly'] ) ) {
			$params['usdaOnly'] = 'true';
		}
		$cache_key = 'ymn_s_' . md5( wp_json_encode( $params ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = self::request( 'GET', '/foods', $params );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		self::count( 'search' );
		set_transient( $cache_key, $res, self::CACHE_SEARCH );
		foreach ( $res['data'] ?? array() as $food ) {
			DB::cache_food( $food );
		}
		return $res;
	}

	/**
	 * GET /foods/{id} - local cache first, it never changes.
	 */
	public static function food( string $id ) {
		$id = sanitize_text_field( $id );
		if ( '' === $id ) {
			return new WP_Error( 'ymn_bad_id', __( 'Missing food id.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		$cached = DB::cached_food( $id );
		if ( $cached ) {
			return array( 'data' => $cached );
		}
		$res = self::request( 'GET', '/foods/' . rawurlencode( $id ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		self::count( 'food' );
		if ( ! empty( $res['data'] ) ) {
			DB::cache_food( $res['data'] );
		}
		return $res;
	}

	/**
	 * GET /foods/barcode/{upc}
	 */
	public static function barcode( string $upc, string $country = '' ) {
		$upc = preg_replace( '/\D+/', '', $upc );
		if ( strlen( $upc ) < 6 || strlen( $upc ) > 14 ) {
			return new WP_Error( 'ymn_bad_upc', __( 'That does not look like a barcode.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		$country   = $country ?: default_country();
		$cache_key = 'ymn_b_' . $upc . '_' . $country;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = self::request( 'GET', '/foods/barcode/' . $upc, array( 'country' => $country ) );
		if ( is_wp_error( $res ) ) {
			if ( 404 === (int) ( $res->get_error_data()['status'] ?? 0 ) ) {
				// Negative cache for a day so repeated scans of an unknown product cost nothing.
				set_transient( $cache_key . '_404', 1, DAY_IN_SECONDS );
			}
			return $res;
		}
		self::count( 'barcode' );
		set_transient( $cache_key, $res, self::CACHE_BARCODE );
		if ( ! empty( $res['data'] ) ) {
			DB::cache_food( $res['data'] );
		}
		return $res;
	}

	public static function barcode_known_missing( string $upc, string $country = '' ): bool {
		$upc = preg_replace( '/\D+/', '', $upc );
		return (bool) get_transient( 'ymn_b_' . $upc . '_' . ( $country ?: default_country() ) . '_404' );
	}

	/**
	 * POST /foods/log/photo (Pro plan and above).
	 */
	public static function analyze_photo( string $base64, string $media_type = 'image/jpeg' ) {
		$res = self::request(
			'POST',
			'/foods/log/photo',
			array(),
			array( 'image' => $base64, 'media_type' => $media_type ),
			90
		);
		if ( ! is_wp_error( $res ) ) {
			self::count( 'photo' );
		}
		return $res;
	}

	/**
	 * POST /foods/log/text (Pro plan and above).
	 */
	public static function analyze_text( string $text ) {
		$res = self::request( 'POST', '/foods/log/text', array(), array( 'text' => $text ), 60 );
		if ( ! is_wp_error( $res ) ) {
			self::count( 'text' );
		}
		return $res;
	}

	/**
	 * GET /mealplans/generate. Cached per parameter set so a member flipping
	 * between diets does not pay twice for the same plan.
	 */
	public static function meal_plan( array $p ) {
		$params = array(
			'calories'   => max( 800, min( 8000, (int) ( $p['calories'] ?? 2000 ) ) ),
			'diet'       => in_array( $p['diet'] ?? '', self::DIETS, true ) ? $p['diet'] : 'balanced',
			'meals'      => max( 3, min( 6, (int) ( $p['meals'] ?? 3 ) ) ),
			'days'       => max( 1, min( 7, (int) ( $p['days'] ?? 1 ) ) ),
			'macroSplit' => in_array( $p['macroSplit'] ?? '', self::SPLITS, true ) ? $p['macroSplit'] : 'balanced',
		);
		$cache_key = 'ymn_mp_' . md5( wp_json_encode( $params ) );
		if ( empty( $p['fresh'] ) ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$res = self::request( 'GET', '/mealplans/generate', $params, null, 60 );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		self::count( 'mealplan' );
		set_transient( $cache_key, $res, 6 * HOUR_IN_SECONDS );
		return $res;
	}

	/**
	 * GET /recipes/search - used to swap one meal in a plan. Cached a day.
	 */
	public static function recipe_search( array $params ) {
		$cache_key = 'ymn_rs_' . md5( wp_json_encode( $params ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = self::request( 'GET', '/recipes/search', $params );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		self::count( 'recipe' );
		set_transient( $cache_key, $res, DAY_IN_SECONDS );
		return $res;
	}

	/**
	 * GET /recipes/{id} with ingredients and method. Cached a week.
	 */
	public static function recipe( string $id ) {
		$id        = sanitize_text_field( $id );
		$cache_key = 'ymn_r_' . md5( $id );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = self::request( 'GET', '/recipes/' . rawurlencode( $id ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		self::count( 'recipe' );
		set_transient( $cache_key, $res, WEEK_IN_SECONDS );
		return $res;
	}


	const DIETS  = array( 'balanced', 'high_protein', 'low_carb', 'keto', 'vegan', 'vegetarian', 'mediterranean', 'paleo' );
	const SPLITS = array( 'balanced', 'high_protein', 'low_carb', 'high_fat' );

	/**
	 * GET /usage - cached briefly; the admin screen polls it.
	 */
	public static function usage( bool $fresh = false ) {
		if ( ! $fresh ) {
			$cached = get_transient( 'ymove_nutrition_usage' );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$res = self::request( 'GET', '/usage' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		set_transient( 'ymove_nutrition_usage', $res, self::CACHE_USAGE );
		return $res;
	}


	/* -------------------------------------------------------------- Transport */

	/**
	 * Perform a request. Returns the decoded JSON array or a WP_Error whose
	 * data carries the upstream HTTP status and, for 403 upgrades, the URL.
	 */
	private static function request( string $method, string $path, array $query = array(), ?array $body = null, int $timeout = 20 ) {
		$key = Settings::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'ymn_no_key', __( 'No Your Move API key is configured.', 'ymove-nutrition' ), array( 'status' => 503 ) );
		}

		$url = YMOVE_NUTRITION_API_BASE . $path;
		if ( $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_filter( $query, fn( $v ) => '' !== $v && null !== $v ) ), $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array(
				'X-API-Key'  => $key,
				'Accept'     => 'application/json',
				'User-Agent' => self::user_agent(),
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ymn_transport', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$json   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) ) {
			$json = array();
		}

		if ( $status >= 200 && $status < 300 ) {
			return $json;
		}

		$message = $json['message'] ?? $json['error'] ?? sprintf( 'HTTP %d', $status );
		$data    = array( 'status' => $status );
		if ( ! empty( $json['upgradeUrl'] ) ) {
			$data['upgradeUrl'] = $json['upgradeUrl'];
		}
		$code = 'ymn_upstream';
		if ( 401 === $status ) {
			$code = 'ymn_bad_key';
		} elseif ( 403 === $status ) {
			$code = 'ymn_plan';
		} elseif ( 404 === $status ) {
			$code = 'ymn_not_found';
		} elseif ( 429 === $status ) {
			$code = 'ymn_rate_limited';
		}
		return new WP_Error( $code, (string) $message, $data );
	}

	private static function user_agent(): string {
		global $wp_version;
		return sprintf(
			'ymove-wordpress/%s (WP %s; PHP %s; site %s)',
			YMOVE_NUTRITION_VERSION,
			$wp_version,
			PHP_VERSION,
			substr( md5( home_url() ), 0, 12 )
		);
	}

	/* ------------------------------------------------------- Local counters */

	/**
	 * Increment the local per-day counter shown on the Usage screen.
	 */
	private static function count( string $type ): void {
		$counters = get_option( 'ymove_nutrition_counters', array() );
		if ( ! is_array( $counters ) ) {
			$counters = array();
		}
		$day = gmdate( 'Y-m-d' );
		if ( ! isset( $counters[ $day ] ) ) {
			$counters[ $day ] = array();
			// Keep 45 days.
			ksort( $counters );
			while ( count( $counters ) > 45 ) {
				array_shift( $counters );
			}
		}
		$counters[ $day ][ $type ] = ( $counters[ $day ][ $type ] ?? 0 ) + 1;
		update_option( 'ymove_nutrition_counters', $counters, false );
	}

	public static function counters(): array {
		$c = get_option( 'ymove_nutrition_counters', array() );
		return is_array( $c ) ? $c : array();
	}
}
