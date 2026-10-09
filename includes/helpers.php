<?php
/**
 * Small shared helpers: source links, country/units defaults, formatting.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a ymove.app URL with plugin attribution parameters.
 */
function ymove_url( string $path, array $extra = array() ): string {
	$args = array_merge(
		array(
			'utm_source' => 'wordpress-plugin',
			'utm_medium' => 'plugin',
		),
		$extra
	);
	return add_query_arg( $args, YMOVE_NUTRITION_SITE . '/' . ltrim( $path, '/' ) );
}

/**
 * Whether the data-source lines (which name and link Your Move) render.
 *
 * Off by default: wordpress.org guideline 10 needs the site owner to opt in
 * to external links on the public site. The calculators' formula link is not
 * affected - see source_link().
 */
function show_source_links(): bool {
	$enabled = (bool) Settings::get( 'source_links', 0 );
	/**
	 * Filter whether source links render on the public site.
	 *
	 * @param bool $enabled
	 */
	return (bool) apply_filters( 'ymove_show_source_links', $enabled );
}

/**
 * Tags and attributes the plugin's own form and result markup uses, for
 * escaping pre-built HTML at output with wp_kses().
 */
function allowed_html(): array {
	$common = array(
		'class'       => true,
		'id'          => true,
		'hidden'      => true,
		'role'        => true,
		'aria-label'  => true,
		'aria-hidden' => true,
		'aria-live'   => true,
		'data-*'      => true,
	);
	$field = $common + array(
		'type'         => true,
		'name'         => true,
		'value'        => true,
		'min'          => true,
		'max'          => true,
		'step'         => true,
		'inputmode'    => true,
		'placeholder'  => true,
		'required'     => true,
		'selected'     => true,
		'checked'      => true,
		'autocomplete' => true,
		'tabindex'     => true,
	);
	return array(
		'div'      => $common,
		'span'     => $common,
		'p'        => $common,
		'strong'   => $common,
		'ol'       => $common,
		'li'       => $common,
		'label'    => $common + array( 'for' => true ),
		'legend'   => $common,
		'fieldset' => $common,
		'form'     => $common + array( 'novalidate' => true ),
		'input'    => $field,
		'select'   => $field,
		'option'   => $field,
		'button'   => $field,
		'a'        => array( 'href' => true, 'target' => true, 'rel' => true, 'class' => true ),
	);
}

/**
 * Render a source / methodology line for a block.
 *
 * The calculator and BMI formula links always render, unbranded: anyone
 * shown a health estimate should be able to see how it was worked out.
 *
 * @param string $kind One of calculator|bmi|data|barcode|analysis|mealplan|recipes.
 */
function source_link( string $kind ): string {
	if ( ! in_array( $kind, array( 'calculator', 'bmi' ), true ) && ! show_source_links() ) {
		return '';
	}

	switch ( $kind ) {
		case 'calculator':
			$href = ymove_url( 'nutrition-api/calorie-calculator-methodology/', array( 'utm_content' => 'calculator' ) );
			$html = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $href ),
				esc_html__( 'Calorie formula explained', 'ymove-nutrition' )
			);
			break;
		case 'bmi':
			$href = ymove_url( 'nutrition-api/calorie-calculator-methodology/', array( 'utm_content' => 'bmi' ) ) . '#bmi';
			$html = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $href ),
				esc_html__( 'How BMI is calculated', 'ymove-nutrition' )
			);
			break;
		case 'barcode':
			$href = ymove_url( 'nutrition-api/barcode-api/', array( 'utm_content' => 'barcode' ) );
			$html = sprintf(
				/* translators: %s: link to the Your Move Nutrition API */
				esc_html__( 'Product data via %s', 'ymove-nutrition' ),
				sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $href ), esc_html__( 'Your Move Nutrition API', 'ymove-nutrition' ) )
			);
			break;
		case 'mealplan':
			$href = ymove_url( 'meal-plan-generator/', array( 'utm_content' => 'mealplan' ) );
			$html = sprintf(
				/* translators: %s: link to the Your Move Nutrition API */
				esc_html__( 'Meal plan and recipes by %s', 'ymove-nutrition' ),
				sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $href ), esc_html__( 'Your Move Nutrition API', 'ymove-nutrition' ) )
			);
			break;
		case 'recipes':
			$href = ymove_url( 'recipe-api/', array( 'utm_content' => 'recipes' ) );
			$html = sprintf(
				/* translators: %s: link to the Your Move Recipe API */
				esc_html__( 'Recipes and nutrition by %s', 'ymove-nutrition' ),
				sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $href ), esc_html__( 'Your Move Recipe API', 'ymove-nutrition' ) )
			);
			break;
		case 'data':
		default:
			$href = ymove_url( 'nutrition-api/', array( 'utm_content' => 'data' ) );
			$html = sprintf(
				/* translators: %s: link to the Your Move Nutrition API */
				esc_html__( 'Data: USDA FoodData Central and Open Food Facts via %s', 'ymove-nutrition' ),
				sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $href ), esc_html__( 'Your Move Nutrition API', 'ymove-nutrition' ) )
			);
			break;
	}

	return '<p class="ymn-source">' . $html . '</p>';
}

/**
 * Optional "Powered by" credit. Off by default (wordpress.org guideline 10).
 */
function credit_link( string $kind = 'nutrition' ): string {
	if ( ! Settings::get( 'credit_link', 0 ) ) {
		return '';
	}
	$recipes = 'recipes' === $kind;
	$href    = ymove_url( $recipes ? 'recipe-api/' : 'nutrition-api/', array( 'utm_medium' => 'credit' ) );
	return sprintf(
		'<p class="ymn-credit"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
		esc_url( $href ),
		$recipes ? esc_html__( 'Recipe API by Your Move', 'ymove-nutrition' ) : esc_html__( 'Nutrition API by Your Move', 'ymove-nutrition' )
	);
}

/**
 * Two-letter country for result boosting: setting, else site locale.
 */
function default_country(): string {
	$country = strtoupper( (string) Settings::get( 'country', '' ) );
	if ( preg_match( '/^[A-Z]{2}$/', $country ) ) {
		return $country;
	}
	$locale = get_locale();
	if ( preg_match( '/_([A-Za-z]{2})$/', $locale, $m ) ) {
		return strtoupper( $m[1] );
	}
	return 'US';
}

/**
 * metric|imperial default for calculators.
 */
function default_units(): string {
	$units = Settings::get( 'units', '' );
	if ( in_array( $units, array( 'metric', 'imperial' ), true ) ) {
		return $units;
	}
	return 'US' === default_country() ? 'imperial' : 'metric';
}

/**
 * Calculator styles. "classic" is the original look and the only one that
 * takes a colour scheme; the others carry their own palette.
 */
function widget_themes(): array {
	return array(
		'classic'   => __( 'Classic', 'ymove-nutrition' ),
		'ios'       => __( 'iOS', 'ymove-nutrition' ),
		'minimal'   => __( 'Minimal', 'ymove-nutrition' ),
		'gradient'  => __( 'Gradient', 'ymove-nutrition' ),
		'brutalist' => __( 'Neo-brutalist', 'ymove-nutrition' ),
	);
}

/**
 * Colour sets for the Gradient style: key => [label, from, via, to].
 */
function gradient_palettes(): array {
	return array(
		'glacier' => array( __( 'Glacier', 'ymove-nutrition' ), '#2563eb', '#0891b2', '#22d3ee' ),
		'ocean'   => array( __( 'Ocean', 'ymove-nutrition' ), '#1e3a8a', '#2563eb', '#38bdf8' ),
		'slate'   => array( __( 'Slate', 'ymove-nutrition' ), '#0f172a', '#334155', '#64748b' ),
		'aurora'  => array( __( 'Aurora', 'ymove-nutrition' ), '#4f46e5', '#0d9488', '#2dd4bf' ),
		'mint'    => array( __( 'Mint', 'ymove-nutrition' ), '#115e59', '#0f766e', '#14b8a6' ),
		'sunset'  => array( __( 'Sunset (vivid)', 'ymove-nutrition' ), '#5b3df5', '#c13cd8', '#ff7a3d' ),
	);
}

/**
 * Dark or white text for a background colour (WCAG relative luminance).
 */
function ink_for( string $hex ): string {
	return luminance( $hex ) > 0.179 ? '#111111' : '#ffffff';
}

/**
 * WCAG relative luminance of a hex colour, 0 (black) to 1 (white).
 */
function luminance( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$lin = array_map(
		function ( $c ) {
			$c = hexdec( $c ) / 255;
			return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		},
		str_split( substr( $hex, 0, 6 ), 2 )
	);
	return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/**
 * A block's theme attribute, falling back to the site-wide choice.
 */
function resolve_theme( $value ): string {
	$themes = widget_themes();
	if ( is_string( $value ) && isset( $themes[ $value ] ) ) {
		return $value;
	}
	$site = Settings::get( 'widget_theme', 'classic' );
	return isset( $themes[ $site ] ) ? $site : 'classic';
}

/**
 * Data shared with every frontend script.
 */
function frontend_config(): array {
	return array(
		'restUrl'   => esc_url_raw( rest_url( 'ymove/v1/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'canTrack'  => Access::user_can_track(),
		'units'     => default_units(),
		'zxingUrl'  => YMOVE_NUTRITION_URL . 'assets/vendor/zxing-library.min.js',
		'captcha'   => array(
			'provider' => (string) Settings::get( 'captcha_provider', '' ),
			'siteKey'  => (string) Settings::get( 'captcha_site_key', '' ),
		),
		'i18n'      => array(
			'loading'        => __( 'Loading...', 'ymove-nutrition' ),
			'noResults'      => __( 'No foods found. Try another word or scan the barcode.', 'ymove-nutrition' ),
			'notFound'       => __( 'Product not found. You can search for it by name instead.', 'ymove-nutrition' ),
			'error'          => __( 'Something went wrong. Please try again.', 'ymove-nutrition' ),
			'upgrade'        => __( 'AI photo and text logging need the site owner to be on the Your Move Pro plan.', 'ymove-nutrition' ),
			'throttled'      => __( 'You have reached the hourly limit. Please try again later.', 'ymove-nutrition' ),
			'cameraDenied'   => __( 'Camera access was denied. Type the barcode number instead.', 'ymove-nutrition' ),
			'cameraMissing'  => __( 'No camera available. Type the barcode number instead.', 'ymove-nutrition' ),
			'add'            => __( 'Add', 'ymove-nutrition' ),
			'added'          => __( 'Added', 'ymove-nutrition' ),
			'remove'         => __( 'Remove', 'ymove-nutrition' ),
			'kcal'           => __( 'kcal', 'ymove-nutrition' ),
			'protein'        => __( 'Protein', 'ymove-nutrition' ),
			'carbs'          => __( 'Carbs', 'ymove-nutrition' ),
			'fat'            => __( 'Fat', 'ymove-nutrition' ),
			'remaining'      => __( 'remaining', 'ymove-nutrition' ),
			'over'           => __( 'over', 'ymove-nutrition' ),
			'today'          => __( 'Today', 'ymove-nutrition' ),
			'analyzing'      => __( 'Analysing your meal...', 'ymove-nutrition' ),
			'confidence'     => __( 'confidence', 'ymove-nutrition' ),
			'noMatch'        => __( 'not matched to a food - not counted', 'ymove-nutrition' ),
			'consentTitle'   => __( 'Send your photo for analysis?', 'ymove-nutrition' ),
			'consentBody'    => __( 'Your photo is sent to the Your Move Nutrition API to identify the foods in it. It is analysed and not stored by this website.', 'ymove-nutrition' ),
			'consentAccept'  => __( 'I agree', 'ymove-nutrition' ),
			'cancel'         => __( 'Cancel', 'ymove-nutrition' ),
			'generating'     => __( 'Building your plan...', 'ymove-nutrition' ),
			'addToDiary'     => __( 'Add to diary', 'ymove-nutrition' ),
			'day'            => __( 'Day', 'ymove-nutrition' ),
			'ingredients'    => __( 'Ingredients', 'ymove-nutrition' ),
			'method'         => __( 'Method', 'ymove-nutrition' ),
			'servings'       => __( 'servings', 'ymove-nutrition' ),
			'noRecipes'      => __( 'No recipes match. Try fewer filters or another word.', 'ymove-nutrition' ),
			'perServing'     => __( 'per serving', 'ymove-nutrition' ),
			'adaptedFrom'    => __( 'Adapted from', 'ymove-nutrition' ),
			'meals'          => array(
				'breakfast' => __( 'Breakfast', 'ymove-nutrition' ),
				'lunch'     => __( 'Lunch', 'ymove-nutrition' ),
				'dinner'    => __( 'Dinner', 'ymove-nutrition' ),
				'snack'     => __( 'Snacks', 'ymove-nutrition' ),
			),
		),
	);
}

/**
 * Round for display.
 */
function num( $value, int $decimals = 0 ): string {
	if ( null === $value || '' === $value ) {
		return '-';
	}
	return number_format_i18n( (float) $value, $decimals );
}

/**
 * Client IP for public-route throttling (leads, meal plans, recipes).
 */
function client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return $ip ?: '0.0.0.0';
}
