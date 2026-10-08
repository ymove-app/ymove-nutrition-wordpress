<?php
/**
 * Who may use the tracker, and per-user throttles so members cannot burn
 * the site owner's API quota.
 *
 * Membership plugins (MemberPress, PMPro, WooCommerce Memberships) restrict
 * the tracker *page* themselves; this class guards the REST routes, which
 * those plugins do not cover.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Access {

	/**
	 * Whether the given (or current) user may use the tracker and its API routes.
	 */
	public static function user_can_track( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$allowed = true;
		if ( 'roles' === Settings::get( 'tracker_access' ) ) {
			$roles   = (array) Settings::get( 'tracker_roles', array() );
			$user    = get_userdata( $user_id );
			$allowed = $user && (bool) array_intersect( $roles, (array) $user->roles );
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			$allowed = true;
		}

		/**
		 * Filter whether a user may use the calorie tracker.
		 *
		 * Membership plugins can hook here in one line, e.g.
		 * add_filter( 'ymove_user_can_track', fn( $ok, $uid ) => pmpro_hasMembershipLevel( null, $uid ), 10, 2 );
		 *
		 * @param bool $allowed
		 * @param int  $user_id
		 */
		return (bool) apply_filters( 'ymove_user_can_track', $allowed, $user_id );
	}

	/**
	 * Consume one unit of a throttle bucket. Returns false when exhausted.
	 *
	 * @param string $bucket search|barcode|photo|text|mealplan
	 */
	public static function consume( string $bucket, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		$limits  = array(
			'search'  => array( (int) Settings::get( 'limit_search_hour', 60 ), HOUR_IN_SECONDS ),
			'barcode' => array( (int) Settings::get( 'limit_barcode_hour', 30 ), HOUR_IN_SECONDS ),
			'photo'   => array( (int) Settings::get( 'limit_photo_day', 10 ), DAY_IN_SECONDS ),
			'text'    => array( (int) Settings::get( 'limit_text_day', 20 ), DAY_IN_SECONDS ),
			'mealplan' => array( (int) Settings::get( 'limit_mealplan_day', 5 ), DAY_IN_SECONDS ),
		);
		if ( ! isset( $limits[ $bucket ] ) ) {
			return true;
		}
		list( $limit, $window ) = $limits[ $bucket ];
		if ( $limit <= 0 ) {
			return true; // 0 = unlimited.
		}
		$key   = 'ymn_' . $bucket . '_' . $user_id . '_' . floor( time() / $window );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Throttle keyed by IP for routes visitors can reach (leads, meal plans, recipes). 0 = unlimited.
	 */
	public static function consume_ip( string $bucket, int $limit, int $window ): bool {
		if ( $limit <= 0 ) {
			return true;
		}
		$key   = 'ymn_ip_' . $bucket . '_' . md5( client_ip() ) . '_' . floor( time() / $window );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}
}
