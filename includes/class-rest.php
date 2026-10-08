<?php
/**
 * REST routes under /wp-json/ymove/v1. Browser code only ever talks to
 * these; they proxy to the Your Move API with the server-side key.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class REST {

	const NS = 'ymove/v1';

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes(): void {
		$track = array( __CLASS__, 'can_track' );
		$edit  = array( __CLASS__, 'can_track_or_edit' );
		$admin = fn() => current_user_can( 'manage_options' );

		// Food data (proxied).
		register_rest_route( self::NS, '/foods/search', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'search' ),
			'permission_callback' => $edit,
			'args'                => array(
				'q'        => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
				'usdaOnly' => array( 'default' => false ),
			),
		) );
		register_rest_route( self::NS, '/foods/barcode/(?P<upc>[0-9]{6,14})', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'barcode' ),
			'permission_callback' => $edit,
		) );

		// AI logging (Pro).
		register_rest_route( self::NS, '/log/photo', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'log_photo' ),
			'permission_callback' => $track,
		) );
		register_rest_route( self::NS, '/log/text', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'log_text' ),
			'permission_callback' => $track,
			'args'                => array( 'text' => array( 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ) ),
		) );

		// Diary.
		register_rest_route( self::NS, '/diary', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'diary_day' ),
				'permission_callback' => $track,
				'args'                => array( 'date' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'diary_add' ),
				'permission_callback' => $track,
			),
		) );
		register_rest_route( self::NS, '/diary/(?P<id>\d+)', array(
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'diary_update' ),
				'permission_callback' => $track,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'diary_delete' ),
				'permission_callback' => $track,
			),
		) );
		register_rest_route( self::NS, '/diary/week', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'diary_week' ),
			'permission_callback' => $track,
			'args'                => array( 'to' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
		) );
		register_rest_route( self::NS, '/diary/recent', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => fn() => rest_ensure_response( array( 'data' => DB::recent_foods( get_current_user_id() ) ) ),
			'permission_callback' => $track,
		) );
		register_rest_route( self::NS, '/diary/all', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => fn() => rest_ensure_response( array( 'data' => DB::all_entries( get_current_user_id() ) ) ),
			'permission_callback' => $track,
		) );
		register_rest_route( self::NS, '/targets', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response( array( 'data' => DB::get_targets( get_current_user_id() ) ) ),
				'permission_callback' => $track,
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'targets_set' ),
				'permission_callback' => $track,
			),
		) );

		// Meal plan generator (members, or visitors when the owner opens it).
		$plan_access = fn() => self::can_track() || (bool) Settings::get( 'mealplan_public', 0 );
		register_rest_route( self::NS, '/mealplan', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'meal_plan' ),
			'permission_callback' => $plan_access,
		) );

		register_rest_route( self::NS, '/mealplan/swap', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'meal_plan_swap' ),
			'permission_callback' => $plan_access,
		) );
		register_rest_route( self::NS, '/mealplan/email', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'meal_plan_email' ),
			'permission_callback' => fn() => $plan_access() && in_array( Meal_Plans::delivery(), array( 'email', 'both' ), true ),
		) );

		// Recipes (visitors too, unless the owner closed it; editors for the block picker).
		$recipes = fn() => Recipes::visitor_can_browse() || current_user_can( 'edit_posts' );
		register_rest_route( self::NS, '/recipes', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'recipes' ),
			'permission_callback' => $recipes,
		) );
		register_rest_route( self::NS, '/recipes/(?P<slug>[A-Za-z0-9_\-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'recipe' ),
			'permission_callback' => $recipes,
		) );

		// Calculator lead capture (public).
		register_rest_route( self::NS, '/leads', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'lead' ),
			'permission_callback' => '__return_true',
		) );

		// Admin.
		register_rest_route( self::NS, '/admin/usage', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'admin_usage' ),
			'permission_callback' => $admin,
		) );
		register_rest_route( self::NS, '/admin/member/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'admin_member' ),
			'permission_callback' => fn() => current_user_can( 'ymove_view_member_logs' ) || current_user_can( 'manage_options' ),
		) );
	}

	/* ---------------------------------------------------------- Permissions */

	public static function can_track(): bool {
		return is_user_logged_in() && Access::user_can_track();
	}

	/**
	 * Editors need food search inside the block editor for the Nutrition
	 * Facts block, even when they are not tracker users.
	 */
	public static function can_track_or_edit(): bool {
		return self::can_track() || current_user_can( 'edit_posts' );
	}

	/* ----------------------------------------------------------- Food proxy */

	public static function search( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		if ( ! Access::consume( 'search' ) ) {
			return self::throttled();
		}
		$res = Api_Client::search( (string) $req['q'], array(
			'page'     => (int) $req['page'],
			'usdaOnly' => filter_var( $req['usdaOnly'], FILTER_VALIDATE_BOOLEAN ),
		) );
		return self::respond( $res );
	}

	public static function barcode( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		$upc = (string) $req['upc'];
		if ( Api_Client::barcode_known_missing( $upc ) ) {
			return new WP_Error( 'ymn_not_found', __( 'Product not found.', 'ymove-nutrition' ), array( 'status' => 404 ) );
		}
		if ( ! Access::consume( 'barcode' ) ) {
			return self::throttled();
		}
		return self::respond( Api_Client::barcode( $upc ) );
	}

	/* ------------------------------------------------------------ AI logging */

	public static function log_photo( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		$body  = $req->get_json_params();
		$image = (string) ( $body['image'] ?? '' );
		$type  = (string) ( $body['media_type'] ?? 'image/jpeg' );
		if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			$type = 'image/jpeg';
		}
		// Strip a data: prefix if the client sent one.
		if ( preg_match( '#^data:image/[a-z]+;base64,#i', $image ) ) {
			$image = preg_replace( '#^data:image/[a-z]+;base64,#i', '', $image );
		}
		if ( strlen( $image ) < 100 || strlen( $image ) > 2.8 * 1024 * 1024 ) {
			return new WP_Error( 'ymn_bad_image', __( 'Image missing or too large (max 2 MB).', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		if ( ! Access::consume( 'photo' ) ) {
			return self::throttled();
		}
		return self::respond( Api_Client::analyze_photo( $image, $type ) );
	}

	public static function log_text( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		$text = trim( (string) $req['text'] );
		if ( strlen( $text ) < 3 ) {
			return new WP_Error( 'ymn_bad_text', __( 'Describe the meal in a few words.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		if ( ! Access::consume( 'text' ) ) {
			return self::throttled();
		}
		return self::respond( Api_Client::analyze_text( mb_substr( $text, 0, 2000 ) ) );
	}

	/* ----------------------------------------------------------------- Diary */

	public static function diary_day( WP_REST_Request $req ) {
		$day     = self::valid_day( (string) $req['date'] );
		$user_id = get_current_user_id();
		$entries = DB::entries_for_day( $user_id, $day );
		return rest_ensure_response( array(
			'date'    => $day,
			'entries' => $entries,
			'totals'  => self::totals( $entries ),
			'targets' => DB::get_targets( $user_id ),
		) );
	}

	public static function diary_add( WP_REST_Request $req ) {
		$b       = $req->get_json_params();
		$user_id = get_current_user_id();
		$day     = self::valid_day( (string) ( $b['date'] ?? '' ) );
		$meal    = in_array( $b['meal'] ?? '', array( 'breakfast', 'lunch', 'dinner', 'snack' ), true ) ? $b['meal'] : 'snack';
		$source  = in_array( $b['source'] ?? '', array( 'search', 'barcode', 'photo', 'text', 'recent', 'manual', 'mealplan', 'recipe' ), true ) ? $b['source'] : 'search';
		$qty     = max( 0.01, min( 99, (float) ( $b['quantity'] ?? 1 ) ) );
		$food_id = sanitize_text_field( (string) ( $b['foodId'] ?? '' ) );

		// Per-serving nutrition. Prefer our cached copy of the food so a
		// tampered client cannot log fantasy numbers against a real food id.
		$per = null;
		if ( $food_id ) {
			$per = DB::cached_food( $food_id );
		}
		if ( ! $per ) {
			$per = array(
				'displayName' => sanitize_text_field( (string) ( $b['displayName'] ?? '' ) ),
				'brand'       => sanitize_text_field( (string) ( $b['brand'] ?? '' ) ),
				'servingSize' => (float) ( $b['servingG'] ?? 0 ),
				'calories'    => (float) ( $b['calories'] ?? 0 ),
				'protein'     => (float) ( $b['protein'] ?? 0 ),
				'carbs'       => (float) ( $b['carbs'] ?? 0 ),
				'fat'         => (float) ( $b['fat'] ?? 0 ),
				'fiber'       => isset( $b['fiber'] ) ? (float) $b['fiber'] : null,
				'sugar'       => isset( $b['sugar'] ) ? (float) $b['sugar'] : null,
				'sodium'      => isset( $b['sodium'] ) ? (float) $b['sodium'] : null,
			);
		}
		$name = ( $per['displayName'] ?? '' ) ?: ( ( $per['shortName'] ?? '' ) ?: ( $per['name'] ?? '' ) );
		if ( '' === trim( (string) $name ) ) {
			return new WP_Error( 'ymn_bad_entry', __( 'Missing food name.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}

		// Manual gram override: scale by grams / servingSize instead of quantity.
		$serving_g = (float) ( $per['servingSize'] ?? 0 );
		if ( ! empty( $b['grams'] ) && $serving_g > 0 ) {
			$qty = max( 0.01, (float) $b['grams'] / $serving_g );
		}

		$mul = fn( $v ) => null === $v ? null : round( (float) $v * $qty, 2 );
		$id  = DB::insert_entry( $user_id, array(
			'logged_on'    => $day,
			'meal'         => $meal,
			'food_id'      => $food_id ?: null,
			'source'       => $source,
			'display_name' => mb_substr( (string) $name, 0, 255 ),
			'brand'        => $per['brand'] ?? null,
			'serving_g'    => $serving_g,
			'quantity'     => round( $qty, 2 ),
			'kcal'         => $mul( $per['calories'] ?? 0 ),
			'protein_g'    => $mul( $per['protein'] ?? 0 ),
			'carbs_g'      => $mul( $per['carbs'] ?? 0 ),
			'fat_g'        => $mul( $per['fat'] ?? 0 ),
			'fiber_g'      => $mul( $per['fiber'] ?? null ),
			'sugar_g'      => $mul( $per['sugar'] ?? null ),
			'sodium_mg'    => $mul( $per['sodium'] ?? null ),
		) );

		return rest_ensure_response( array( 'data' => DB::get_entry( $user_id, $id ) ) );
	}

	public static function diary_update( WP_REST_Request $req ) {
		$user_id = get_current_user_id();
		$id      = (int) $req['id'];
		$current = DB::get_entry( $user_id, $id );
		if ( ! $current ) {
			return new WP_Error( 'ymn_not_found', __( 'Entry not found.', 'ymove-nutrition' ), array( 'status' => 404 ) );
		}
		$b      = $req->get_json_params();
		$fields = array();
		if ( isset( $b['meal'] ) && in_array( $b['meal'], array( 'breakfast', 'lunch', 'dinner', 'snack' ), true ) ) {
			$fields['meal'] = $b['meal'];
		}
		if ( isset( $b['date'] ) ) {
			$fields['logged_on'] = self::valid_day( (string) $b['date'] );
		}
		if ( isset( $b['quantity'] ) ) {
			$new_q = max( 0.01, min( 99, (float) $b['quantity'] ) );
			$old_q = max( 0.01, $current['quantity'] );
			$ratio = $new_q / $old_q;
			$fields['quantity']  = round( $new_q, 2 );
			$fields['kcal']      = round( $current['kcal'] * $ratio, 2 );
			$fields['protein_g'] = round( $current['protein'] * $ratio, 2 );
			$fields['carbs_g']   = round( $current['carbs'] * $ratio, 2 );
			$fields['fat_g']     = round( $current['fat'] * $ratio, 2 );
			$fields['fiber_g']   = null === $current['fiber'] ? null : round( $current['fiber'] * $ratio, 2 );
			$fields['sugar_g']   = null === $current['sugar'] ? null : round( $current['sugar'] * $ratio, 2 );
			$fields['sodium_mg'] = null === $current['sodium'] ? null : round( $current['sodium'] * $ratio, 2 );
		}
		DB::update_entry( $user_id, $id, $fields );
		return rest_ensure_response( array( 'data' => DB::get_entry( $user_id, $id ) ) );
	}

	public static function diary_delete( WP_REST_Request $req ) {
		$ok = DB::delete_entry( get_current_user_id(), (int) $req['id'] );
		return rest_ensure_response( array( 'deleted' => $ok ) );
	}

	public static function diary_week( WP_REST_Request $req ) {
		$to   = self::valid_day( (string) $req['to'] );
		$from = gmdate( 'Y-m-d', strtotime( $to . ' -6 days' ) );
		$days = DB::daily_totals( get_current_user_id(), $from, $to );
		// Fill missing days with zeros so the chart is always 7 bars.
		$by_date = array_column( $days, null, 'date' );
		$out     = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$d     = gmdate( 'Y-m-d', strtotime( $from . " +{$i} days" ) );
			$out[] = $by_date[ $d ] ?? array( 'date' => $d, 'kcal' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'entries' => 0 );
		}
		return rest_ensure_response( array( 'from' => $from, 'to' => $to, 'days' => $out, 'targets' => DB::get_targets( get_current_user_id() ) ) );
	}

	public static function targets_set( WP_REST_Request $req ) {
		$b = $req->get_json_params();
		$t = array(
			'kcal'    => max( 800, min( 8000, (int) ( $b['kcal'] ?? 2000 ) ) ),
			'protein' => max( 0, min( 600, (int) ( $b['protein'] ?? 150 ) ) ),
			'carbs'   => max( 0, min( 1200, (int) ( $b['carbs'] ?? 200 ) ) ),
			'fat'     => max( 0, min( 400, (int) ( $b['fat'] ?? 67 ) ) ),
		);
		DB::set_targets( get_current_user_id(), $t );
		return rest_ensure_response( array( 'data' => DB::get_targets( get_current_user_id() ) ) );
	}

	/* -------------------------------------------------------------- Meal plan */

	public static function meal_plan( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		if ( self::can_track() ) {
			if ( ! Access::consume( 'mealplan' ) ) {
				return self::throttled();
			}
		} elseif ( ! Access::consume_ip( 'mealplan', (int) Settings::get( 'limit_mealplan_ip_day', 3 ), DAY_IN_SECONDS ) ) {
			return self::throttled();
		}
		$res = Api_Client::meal_plan( array(
			'calories'   => $req['calories'],
			'diet'       => sanitize_key( (string) $req['diet'] ),
			'meals'      => $req['meals'],
			'days'       => $req['days'],
			'macroSplit' => sanitize_key( (string) $req['macroSplit'] ),
			'fresh'      => ! empty( $req['fresh'] ),
		) );
		if ( is_wp_error( $res ) || empty( $res['data']['days'] ) ) {
			return self::respond( $res );
		}
		// Kept server-side so swaps and emails work from the token alone.
		$res['token'] = Meal_Plans::store( $res['data'] );
		return rest_ensure_response( $res );
	}

	public static function meal_plan_swap( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		if ( ! Access::consume_ip( 'mealswap', self::can_track() ? 120 : 30, DAY_IN_SECONDS ) ) {
			return self::throttled();
		}
		$b = (array) $req->get_json_params();
		return self::respond( Meal_Plans::swap( (string) ( $b['token'] ?? '' ), (int) ( $b['day'] ?? 0 ), (int) ( $b['index'] ?? 0 ) ) );
	}

	/* ---------------------------------------------------------------- Recipes */

	public static function recipes( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		if ( ! current_user_can( 'edit_posts' ) && ! Access::consume_ip( 'recipes', max( 1, (int) Settings::get( 'limit_recipes_ip_day', 200 ) ), DAY_IN_SECONDS ) ) {
			return self::throttled();
		}
		$res = Api_Client::recipe_search( Recipes::search_params( $req->get_params() ) );
		if ( is_wp_error( $res ) ) {
			return self::respond( $res );
		}
		return rest_ensure_response( array(
			'data'       => array_map( array( Recipes::class, 'shape' ), (array) ( $res['data'] ?? array() ) ),
			'pagination' => $res['pagination'] ?? array(),
		) );
	}

	public static function recipe( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		if ( ! current_user_can( 'edit_posts' ) && ! Access::consume_ip( 'recipes', max( 1, (int) Settings::get( 'limit_recipes_ip_day', 200 ) ), DAY_IN_SECONDS ) ) {
			return self::throttled();
		}
		$r = Recipes::get( (string) $req['slug'] );
		return is_wp_error( $r ) ? self::respond( $r ) : rest_ensure_response( array( 'data' => $r ) );
	}

	public static function meal_plan_email( WP_REST_Request $req ) {
		$b = (array) $req->get_json_params();
		if ( ! empty( $b['website'] ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		if ( ! Access::consume_ip( 'lead', 5, HOUR_IN_SECONDS ) ) {
			return self::throttled();
		}
		$email = sanitize_email( (string) ( $b['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'ymn_bad_email', __( 'Please enter a valid email address.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		if ( ! Leads::verify_captcha( (string) ( $b['captcha'] ?? '' ) ) ) {
			return new WP_Error( 'ymn_captcha', __( 'Spam check failed. Please reload the page and try again.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		$consent = ! empty( $b['consent'] );
		if ( Settings::get( 'lead_consent_text' ) && ! $consent ) {
			return new WP_Error( 'ymn_consent', __( 'Please tick the consent box.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		$plan = Meal_Plans::get( (string) ( $b['token'] ?? '' ) );
		if ( ! $plan ) {
			return new WP_Error( 'ymn_plan_gone', __( 'This meal plan has expired. Please generate a new one.', 'ymove-nutrition' ), array( 'status' => 404 ) );
		}
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$message = Meal_Plans::message( $plan );
		/* translators: %s: site name */
		$message['subject'] = sprintf( __( 'Your meal plan from %s', 'ymove-nutrition' ), $site );
		$results = array(
			'type'     => 'mealplan',
			'target'   => (string) (int) ( $plan['calories'] ?? 0 ),
			'goal'     => sanitize_key( (string) ( $plan['diet'] ?? '' ) ),
			'meals'    => (string) (int) ( $plan['mealsPerDay'] ?? 0 ),
			'days'     => (string) count( (array) ( $plan['days'] ?? array() ) ),
		);
		Leads::capture( $email, $results, esc_url_raw( (string) ( $b['page'] ?? '' ) ), $consent, $message );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* ------------------------------------------------------------------ Leads */

	public static function lead( WP_REST_Request $req ) {
		$b = $req->get_json_params();
		// Honeypot: the visible form never fills "website".
		if ( ! empty( $b['website'] ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		if ( ! Access::consume_ip( 'lead', 5, HOUR_IN_SECONDS ) ) {
			return self::throttled();
		}
		$email = sanitize_email( (string) ( $b['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'ymn_bad_email', __( 'Please enter a valid email address.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		if ( ! Leads::verify_captcha( (string) ( $b['captcha'] ?? '' ) ) ) {
			return new WP_Error( 'ymn_captcha', __( 'Spam check failed. Please reload the page and try again.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		$consent = ! empty( $b['consent'] );
		if ( Settings::get( 'lead_consent_text' ) && ! $consent ) {
			return new WP_Error( 'ymn_consent', __( 'Please tick the consent box.', 'ymove-nutrition' ), array( 'status' => 400 ) );
		}
		$results = is_array( $b['results'] ?? null ) ? array_map( 'sanitize_text_field', array_map( 'strval', array_filter( $b['results'], 'is_scalar' ) ) ) : array();
		$page    = esc_url_raw( (string) ( $b['page'] ?? '' ) );

		Leads::capture( $email, $results, $page, $consent );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* ------------------------------------------------------------------ Admin */

	public static function admin_usage( WP_REST_Request $req ) {
		if ( ! Api_Client::is_connected() ) {
			return self::not_connected();
		}
		$res = Api_Client::usage( (bool) $req['fresh'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( array( 'data' => $res['data'] ?? $res, 'local' => Api_Client::counters() ) );
	}

	public static function admin_member( WP_REST_Request $req ) {
		$uid  = (int) $req['id'];
		$to   = gmdate( 'Y-m-d' );
		$from = gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
		return rest_ensure_response( array(
			'user'    => array( 'id' => $uid, 'name' => get_the_author_meta( 'display_name', $uid ) ),
			'days'    => DB::daily_totals( $uid, $from, $to ),
			'today'   => DB::entries_for_day( $uid, $to ),
			'targets' => DB::get_targets( $uid ),
		) );
	}

	/* ---------------------------------------------------------------- Helpers */

	private static function valid_day( string $day ): string {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $day, $m ) && wp_checkdate( (int) $m[2], (int) $m[3], (int) $m[1], $day ) ) {
			return $day;
		}
		return wp_date( 'Y-m-d' );
	}

	private static function totals( array $entries ): array {
		$t = array( 'kcal' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'sugar' => 0, 'sodium' => 0 );
		foreach ( $entries as $e ) {
			$t['kcal']    += $e['kcal'];
			$t['protein'] += $e['protein'];
			$t['carbs']   += $e['carbs'];
			$t['fat']     += $e['fat'];
			$t['fiber']   += (float) $e['fiber'];
			$t['sugar']   += (float) $e['sugar'];
			$t['sodium']  += (float) $e['sodium'];
		}
		return array_map( fn( $v ) => round( $v, 1 ), $t );
	}

	/**
	 * Turn an API result or WP_Error into a REST response with a stable
	 * error shape the frontend can switch on.
	 */
	private static function respond( $res ) {
		if ( is_wp_error( $res ) ) {
			$data   = (array) $res->get_error_data();
			$status = (int) ( $data['status'] ?? 500 );
			// Never leak a 401 to the browser as "unauthorized" - the member did nothing wrong.
			if ( 401 === $status ) {
				$status = 503;
			}
			$body = array(
				'code'    => $res->get_error_code(),
				'message' => $res->get_error_message(),
			);
			if ( ! empty( $data['upgradeUrl'] ) && current_user_can( 'manage_options' ) ) {
				$body['upgradeUrl'] = $data['upgradeUrl'];
			}
			return new WP_REST_Response( $body, $status );
		}
		return rest_ensure_response( $res );
	}

	private static function not_connected() {
		return new WP_Error( 'ymn_not_connected', __( 'This site has not connected a Your Move API key yet.', 'ymove-nutrition' ), array( 'status' => 503 ) );
	}

	private static function throttled() {
		return new WP_Error( 'ymn_throttled', __( 'Too many requests. Please wait a bit.', 'ymove-nutrition' ), array( 'status' => 429 ) );
	}
}
