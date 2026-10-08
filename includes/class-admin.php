<?php
/**
 * Settings page, usage screen, members (coach) screen and admin notices.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	const PAGE = 'ymove-nutrition';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'connect_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( YMOVE_NUTRITION_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'wp_ajax_ymove_dismiss_connect', array( __CLASS__, 'dismiss_notice' ) );
		add_action( 'admin_post_ymove_leads_csv', array( __CLASS__, 'leads_csv' ) );
		add_action( 'admin_post_ymove_lead_delete', array( __CLASS__, 'lead_delete' ) );
		add_action( 'admin_post_ymove_test_mail', array( __CLASS__, 'test_mail' ) );
	}

	public static function menu(): void {
		add_options_page(
			__( 'Your Move Nutrition', 'ymove-nutrition' ),
			__( 'Your Move Nutrition', 'ymove-nutrition' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
		add_users_page(
			__( 'Nutrition logs', 'ymove-nutrition' ),
			__( 'Nutrition logs', 'ymove-nutrition' ),
			'ymove_view_member_logs',
			self::PAGE . '-members',
			array( __CLASS__, 'render_members' )
		);
	}

	public static function register_settings(): void {
		register_setting( 'ymove_nutrition', Settings::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( 'YMove_Nutrition\\Settings', 'sanitize' ),
		) );
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'ymove-nutrition' ) . '</a>' );
		return $links;
	}

	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'ymove-nutrition-admin', YMOVE_NUTRITION_URL . 'assets/css/admin.css', array(), YMOVE_NUTRITION_VERSION );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'ymove-nutrition-admin', YMOVE_NUTRITION_URL . 'assets/js/admin.js', array( 'wp-color-picker' ), YMOVE_NUTRITION_VERSION, true );
		wp_add_inline_script( 'ymove-nutrition-admin', 'jQuery( function ( $ ) { $( ".ymove-color" ).wpColorPicker(); } );' );
		wp_add_inline_script( 'ymove-nutrition-admin', 'window.ymoveNutritionAdmin = ' . wp_json_encode( array(
			'restUrl' => esc_url_raw( rest_url( 'ymove/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'ajaxNonce' => wp_create_nonce( 'ymove_dismiss' ),
		) ) . ';', 'before' );
	}

	/* ---------------------------------------------------------------- Notice */

	public static function connect_notice(): void {
		if ( Api_Client::is_connected() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), 'ymove_nutrition_dismissed', true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, self::PAGE ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible ymove-connect-notice">
			<p><strong><?php esc_html_e( 'Your Move Nutrition:', 'ymove-nutrition' ); ?></strong>
			<?php esc_html_e( 'the calorie calculator is live. Connect a Your Move API key to turn it into a calorie tracker with barcode scanning and AI photo logging for your members.', 'ymove-nutrition' ); ?>
			<a href="<?php echo esc_url( ymove_url( 'nutrition-api/signup', array( 'source' => 'wordpress-plugin' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get a key', 'ymove-nutrition' ); ?></a> |
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ); ?>"><?php esc_html_e( 'Enter a key', 'ymove-nutrition' ); ?></a></p>
		</div>
		<?php
	}

	public static function dismiss_notice(): void {
		check_ajax_referer( 'ymove_dismiss' );
		update_user_meta( get_current_user_id(), 'ymove_nutrition_dismissed', 1 );
		wp_send_json_success();
	}

	/* --------------------------------------------------------------- Settings */

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s         = Settings::all();
		$connected = Api_Client::is_connected();
		$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap ymove-admin">
			<h1><?php esc_html_e( 'Your Move Nutrition', 'ymove-nutrition' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ); ?>"><?php esc_html_e( 'Settings', 'ymove-nutrition' ); ?></a>
				<a class="nav-tab <?php echo 'usage' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE . '&tab=usage' ) ); ?>"><?php esc_html_e( 'Usage', 'ymove-nutrition' ); ?></a>
				<a class="nav-tab <?php echo 'leads' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE . '&tab=leads' ) ); ?>"><?php esc_html_e( 'Leads', 'ymove-nutrition' ); ?> <span class="count">(<?php echo (int) DB::leads_count(); ?>)</span></a>
				<a class="nav-tab <?php echo 'help' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE . '&tab=help' ) ); ?>"><?php esc_html_e( 'Blocks & shortcodes', 'ymove-nutrition' ); ?></a>
			</nav>
			<?php
			if ( 'usage' === $tab ) {
				self::render_usage();
			} elseif ( 'leads' === $tab ) {
				self::render_leads();
			} elseif ( 'help' === $tab ) {
				self::render_help();
			} else {
				self::render_settings( $s, $connected );
			}
			?>
		</div>
		<?php
	}

	private static function render_settings( array $s, bool $connected ): void {
		$name = Settings::OPTION;
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ymove_nutrition' ); ?>

			<h2><?php esc_html_e( 'API connection', 'ymove-nutrition' ); ?></h2>
			<div class="ymove-card">
				<?php if ( $connected ) : ?>
					<p class="ymove-status ymove-status-ok"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Connected', 'ymove-nutrition' ); ?> <code><?php echo esc_html( Settings::masked_key() ); ?></code>
						<?php if ( Settings::has_constant_key() ) : ?><em><?php esc_html_e( '(set via YMOVE_API_KEY in wp-config.php)', 'ymove-nutrition' ); ?></em><?php endif; ?></p>
					<div id="ymove-usage-summary" class="ymove-usage-summary"><?php esc_html_e( 'Checking plan...', 'ymove-nutrition' ); ?></div>
				<?php else : ?>
					<p class="ymove-status"><span class="dashicons dashicons-marker"></span> <?php esc_html_e( 'Not connected. The calculators work without a key; the tracker, barcode scanner and Nutrition Facts block need one.', 'ymove-nutrition' ); ?></p>
					<p><a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url( ymove_url( 'nutrition-api/signup', array( 'source' => 'wordpress-plugin' ) ) ); ?>"><?php esc_html_e( 'Get a Your Move API key', 'ymove-nutrition' ); ?></a>
					<span class="description"><?php esc_html_e( 'Basic covers food search and barcodes. Pro adds AI photo, text and voice logging.', 'ymove-nutrition' ); ?></span></p>
				<?php endif; ?>
				<?php if ( ! Settings::has_constant_key() ) : ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="ymove-api-key"><?php esc_html_e( 'API key', 'ymove-nutrition' ); ?></label></th>
						<td><input type="password" id="ymove-api-key" name="<?php echo esc_attr( $name ); ?>[api_key]" class="regular-text" autocomplete="off" placeholder="<?php echo $connected ? esc_attr__( 'Leave empty to keep the current key', 'ymove-nutrition' ) : 'ym_...'; ?>">
						<?php if ( $connected ) : ?><label class="ymove-inline"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[clear_api_key]" value="1"> <?php esc_html_e( 'Disconnect', 'ymove-nutrition' ); ?></label><?php endif; ?>
						<p class="description"><?php esc_html_e( 'Stored encrypted in this site\'s database and only ever used server-side.', 'ymove-nutrition' ); ?></p></td></tr>
				</table>
				<?php endif; ?>
			</div>

			<h2><?php esc_html_e( 'Defaults', 'ymove-nutrition' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="ymove-country"><?php esc_html_e( 'Country for food results', 'ymove-nutrition' ); ?></label></th>
					<td><input type="text" id="ymove-country" name="<?php echo esc_attr( $name ); ?>[country]" value="<?php echo esc_attr( $s['country'] ); ?>" class="small-text" maxlength="2" placeholder="<?php echo esc_attr( default_country() ); ?>">
					<p class="description"><?php esc_html_e( 'Two-letter code (NL, DE, US). Local packaged products rank first. Empty = follow the site language.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Units', 'ymove-nutrition' ); ?></th>
					<td><select name="<?php echo esc_attr( $name ); ?>[units]">
						<option value="" <?php selected( $s['units'], '' ); ?>><?php esc_html_e( 'Automatic (imperial for US, metric elsewhere)', 'ymove-nutrition' ); ?></option>
						<option value="metric" <?php selected( $s['units'], 'metric' ); ?>><?php esc_html_e( 'Metric', 'ymove-nutrition' ); ?></option>
						<option value="imperial" <?php selected( $s['units'], 'imperial' ); ?>><?php esc_html_e( 'Imperial', 'ymove-nutrition' ); ?></option>
					</select></td></tr>
			</table>

			<h2><?php esc_html_e( 'Calculator style', 'ymove-nutrition' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The look of every calorie and BMI calculator on the site. A block can still pick its own style in the block sidebar, or with theme="..." on the shortcode.', 'ymove-nutrition' ); ?></p>
			<fieldset class="ymove-themes">
				<legend class="screen-reader-text"><?php esc_html_e( 'Calculator style', 'ymove-nutrition' ); ?></legend>
				<?php
				$theme_notes = array(
					'classic'   => __( 'The original card. Works with the light, dark, soft and bold colour schemes.', 'ymove-nutrition' ),
					'ios'       => __( 'Grouped inset lists, system blue, segmented control.', 'ymove-nutrition' ),
					'minimal'   => __( 'Monochrome, hairline rules, underlined fields.', 'ymove-nutrition' ),
					'gradient'  => __( 'Pill fields and a soft colour wash. Pick the colours below.', 'ymove-nutrition' ),
					'brutalist' => __( 'Thick ink borders, hard shadows, acid lime.', 'ymove-nutrition' ),
				);
				foreach ( widget_themes() as $key => $label ) :
					?>
				<label class="ymove-theme ymove-theme-<?php echo esc_attr( $key ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>[widget_theme]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['widget_theme'], $key ); ?>>
					<span class="ymove-theme-preview" aria-hidden="true">
						<span class="ymove-tp-seg"><i></i><i></i></span>
						<span class="ymove-tp-field"></span><span class="ymove-tp-field"></span>
						<span class="ymove-tp-btn"><?php esc_html_e( 'Calculate', 'ymove-nutrition' ); ?></span>
						<span class="ymove-tp-big">1,698</span>
					</span>
					<strong><?php echo esc_html( $label ); ?></strong>
					<span class="description"><?php echo esc_html( $theme_notes[ $key ] ?? '' ); ?></span>
				</label>
				<?php endforeach; ?>
			</fieldset>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Gradient colours', 'ymove-nutrition' ); ?></th>
					<td><fieldset class="ymove-palettes">
					<?php foreach ( gradient_palettes() as $key => $pal ) : ?>
						<label class="ymove-palette"><input type="radio" name="<?php echo esc_attr( $name ); ?>[gradient_palette]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['gradient_palette'], $key ); ?>>
							<span class="ymove-palette-swatch" style="background:linear-gradient(90deg,<?php echo esc_attr( $pal[1] . ',' . $pal[2] . ',' . $pal[3] ); ?>)"></span>
							<span><?php echo esc_html( $pal[0] ); ?></span></label>
					<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Used by the Gradient style.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><label for="ymove-accent"><?php esc_html_e( 'Accent colour', 'ymove-nutrition' ); ?></label></th>
					<td><input type="text" id="ymove-accent" class="ymove-color" name="<?php echo esc_attr( $name ); ?>[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>">
					<p class="description"><?php esc_html_e( 'Buttons and highlights in the Classic and iOS styles, and the lime in Neo-brutalist. Leave empty for each style\'s own colour. A block can override it in its sidebar. Text on the colour switches between dark and white automatically.', 'ymove-nutrition' ); ?></p></td></tr>
			</table>
			<p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[theme_fonts]" value="1" <?php checked( $s['theme_fonts'] ); ?>> <?php esc_html_e( 'Use the Gradient and Neo-brutalist web fonts (DM Sans, Space Grotesk, Archivo Black). They ship with the plugin; nothing loads from Google.', 'ymove-nutrition' ); ?></label>
			<br><span class="description"><?php esc_html_e( 'Off by default: loading them sends visitor IP addresses to Google. Without them the styles fall back to system fonts.', 'ymove-nutrition' ); ?></span></p>

			<h2><?php esc_html_e( 'Calculator: activity levels and goals', 'ymove-nutrition' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These apply to every calculator on the site. TDEE = BMR x activity multiplier; a goal adds or removes kcal per day. Leave a label empty to drop that activity level.', 'ymove-nutrition' ); ?></p>
			<div class="ymove-card ymove-coeffs">
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity level label', 'ymove-nutrition' ); ?></th><th style="width:120px"><?php esc_html_e( 'Multiplier', 'ymove-nutrition' ); ?></th></tr></thead><tbody>
				<?php
				$acts = Settings::activities();
				while ( count( $acts ) < 7 ) { $acts[] = array( 'label' => '', 'factor' => '' ); }
				foreach ( $acts as $i => $act ) : ?>
					<tr><td><input type="text" class="large-text" name="<?php echo esc_attr( $name ); ?>[activity_levels][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $act['label'] ); ?>" placeholder="<?php esc_attr_e( '(unused)', 'ymove-nutrition' ); ?>"></td>
						<td><input type="number" step="0.001" min="1" max="2.5" class="small-text" name="<?php echo esc_attr( $name ); ?>[activity_levels][<?php echo (int) $i; ?>][factor]" value="<?php echo esc_attr( $act['factor'] ); ?>"></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<table class="widefat striped" style="margin-top:1rem"><thead><tr><th style="width:60px"><?php esc_html_e( 'On', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Goal label', 'ymove-nutrition' ); ?></th><th style="width:140px"><?php esc_html_e( 'kcal / day', 'ymove-nutrition' ); ?></th></tr></thead><tbody>
				<?php foreach ( Settings::goals() as $i => $g ) : ?>
					<tr><td><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[goals][<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( $g['enabled'] ); ?>></td>
						<td><input type="text" class="large-text" name="<?php echo esc_attr( $name ); ?>[goals][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $g['label'] ); ?>"></td>
						<td><input type="number" step="10" min="-1500" max="1500" class="small-text" name="<?php echo esc_attr( $name ); ?>[goals][<?php echo (int) $i; ?>][delta]" value="<?php echo esc_attr( $g['delta'] ); ?>"></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<p class="description"><?php esc_html_e( 'Per block you can also hide the goal selector, hide the unit switch, pick the formula, show BMI, and choose a template, style and colour scheme - in the block settings sidebar or as shortcode attributes.', 'ymove-nutrition' ); ?></p>
			</div>

			<h2><?php esc_html_e( 'Who can use the tracker', 'ymove-nutrition' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Access', 'ymove-nutrition' ); ?></th>
					<td><fieldset>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[tracker_access]" value="logged_in" <?php checked( $s['tracker_access'], 'logged_in' ); ?>> <?php esc_html_e( 'Any logged-in user', 'ymove-nutrition' ); ?></label><br>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[tracker_access]" value="roles" <?php checked( $s['tracker_access'], 'roles' ); ?>> <?php esc_html_e( 'Only these roles:', 'ymove-nutrition' ); ?></label>
						<div class="ymove-roles">
						<?php foreach ( wp_roles()->roles as $slug => $role ) : ?>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[tracker_roles][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $s['tracker_roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $role['name'] ) ); ?></label>
						<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Membership plugins restrict the page; this restricts the data routes too. Developers can also filter ymove_user_can_track.', 'ymove-nutrition' ); ?></p>
					</fieldset></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Per-member limits', 'ymove-nutrition' ); ?></th>
					<td class="ymove-limits">
						<label><?php esc_html_e( 'Searches / hour', 'ymove-nutrition' ); ?> <input type="number" min="0" name="<?php echo esc_attr( $name ); ?>[limit_search_hour]" value="<?php echo esc_attr( $s['limit_search_hour'] ); ?>" class="small-text"></label>
						<label><?php esc_html_e( 'Barcodes / hour', 'ymove-nutrition' ); ?> <input type="number" min="0" name="<?php echo esc_attr( $name ); ?>[limit_barcode_hour]" value="<?php echo esc_attr( $s['limit_barcode_hour'] ); ?>" class="small-text"></label>
						<label><?php esc_html_e( 'Photos / day', 'ymove-nutrition' ); ?> <input type="number" min="0" name="<?php echo esc_attr( $name ); ?>[limit_photo_day]" value="<?php echo esc_attr( $s['limit_photo_day'] ); ?>" class="small-text"></label>
						<label><?php esc_html_e( 'Text logs / day', 'ymove-nutrition' ); ?> <input type="number" min="0" name="<?php echo esc_attr( $name ); ?>[limit_text_day]" value="<?php echo esc_attr( $s['limit_text_day'] ); ?>" class="small-text"></label>
						<label><?php esc_html_e( 'Meal plans / day', 'ymove-nutrition' ); ?> <input type="number" min="0" name="<?php echo esc_attr( $name ); ?>[limit_mealplan_day]" value="<?php echo esc_attr( $s['limit_mealplan_day'] ); ?>" class="small-text"></label>
						<p class="description"><?php esc_html_e( 'Protects your API quota. 0 = no limit. Cached lookups never count.', 'ymove-nutrition' ); ?></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Meal plan generator', 'ymove-nutrition' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Visitors', 'ymove-nutrition' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[mealplan_public]" value="1" <?php checked( $s['mealplan_public'] ); ?>> <?php esc_html_e( 'Let visitors who are not logged in generate meal plans (lead magnet). Members follow the tracker access rules above.', 'ymove-nutrition' ); ?></label>
					<p><label><?php esc_html_e( 'Plans per visitor per day', 'ymove-nutrition' ); ?> <input type="number" min="1" name="<?php echo esc_attr( $name ); ?>[limit_mealplan_ip_day]" value="<?php echo esc_attr( $s['limit_mealplan_ip_day'] ); ?>" class="small-text"></label>
					<span class="description"><?php esc_html_e( 'Per IP address. Identical requests are served from cache for 6 hours and do not count.', 'ymove-nutrition' ); ?></span></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Take the plan home', 'ymove-nutrition' ); ?></th>
					<td><fieldset>
					<?php
					$deliveries = array(
						'both'  => __( 'Email and PDF', 'ymove-nutrition' ),
						'email' => __( 'Email only - every address becomes a lead (lead magnet)', 'ymove-nutrition' ),
						'pdf'   => __( 'PDF only - instant download, no email asked', 'ymove-nutrition' ),
						'off'   => __( 'Neither - the plan stays on the page', 'ymove-nutrition' ),
					);
					foreach ( $deliveries as $key => $label ) :
						?>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mealplan_delivery]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['mealplan_delivery'], $key ); ?>> <?php echo esc_html( $label ); ?></label><br>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Both include every recipe in full (photo, ingredients, method). Emailed plans use the results email settings below (consent, spam protection, call-to-action) and appear under Leads. The PDF opens a print view; visitors choose "Save as PDF".', 'ymove-nutrition' ); ?></p>
					</fieldset></td></tr>
			</table>

			<h2><?php esc_html_e( 'Recipes', 'ymove-nutrition' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Visitors', 'ymove-nutrition' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[recipes_public]" value="1" <?php checked( $s['recipes_public'] ); ?>> <?php esc_html_e( 'Let visitors who are not logged in browse and search recipes in the Recipes block. Single embedded recipes always show.', 'ymove-nutrition' ); ?></label>
					<p><label><?php esc_html_e( 'Recipe lookups per visitor per day', 'ymove-nutrition' ); ?> <input type="number" min="1" name="<?php echo esc_attr( $name ); ?>[limit_recipes_ip_day]" value="<?php echo esc_attr( $s['limit_recipes_ip_day'] ); ?>" class="small-text"></label>
					<span class="description"><?php esc_html_e( 'Per IP address. Searches are cached for a day and recipes for a week, so repeat views cost no API calls.', 'ymove-nutrition' ); ?></span></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Links and credit', 'ymove-nutrition' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Source links', 'ymove-nutrition' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[source_links]" value="1" <?php checked( $s['source_links'] ); ?>> <?php esc_html_e( 'Show where the numbers come from under each block (formula explanation, USDA / Open Food Facts data source).', 'ymove-nutrition' ); ?></label></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Support Your Move', 'ymove-nutrition' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[credit_link]" value="1" <?php checked( $s['credit_link'] ); ?>> <?php esc_html_e( 'Show a small "Nutrition API by Your Move" credit under the blocks and in results emails. Optional - it helps keep the free calculator free.', 'ymove-nutrition' ); ?></label></td></tr>
			</table>

			<h2><?php esc_html_e( 'Calculator leads', 'ymove-nutrition' ); ?></h2>
			<p class="description"><?php esc_html_e( 'When a calculator block has email capture enabled, visitors who enter their email get their results by email and you get a lead. Choose "email required" on the block to gate the results behind the address.', 'ymove-nutrition' ); ?></p>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="ymove-lead-subject"><?php esc_html_e( 'Results email subject', 'ymove-nutrition' ); ?></label></th>
					<td><input type="text" id="ymove-lead-subject" name="<?php echo esc_attr( $name ); ?>[lead_subject]" value="<?php echo esc_attr( $s['lead_subject'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Your calorie results from {site}', 'ymove-nutrition' ); ?>">
					<p class="description"><?php esc_html_e( 'Placeholders: {site}, {target}.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><label for="ymove-lead-intro"><?php esc_html_e( 'Results email intro', 'ymove-nutrition' ); ?></label></th>
					<td><textarea id="ymove-lead-intro" name="<?php echo esc_attr( $name ); ?>[lead_intro]" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Here are the numbers you calculated. Save this email so you have them handy.', 'ymove-nutrition' ); ?>"><?php echo esc_textarea( $s['lead_intro'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Your site logo (Appearance > Customize > Site Identity) is shown at the top of the email automatically.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Call to action button', 'ymove-nutrition' ); ?></th>
					<td><input type="text" name="<?php echo esc_attr( $name ); ?>[lead_cta_label]" value="<?php echo esc_attr( $s['lead_cta_label'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Book a free intake call', 'ymove-nutrition' ); ?>">
					<input type="url" name="<?php echo esc_attr( $name ); ?>[lead_cta_url]" value="<?php echo esc_attr( $s['lead_cta_url'] ); ?>" class="regular-text" placeholder="https://">
					<p class="description"><?php esc_html_e( 'Shown as a button under the results in the email. Leave empty for no button.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><label for="ymove-lead-consent"><?php esc_html_e( 'Consent checkbox text', 'ymove-nutrition' ); ?></label></th>
					<td><input type="text" id="ymove-lead-consent" name="<?php echo esc_attr( $name ); ?>[lead_consent_text]" value="<?php echo esc_attr( $s['lead_consent_text'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'I agree to receive my results and occasional tips by email.', 'ymove-nutrition' ); ?>">
					<p class="description"><?php esc_html_e( 'When filled in, a required checkbox with this text appears next to the email field (GDPR). Empty = no checkbox.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><label for="ymove-lead-email"><?php esc_html_e( 'Notify me at', 'ymove-nutrition' ); ?></label></th>
					<td><input type="email" id="ymove-lead-email" name="<?php echo esc_attr( $name ); ?>[lead_notify_email]" value="<?php echo esc_attr( $s['lead_notify_email'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'One email per lead. Leave empty to only see them under the Leads tab.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Spam protection', 'ymove-nutrition' ); ?></th>
					<td><select name="<?php echo esc_attr( $name ); ?>[captcha_provider]" id="ymove-captcha-provider">
						<option value="" <?php selected( $s['captcha_provider'], '' ); ?>><?php esc_html_e( 'Built-in only (honeypot + rate limit, no third-party script)', 'ymove-nutrition' ); ?></option>
						<option value="turnstile" <?php selected( $s['captcha_provider'], 'turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile (recommended, no cookies)', 'ymove-nutrition' ); ?></option>
						<option value="recaptcha" <?php selected( $s['captcha_provider'], 'recaptcha' ); ?>><?php esc_html_e( 'Google reCAPTCHA v3', 'ymove-nutrition' ); ?></option>
					</select>
					<p><input type="text" name="<?php echo esc_attr( $name ); ?>[captcha_site_key]" value="<?php echo esc_attr( $s['captcha_site_key'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Site key', 'ymove-nutrition' ); ?>" autocomplete="off">
					<input type="password" name="<?php echo esc_attr( $name ); ?>[captcha_secret]" value="" class="regular-text" placeholder="<?php echo $s['captcha_secret'] ? esc_attr__( 'Secret key saved - enter to replace', 'ymove-nutrition' ) : esc_attr__( 'Secret key', 'ymove-nutrition' ); ?>" autocomplete="off"></p>
					<p class="description"><?php esc_html_e( 'Only loads on pages with an email form. Both providers add a script from Google or Cloudflare to those pages - mention it in your privacy policy.', 'ymove-nutrition' ); ?></p></td></tr>
				<tr><th scope="row"><label for="ymove-lead-webhook"><?php esc_html_e( 'Webhook URL', 'ymove-nutrition' ); ?></label></th>
					<td><input type="url" id="ymove-lead-webhook" name="<?php echo esc_attr( $name ); ?>[lead_webhook]" value="<?php echo esc_attr( $s['lead_webhook'] ); ?>" class="large-text" placeholder="https://hooks.zapier.com/hooks/catch/...">
					<p class="description"><?php esc_html_e( 'Every lead is POSTed as JSON (email, consent, results, page). Works with Zapier, Make, n8n and any CRM that accepts a webhook - Mailchimp, ConvertKit, HubSpot, ActiveCampaign included.', 'ymove-nutrition' ); ?></p></td></tr>
			</table>


			<h2><?php esc_html_e( 'Data', 'ymove-nutrition' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="ymove-retention"><?php esc_html_e( 'Delete diary entries older than', 'ymove-nutrition' ); ?></label></th>
					<td><input type="number" min="0" id="ymove-retention" name="<?php echo esc_attr( $name ); ?>[retention_days]" value="<?php echo esc_attr( $s['retention_days'] ); ?>" class="small-text"> <?php esc_html_e( 'days (0 = keep forever)', 'ymove-nutrition' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'On uninstall', 'ymove-nutrition' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Delete all diaries, targets, leads and settings when the plugin is deleted.', 'ymove-nutrition' ); ?></label></td></tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<div class="ymove-card" id="ymove-mail">
			<h3 style="margin-top:0"><?php esc_html_e( 'Test email delivery', 'ymove-nutrition' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Results emails and lead notifications are sent by this site\'s own mail (wp_mail). If they do not arrive, install an SMTP plugin such as WP Mail SMTP or FluentSMTP.', 'ymove-nutrition' ); ?></p>
			<?php self::test_mail_notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ymove_test_mail">
				<?php wp_nonce_field( 'ymove_test_mail' ); ?>
				<p><input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" class="regular-text" required>
				<button type="submit" class="button"><?php esc_html_e( 'Send a test results email', 'ymove-nutrition' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'Check the spam folder too.', 'ymove-nutrition' ); ?></p>
			</form>
			<?php $recent = Mailer::recent(); ?>
			<?php if ( $recent ) : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'When', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Type', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'To', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Result', 'ymove-nutrition' ); ?></th></tr></thead><tbody>
			<?php foreach ( array_slice( $recent, 0, 8 ) as $row ) : ?>
				<tr><td><?php echo esc_html( human_time_diff( (int) $row['time'] ) ); ?></td><td><?php echo esc_html( $row['context'] ); ?></td><td><?php echo esc_html( $row['to'] ); ?></td>
				<td><?php echo $row['ok'] ? '<span class="ymove-ok">' . esc_html__( 'Sent', 'ymove-nutrition' ) . '</span>' : '<span class="ymove-warn">' . esc_html( $row['error'] ?: __( 'Failed', 'ymove-nutrition' ) ) . '</span>'; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p class="description"><?php esc_html_e( '"Sent" means the mail server accepted it; it can still land in spam. If visitors do not receive results, set up an SMTP plugin.', 'ymove-nutrition' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Send a sample results email through the configured transport.
	 */
	public static function test_mail(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ymove-nutrition' ) );
		}
		check_admin_referer( 'ymove_test_mail' );
		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		if ( ! is_email( $to ) ) {
			$result = array( 'ok' => false, 'via' => '', 'error' => __( 'Enter a valid email address.', 'ymove-nutrition' ) );
		} else {
			$sample = Leads::results_message( array( 'target' => 1698, 'bmr' => 1418, 'tdee' => 2198, 'protein_g' => 127, 'carbs_g' => 170, 'fat_g' => 57, 'goal' => 'lose', 'formula' => 'mifflin' ) );
			$result = Mailer::send( array_merge( $sample, array(
				'to'      => $to,
				/* translators: %s: site name */
				'subject' => sprintf( __( '[%s] Test: calculator results email', 'ymove-nutrition' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
				'context' => 'test',
			) ) );
		}
		set_transient( 'ymove_test_mail_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE . '#ymove-mail' ) );
		exit;
	}

	private static function test_mail_notice(): void {
		$key    = 'ymove_test_mail_' . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( $key );
		if ( $result['ok'] ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Test email sent. Check the inbox (and spam folder).', 'ymove-nutrition' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Test email failed:', 'ymove-nutrition' ) . ' ' . esc_html( $result['error'] ) . '</p></div>';
		}
	}

	private static function render_usage(): void {
		if ( ! Api_Client::is_connected() ) {
			echo '<p>' . esc_html__( 'Connect an API key to see usage.', 'ymove-nutrition' ) . '</p>';
			return;
		}
		?>
		<div class="ymove-card">
			<h2><?php esc_html_e( 'Plan', 'ymove-nutrition' ); ?> <button type="button" class="button button-small" id="ymove-usage-refresh"><?php esc_html_e( 'Refresh', 'ymove-nutrition' ); ?></button></h2>
			<div id="ymove-usage-plan"><?php esc_html_e( 'Loading...', 'ymove-nutrition' ); ?></div>
		</div>
		<div class="ymove-card">
			<h2><?php esc_html_e( 'Calls made by this site (last 30 days)', 'ymove-nutrition' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Counted locally when a request actually reaches the API. Cached searches and barcodes are free and not counted.', 'ymove-nutrition' ); ?></p>
			<div id="ymove-usage-chart" class="ymove-chart"></div>
			<table class="widefat striped" id="ymove-usage-table"><thead><tr>
				<th><?php esc_html_e( 'Day', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Search', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Barcode', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Food detail', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Photo', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Text', 'ymove-nutrition' ); ?></th>
			</tr></thead><tbody></tbody></table>
		</div>
		<?php
	}

	private static function render_help(): void {
		$rows = array(
			array( __( 'Calorie Calculator', 'ymove-nutrition' ), '[ymove_calculator title="" layout="card" theme="" scheme="default" formula="mifflin" goal="maintain" units="" showmacros="1" showgoal="1" showbmi="0" hideunits="0" instant="0" leadmode="off" accentcolor="#2563eb"]', __( 'Free. layout: card | plain | split | steps | chat. theme: classic | ios | minimal | gradient | brutalist (empty = site default). palette (gradient): glacier | ocean | slate | aurora | mint | sunset. scheme (classic only): default | dark | soft | bold. formula: mifflin | harris | katch. leadmode: off | optional | required.', 'ymove-nutrition' ) ),
			array( __( 'BMI Calculator', 'ymove-nutrition' ), '[ymove_bmi title="" layout="card" theme="" scheme="default" units="" hideunits="0" leadmode="off"]', __( 'Free. Adult BMI with WHO categories.', 'ymove-nutrition' ) ),
			array( __( 'Nutrition Facts', 'ymove-nutrition' ), '[ymove_nutrition food="FOOD_ID" per="serving" schema="1"]', __( 'Needs a key. Pick the food in the block editor; the shortcode takes a food id from the search.', 'ymove-nutrition' ) ),
			array( __( 'Calorie Tracker', 'ymove-nutrition' ), '[ymove_tracker showphoto="1" showbarcode="1" showweek="1"]', __( 'Needs a key and a logged-in member. Put it on a members-only page.', 'ymove-nutrition' ) ),
			array( __( 'Barcode Lookup', 'ymove-nutrition' ), '[ymove_barcode]', __( 'Needs a key and a logged-in member. Scan a product and show its label.', 'ymove-nutrition' ) ),
			array( __( 'Meal Plan Generator', 'ymove-nutrition' ), '[ymove_meal_plan diet="high_protein" days="3" meals="4" calories="2100"]', __( 'Needs a key. Members by default; open it to visitors under Settings. Meals can be added to the diary.', 'ymove-nutrition' ) ),
		);
		?>
		<div class="ymove-card">
			<p><?php esc_html_e( 'Every block is also available as a shortcode for classic editors and page builders.', 'ymove-nutrition' ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Block', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Shortcode', 'ymove-nutrition' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $rows as $r ) : ?>
				<tr><td><?php echo esc_html( $r[0] ); ?></td><td><code><?php echo esc_html( $r[1] ); ?></code></td><td><?php echo esc_html( $r[2] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<h3><?php esc_html_e( 'Elementor, Divi, Beaver Builder, Bricks, classic editor', 'ymove-nutrition' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Copy a shortcode from the table above and adjust the attributes (all optional).', 'ymove-nutrition' ); ?></li>
				<li><strong>Elementor:</strong> <?php esc_html_e( 'drag the "Shortcode" widget (or "Text Editor") into your section and paste it. Works in the free version; no Elementor Pro needed.', 'ymove-nutrition' ); ?></li>
				<li><strong>Divi:</strong> <?php esc_html_e( 'add a "Code" or "Text" module and paste it.', 'ymove-nutrition' ); ?></li>
				<li><strong>Beaver Builder / Bricks / WPBakery:</strong> <?php esc_html_e( 'use the HTML, Shortcode or Text module.', 'ymove-nutrition' ); ?></li>
				<li><strong><?php esc_html_e( 'Classic editor / widgets:', 'ymove-nutrition' ); ?></strong> <?php esc_html_e( 'paste it straight into the content or a Text widget.', 'ymove-nutrition' ); ?></li>
				<li><strong>PHP:</strong> <code>echo do_shortcode( '[ymove_calculator layout="split"]' );</code></li>
			</ol>
			<p class="description"><?php esc_html_e( 'The block and the shortcode render identical HTML, so styling and lead capture behave the same. Use the "split" layout in a full-width section, "steps" in a narrow column.', 'ymove-nutrition' ); ?></p>
			<h3><?php esc_html_e( 'Developer hooks', 'ymove-nutrition' ); ?></h3>
			<ul>
				<li><code>ymove_user_can_track( bool $allowed, int $user_id )</code> - <?php esc_html_e( 'decide who may use the tracker, e.g. tie it to a membership level.', 'ymove-nutrition' ); ?></li>
				<li><code>ymove_show_source_links( bool $show )</code> - <?php esc_html_e( 'hide or show the source lines under the blocks.', 'ymove-nutrition' ); ?></li>
				<li><code>ymove_calculator_lead( string $email, array $results, string $page )</code> - <?php esc_html_e( 'fires when a visitor emails themselves their results.', 'ymove-nutrition' ); ?></li>
				<li><code>define( 'YMOVE_API_KEY', '...' )</code> - <?php esc_html_e( 'set the key in wp-config.php instead of the database.', 'ymove-nutrition' ); ?></li>
			</ul>
			<p><a href="<?php echo esc_url( ymove_url( 'nutrition-api/wordpress-plugin/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Full documentation', 'ymove-nutrition' ); ?></a></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ Leads */

	private static function render_leads(): void {
		$leads = DB::leads( 200 );
		$total = DB::leads_count();
		?>
		<div class="ymove-card">
			<p>
				<?php echo esc_html( sprintf( /* translators: %d: number of leads */ _n( '%d lead', '%d leads', $total, 'ymove-nutrition' ), $total ) ); ?>
				<?php if ( $total ) : ?>
					&middot; <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ymove_leads_csv' ), 'ymove_leads_csv' ) ); ?>"><?php esc_html_e( 'Download CSV', 'ymove-nutrition' ); ?></a>
				<?php endif; ?>
			</p>
			<table class="widefat striped"><thead><tr>
				<th><?php esc_html_e( 'Date', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Email', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Goal', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Target kcal', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'BMI', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Consent', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Page', 'ymove-nutrition' ); ?></th><th></th>
			</tr></thead><tbody>
			<?php if ( ! $leads ) : ?><tr><td colspan="8"><?php esc_html_e( 'No leads yet. Enable email capture on a calculator block.', 'ymove-nutrition' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $leads as $l ) : $r = $l['results']; ?>
				<tr>
					<td><?php echo esc_html( get_date_from_gmt( $l['created_at'], 'Y-m-d H:i' ) ); ?></td>
					<td><a href="mailto:<?php echo esc_attr( $l['email'] ); ?>"><?php echo esc_html( $l['email'] ); ?></a></td>
					<td><?php echo esc_html( str_replace( '_', ' ', $r['goal'] ?? '' ) ); ?></td>
					<td><?php echo esc_html( isset( $r['target'] ) ? num( $r['target'] ) : '' ); ?></td>
					<td><?php echo esc_html( isset( $r['bmi'] ) ? num( $r['bmi'], 1 ) : '' ); ?></td>
					<td><?php echo $l['consent'] ? '&#10003;' : '&ndash;'; ?></td>
					<td><?php echo $l['page_url'] ? '<a href="' . esc_url( $l['page_url'] ) . '">' . esc_html( wp_parse_url( $l['page_url'], PHP_URL_PATH ) ?: '/' ) . '</a>' : ''; ?></td>
					<td><a class="submitdelete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ymove_lead_delete&id=' . (int) $l['id'] ), 'ymove_lead_delete_' . (int) $l['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'ymove-nutrition' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php if ( $total > 200 ) : ?><p class="description"><?php esc_html_e( 'Showing the latest 200. The CSV contains everything.', 'ymove-nutrition' ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	public static function leads_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ymove-nutrition' ) );
		}
		check_admin_referer( 'ymove_leads_csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="calculator-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo Leads::csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function lead_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ymove-nutrition' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'ymove_lead_delete_' . $id );
		DB::delete_lead( $id );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE . '&tab=leads' ) );
		exit;
	}

	/* ---------------------------------------------------------------- Members */

	public static function render_members(): void {
		if ( ! current_user_can( 'ymove_view_member_logs' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rows = DB::members_summary();
		?>
		<div class="wrap ymove-admin">
			<h1><?php esc_html_e( 'Nutrition logs', 'ymove-nutrition' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Members who have logged food. Click a name for their last 30 days.', 'ymove-nutrition' ); ?></p>
			<div class="ymove-members">
				<table class="widefat striped"><thead><tr>
					<th><?php esc_html_e( 'Member', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Last entry', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Days logged (7d)', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Avg kcal / logged day (7d)', 'ymove-nutrition' ); ?></th><th><?php esc_html_e( 'Entries', 'ymove-nutrition' ); ?></th>
				</tr></thead><tbody>
				<?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No logs yet.', 'ymove-nutrition' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $rows as $r ) :
					$u   = get_userdata( (int) $r['user_id'] );
					$avg = $r['days_7d'] ? round( $r['kcal_7d'] / $r['days_7d'] ) : 0;
					?>
					<tr><td><a href="#" class="ymove-member" data-id="<?php echo (int) $r['user_id']; ?>"><?php echo esc_html( $u ? $u->display_name : '#' . $r['user_id'] ); ?></a></td>
						<td><?php echo esc_html( $r['last_day'] ); ?></td><td><?php echo (int) $r['days_7d']; ?></td><td><?php echo esc_html( $avg ? number_format_i18n( $avg ) : '-' ); ?></td><td><?php echo (int) $r['entries']; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<div id="ymove-member-detail" class="ymove-card" hidden></div>
			</div>
		</div>
		<?php
	}
}
