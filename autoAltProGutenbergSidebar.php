<?php
function autoaltpro_enqueue_gutenberg_sidebar_assets() {
    $dir = __DIR__;
    $url = plugin_dir_url( __FILE__ );

    // Register sidebar script
    $asset_file = $dir . '/build/sidebar.asset.php';
    if ( file_exists( $asset_file ) ) {
        $asset = require $asset_file;
        wp_register_script(
            'autoaltpro-gutenberg-sidebar',
            $url . 'build/sidebar.js',
            $asset['dependencies'],
            $asset['version']
        );
    } else {
        wp_register_script(
            'autoaltpro-gutenberg-sidebar',
            $url . 'build/sidebar.js',
            array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ),
            filemtime( $dir . '/build/sidebar.js' )
        );
    }

    // Pass settings to the script
    $settings = array(
        'restUrl'   => esc_url_raw( rest_url( 'autoalt-pro/v1/generate' ) ),
        'restNonce' => wp_create_nonce( 'wp_rest' ),
        'pluginUrl' => esc_url_raw( $url ),
    );
    wp_add_inline_script(
        'autoaltpro-gutenberg-sidebar',
        'var autoAltProSettings = ' . wp_json_encode( $settings ) . ';'
    );

    // Enqueue the script
    wp_enqueue_script( 'autoaltpro-gutenberg-sidebar' );

    // Enqueue sidebar styles
    $style_file = $dir . '/build/sidebar.css';
    if ( file_exists( $style_file ) ) {
        wp_enqueue_style(
            'autoaltpro-gutenberg-sidebar-style',
            $url . 'build/sidebar.css',
            array(),
            filemtime( $style_file )
        );
    }
}
add_action( 'enqueue_block_editor_assets', 'autoaltpro_enqueue_gutenberg_sidebar_assets' );