<?php
/**
 * Generated meal plans kept server-side for a day under a random token, so
 * a visitor can swap single recipes and email the plan without the browser
 * ever deciding what the site sends.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meal_Plans {

	const TTL = DAY_IN_SECONDS;

	/**
	 * off | email | pdf | both
	 */
	public static function delivery(): string {
		$d = (string) Settings::get( 'mealplan_delivery', 'both' );
		return in_array( $d, array( 'off', 'email', 'pdf', 'both' ), true ) ? $d : 'both';
	}

	public static function store( array $plan ): string {
		$token = wp_generate_password( 24, false );
		set_transient( 'ymn_plan_' . $token, $plan, self::TTL );
		return $token;
	}

	public static function get( string $token ): ?array {
		if ( ! preg_match( '/^[A-Za-z0-9]{24}$/', $token ) ) {
			return null;
		}
		$plan = get_transient( 'ymn_plan_' . $token );
		return is_array( $plan ) ? $plan : null;
	}

	private static function save( string $token, array $plan ): void {
		set_transient( 'ymn_plan_' . $token, $plan, self::TTL );
	}

	/* ----------------------------------------------------------------- Swap */

	/**
	 * Replace one meal with another recipe of the same meal type and diet,
	 * close to the same calories, not already in the plan.
	 *
	 * @return array|WP_Error { meal, dayTotals, averageDailyTotals }
	 */
	public static function swap( string $token, int $day, int $index ) {
		$plan = self::get( $token );
		if ( ! $plan || ! isset( $plan['days'][ $day ]['meals'][ $index ] ) ) {
			return new WP_Error( 'ymn_plan_gone', __( 'This meal plan has expired. Please generate a new one.', 'ymove-nutrition' ), array( 'status' => 404 ) );
		}
		$current = $plan['days'][ $day ]['meals'][ $index ];
		$target  = max( 150, (float) ( $current['calories'] ?? 500 ) );
		$type    = sanitize_key( (string) ( $current['type'] ?? 'lunch' ) );
		$diet    = (string) ( $plan['diet'] ?? 'balanced' );

		$used = array();
		foreach ( $plan['days'] as $d ) {
			foreach ( $d['meals'] as $m ) {
				$used[ (string) ( $m['recipeId'] ?? '' ) ] = true;
			}
		}

		$search = array(
			'mealType'    => $type,
			'maxCalories' => (int) round( $target * 1.25 ),
			'pageSize'    => 40,
		);
		if ( 'balanced' !== $diet ) {
			$search['diet'] = $diet;
		}
		$res = Api_Client::recipe_search( $search );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$candidates = array_values( array_filter(
			(array) ( $res['data'] ?? array() ),
			fn( $r ) => empty( $used[ (string) ( $r['id'] ?? '' ) ] ) && (float) ( $r['calories'] ?? 0 ) >= $target * 0.7
		) );
		if ( ! $candidates ) {
			$candidates = array_values( array_filter( (array) ( $res['data'] ?? array() ), fn( $r ) => empty( $used[ (string) ( $r['id'] ?? '' ) ] ) ) );
		}
		if ( ! $candidates ) {
			return new WP_Error( 'ymn_no_swap', __( 'No other recipe fits this meal. Try generating a new plan.', 'ymove-nutrition' ), array( 'status' => 404 ) );
		}
		// Prefer the closest few by calories, then pick one at random for variety.
		usort( $candidates, fn( $a, $b ) => abs( (float) $a['calories'] - $target ) <=> abs( (float) $b['calories'] - $target ) );
		$pick = $candidates[ wp_rand( 0, min( 5, count( $candidates ) ) - 1 ) ];

		$detail = Api_Client::recipe( (string) $pick['id'] );
		if ( is_wp_error( $detail ) ) {
			return $detail;
		}
		$meal = self::meal_from_recipe( $type, (array) ( $detail['data'] ?? array() ) );

		$plan['days'][ $day ]['meals'][ $index ] = $meal;
		$plan = self::recount( $plan );
		self::save( $token, $plan );

		return array(
			'meal'               => $meal,
			'dayTotals'          => $plan['days'][ $day ]['totals'],
			'averageDailyTotals' => $plan['averageDailyTotals'],
		);
	}

	/**
	 * A /recipes/{id} record in the same shape the meal plan uses.
	 */
	private static function meal_from_recipe( string $type, array $r ): array {
		$round1 = fn( $v ) => round( (float) $v, 1 );
		return array(
			'type'              => $type,
			'name'              => (string) ( $r['title'] ?? '' ),
			'recipeId'          => (string) ( $r['id'] ?? '' ),
			'recipeSlug'        => (string) ( $r['slug'] ?? '' ),
			'imageUrl'          => $r['imageUrl'] ?? null,
			'portionMultiplier' => 1,
			'calories'          => (int) round( (float) ( $r['calories'] ?? 0 ) ),
			'protein'           => $round1( $r['protein'] ?? 0 ),
			'carbs'             => $round1( $r['carbs'] ?? 0 ),
			'fat'               => $round1( $r['fat'] ?? 0 ),
			'recipe'            => array(
				'id'           => (string) ( $r['id'] ?? '' ),
				'title'        => (string) ( $r['title'] ?? '' ),
				'description'  => $r['description'] ?? null,
				'prepTimeMin'  => (int) ( $r['prepTimeMin'] ?? 0 ),
				'cookTimeMin'  => (int) ( $r['cookTimeMin'] ?? 0 ),
				'servings'     => (int) ( $r['servings'] ?? 1 ),
				'instructions' => array_values( array_map( 'strval', (array) ( $r['instructions'] ?? array() ) ) ),
				'imageUrl'     => $r['imageUrl'] ?? null,
			),
			'foods'             => array_map(
				fn( $i ) => array(
					'name'     => (string) ( $i['name'] ?? '' ),
					'portion'  => trim( $round1( $i['quantity'] ?? 0 ) . ' ' . ( $i['unit'] ?? '' ) ),
					'calories' => (int) round( (float) ( $i['calories'] ?? 0 ) ),
				),
				(array) ( $r['ingredients'] ?? array() )
			),
		);
	}

	private static function recount( array $plan ): array {
		$sum = array( 'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0 );
		foreach ( $plan['days'] as $i => $d ) {
			$t = array( 'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0 );
			foreach ( $d['meals'] as $m ) {
				foreach ( $t as $k => $v ) {
					$t[ $k ] += (float) ( $m[ $k ] ?? 0 );
				}
			}
			$t['calories']              = (int) round( $t['calories'] );
			$t['protein']               = round( $t['protein'], 1 );
			$t['carbs']                 = round( $t['carbs'], 1 );
			$t['fat']                   = round( $t['fat'], 1 );
			$plan['days'][ $i ]['totals'] = $t;
			foreach ( $sum as $k => $v ) {
				$sum[ $k ] += $t[ $k ];
			}
		}
		$n                          = max( 1, count( $plan['days'] ) );
		$plan['averageDailyTotals'] = array(
			'calories' => (int) round( $sum['calories'] / $n ),
			'protein'  => round( $sum['protein'] / $n, 1 ),
			'carbs'    => round( $sum['carbs'] / $n, 1 ),
			'fat'      => round( $sum['fat'] / $n, 1 ),
		);
		return $plan;
	}

	/* ---------------------------------------------------------------- Email */

	/**
	 * The plan as a structured Mailer message with every recipe in full.
	 */
	public static function message( array $plan ): array {
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$meals = array(
			'breakfast' => __( 'Breakfast', 'ymove-nutrition' ),
			'lunch'     => __( 'Lunch', 'ymove-nutrition' ),
			'dinner'    => __( 'Dinner', 'ymove-nutrition' ),
			'snack'     => __( 'Snack', 'ymove-nutrition' ),
		);
		$avg  = (array) ( $plan['averageDailyTotals'] ?? array() );
		$days = (array) ( $plan['days'] ?? array() );

		$sections = array();
		foreach ( $days as $d ) {
			$t = (array) ( $d['totals'] ?? array() );
			if ( count( $days ) > 1 ) {
				$sections[] = array(
					/* translators: %d: day number */
					'heading' => sprintf( __( 'Day %d', 'ymove-nutrition' ), (int) ( $d['dayIndex'] ?? 1 ) ),
					'meta'    => sprintf( '%s kcal · P %s g · C %s g · F %s g', num( $t['calories'] ?? 0 ), num( $t['protein'] ?? 0 ), num( $t['carbs'] ?? 0 ), num( $t['fat'] ?? 0 ) ),
				);
			}
			foreach ( (array) ( $d['meals'] ?? array() ) as $m ) {
				$r    = (array) ( $m['recipe'] ?? array() );
				$time = (int) ( $r['prepTimeMin'] ?? 0 ) + (int) ( $r['cookTimeMin'] ?? 0 );
				$sections[] = array(
					'kicker' => $meals[ $m['type'] ?? '' ] ?? ucfirst( (string) ( $m['type'] ?? '' ) ),
					'title'  => (string) ( $m['name'] ?? '' ),
					'image'  => (string) ( $m['imageUrl'] ?? '' ),
					'meta'   => sprintf( '%s kcal · P %s g · C %s g · F %s g', num( $m['calories'] ?? 0 ), num( $m['protein'] ?? 0 ), num( $m['carbs'] ?? 0 ), num( $m['fat'] ?? 0 ) ) . ( $time ? ' · ' . $time . ' min' : '' ),
					'text'   => (string) ( $r['description'] ?? '' ),
					'lists'  => array_values( array_filter( array(
						! empty( $m['foods'] ) ? array(
							'heading' => __( 'Ingredients', 'ymove-nutrition' ),
							'ordered' => false,
							'items'   => array_map( fn( $f ) => trim( ( $f['portion'] ?? '' ) . ' ' . ( $f['name'] ?? '' ) ), (array) $m['foods'] ),
						) : null,
						! empty( $r['instructions'] ) ? array(
							'heading' => __( 'Method', 'ymove-nutrition' ),
							'ordered' => true,
							'items'   => array_map( 'strval', (array) $r['instructions'] ),
						) : null,
					) ) ),
				);
			}
		}

		return array(
			'heading'  => $site,
			'intro'    => sprintf(
				/* translators: 1: kcal, 2: number of days */
				_n( 'Your %1$s kcal meal plan for %2$d day, with every recipe in full.', 'Your %1$s kcal meal plan for %2$d days, with every recipe in full.', max( 1, count( $days ) ), 'ymove-nutrition' ),
				num( $plan['calories'] ?? 0 ),
				max( 1, count( $days ) )
			),
			'rows'     => array(
				array( __( 'Average per day', 'ymove-nutrition' ), num( $avg['calories'] ?? 0 ) . ' kcal', true ),
				array( __( 'Protein', 'ymove-nutrition' ), num( $avg['protein'] ?? 0 ) . ' g', false ),
				array( __( 'Carbohydrates', 'ymove-nutrition' ), num( $avg['carbs'] ?? 0 ) . ' g', false ),
				array( __( 'Fat', 'ymove-nutrition' ), num( $avg['fat'] ?? 0 ) . ' g', false ),
			),
			'sections' => $sections,
			'cta'      => array_filter( array( trim( (string) Settings::get( 'lead_cta_label' ) ), trim( (string) Settings::get( 'lead_cta_url' ) ) ) ),
			'note'     => __( 'Meal plan and recipes by Your Move Nutrition. Estimates, not medical advice.', 'ymove-nutrition' ),
		);
	}
}
