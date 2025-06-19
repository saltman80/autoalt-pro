<?php
namespace AutoAltPro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PlanConfirmationAiFileAdder {

    public static function init() {
        add_action( 'wp_ajax_aap_confirm_ai_plan', array( __CLASS__, 'handle_plan_confirmation' ) );
    }

    public static function handle_plan_confirmation() {
        check_ajax_referer( 'aap_confirm_ai_plan_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'autoalt-pro' ) ), 403 );
        }

        $plan_id = isset( $_POST['plan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_id'] ) ) : '';
        if ( empty( $plan_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing plan ID', 'autoalt-pro' ) ), 400 );
        }

        $default_api_url = 'https://api.ai.example.com/confirm-plan';
        $api_url = apply_filters( 'aap_ai_confirm_plan_api_url', $default_api_url, $plan_id );

        $default_args = array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array( 'plan_id' => $plan_id ) ),
            'timeout' => 15,
        );
        $request_args = apply_filters( 'aap_ai_confirm_plan_request_args', $default_args, $plan_id );

        $response = wp_remote_post( $api_url, $request_args );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ), 500 );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid API response', 'autoalt-pro' ) ), 500 );
        }

        if ( $status_code !== 200 || empty( $data['success'] ) ) {
            $error_message = isset( $data['error'] ) ? sanitize_text_field( $data['error'] ) : __( 'Plan confirmation failed', 'autoalt-pro' );
            wp_send_json_error( array( 'message' => $error_message ), $status_code );
        }

        $plan_details = array(
            'plan_id'     => $plan_id,
            'token_limit' => isset( $data['token_limit'] ) ? intval( $data['token_limit'] ) : 0,
            'model'       => isset( $data['model'] ) ? sanitize_text_field( $data['model'] ) : '',
            'expires'     => isset( $data['expires'] ) ? sanitize_text_field( $data['expires'] ) : '',
        );

        $upload_dir = wp_upload_dir();
        $base_dir   = isset( $upload_dir['basedir'] ) ? trailingslashit( $upload_dir['basedir'] ) : '';
        $dir        = $base_dir . 'autoalt-pro';

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        if ( ! $wp_filesystem->is_dir( $dir ) ) {
            if ( ! $wp_filesystem->mkdir( $dir, FS_CHMOD_DIR ) ) {
                wp_send_json_error( array( 'message' => __( 'Failed to create directory', 'autoalt-pro' ) ), 500 );
            }
        }

        $file_path = trailingslashit( $dir ) . 'ai_plan.json';
        $content   = wp_json_encode( $plan_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        if ( ! $wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE ) ) {
            wp_send_json_error( array( 'message' => __( 'Failed to write plan file', 'autoalt-pro' ) ), 500 );
        }

        wp_send_json_success( array( 'message' => __( 'Plan confirmed and file written', 'autoalt-pro' ), 'plan' => $plan_details ) );
    }
}

PlanConfirmationAiFileAdder::init();