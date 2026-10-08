<?php
/**
 * Plugin Name:       Your Move Nutrition - Calorie Calculator, Tracker & Barcode Scanner
 * Plugin URI:        https://ymove.app/nutrition-api/wordpress-plugin/
 * Description:       Free calorie, TDEE and BMI calculator blocks. Connect a Your Move API key to give logged-in members a calorie tracker with food search, barcode scanning and AI photo logging.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Your Move
 * Author URI:        https://ymove.app/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ymove-nutrition
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YMOVE_NUTRITION_VERSION', '0.1.0' );
define( 'YMOVE_NUTRITION_DB_VERSION', '2' );
define( 'YMOVE_NUTRITION_FILE', __FILE__ );
define( 'YMOVE_NUTRITION_DIR', plugin_dir_path( __FILE__ ) );
define( 'YMOVE_NUTRITION_URL', plugin_dir_url( __FILE__ ) );
define( 'YMOVE_NUTRITION_API_BASE', 'https://exercise-api.ymove.app/api/v2' );
define( 'YMOVE_NUTRITION_SITE', 'https://ymove.app' );

require_once YMOVE_NUTRITION_DIR . 'includes/helpers.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-settings.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-db.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-access.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-api-client.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-mailer.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-leads.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-meal-plans.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-recipes.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-rest.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-blocks.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-admin.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-privacy.php';
require_once YMOVE_NUTRITION_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'YMove_Nutrition\\DB', 'install' ) );

add_action( 'plugins_loaded', array( 'YMove_Nutrition\\Plugin', 'instance' ) );
