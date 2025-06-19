<?php
defined( 'WPINC' ) || die;

/**
 * Define plugin file constant if not already defined.
 */
if ( ! defined( 'AUTOALT_PRO_PLUGIN_FILE' ) ) {
    define( 'AUTOALT_PRO_PLUGIN_FILE', __FILE__ );
}

spl_autoload_register( function ( $class ) {
    $prefix   = 'AutoAltPro\\';
    $base_dir = __DIR__ . '/src/';
    $len      = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $relative_class = substr( $class, $len );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Activation, deactivation, and uninstall hooks
register_activation_hook( AUTOALT_PRO_PLUGIN_FILE, [ 'AutoAltPro\\Core\\Setup', 'activate' ] );
register_deactivation_hook( AUTOALT_PRO_PLUGIN_FILE, [ 'AutoAltPro\\Core\\Setup', 'deactivate' ] );
register_uninstall_hook( AUTOALT_PRO_PLUGIN_FILE, 'autoalt_pro_plugin_uninstall' );

// Initialize plugin on WP init
add_action( 'init', 'autoalt_pro_plugin_init' );

/**
 * Initializes the AutoAlt Pro plugin.
 */
function autoalt_pro_plugin_init() {
    \AutoAltPro\Core\Setup::init();
}

/**
 * Uninstall callback for AutoAlt Pro.
 * This function must be a top-level function for WordPress to call on uninstall.
 */
function autoalt_pro_plugin_uninstall() {
    \AutoAltPro\Core\Setup::uninstall();
}