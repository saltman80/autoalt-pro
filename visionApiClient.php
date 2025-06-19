<?php

class VisionApiClient {
    const DEFAULT_ENDPOINT = 'https://vision.googleapis.com/v1/images:annotate';

    /**
     * Generate a descriptive alt text for an image URL using the Vision API.
     *
     * @param string $image_url The URL of the image to describe.
     * @return string|WP_Error Description on success, WP_Error on failure.
     */
    public static function generate_description( $image_url ) {
        $image_url = trim( $image_url );
        if ( empty( $image_url ) || false === filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error(
                'invalid_image_url',
                __( 'Invalid image URL provided.', 'autoalt-pro' )
            );
        }

        $settings = get_option( 'autoalt_pro_settings', array() );
        $api_key = isset( $settings['ai_api_key'] ) ? trim( $settings['ai_api_key'] ) : '';
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'no_api_key',
                __( 'Vision API key is missing.', 'autoalt-pro' )
            );
        }

        $endpoint = defined( 'AUTOALTPRO_VISION_API_ENDPOINT' ) ? AUTOALTPRO_VISION_API_ENDPOINT : self::DEFAULT_ENDPOINT;
        $url      = esc_url_raw( add_query_arg( 'key', $api_key, $endpoint ) );

        $payload = array(
            'requests' => array(
                array(
                    'image'    => array(
                        'source' => array(
                            'imageUri' => esc_url_raw( $image_url ),
                        ),
                    ),
                    'features' => array(
                        array(
                            'type'       => 'LABEL_DETECTION',
                            'maxResults' => 5,
                        ),
                    ),
                ),
            ),
        );

        $response = wp_remote_post(
            $url,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $json_error = json_last_error_msg();
            return new WP_Error(
                'json_parse_error',
                sprintf( __( 'Error parsing JSON response: %s', 'autoalt-pro' ), $json_error ),
                array( 'response_body' => $body )
            );
        }

        if ( 200 !== $code || empty( $data ) ) {
            $message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error from Vision API.', 'autoalt-pro' );
            return new WP_Error(
                'vision_api_error',
                $message,
                array( 'status' => $code )
            );
        }

        if ( ! empty( $data['responses'][0]['labelAnnotations'][0]['description'] ) ) {
            return sanitize_text_field( $data['responses'][0]['labelAnnotations'][0]['description'] );
        }

        return new WP_Error(
            'no_description',
            __( 'No description returned from Vision API.', 'autoalt-pro' )
        );
    }
}