<?php
/**
 * Recipes from the Your Move Recipe API: shaping API records for the
 * browser, licence attribution, and schema.org Recipe markup.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recipes {

	const MEAL_TYPES = array( 'breakfast', 'lunch', 'dinner', 'snack', 'pre_workout', 'post_workout', 'drink' );
	const DIETS      = array( 'high_protein', 'low_carb', 'keto', 'vegan', 'vegetarian', 'mediterranean', 'paleo' );

	/**
	 * Members always; visitors unless the owner closed it in Settings.
	 */
	public static function visitor_can_browse(): bool {
		return ( is_user_logged_in() && Access::user_can_track() ) || (bool) Settings::get( 'recipes_public', 1 );
	}

	/**
	 * Allowed /recipes/search parameters from a request or block attributes.
	 */
	public static function search_params( array $in ): array {
		$out = array(
			'q'        => substr( sanitize_text_field( (string) ( $in['q'] ?? '' ) ), 0, 80 ),
			'page'     => max( 1, min( 50, (int) ( $in['page'] ?? 1 ) ) ),
			'pageSize' => max( 3, min( 24, (int) ( $in['pageSize'] ?? 9 ) ) ),
		);
		if ( in_array( $in['mealType'] ?? '', self::MEAL_TYPES, true ) ) {
			$out['mealType'] = $in['mealType'];
		}
		if ( in_array( $in['diet'] ?? '', self::DIETS, true ) ) {
			$out['diet'] = $in['diet'];
		}
		$max = (int) ( $in['maxCalories'] ?? 0 );
		if ( $max >= 100 ) {
			$out['maxCalories'] = min( 3000, $max );
		}
		return $out;
	}

	/**
	 * The fields the frontend renders. Search results carry ingredients as
	 * a comma list and instructions as one string; the full record carries
	 * arrays - both come out as arrays here.
	 */
	public static function shape( array $r ): array {
		$desc   = (string) ( $r['description'] ?? '' );
		$source = null;
		// Licensed recipes end with "[Adapted from <title> (<licence>): <url>]"; the licence requires we show it.
		if ( preg_match( '/\s*\[Adapted from (.+?) \(([^)]*)\):\s*(https?:\/\/[^\s\]]+)\]\s*$/', $desc, $m ) ) {
			$desc   = substr( $desc, 0, -strlen( $m[0] ) );
			$source = array( 'title' => $m[1], 'license' => $m[2], 'url' => esc_url_raw( $m[3] ) );
		}
		$ingredients = $r['ingredients'] ?? array();
		if ( is_string( $ingredients ) ) {
			$ingredients = array_map( fn( $n ) => array( 'name' => trim( $n ) ), array_filter( explode( ',', $ingredients ) ) );
		}
		$round1 = fn( $v ) => round( (float) $v, 1 );
		return array(
			'id'          => (string) ( $r['id'] ?? '' ),
			'slug'        => (string) ( $r['slug'] ?? '' ),
			'title'       => (string) ( $r['title'] ?? '' ),
			'description' => trim( $desc ),
			'source'      => $source,
			'imageUrl'    => $r['imageUrl'] ?? null,
			'mealType'    => (string) ( $r['mealType'] ?? '' ),
			'dietTags'    => array_values( array_map( 'strval', (array) ( $r['dietTags'] ?? array() ) ) ),
			'cuisine'     => $r['cuisineType'] ?? null,
			'difficulty'  => $r['difficulty'] ?? null,
			'prepTimeMin' => (int) ( $r['prepTimeMin'] ?? 0 ),
			'cookTimeMin' => (int) ( $r['cookTimeMin'] ?? 0 ),
			'servings'    => max( 1, (int) ( $r['servings'] ?? 1 ) ),
			'calories'    => (int) round( (float) ( $r['calories'] ?? 0 ) ),
			'protein'     => $round1( $r['protein'] ?? 0 ),
			'carbs'       => $round1( $r['carbs'] ?? 0 ),
			'fat'         => $round1( $r['fat'] ?? 0 ),
			'ingredients' => array_values( array_map(
				fn( $i ) => array(
					'name'     => (string) ( $i['name'] ?? '' ),
					'quantity' => isset( $i['quantity'] ) ? round( (float) $i['quantity'], 2 ) : null,
					'unit'     => (string) ( $i['unit'] ?? '' ),
				),
				(array) $ingredients
			) ),
			'instructions' => is_array( $r['instructions'] ?? null ) ? array_values( array_map( 'strval', $r['instructions'] ) ) : array(),
		);
	}

	public static function ingredient_line( array $i ): string {
		$q   = $i['quantity'] ?? null;
		// 14, 1.5, 0.25 - never 14.00.
		$qty = null === $q ? '' : num( $q, (int) strlen( rtrim( substr( strrchr( (string) round( (float) $q, 2 ), '.' ) ?: '', 1 ), '0' ) ) ) . ' ' . ( $i['unit'] ?? '' );
		return trim( trim( $qty ) . ' ' . ( $i['name'] ?? '' ) );
	}

	/**
	 * Fetch and shape one recipe by slug or id.
	 *
	 * @return array|\WP_Error
	 */
	public static function get( string $id_or_slug ) {
		$res = Api_Client::recipe( $id_or_slug );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::shape( (array) ( $res['data'] ?? array() ) );
	}

	/**
	 * schema.org Recipe for a single embedded recipe.
	 */
	public static function json_ld( array $r ): array {
		$iso = fn( int $min ) => $min > 0 ? 'PT' . $min . 'M' : null;
		$ld  = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'Recipe',
			'name'               => $r['title'],
			'description'        => $r['description'] ?: null,
			'image'              => $r['imageUrl'] ?: null,
			'recipeYield'        => (string) $r['servings'],
			'prepTime'           => $iso( $r['prepTimeMin'] ),
			'cookTime'           => $iso( $r['cookTimeMin'] ),
			'totalTime'          => $iso( $r['prepTimeMin'] + $r['cookTimeMin'] ),
			'recipeCategory'     => $r['mealType'] ? ucfirst( str_replace( '_', ' ', $r['mealType'] ) ) : null,
			'recipeCuisine'      => $r['cuisine'] ? ucfirst( (string) $r['cuisine'] ) : null,
			'keywords'           => $r['dietTags'] ? implode( ', ', array_map( fn( $d ) => str_replace( '_', ' ', $d ), $r['dietTags'] ) ) : null,
			'recipeIngredient'   => array_map( array( __CLASS__, 'ingredient_line' ), $r['ingredients'] ),
			'recipeInstructions' => array_map( fn( $s ) => array( '@type' => 'HowToStep', 'text' => $s ), $r['instructions'] ),
			'nutrition'          => array(
				'@type'               => 'NutritionInformation',
				'servingSize'         => '1 serving',
				'calories'            => $r['calories'] . ' calories',
				'proteinContent'      => $r['protein'] . ' g',
				'carbohydrateContent' => $r['carbs'] . ' g',
				'fatContent'          => $r['fat'] . ' g',
			),
		);
		if ( $r['source'] ) {
			$ld['isBasedOn'] = $r['source']['url'];
		}
		return array_filter( $ld, fn( $v ) => null !== $v && array() !== $v );
	}
}
