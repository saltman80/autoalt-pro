<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutoAltPro {
    const VERSION     = '1.0.0';
    const TEXT_DOMAIN = 'autoalt-pro';

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_autoalt_scan_media', [ $this, 'ajax_scan_media' ] );
        add_action( 'wp_ajax_autoalt_scan_post', [ $this, 'ajax_scan_post' ] );
        add_action( 'wp_ajax_autoalt_generate_alt', [ $this, 'ajax_generate_alt' ] );
        add_action( 'wp_ajax_autoalt_bulk_generate', [ $this, 'ajax_bulk_generate' ] );
    }

    private function define_constants() {
        define( 'AUTOALTPRO_VERSION', self::VERSION );
        define( 'AUTOALTPRO_DIR',     plugin_dir_path( __FILE__ ) );
        define( 'AUTOALTPRO_URL',     plugin_dir_url( __FILE__ ) );
    }

    private function includes() {
        require_once AUTOALTPRO_DIR . 'includes/class-autoaltpro-api.php';
        require_once AUTOALTPRO_DIR . 'includes/class-autoaltpro-admin.php';
    }

    public function load_textdomain() {
        load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function add_admin_pages() {
        add_menu_page(
            __( 'AutoAlt Pro', self::TEXT_DOMAIN ),
            __( 'AutoAlt Pro', self::TEXT_DOMAIN ),
            'manage_options',
            'autoaltpro_dashboard',
            [ 'AutoAltPro_Admin', 'render_dashboard' ],
            'dashicons-format-image',
            60
        );
        add_submenu_page(
            'autoaltpro_dashboard',
            __( 'Settings', self::TEXT_DOMAIN ),
            __( 'Settings', self::TEXT_DOMAIN ),
            'manage_options',
            'autoaltpro_settings',
            [ 'AutoAltPro_Admin', 'render_settings' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'autoaltpro' ) === false ) {
            return;
        }
        wp_enqueue_style( 'autoaltpro-admin', AUTOALTPRO_URL . 'assets/css/admin.css', [], self::VERSION );
        wp_enqueue_script( 'autoaltpro-admin', AUTOALTPRO_URL . 'assets/js/admin.js', [ 'jquery' ], self::VERSION, true );
        wp_localize_script( 'autoaltpro-admin', 'AutoAltPro', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'autoalt_nonce' ),
        ] );
    }

    public function ajax_scan_media() {
        check_ajax_referer( 'autoalt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', self::TEXT_DOMAIN ) ], 403 );
        }
        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 20;
        $api   = new AutoAltPro_API();
        $data  = $api->scan_media_library( $limit );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( [ 'message' => $data->get_error_message() ] );
        }
        wp_send_json_success( [ 'data' => $data ] );
    }

    public function ajax_scan_post() {
        check_ajax_referer( 'autoalt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', self::TEXT_DOMAIN ) ], 403 );
        }
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID', self::TEXT_DOMAIN ) ] );
        }
        $api    = new AutoAltPro_API();
        $result = $api->scan_post_content( $post_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [ 'data' => $result ] );
    }

    public function ajax_generate_alt() {
        check_ajax_referer( 'autoalt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', self::TEXT_DOMAIN ) ], 403 );
        }
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        if ( ! $attachment_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid attachment ID', self::TEXT_DOMAIN ) ] );
        }
        $api      = new AutoAltPro_API();
        $alt_text = $api->generate_alt_text( $attachment_id );
        if ( is_wp_error( $alt_text ) ) {
            wp_send_json_error( [ 'message' => $alt_text->get_error_message() ] );
        }
        wp_send_json_success( [ 'alt' => $alt_text ] );
    }

    public function ajax_bulk_generate() {
        check_ajax_referer( 'autoalt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', self::TEXT_DOMAIN ) ], 403 );
        }
        $attachment_ids = isset( $_POST['attachment_ids'] ) && is_array( $_POST['attachment_ids'] ) ? array_map( 'intval', $_POST['attachment_ids'] ) : [];
        if ( empty( $attachment_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'No attachments specified', self::TEXT_DOMAIN ) ] );
        }
        $api     = new AutoAltPro_API();
        $results = $api->bulk_generate_alt_text( $attachment_ids );
        wp_send_json_success( [ 'data' => $results ] );
    }
}

AutoAltPro::instance();