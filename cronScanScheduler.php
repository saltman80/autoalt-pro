<?php
/**
 * Schedule a daily scan event if enabled.
 */
function autoalt_pro_schedule_daily_scan() {
    $settings = get_option( 'autoalt_pro_settings', array() );
    if ( empty( $settings['auto_scan_enabled'] ) ) {
        return;
    }
    if ( ! wp_next_scheduled( 'autoalt_pro_daily_generate' ) ) {
        wp_schedule_event( time(), 'daily', 'autoalt_pro_daily_generate' );
    }
}

/**
 * Clear the scheduled daily scan on plugin deactivation.
 */
function autoalt_pro_clear_daily_scan() {
    wp_clear_scheduled_hook( 'autoalt_pro_daily_generate' );
}

/**
 * Run the daily scan: generate bulk alt text for all missing.
 */
function autoalt_pro_run_daily_scan() {
    if ( ! class_exists( 'AutoAltPro\\Processor' ) ) {
        return;
    }
    try {
        \AutoAltPro\Processor::generateBulkAltText();
    } catch ( \Throwable $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[AutoAltPro] cron error: ' . $e->__toString() );
        }
    }
}

// Hook the scan into WP-Cron.
add_action( 'autoalt_pro_daily_generate', 'autoalt_pro_run_daily_scan' );

// Register activation and deactivation hooks against this file.
register_activation_hook( __FILE__, 'autoalt_pro_schedule_daily_scan' );
register_deactivation_hook( __FILE__, 'autoalt_pro_clear_daily_scan' );