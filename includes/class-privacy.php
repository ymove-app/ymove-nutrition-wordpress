<?php
/**
 * WordPress privacy tools: exporter and eraser for diaries, targets and
 * calculator leads, plus the privacy-policy suggestion text.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Privacy {

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
		add_action( 'delete_user', fn( $id ) => DB::delete_user_data( (int) $id ) );
	}

	public static function register_exporter( array $exporters ): array {
		$exporters['ymove-nutrition'] = array(
			'exporter_friendly_name' => __( 'Your Move Nutrition', 'ymove-nutrition' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['ymove-nutrition'] = array(
			'eraser_friendly_name' => __( 'Your Move Nutrition', 'ymove-nutrition' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( string $email, int $page = 1 ): array {
		$items = array();
		$user  = get_user_by( 'email', $email );
		if ( $user ) {
			$targets = DB::get_targets( $user->ID );
			if ( empty( $targets['isDefault'] ) ) {
				$items[] = array(
					'group_id'    => 'ymove-targets',
					'group_label' => __( 'Nutrition targets', 'ymove-nutrition' ),
					'item_id'     => 'ymove-targets-' . $user->ID,
					'data'        => array(
						array( 'name' => __( 'Calories', 'ymove-nutrition' ), 'value' => $targets['kcal'] ),
						array( 'name' => __( 'Protein (g)', 'ymove-nutrition' ), 'value' => $targets['protein'] ),
						array( 'name' => __( 'Carbs (g)', 'ymove-nutrition' ), 'value' => $targets['carbs'] ),
						array( 'name' => __( 'Fat (g)', 'ymove-nutrition' ), 'value' => $targets['fat'] ),
					),
				);
			}
			foreach ( DB::all_entries( $user->ID ) as $e ) {
				$items[] = array(
					'group_id'    => 'ymove-diary',
					'group_label' => __( 'Food diary', 'ymove-nutrition' ),
					'item_id'     => 'ymove-diary-' . $e['id'],
					'data'        => array(
						array( 'name' => __( 'Date', 'ymove-nutrition' ), 'value' => $e['date'] ),
						array( 'name' => __( 'Meal', 'ymove-nutrition' ), 'value' => $e['meal'] ),
						array( 'name' => __( 'Food', 'ymove-nutrition' ), 'value' => $e['displayName'] ),
						array( 'name' => __( 'Quantity', 'ymove-nutrition' ), 'value' => $e['quantity'] . ' x ' . $e['servingG'] . ' g' ),
						array( 'name' => __( 'Calories', 'ymove-nutrition' ), 'value' => $e['kcal'] ),
						array( 'name' => __( 'Logged via', 'ymove-nutrition' ), 'value' => $e['source'] ),
					),
				);
			}
		}
		foreach ( DB::leads_by_email( $email ) as $lead ) {
			$items[] = array(
				'group_id'    => 'ymove-leads',
				'group_label' => __( 'Calorie calculator results sent by email', 'ymove-nutrition' ),
				'item_id'     => 'ymove-lead-' . $lead['id'],
				'data'        => array(
					array( 'name' => __( 'Date', 'ymove-nutrition' ), 'value' => $lead['created_at'] ),
					array( 'name' => __( 'Results', 'ymove-nutrition' ), 'value' => $lead['results'] ),
					array( 'name' => __( 'Page', 'ymove-nutrition' ), 'value' => $lead['page_url'] ),
				),
			);
		}
		return array( 'data' => $items, 'done' => true );
	}

	public static function erase( string $email, int $page = 1 ): array {
		$removed = 0;
		$user    = get_user_by( 'email', $email );
		if ( $user ) {
			$removed += DB::delete_user_data( $user->ID );
		}
		$removed += DB::delete_leads_by_email( $email );
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	public static function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p>' . esc_html__( 'This site uses the Your Move Nutrition plugin. The calorie and BMI calculators run entirely in your browser; nothing is stored unless you choose to email yourself the results, in which case your email address and the results are saved so they can be sent to you.', 'ymove-nutrition' ) . '</p>';
		$text .= '<p>' . esc_html__( 'Logged-in members who use the calorie tracker have their food diary and nutrition targets stored in this site\'s database. Food searches, barcode numbers, meal descriptions and meal photos you submit are sent to the Your Move Nutrition API (exercise-api.ymove.app) to look up or identify foods. Photos are analysed and not stored by this site. See the Your Move privacy policy at https://ymove.app/privacy for how they process requests.', 'ymove-nutrition' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Your Move Nutrition', 'ymove-nutrition' ), wp_kses_post( $text ) );
	}
}
