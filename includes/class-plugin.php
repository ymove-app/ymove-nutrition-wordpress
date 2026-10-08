<?php
/**
 * Bootstrap.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		DB::maybe_upgrade();
		REST::init();
		Blocks::init();
		Privacy::init();
		if ( is_admin() ) {
			Admin::init();
		}

		add_action( 'init', array( $this, 'capabilities' ) );
		add_action( 'ymove_nutrition_daily', array( $this, 'daily' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		register_deactivation_hook( YMOVE_NUTRITION_FILE, fn() => wp_clear_scheduled_hook( 'ymove_nutrition_daily' ) );
	}

	/**
	 * Admins can see member logs by default; site owners can grant the
	 * capability to a coach role with any role editor.
	 */
	public function capabilities(): void {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'ymove_view_member_logs' ) ) {
			$admin->add_cap( 'ymove_view_member_logs' );
		}
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( 'ymove_nutrition_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ymove_nutrition_daily' );
		}
	}

	public function daily(): void {
		DB::purge_old_entries( (int) Settings::get( 'retention_days', 0 ) );
	}
}
