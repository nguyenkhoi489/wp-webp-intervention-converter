<?php
/**
 * Plugin Name: WP WebP Intervention Converter
 * Plugin URI: https://example.com/wp-webp-intervention-converter
 * Description: Converts JPG/PNG images to WebP format using Intervention Image v3 with file size optimization.
 * Version: 1.0.0
 * Author: Nguyên Khôi
 * Author URI: https://nguyenkhoi.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-webp-intervention-converter
 * Requires PHP: 8.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_WEBP_CONVERTER_VERSION', '1.0.0');
define('WP_WEBP_CONVERTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_WEBP_CONVERTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader for Intervention Image v3
require_once __DIR__ . '/vendor/autoload.php';

// Include plugin classes
require_once WP_WEBP_CONVERTER_PLUGIN_DIR . 'includes/class-webp-settings.php';
require_once WP_WEBP_CONVERTER_PLUGIN_DIR . 'includes/class-webp-converter.php';

/**
 * Main plugin initialization
 */
class WP_WebP_Intervention_Converter {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize plugin
     */
    private function __construct() {
        // Initialize settings page
        new WebP_Settings();
        
        // Initialize converter
        new WebP_Converter();
    }
}

/**
 * Plugin activation hook
 */
function wp_webp_converter_activate(): void {
    // Set default options
    $defaults = [
        'enable_auto_convert' => true,
        'default_quality' => 80,
        'max_file_size' => 200, // KB
    ];
    
    foreach ($defaults as $key => $value) {
        if (get_option("webp_converter_{$key}") === false) {
            add_option("webp_converter_{$key}", $value);
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'wp_webp_converter_activate');

/**
 * Plugin deactivation hook
 */
function wp_webp_converter_deactivate(): void {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'wp_webp_converter_deactivate');

// Initialize the plugin
WP_WebP_Intervention_Converter::get_instance();
