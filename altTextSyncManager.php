<?php

class AltTextSyncManager {

    public static function init_sync_manager() {
        add_action( 'add_attachment', array( __CLASS__, 'sync_single_attachment' ) );
        add_action( 'admin_post_autoalt_bulk_sync', array( __CLASS__, 'handle_bulk_request' ) );
        add_action( 'wp_ajax_autoalt_bulk_sync', array( __CLASS__, 'ajax_bulk_sync' ) );
        add_action( 'save_post', array( __CLASS__, 'sync_post_images' ) );
    }

    public static function sync_single_attachment( $attachment_id ) {
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return false;
        }
        $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( ! empty( $alt ) ) {
            return false;
        }
        $generated = self::generate_alt_text( $attachment_id );
        if ( empty( $generated ) ) {
            return false;
        }
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $generated );
        return true;
    }

    public static function sync_bulk( $attachment_ids = array(), $page = 1 ) {
        $results = array();
        if ( empty( $attachment_ids ) ) {
            $batch_size = apply_filters( 'autoalt_batch_size', 100 );
            $query = new WP_Query( array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => $batch_size,
                'paged'          => max( 1, intval( $page ) ),
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_wp_attachment_image_alt',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_wp_attachment_image_alt',
                        'value'   => '',
                        'compare' => '=',
                    ),
                ),
            ) );
            $attachment_ids = wp_list_pluck( $query->posts, 'ID' );
            foreach ( $attachment_ids as $id ) {
                $results[ $id ] = self::sync_single_attachment( $id );
            }
            $has_more  = ( $query->found_posts > $page * $batch_size );
            return array(
                'results'   => $results,
                'has_more'  => $has_more,
                'next_page' => $page + 1,
            );
        } else {
            foreach ( $attachment_ids as $id ) {
                $results[ $id ] = self::sync_single_attachment( $id );
            }
            return array(
                'results'   => $results,
                'has_more'  => false,
                'next_page' => null,
            );
        }
    }

    public static function handle_bulk_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized', '', 403 );
        }
        check_admin_referer( 'autoalt_bulk_sync' );
        $ids   = isset( $_POST['attachment_ids'] ) ? array_map( 'intval', (array) $_POST['attachment_ids'] ) : array();
        $page  = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
        self::sync_bulk( $ids, $page );
        wp_redirect( wp_get_referer() ?: admin_url() );
        exit;
    }

    public static function ajax_bulk_sync() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        check_ajax_referer( 'autoalt_bulk_sync', 'nonce' );
        $ids  = isset( $_POST['attachment_ids'] ) ? array_map( 'intval', (array) $_POST['attachment_ids'] ) : array();
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
        $data = self::sync_bulk( $ids, $page );
        wp_send_json_success( $data );
    }

    public static function sync_post_images( $post_id ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $post = get_post( $post_id );
        if ( empty( $post->post_content ) || strpos( $post->post_content, '<img' ) === false ) {
            return;
        }
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        $html = mb_convert_encoding( $post->post_content, 'HTML-ENTITIES', 'UTF-8' );
        $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        $imgs    = $dom->getElementsByTagName( 'img' );
        $updated = false;
        foreach ( $imgs as $img ) {
            $src = $img->getAttribute( 'src' );
            $alt = $img->getAttribute( 'alt' );
            if ( empty( $alt ) ) {
                $aid = attachment_url_to_postid( $src );
                if ( $aid && wp_attachment_is_image( $aid ) ) {
                    $generated = self::generate_alt_text( $aid );
                    if ( $generated ) {
                        $img->setAttribute( 'alt', $generated );
                        update_post_meta( $aid, '_wp_attachment_image_alt', $generated );
                        $updated = true;
                    }
                }
            }
        }
        if ( $updated ) {
            $body        = $dom->getElementsByTagName( 'body' )->item( 0 );
            $new_content = '';
            foreach ( $body->childNodes as $child ) {
                $new_content .= $dom->saveHTML( $child );
            }
            remove_action( 'save_post', array( __CLASS__, 'sync_post_images' ) );
            wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );
            add_action( 'save_post', array( __CLASS__, 'sync_post_images' ) );
        }
    }

    protected static function generate_alt_text( $attachment_id ) {
        $url = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) {
            error_log( "AutoAlt: failed to get URL for attachment {$attachment_id}" );
            return '';
        }
        $settings = get_option( 'autoalt_pro_settings', array() );
        $api_key  = isset( $settings['ai_api_key'] ) ? $settings['ai_api_key'] : '';
        $endpoint = isset( $settings['ai_api_endpoint'] ) ? $settings['ai_api_endpoint'] : '';
        if ( empty( $api_key ) || empty( $endpoint ) ) {
            $file = basename( get_attached_file( $attachment_id ) );
            return str_replace( array( '-', '_' ), ' ', pathinfo( $file, PATHINFO_FILENAME ) );
        }
        $args = array(
            'body'    => wp_json_encode( array( 'image_url' => $url ) ),
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 30,
        );
        $response = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $response ) ) {
            error_log( "AutoAlt: wp_remote_post error for attachment {$attachment_id}: " . $response->get_error_message() );
            return '';
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code !== 200 ) {
            error_log( "AutoAlt: remote API returned status {$code} for attachment {$attachment_id}. Body: {$body}" );
            return '';
        }
        $data = json_decode( $body, true );
        if ( empty( $data['description'] ) ) {
            error_log( "AutoAlt: no description in API response for attachment {$attachment_id}" );
            return '';
        }
        return sanitize_text_field( $data['description'] );
    }

}

AltTextSyncManager::init_sync_manager();