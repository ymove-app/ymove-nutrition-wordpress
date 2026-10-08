<?php
/**
 * Blocks, shortcodes and their shared server-side renderers.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blocks {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
	}

	public static function register(): void {
		$blocks = array( 'calculator', 'bmi', 'nutrition-facts', 'tracker', 'barcode', 'meal-plan', 'recipes' );
		foreach ( $blocks as $block ) {
			register_block_type(
				YMOVE_NUTRITION_DIR . 'blocks/' . $block,
				array( 'render_callback' => array( __CLASS__, 'render_' . str_replace( '-', '_', $block ) ) )
			);
		}

		add_shortcode( 'ymove_calculator', fn( $atts ) => self::render_calculator( self::shortcode_atts( $atts, 'calculator' ) ) );
		add_shortcode( 'ymove_bmi', fn( $atts ) => self::render_bmi( self::shortcode_atts( $atts, 'bmi' ) ) );
		add_shortcode( 'ymove_tracker', fn( $atts ) => self::render_tracker( self::shortcode_atts( $atts, 'tracker' ) ) );
		add_shortcode( 'ymove_barcode', fn( $atts ) => self::render_barcode( self::shortcode_atts( $atts, 'barcode' ) ) );
		add_shortcode( 'ymove_nutrition', array( __CLASS__, 'shortcode_nutrition' ) );
		add_shortcode( 'ymove_meal_plan', fn( $atts ) => self::render_meal_plan( self::shortcode_atts( $atts, 'meal-plan' ) ) );
		add_shortcode( 'ymove_recipes', fn( $atts ) => self::render_recipes( self::shortcode_atts( $atts, 'recipes' ) ) );
	}

	/**
	 * Map shortcode attributes onto the block's attribute names / defaults.
	 */
	private static function shortcode_atts( $atts, string $block ): array {
		$atts = is_array( $atts ) ? $atts : array();
		$meta = json_decode( (string) file_get_contents( YMOVE_NUTRITION_DIR . 'blocks/' . $block . '/block.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$out  = array();
		foreach ( (array) ( $meta['attributes'] ?? array() ) as $name => $def ) {
			$key = strtolower( $name );
			if ( isset( $atts[ $key ] ) ) {
				$val = $atts[ $key ];
				if ( 'boolean' === ( $def['type'] ?? '' ) ) {
					$val = filter_var( $val, FILTER_VALIDATE_BOOLEAN );
				}
				$out[ $name ] = $val;
			} elseif ( array_key_exists( 'default', $def ) ) {
				$out[ $name ] = $def['default'];
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------- Assets */

	public static function register_assets(): void {
		$v = YMOVE_NUTRITION_VERSION;
		wp_register_style( 'ymove-nutrition', YMOVE_NUTRITION_URL . 'assets/css/ymove.css', array(), $v );
		wp_register_script( 'ymove-nutrition-calculator', YMOVE_NUTRITION_URL . 'assets/js/calculator.js', array(), $v, true );
		wp_register_script( 'ymove-nutrition-scanner', YMOVE_NUTRITION_URL . 'assets/js/scanner.js', array(), $v, true );
		wp_register_script( 'ymove-nutrition-tracker', YMOVE_NUTRITION_URL . 'assets/js/tracker.js', array( 'ymove-nutrition-scanner' ), $v, true );
		wp_register_script( 'ymove-nutrition-barcode', YMOVE_NUTRITION_URL . 'assets/js/barcode.js', array( 'ymove-nutrition-scanner' ), $v, true );
		wp_register_script( 'ymove-nutrition-mealplan', YMOVE_NUTRITION_URL . 'assets/js/mealplan.js', array(), $v, true );
		wp_register_script( 'ymove-nutrition-recipes', YMOVE_NUTRITION_URL . 'assets/js/recipes.js', array(), $v, true );
	}

	private static function enqueue( string $script ): void {
		// Block themes render content before wp_enqueue_scripts fires; register
		// now or wp_add_inline_script() below silently drops the config.
		if ( ! wp_script_is( 'ymove-nutrition-' . $script, 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'ymove-nutrition' );
		wp_enqueue_script( 'ymove-nutrition-' . $script );
		// Once per handle (a page can hold many widgets); the assignment is
		// idempotent, so whichever handle prints first wins.
		static $configured = array();
		if ( empty( $configured[ $script ] ) ) {
			$configured[ $script ] = true;
			wp_add_inline_script( 'ymove-nutrition-' . $script, 'window.ymoveNutrition = window.ymoveNutrition || ' . wp_json_encode( frontend_config() ) . ';', 'before' );
		}
	}

	public static function editor_assets(): void {
		wp_enqueue_style( 'ymove-nutrition', YMOVE_NUTRITION_URL . 'assets/css/ymove.css', array(), YMOVE_NUTRITION_VERSION );
		wp_add_inline_script(
			'wp-blocks',
			'window.ymoveNutritionEditor = ' . wp_json_encode( array(
				'restUrl'   => esc_url_raw( rest_url( 'ymove/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'connected' => Api_Client::is_connected(),
				'settings'  => admin_url( 'options-general.php?page=ymove-nutrition' ),
				'units'     => default_units(),
				'themes'    => widget_themes(),
				'siteTheme' => resolve_theme( '' ),
				'palettes'  => array_map( fn( $p ) => $p[0], gradient_palettes() ),
			) ) . ';',
			'after'
		);
	}

	/* ------------------------------------------------------------ Renderers */

	public static function render_calculator( array $a ): string {
		self::enqueue( 'calculator' );
		$units    = in_array( $a['units'] ?? '', array( 'metric', 'imperial' ), true ) ? $a['units'] : default_units();
		$goals    = array_values( array_filter( Settings::goals(), fn( $g ) => ! empty( $g['enabled'] ) ) );
		$goal_def = in_array( $a['goal'] ?? '', array_column( $goals, 'key' ), true ) ? $a['goal'] : ( $goals[0]['key'] ?? 'maintain' );
		$macros   = ! isset( $a['showMacros'] ) || $a['showMacros'];
		$lead     = self::lead_mode( $a );
		$formula  = in_array( $a['formula'] ?? '', array( 'mifflin', 'harris', 'katch' ), true ) ? $a['formula'] : 'mifflin';
		$instant  = ! empty( $a['instant'] );
		$title    = trim( (string) ( $a['title'] ?? '' ) );
		$layout   = in_array( $a['layout'] ?? '', array( 'card', 'plain', 'split', 'steps', 'chat' ), true ) ? $a['layout'] : 'card';
		$scheme   = in_array( $a['scheme'] ?? '', array( 'default', 'dark', 'soft', 'bold' ), true ) ? $a['scheme'] : 'default';
		$hide_u   = ! empty( $a['hideUnits'] );
		$show_g   = ! isset( $a['showGoal'] ) || $a['showGoal'];
		$show_bmi = ! empty( $a['showBmi'] );
		$theme    = resolve_theme( $a['theme'] ?? '' );
		$style    = self::accent_style( $theme, (string) ( $a['accentColor'] ?? '' ) );
		$steps    = 'steps' === $layout;
		$classes  = 'ymn ymn-calculator ymn-layout-' . $layout . self::theme_classes( $theme, $scheme, (string) ( $a['palette'] ?? '' ) );

		// Split layout: results are always visible, in an "instant" fashion.
		if ( 'split' === $layout ) {
			$instant = true;
		} elseif ( 'chat' === $layout ) {
			$instant = false; // Results arrive as the last chat message.
		}

		// Chat display: grows with the conversation (auto), a fixed-height box, or a floating bottom-right widget.
		$chat = 'chat' === $layout && in_array( $a['chatDisplay'] ?? '', array( 'fixed', 'floating' ), true ) ? $a['chatDisplay'] : 'auto';
		if ( 'auto' !== $chat ) {
			$classes .= ' ymn-chat-' . $chat;
			$height   = '--ymn-chat-h:' . max( 300, min( 900, (int) ( $a['chatHeight'] ?? 520 ) ) ) . 'px';
			$style    = $style ? substr( $style, 0, -1 ) . ';' . $height . '"' : ' style="' . $height . '"';
		}
		$floating = 'floating' === $chat;
		$launch   = trim( (string) ( $a['chatLabel'] ?? '' ) ) ?: __( 'How many calories do I need?', 'ymove-nutrition' );

		$field = function ( string $label, string $input, string $class = '' ): string {
			return '<label' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '><span>' . esc_html( $label ) . '</span>' . $input . '</label>';
		};
		$num = fn( string $name, array $attrs ) => '<input type="number" name="' . esc_attr( $name ) . '" ' . implode( ' ', array_map( fn( $k, $v ) => esc_attr( $k ) . '="' . esc_attr( (string) $v ) . '"', array_keys( $attrs ), $attrs ) ) . '>';

		// --- Field groups (shared by all layouts, wrapped per step in the wizard) ---
		$g_about  = $field( __( 'Sex', 'ymove-nutrition' ), '<select name="sex"><option value="female">' . esc_html__( 'Female', 'ymove-nutrition' ) . '</option><option value="male">' . esc_html__( 'Male', 'ymove-nutrition' ) . '</option></select>' )
			. $field( __( 'Age', 'ymove-nutrition' ), $num( 'age', array( 'min' => 13, 'max' => 100, 'inputmode' => 'numeric', 'required' => 'required' ) ) );
		$g_body   = $field( __( 'Height (cm)', 'ymove-nutrition' ), $num( 'height_cm', array( 'min' => 100, 'max' => 250, 'inputmode' => 'decimal' ) ), 'ymn-metric' )
			. $field( __( 'Height', 'ymove-nutrition' ), '<span class="ymn-inline">' . $num( 'height_ft', array( 'min' => 3, 'max' => 8, 'inputmode' => 'numeric', 'placeholder' => 'ft' ) ) . $num( 'height_in', array( 'min' => 0, 'max' => 11, 'inputmode' => 'numeric', 'placeholder' => 'in' ) ) . '</span>', 'ymn-imperial ymn-height-ft' )
			. $field( __( 'Weight (kg)', 'ymove-nutrition' ), $num( 'weight_kg', array( 'min' => 30, 'max' => 300, 'step' => '0.1', 'inputmode' => 'decimal' ) ), 'ymn-metric' )
			. $field( __( 'Weight (lb)', 'ymove-nutrition' ), $num( 'weight_lb', array( 'min' => 66, 'max' => 660, 'step' => '0.1', 'inputmode' => 'decimal' ) ), 'ymn-imperial' )
			. ( 'katch' === $formula ? $field( __( 'Body fat %', 'ymove-nutrition' ), $num( 'bodyfat', array( 'min' => 3, 'max' => 70, 'step' => '0.5', 'inputmode' => 'decimal', 'required' => 'required' ) ), 'ymn-wide' ) : '' );

		$act_opts = '';
		foreach ( Settings::activities() as $i => $act ) {
			$act_opts .= '<option value="' . esc_attr( (string) $act['factor'] ) . '"' . ( 2 === $i ? ' selected' : '' ) . '>' . esc_html( $act['label'] ) . '</option>';
		}
		$g_plan = $field( __( 'Activity level', 'ymove-nutrition' ), '<select name="activity">' . $act_opts . '</select>', 'ymn-wide' );
		if ( $show_g ) {
			$goal_opts = '';
			foreach ( $goals as $g ) {
				$goal_opts .= '<option value="' . esc_attr( $g['key'] ) . '" data-delta="' . esc_attr( (string) $g['delta'] ) . '"' . ( $g['key'] === $goal_def ? ' selected' : '' ) . '>' . esc_html( $g['label'] ) . '</option>';
			}
			$g_plan .= $field( __( 'Goal', 'ymove-nutrition' ), '<select name="goal">' . $goal_opts . '</select>', 'ymn-wide' );
		} else {
			$g_plan .= '<input type="hidden" name="goal" value="maintain" data-delta="0">';
		}
		if ( $macros ) {
			$g_plan .= $field( __( 'Macro split', 'ymove-nutrition' ), '<select name="split">'
				. '<option value="30,40,30">' . esc_html__( 'Balanced (30% protein, 40% carbs, 30% fat)', 'ymove-nutrition' ) . '</option>'
				. '<option value="40,30,30">' . esc_html__( 'High protein (40 / 30 / 30)', 'ymove-nutrition' ) . '</option>'
				. '<option value="35,25,40">' . esc_html__( 'Low carb (35 / 25 / 40)', 'ymove-nutrition' ) . '</option>'
				. '<option value="25,55,20">' . esc_html__( 'Endurance (25 / 55 / 20)', 'ymove-nutrition' ) . '</option>'
				. '</select>', 'ymn-wide' );
		}

		$units_html = $hide_u ? '' : '<div class="ymn-units" role="group" aria-label="' . esc_attr__( 'Units', 'ymove-nutrition' ) . '">'
			. '<button type="button" class="ymn-unit' . ( 'metric' === $units ? ' is-active' : '' ) . '" data-units="metric">' . esc_html__( 'Metric', 'ymove-nutrition' ) . '</button>'
			. '<button type="button" class="ymn-unit' . ( 'imperial' === $units ? ' is-active' : '' ) . '" data-units="imperial">' . esc_html__( 'Imperial', 'ymove-nutrition' ) . '</button></div>';

		$submit_label = 'required' === $lead ? __( 'Calculate and email me my results', 'ymove-nutrition' ) : __( 'Calculate', 'ymove-nutrition' );

		if ( $steps ) {
			$step = fn( int $n, string $title, string $body, bool $last = false ) => '<fieldset class="ymn-step" data-step="' . $n . '"' . ( 1 === $n ? '' : ' hidden' ) . '><legend class="ymn-step-title">' . esc_html( $title ) . '</legend><div class="ymn-grid">' . $body . '</div><div class="ymn-step-nav">'
				. ( 1 === $n ? '' : '<button type="button" class="ymn-btn" data-step-back>' . esc_html__( 'Back', 'ymove-nutrition' ) . '</button>' )
				. ( $last ? '<button type="submit" class="ymn-btn ymn-btn-primary">' . esc_html( $submit_label ) . '</button>' : '<button type="button" class="ymn-btn ymn-btn-primary" data-step-next>' . esc_html__( 'Next', 'ymove-nutrition' ) . '</button>' )
				. '</div></fieldset>';
			$form_body = '<ol class="ymn-progress" aria-hidden="true"><li class="is-active">' . esc_html__( 'About you', 'ymove-nutrition' ) . '</li><li>' . esc_html__( 'Your body', 'ymove-nutrition' ) . '</li><li>' . esc_html__( 'Your plan', 'ymove-nutrition' ) . '</li></ol>'
				. $step( 1, __( 'About you', 'ymove-nutrition' ), $g_about )
				. $step( 2, __( 'Your body', 'ymove-nutrition' ), $units_html . $g_body )
				. $step( 3, __( 'Your plan', 'ymove-nutrition' ), $g_plan, true );
		} else {
			$form_body = $units_html
				. self::group( __( 'About you', 'ymove-nutrition' ), $g_about . $g_body )
				. self::group( __( 'Your plan', 'ymove-nutrition' ), $g_plan )
				. ( 'split' === $layout ? '' : '<button type="submit" class="ymn-btn ymn-btn-primary">' . esc_html( $submit_label ) . '</button>' );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-units="<?php echo esc_attr( $units ); ?>" data-goal="<?php echo esc_attr( $goal_def ); ?>" data-lead="<?php echo esc_attr( $lead ); ?>" data-formula="<?php echo esc_attr( $formula ); ?>" data-instant="<?php echo $instant ? '1' : '0'; ?>" data-layout="<?php echo esc_attr( $layout ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $floating ) : ?>
			<div class="ymn-chat-head"><strong><?php echo esc_html( $title ?: $launch ); ?></strong><button type="button" class="ymn-icon ymn-chat-close" aria-label="<?php esc_attr_e( 'Close', 'ymove-nutrition' ); ?>">&times;</button></div>
			<?php elseif ( $title ) : ?><h3 class="ymn-title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
			<div class="ymn-columns">
			<form class="ymn-form" novalidate>
				<?php echo $form_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="ymn-error" role="alert" hidden></p>
			</form>
			<div class="ymn-result<?php echo 'required' === $lead ? ' is-gated' : ''; ?>"<?php echo 'split' === $layout ? '' : ' hidden'; ?> aria-live="polite">
				<?php if ( 'split' === $layout ) : ?><?php echo self::empty_state( __( 'Your numbers show up here', 'ymove-nutrition' ), __( 'Fill in the form: daily calories, BMR, TDEE and a macro split appear here, tailored to your goal.', 'ymove-nutrition' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
				<?php if ( 'required' === $lead ) : ?>
				<div class="ymn-gate">
					<p class="ymn-gate-text"><?php esc_html_e( 'Your results are ready. Enter your email and we will show them here and send you a copy.', 'ymove-nutrition' ); ?></p>
					<?php echo self::lead_form( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php endif; ?>
				<div class="ymn-result-main">
					<div class="ymn-stat ymn-stat-big"><span class="ymn-stat-value" data-out="target"></span><span class="ymn-stat-label"><?php echo esc_html( $show_g ? __( 'kcal per day for your goal', 'ymove-nutrition' ) : __( 'kcal per day to maintain your weight', 'ymove-nutrition' ) ); ?></span></div>
					<div class="ymn-stat"><span class="ymn-stat-value" data-out="bmr"></span><span class="ymn-stat-label"><?php esc_html_e( 'BMR (at rest)', 'ymove-nutrition' ); ?></span></div>
					<div class="ymn-stat"><span class="ymn-stat-value" data-out="tdee"></span><span class="ymn-stat-label"><?php esc_html_e( 'TDEE (maintenance)', 'ymove-nutrition' ); ?></span></div>
					<?php if ( $show_bmi ) : ?>
					<div class="ymn-stat"><span class="ymn-stat-value" data-out="bmi"></span><span class="ymn-stat-label"><?php esc_html_e( 'BMI', 'ymove-nutrition' ); ?></span></div>
					<div class="ymn-stat"><span class="ymn-stat-value" data-out="category"></span><span class="ymn-stat-label"><?php esc_html_e( 'WHO category', 'ymove-nutrition' ); ?></span></div>
					<?php endif; ?>
				</div>
				<?php if ( $macros ) : ?>
				<div class="ymn-macros">
					<div class="ymn-macro"><span class="ymn-macro-value" data-out="protein"></span><span><?php esc_html_e( 'Protein', 'ymove-nutrition' ); ?></span></div>
					<div class="ymn-macro"><span class="ymn-macro-value" data-out="carbs"></span><span><?php esc_html_e( 'Carbs', 'ymove-nutrition' ); ?></span></div>
					<div class="ymn-macro"><span class="ymn-macro-value" data-out="fat"></span><span><?php esc_html_e( 'Fat', 'ymove-nutrition' ); ?></span></div>
				</div>
				<?php endif; ?>
				<p class="ymn-note" data-out="note"></p>
				<?php if ( Access::user_can_track() && Api_Client::is_connected() ) : ?>
				<button type="button" class="ymn-btn ymn-btn-secondary" data-action="save-target"><?php esc_html_e( 'Use as my tracker target', 'ymove-nutrition' ); ?></button>
				<?php endif; ?>
				<?php if ( 'optional' === $lead ) : ?>
				<?php echo self::lead_form( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
				<?php echo source_link( 'calculator' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			</div>
			<?php echo credit_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		$html = (string) ob_get_clean();
		if ( $floating ) {
			// <details> opens and closes without JS; calculator.js starts the chat on first open.
			$html = '<details class="ymn-chat-float ymn-chat-float-' . esc_attr( $theme ) . '"><summary class="ymn-chat-launcher"' . self::launcher_style( $theme, (string) ( $a['accentColor'] ?? '' ), (string) ( $a['palette'] ?? '' ) ) . '>'
				. '<span class="ymn-chat-launcher-icon" aria-hidden="true">&#128172;</span><span>' . esc_html( $launch ) . '</span></summary>'
				. $html . '</details>';
		}
		return $html;
	}

	/**
	 * The floating chat button sits outside the themed box, so it gets the
	 * theme's colours inline: accent for Classic / iOS, black for Minimal,
	 * the palette for Gradient, the pick colour for Neo-brutalist.
	 */
	private static function launcher_style( string $theme, string $block_color, string $palette ): string {
		$color = sanitize_hex_color( $block_color ) ?: sanitize_hex_color( (string) Settings::get( 'accent_color', '' ) );
		switch ( $theme ) {
			case 'minimal':
				$bg = '#111111';
				break;
			case 'gradient':
				$palettes = gradient_palettes();
				$p        = $palettes[ $palette ] ?? $palettes[ (string) Settings::get( 'gradient_palette', 'glacier' ) ] ?? $palettes['glacier'];
				return ' style="' . esc_attr( 'background:linear-gradient(90deg,' . $p[1] . ',' . $p[2] . ');color:#fff' ) . '"';
			case 'brutalist':
				$bg = $color ?: '#c6ff3d';
				break;
			case 'ios':
				$bg = $color ?: '#007aff';
				break;
			default:
				$bg = $color ?: '#2563eb';
		}
		// Bold label: white stays readable (3:1) on brighter colours than body text would allow.
		return ' style="' . esc_attr( 'background:' . $bg . ';color:' . ( luminance( $bg ) > 0.3 ? '#111111' : '#ffffff' ) ) . '"';
	}

	public static function render_bmi( array $a ): string {
		self::enqueue( 'calculator' );
		$units  = in_array( $a['units'] ?? '', array( 'metric', 'imperial' ), true ) ? $a['units'] : default_units();
		$lead   = self::lead_mode( $a );
		$title  = trim( (string) ( $a['title'] ?? '' ) );
		$layout = in_array( $a['layout'] ?? '', array( 'card', 'plain', 'split' ), true ) ? $a['layout'] : 'card';
		$scheme = in_array( $a['scheme'] ?? '', array( 'default', 'dark', 'soft', 'bold' ), true ) ? $a['scheme'] : 'default';
		$theme  = resolve_theme( $a['theme'] ?? '' );
		$hide_u = ! empty( $a['hideUnits'] );
		$style  = self::accent_style( $theme, (string) ( $a['accentColor'] ?? '' ) );
		ob_start();
		?>
		<div class="ymn ymn-bmi ymn-layout-<?php echo esc_attr( $layout . self::theme_classes( $theme, $scheme, (string) ( $a['palette'] ?? '' ) ) ); ?>" data-units="<?php echo esc_attr( $units ); ?>" data-lead="<?php echo esc_attr( $lead ); ?>" data-instant="<?php echo 'split' === $layout ? '1' : '0'; ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $title ) : ?><h3 class="ymn-title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
			<div class="ymn-columns">
			<form class="ymn-form" novalidate>
				<?php if ( ! $hide_u ) : ?>
				<div class="ymn-units" role="group" aria-label="<?php esc_attr_e( 'Units', 'ymove-nutrition' ); ?>">
					<button type="button" class="ymn-unit<?php echo 'metric' === $units ? ' is-active' : ''; ?>" data-units="metric"><?php esc_html_e( 'Metric', 'ymove-nutrition' ); ?></button>
					<button type="button" class="ymn-unit<?php echo 'imperial' === $units ? ' is-active' : ''; ?>" data-units="imperial"><?php esc_html_e( 'Imperial', 'ymove-nutrition' ); ?></button>
				</div>
				<?php endif; ?>
				<div class="ymn-group"><div class="ymn-grid">
					<label class="ymn-metric"><span><?php esc_html_e( 'Height (cm)', 'ymove-nutrition' ); ?></span><input type="number" name="height_cm" min="100" max="250" inputmode="decimal"></label>
					<label class="ymn-imperial"><span><?php esc_html_e( 'Height', 'ymove-nutrition' ); ?></span>
						<span class="ymn-inline"><input type="number" name="height_ft" min="3" max="8" inputmode="numeric" placeholder="ft"><input type="number" name="height_in" min="0" max="11" inputmode="numeric" placeholder="in"></span></label>
					<label class="ymn-metric"><span><?php esc_html_e( 'Weight (kg)', 'ymove-nutrition' ); ?></span><input type="number" name="weight_kg" min="30" max="300" step="0.1" inputmode="decimal"></label>
					<label class="ymn-imperial"><span><?php esc_html_e( 'Weight (lb)', 'ymove-nutrition' ); ?></span><input type="number" name="weight_lb" min="66" max="660" step="0.1" inputmode="decimal"></label>
				</div></div>
				<?php if ( 'split' !== $layout ) : ?><button type="submit" class="ymn-btn ymn-btn-primary"><?php esc_html_e( 'Calculate BMI', 'ymove-nutrition' ); ?></button><?php endif; ?>
				<p class="ymn-error" role="alert" hidden></p>
			</form>
			<div class="ymn-result<?php echo 'required' === $lead ? ' is-gated' : ''; ?>"<?php echo 'split' === $layout ? '' : ' hidden'; ?> aria-live="polite">
				<?php if ( 'split' === $layout ) : ?><?php echo self::empty_state( __( 'Your BMI shows up here', 'ymove-nutrition' ), __( 'Fill in your height and weight to see your BMI, WHO category and healthy weight range.', 'ymove-nutrition' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
				<?php if ( 'required' === $lead ) : ?>
				<div class="ymn-gate">
					<p class="ymn-gate-text"><?php esc_html_e( 'Your result is ready. Enter your email and we will show it here and send you a copy.', 'ymove-nutrition' ); ?></p>
					<?php echo self::lead_form( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php endif; ?>
				<div class="ymn-result-main">
					<div class="ymn-stat ymn-stat-big"><span class="ymn-stat-value" data-out="bmi"></span><span class="ymn-stat-label"><?php esc_html_e( 'Body Mass Index', 'ymove-nutrition' ); ?></span></div>
					<div class="ymn-stat"><span class="ymn-stat-value" data-out="category"></span><span class="ymn-stat-label"><?php esc_html_e( 'WHO category', 'ymove-nutrition' ); ?></span></div>
					<div class="ymn-stat"><span class="ymn-stat-value" data-out="range"></span><span class="ymn-stat-label"><?php esc_html_e( 'Healthy weight range', 'ymove-nutrition' ); ?></span></div>
				</div>
				<div class="ymn-bmi-scale" aria-hidden="true"><span class="ymn-bmi-marker" data-out="marker"></span></div>
				<p class="ymn-note"><?php esc_html_e( 'BMI is a screening measure for adults. It does not distinguish muscle from fat, so athletes and older adults should read it with care.', 'ymove-nutrition' ); ?></p>
				<?php if ( 'optional' === $lead ) : ?>
				<?php echo self::lead_form( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
				<?php echo source_link( 'bmi' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			</div>
			<?php echo credit_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Theme class, plus the colour scheme for the classic theme (the other
	 * themes carry their own palette). Loads the theme's web font if allowed.
	 */
	private static function theme_classes( string $theme, string $scheme, string $palette = '' ): string {
		if ( in_array( $theme, array( 'gradient', 'brutalist' ), true ) && Settings::get( 'theme_fonts' ) ) {
			wp_enqueue_style( 'ymove-nutrition-fonts', YMOVE_NUTRITION_URL . 'assets/css/fonts.css', array(), YMOVE_NUTRITION_VERSION );
		}
		$extra = '';
		if ( 'classic' === $theme ) {
			$extra = ' ymn-scheme-' . $scheme;
		} elseif ( 'gradient' === $theme ) {
			$palettes = gradient_palettes();
			$palette  = isset( $palettes[ $palette ] ) ? $palette : (string) Settings::get( 'gradient_palette', 'glacier' );
			$extra    = ' ymn-gradient-' . ( isset( $palettes[ $palette ] ) ? $palette : 'glacier' );
		}
		return ' ymn-theme-' . $theme . $extra;
	}

	/**
	 * Inline accent colour: the block's own, else the site-wide one. Classic
	 * and iOS use it as their accent, Neo-brutalist in place of the lime.
	 * Minimal (monochrome) and Gradient (palettes) ignore it.
	 */
	private static function accent_style( string $theme, string $block_color ): string {
		$color = sanitize_hex_color( $block_color ) ?: sanitize_hex_color( (string) Settings::get( 'accent_color', '' ) );
		if ( ! $color ) {
			return '';
		}
		$ink = ink_for( $color );
		if ( 'brutalist' === $theme ) {
			$vars = '--br-pick:' . $color . ';--br-pick-ink:' . $ink;
		} elseif ( in_array( $theme, array( 'classic', 'ios' ), true ) ) {
			$vars = '--ymn-accent:' . $color . ';--ymn-accent-ink:' . $ink;
		} else {
			return '';
		}
		return ' style="' . esc_attr( $vars ) . '"';
	}

	/**
	 * A titled field group. The title only shows in themes that use it (iOS).
	 */
	private static function group( string $title, string $fields ): string {
		return '<div class="ymn-group"><p class="ymn-group-title">' . esc_html( $title ) . '</p><div class="ymn-grid">' . $fields . '</div></div>';
	}

	/**
	 * Placeholder for the split layout's results column before a result exists.
	 */
	private static function empty_state( string $title, string $text ): string {
		return '<div class="ymn-result-empty"><span class="ymn-empty-mark" aria-hidden="true"></span><strong class="ymn-empty-title">' . esc_html( $title ) . '</strong><span class="ymn-empty-text">' . esc_html( $text ) . '</span></div>';
	}

	/**
	 * off | optional | required. The old boolean leadCapture maps to optional.
	 */
	private static function lead_mode( array $a ): string {
		$mode = $a['leadMode'] ?? '';
		if ( in_array( $mode, array( 'optional', 'required' ), true ) ) {
			return $mode;
		}
		return ! empty( $a['leadCapture'] ) ? 'optional' : 'off';
	}

	/**
	 * The email form shared by both modes. In gated mode it unlocks the
	 * results; in optional mode it just emails them.
	 */
	private static function lead_form( bool $gated ): string {
		$consent = trim( (string) Settings::get( 'lead_consent_text' ) );
		self::enqueue_captcha();
		ob_start();
		?>
		<form class="ymn-lead" novalidate>
			<label><span><?php echo esc_html( $gated ? __( 'Your email', 'ymove-nutrition' ) : __( 'Email me these results', 'ymove-nutrition' ) ); ?></span>
				<span class="ymn-inline"><input type="email" name="email" required placeholder="you@example.com" autocomplete="email"><button type="submit" class="ymn-btn <?php echo $gated ? 'ymn-btn-primary' : 'ymn-btn-secondary'; ?>"><?php echo esc_html( $gated ? __( 'Show my results', 'ymove-nutrition' ) : __( 'Send', 'ymove-nutrition' ) ); ?></button></span></label>
			<?php if ( $consent ) : ?>
			<label class="ymn-consent"><input type="checkbox" name="consent" value="1" required> <span><?php echo esc_html( $consent ); ?></span></label>
			<?php endif; ?>
			<?php if ( 'turnstile' === Settings::get( 'captcha_provider' ) && Settings::get( 'captcha_site_key' ) ) : ?><div class="ymn-captcha" data-provider="turnstile"></div><?php endif; ?>
			<input type="text" name="website" tabindex="-1" autocomplete="off" class="ymn-hp" aria-hidden="true">
			<p class="ymn-lead-error" role="alert" hidden></p>
			<p class="ymn-lead-done" hidden><?php esc_html_e( 'Sent - check your inbox.', 'ymove-nutrition' ); ?></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Load the captcha provider script only on pages that render a lead form.
	 */
	private static function enqueue_captcha(): void {
		$provider = Settings::get( 'captcha_provider' );
		$key      = Settings::get( 'captcha_site_key' );
		if ( ! $provider || ! $key ) {
			return;
		}
		if ( 'recaptcha' === $provider ) {
			wp_enqueue_script( 'ymove-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $key ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		} else {
			wp_enqueue_script( 'ymove-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	/**
	 * Nutrition Facts label from the snapshot stored in the block. No API
	 * call at render time.
	 */
	public static function render_nutrition_facts( array $a ): string {
		if ( ! wp_style_is( 'ymove-nutrition', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'ymove-nutrition' );
		$food = is_array( $a['food'] ?? null ) ? $a['food'] : null;
		if ( ! $food && ! empty( $a['foodId'] ) && Api_Client::is_connected() ) {
			$res  = Api_Client::food( (string) $a['foodId'] );
			$food = is_wp_error( $res ) ? null : ( $res['data'] ?? null );
		}
		if ( ! $food ) {
			return current_user_can( 'edit_posts' )
				? '<div class="ymn ymn-notice">' . esc_html__( 'Nutrition Facts: pick a food in the block settings.', 'ymove-nutrition' ) . '</div>'
				: '';
		}
		$per100  = ( $a['per'] ?? 'serving' ) === '100g';
		$serving = (float) ( $food['servingSize'] ?? 100 );
		$factor  = $per100 && $serving > 0 ? 100 / $serving : 1;
		$val     = fn( $k ) => isset( $food[ $k ] ) && null !== $food[ $k ] ? (float) $food[ $k ] * $factor : null;
		$title   = ( $a['title'] ?? '' ) ?: ( $food['displayName'] ?? $food['shortName'] ?? $food['name'] ?? '' );
		$serving_label = $per100 ? '100 g' : ( ( $food['servingDescription'] ?? '' ) ?: num( $serving ) . ' g' );
		$rows    = array(
			array( __( 'Total Fat', 'ymove-nutrition' ), $val( 'fat' ), 'g', true ),
			array( __( 'Saturated Fat', 'ymove-nutrition' ), $val( 'saturatedFat' ), 'g', false ),
			array( __( 'Cholesterol', 'ymove-nutrition' ), $val( 'cholesterol' ), 'mg', true ),
			array( __( 'Sodium', 'ymove-nutrition' ), $val( 'sodium' ), 'mg', true ),
			array( __( 'Total Carbohydrate', 'ymove-nutrition' ), $val( 'carbs' ), 'g', true ),
			array( __( 'Dietary Fiber', 'ymove-nutrition' ), $val( 'fiber' ), 'g', false ),
			array( __( 'Total Sugars', 'ymove-nutrition' ), $val( 'sugar' ), 'g', false ),
			array( __( 'Protein', 'ymove-nutrition' ), $val( 'protein' ), 'g', true ),
		);

		ob_start();
		?>
		<div class="ymn ymn-label">
			<div class="ymn-label-title"><?php esc_html_e( 'Nutrition Facts', 'ymove-nutrition' ); ?></div>
			<div class="ymn-label-food"><?php echo esc_html( $title ); ?></div>
			<div class="ymn-label-serving"><?php esc_html_e( 'Serving size', 'ymove-nutrition' ); ?> <strong><?php echo esc_html( $serving_label ); ?></strong></div>
			<div class="ymn-label-cal"><span><?php esc_html_e( 'Calories', 'ymove-nutrition' ); ?></span><strong><?php echo esc_html( num( $val( 'calories' ) ) ); ?></strong></div>
			<table class="ymn-label-table">
				<?php foreach ( $rows as $row ) : if ( null === $row[1] ) { continue; } ?>
				<tr class="<?php echo $row[3] ? 'is-main' : 'is-sub'; ?>"><th scope="row"><?php echo esc_html( $row[0] ); ?></th><td><?php echo esc_html( num( $row[1], 1 ) . ' ' . $row[2] ); ?></td></tr>
				<?php endforeach; ?>
			</table>
			<?php if ( ! empty( $food['brand'] ) ) : ?><div class="ymn-label-brand"><?php echo esc_html( $food['brand'] ); ?></div><?php endif; ?>
			<?php echo source_link( 'data' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		$html = (string) ob_get_clean();

		if ( ! empty( $a['schema'] ) ) {
			$ld = array(
				'@context'            => 'https://schema.org',
				'@type'               => 'NutritionInformation',
				'servingSize'         => $serving_label,
				'calories'            => num( $val( 'calories' ) ) . ' calories',
				'fatContent'          => null === $val( 'fat' ) ? null : num( $val( 'fat' ), 1 ) . ' g',
				'carbohydrateContent' => null === $val( 'carbs' ) ? null : num( $val( 'carbs' ), 1 ) . ' g',
				'proteinContent'      => null === $val( 'protein' ) ? null : num( $val( 'protein' ), 1 ) . ' g',
				'fiberContent'        => null === $val( 'fiber' ) ? null : num( $val( 'fiber' ), 1 ) . ' g',
				'sugarContent'        => null === $val( 'sugar' ) ? null : num( $val( 'sugar' ), 1 ) . ' g',
				'sodiumContent'       => null === $val( 'sodium' ) ? null : num( $val( 'sodium' ) ) . ' mg',
			);
			$html .= '<script type="application/ld+json">' . wp_json_encode( array_filter( $ld ) ) . '</script>';
		}
		return $html;
	}

	public static function shortcode_nutrition( $atts ): string {
		$atts = shortcode_atts( array( 'food' => '', 'per' => 'serving', 'title' => '', 'schema' => '0' ), is_array( $atts ) ? $atts : array() );
		return self::render_nutrition_facts( array(
			'foodId' => $atts['food'],
			'per'    => $atts['per'],
			'title'  => $atts['title'] ?: null,
			'schema' => filter_var( $atts['schema'], FILTER_VALIDATE_BOOLEAN ),
		) );
	}

	public static function render_tracker( array $a ): string {
		$notice = self::gate_notice();
		if ( null !== $notice ) {
			return $notice ? '<div class="ymn ymn-tracker">' . $notice . '</div>' : '';
		}
		self::enqueue( 'tracker' );
		$features = array(
			'photo'   => ! isset( $a['showPhoto'] ) || $a['showPhoto'],
			'text'    => ! isset( $a['showText'] ) || $a['showText'],
			'barcode' => ! isset( $a['showBarcode'] ) || $a['showBarcode'],
			'week'    => ! isset( $a['showWeek'] ) || $a['showWeek'],
		);
		$color = sanitize_hex_color( (string) ( $a['accentColor'] ?? '' ) );
		$style = $color ? ' style="--ymn-accent:' . esc_attr( $color ) . '"' : '';
		return '<div class="ymn ymn-tracker" data-features="' . esc_attr( wp_json_encode( $features ) ) . '"' . $style . '>'
			. '<div class="ymn-mount"><p class="ymn-loading">' . esc_html__( 'Loading your diary...', 'ymove-nutrition' ) . '</p></div>'
			. source_link( 'data' )
			. credit_link()
			. '</div>';
	}

	public static function render_barcode( array $a ): string {
		$notice = self::gate_notice();
		if ( null !== $notice ) {
			return $notice ? '<div class="ymn ymn-barcode">' . $notice . '</div>' : '';
		}
		self::enqueue( 'barcode' );
		$color = sanitize_hex_color( (string) ( $a['accentColor'] ?? '' ) );
		$style = $color ? ' style="--ymn-accent:' . esc_attr( $color ) . '"' : '';
		return '<div class="ymn ymn-barcode"' . $style . '><div class="ymn-mount"></div>' . source_link( 'barcode' ) . credit_link() . '</div>';
	}

	public static function render_meal_plan( array $a ): string {
		$public = (bool) Settings::get( 'mealplan_public', 0 );
		if ( ! Api_Client::is_connected() ) {
			return current_user_can( 'manage_options' )
				? '<div class="ymn ymn-mealplan"><div class="ymn-notice">' . sprintf(
					/* translators: %s: settings page URL */
					wp_kses_post( __( 'The meal plan generator needs a Your Move API key. <a href="%s">Connect one in Settings</a> - only admins see this message.', 'ymove-nutrition' ) ),
					esc_url( admin_url( 'options-general.php?page=ymove-nutrition' ) )
				) . '</div></div>'
				: '';
		}
		if ( ! $public && ! is_user_logged_in() ) {
			return '<div class="ymn ymn-mealplan"><div class="ymn-notice">' . sprintf(
				/* translators: %s: login URL */
				wp_kses_post( __( '<a href="%s">Log in</a> to generate a meal plan.', 'ymove-nutrition' ) ),
				esc_url( wp_login_url( get_permalink() ?: home_url() ) )
			) . '</div></div>';
		}
		if ( ! $public && ! Access::user_can_track() ) {
			return '<div class="ymn ymn-mealplan"><div class="ymn-notice">' . esc_html__( 'The meal plan generator is available to members only.', 'ymove-nutrition' ) . '</div></div>';
		}
		self::enqueue( 'mealplan' );
		$diet  = in_array( $a['diet'] ?? '', Api_Client::DIETS, true ) ? $a['diet'] : 'balanced';
		$days  = max( 1, min( 7, (int) ( $a['days'] ?? 1 ) ) );
		$meals = max( 3, min( 6, (int) ( $a['meals'] ?? 3 ) ) );
		$color = sanitize_hex_color( (string) ( $a['accentColor'] ?? '' ) );
		$style = $color ? ' style="--ymn-accent:' . esc_attr( $color ) . '"' : '';
		$cfg   = array(
			'diet'         => $diet,
			'days'         => $days,
			'meals'        => $meals,
			'calories'     => (int) ( $a['calories'] ?? 0 ),
			'showRecipes'  => ! isset( $a['showRecipes'] ) || $a['showRecipes'],
			'canLog'       => Access::user_can_track(),
			'delivery'     => Meal_Plans::delivery(),
			'consent'      => trim( (string) Settings::get( 'lead_consent_text' ) ),
			'site'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		if ( in_array( $cfg['delivery'], array( 'email', 'both' ), true ) ) {
			self::enqueue_captcha();
		}
		return '<div class="ymn ymn-mealplan" data-config="' . esc_attr( wp_json_encode( $cfg ) ) . '"' . $style . '>'
			. '<div class="ymn-mount"></div>'
			. source_link( 'mealplan' )
			. credit_link()
			. '</div>';
	}

	/**
	 * Recipes: one embedded recipe (server-rendered with schema.org markup)
	 * when `recipe` is set, otherwise a searchable recipe browser.
	 */
	public static function render_recipes( array $a ): string {
		if ( ! Api_Client::is_connected() ) {
			return current_user_can( 'manage_options' )
				? '<div class="ymn ymn-recipes"><div class="ymn-notice">' . sprintf(
					/* translators: %s: settings page URL */
					wp_kses_post( __( 'Recipes need a Your Move API key. <a href="%s">Connect one in Settings</a> - only admins see this message.', 'ymove-nutrition' ) ),
					esc_url( admin_url( 'options-general.php?page=ymove-nutrition' ) )
				) . '</div></div>'
				: '';
		}
		$color = sanitize_hex_color( (string) ( $a['accentColor'] ?? '' ) );
		$style = $color ? ' style="--ymn-accent:' . esc_attr( $color ) . ';--ymn-accent-ink:' . esc_attr( ink_for( $color ) ) . '"' : '';
		$slug  = sanitize_title( (string) ( $a['recipe'] ?? '' ) );

		if ( '' !== $slug ) {
			$r = Recipes::get( $slug );
			if ( is_wp_error( $r ) || '' === $r['title'] ) {
				return current_user_can( 'edit_posts' )
					? '<div class="ymn ymn-recipes"><div class="ymn-notice">' . esc_html__( 'This recipe could not be loaded. Pick another one in the block settings.', 'ymove-nutrition' ) . '</div></div>'
					: '';
			}
			// Only members get the diary button, so the script is only needed for them.
			if ( Access::user_can_track() ) {
				self::enqueue( 'recipes' );
			} else {
				if ( ! wp_style_is( 'ymove-nutrition', 'registered' ) ) {
					self::register_assets();
				}
				wp_enqueue_style( 'ymove-nutrition' );
			}
			return '<div class="ymn ymn-recipes ymn-recipes-single"' . $style . '>'
				. self::recipe_html( $r )
				. source_link( 'recipes' )
				. credit_link( 'recipes' )
				. '</div>'
				. '<script type="application/ld+json">' . wp_json_encode( Recipes::json_ld( $r ) ) . '</script>';
		}

		if ( ! Recipes::visitor_can_browse() ) {
			return '<div class="ymn ymn-recipes"><div class="ymn-notice">' . sprintf(
				/* translators: %s: login URL */
				wp_kses_post( __( '<a href="%s">Log in</a> to browse recipes.', 'ymove-nutrition' ) ),
				esc_url( wp_login_url( get_permalink() ?: home_url() ) )
			) . '</div></div>';
		}
		self::enqueue( 'recipes' );
		$cfg = array_merge(
			Recipes::search_params( array(
				'q'           => $a['query'] ?? '',
				'mealType'    => $a['mealType'] ?? '',
				'diet'        => $a['diet'] ?? '',
				'maxCalories' => $a['maxCalories'] ?? 0,
				'pageSize'    => $a['perPage'] ?? 9,
			) ),
			array(
				'showFilters' => ! isset( $a['showFilters'] ) || $a['showFilters'],
				'canLog'      => Access::user_can_track(),
			)
		);
		return '<div class="ymn ymn-recipes" data-config="' . esc_attr( wp_json_encode( $cfg ) ) . '"' . $style . '>'
			. '<div class="ymn-mount"><p class="ymn-loading">' . esc_html__( 'Loading recipes...', 'ymove-nutrition' ) . '</p></div>'
			. source_link( 'recipes' )
			. credit_link( 'recipes' )
			. '</div>';
	}

	/**
	 * Full recipe markup. recipes.js builds the same structure for the
	 * browser view, so the two share styles.
	 */
	private static function recipe_html( array $r ): string {
		$time  = $r['prepTimeMin'] + $r['cookTimeMin'];
		$chips = array_filter( array(
			$r['mealType'] ? ucfirst( str_replace( '_', ' ', $r['mealType'] ) ) : '',
			$time ? sprintf( /* translators: %d: minutes */ __( '%d min', 'ymove-nutrition' ), $time ) : '',
			sprintf( /* translators: %d: number of servings */ _n( '%d serving', '%d servings', $r['servings'], 'ymove-nutrition' ), $r['servings'] ),
			$r['difficulty'] ? ucfirst( (string) $r['difficulty'] ) : '',
		) );
		$macros = array(
			array( __( 'kcal', 'ymove-nutrition' ), num( $r['calories'] ) ),
			array( __( 'Protein', 'ymove-nutrition' ), num( $r['protein'] ) . ' g' ),
			array( __( 'Carbs', 'ymove-nutrition' ), num( $r['carbs'] ) . ' g' ),
			array( __( 'Fat', 'ymove-nutrition' ), num( $r['fat'] ) . ' g' ),
		);
		$log = array(
			'displayName' => $r['title'],
			'mealType'    => $r['mealType'],
			'calories'    => $r['calories'],
			'protein'     => $r['protein'],
			'carbs'       => $r['carbs'],
			'fat'         => $r['fat'],
		);
		ob_start();
		?>
		<article class="ymn-rx" data-log="<?php echo esc_attr( wp_json_encode( $log ) ); ?>">
			<?php if ( $r['imageUrl'] ) : ?>
				<img class="ymn-rx-img" src="<?php echo esc_url( $r['imageUrl'] ); ?>" alt="<?php echo esc_attr( $r['title'] ); ?>" loading="lazy">
			<?php endif; ?>
			<div class="ymn-rx-body">
				<h2 class="ymn-rx-title"><?php echo esc_html( $r['title'] ); ?></h2>
				<div class="ymn-chips">
					<?php foreach ( $chips as $chip ) : ?><span class="ymn-chip"><?php echo esc_html( $chip ); ?></span><?php endforeach; ?>
					<?php foreach ( $r['dietTags'] as $tag ) : ?><span class="ymn-chip ymn-chip-diet"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $tag ) ) ); ?></span><?php endforeach; ?>
				</div>
				<?php if ( $r['description'] ) : ?><p class="ymn-rx-desc"><?php echo esc_html( $r['description'] ); ?></p><?php endif; ?>
				<div class="ymn-rx-macros" aria-label="<?php esc_attr_e( 'Nutrition per serving', 'ymove-nutrition' ); ?>">
					<?php foreach ( $macros as $m ) : ?><div><strong><?php echo esc_html( $m[1] ); ?></strong><span><?php echo esc_html( $m[0] ); ?></span></div><?php endforeach; ?>
				</div>
				<p class="ymn-rx-per"><?php esc_html_e( 'Nutrition per serving', 'ymove-nutrition' ); ?></p>
				<div class="ymn-recipe-actions"></div>
				<div class="ymn-rx-cols">
					<?php if ( $r['ingredients'] ) : ?>
					<section>
						<h3><?php esc_html_e( 'Ingredients', 'ymove-nutrition' ); ?></h3>
						<ul class="ymn-ingredients">
							<?php foreach ( $r['ingredients'] as $i ) : ?><li><?php echo esc_html( Recipes::ingredient_line( $i ) ); ?></li><?php endforeach; ?>
						</ul>
					</section>
					<?php endif; ?>
					<?php if ( $r['instructions'] ) : ?>
					<section>
						<h3><?php esc_html_e( 'Method', 'ymove-nutrition' ); ?></h3>
						<ol class="ymn-steps">
							<?php foreach ( $r['instructions'] as $step ) : ?><li><?php echo esc_html( $step ); ?></li><?php endforeach; ?>
						</ol>
					</section>
					<?php endif; ?>
				</div>
				<?php if ( $r['source'] ) : ?>
					<p class="ymn-rx-attr"><?php esc_html_e( 'Adapted from', 'ymove-nutrition' ); ?> <a href="<?php echo esc_url( $r['source']['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $r['source']['title'] ); ?></a><?php echo $r['source']['license'] ? ' (' . esc_html( $r['source']['license'] ) . ')' : ''; ?></p>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Why the tracker cannot render for this visitor: a notice to show, ''
	 * to render nothing at all, or null when it can render.
	 */
	private static function gate_notice(): ?string {
		if ( ! Api_Client::is_connected() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="ymn-notice">' . sprintf(
					/* translators: %s: settings page URL */
					wp_kses_post( __( 'The tracker needs a Your Move API key. <a href="%s">Connect one in Settings</a> - only admins see this message.', 'ymove-nutrition' ) ),
					esc_url( admin_url( 'options-general.php?page=ymove-nutrition' ) )
				) . '</div>';
			}
			return '';
		}
		if ( ! is_user_logged_in() ) {
			return '<div class="ymn-notice">' . sprintf(
				/* translators: %s: login URL */
				wp_kses_post( __( '<a href="%s">Log in</a> to use the calorie tracker.', 'ymove-nutrition' ) ),
				esc_url( wp_login_url( get_permalink() ?: home_url() ) )
			) . '</div>';
		}
		if ( ! Access::user_can_track() ) {
			return '<div class="ymn-notice">' . esc_html__( 'The calorie tracker is available to members only.', 'ymove-nutrition' ) . '</div>';
		}
		return null;
	}
}
