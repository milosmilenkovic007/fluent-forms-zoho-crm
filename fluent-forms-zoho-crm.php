<?php
/**
 * Plugin Name: HJ Zoho + Google Ads Integration
 * Description: Fluent Forms → Zoho CRM (OAuth za EU/US/IN/AU), hvatanje GCLID/UTM, Google Ads konverzije (thank-you i onSubmit), logovi.
 * Version: 1.1.0
 * Author: milos
 */

if (!defined('ABSPATH')) { 
    exit; 
}

// Define plugin constants
if (!defined('HJ_ZOHO_ADS_VERSION')) {
    define('HJ_ZOHO_ADS_VERSION', '1.1.0');
}
if (!defined('HJ_ZOHO_ADS_PLUGIN_DIR')) {
    define('HJ_ZOHO_ADS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('HJ_ZOHO_ADS_PLUGIN_URL')) {
    define('HJ_ZOHO_ADS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Auto-loader for plugin classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'HJ_Zoho_') !== 0) {
        return;
    }
    
    // Convert class name to file name
    // HJ_Zoho_Admin -> hj-zoho-admin
    // HJ_Zoho_Ads_Integration -> hj-zoho-ads-integration
    $class_file = strtolower(str_replace('_', '-', $class));
    
    $file_paths = [
        HJ_ZOHO_ADS_PLUGIN_DIR . 'includes/class-' . $class_file . '.php',
        HJ_ZOHO_ADS_PLUGIN_DIR . 'admin/class-' . $class_file . '.php',
    ];
    
    foreach ($file_paths as $file_path) {
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
});

// Manually include required files to ensure they're loaded
$required_files = [
    HJ_ZOHO_ADS_PLUGIN_DIR . 'includes/class-hj-zoho-ads-integration.php',
    HJ_ZOHO_ADS_PLUGIN_DIR . 'admin/class-hj-zoho-admin.php',
    HJ_ZOHO_ADS_PLUGIN_DIR . 'includes/class-hj-zoho-crm.php',
    HJ_ZOHO_ADS_PLUGIN_DIR . 'includes/class-hj-zoho-tracking.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        wp_die('Missing required file: ' . $file);
    }
}

// Initialize the plugin
function hj_zoho_ads_init() {
    if (!class_exists('HJ_Zoho_Ads_Integration')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>HJ Zoho Plugin Error: Main class not found. Please check file permissions.</p></div>';
        });
        return;
    }
    
    // Check if all required classes are available
    $required_classes = ['HJ_Zoho_Admin', 'HJ_Zoho_CRM', 'HJ_Zoho_Tracking'];
    foreach ($required_classes as $class) {
        if (!class_exists($class)) {
            add_action('admin_notices', function() use ($class) {
                echo '<div class="notice notice-error"><p>HJ Zoho Plugin Error: Required class ' . $class . ' not found.</p></div>';
            });
            return;
        }
    }
    
    new HJ_Zoho_Ads_Integration();
}
add_action('plugins_loaded', 'hj_zoho_ads_init', 10);

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary database tables or options if needed
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});