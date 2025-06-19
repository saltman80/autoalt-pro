<?php

add_action( 'rest_api_init', function () {
    $args = array(
        'methods'             => 'POST',
        'callback'            => array( 'ImageAnalysisRoutes', 'generate_bulk' ),
        'permission_callback' => array( 'ImageAnalysisRoutes', 'permissions_check' ),
        'args'                => array(
            'ids' => array(
                'required'          => true,
                'type'              => 'array',
                'items'             => array(
                    'type' => 'integer',
                ),
                'validate_callback' => function ( $param ) {
                    return is_array( $param ) && ! empty( $param );
                },
                'sanitize_callback' => function ( $param ) {
                    return array_map( 'intval', $param );
                },
            ),
        ),
    );
    register_rest_route( 'autoalt-pro/v1', '/generate', $args );
    register_rest_route( 'autoalt-pro/v1', '/alt', $args );
} );

class ImageAnalysisRoutes {

    /**
     * Permission check for bulk generate.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public static function permissions_check( $request ) {
        return current_user_can( 'upload_files' );
    }

    /**
     * Handle bulk alt text generation.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function generate_bulk( $request ) {
        $ids = $request->get_param( 'ids' );

        if ( ! is_array( $ids ) || empty( $ids ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => __( 'No image IDs provided.', 'autoalt-pro' ),
                ),
                400
            );
        }

        // Validate that each ID is an existing image attachment.
        $invalid_ids = array();
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( ! $post || 'attachment' !== $post->post_type ) {
                $invalid_ids[] = $id;
                continue;
            }
            $mime = get_post_mime_type( $post );
            if ( strpos( $mime, 'image/' ) !== 0 ) {
                $invalid_ids[] = $id;
            }
        }
        if ( ! empty( $invalid_ids ) ) {
            return new WP_REST_Response(
                array(
                    'success'     => false,
                    'message'     => __( 'Some provided IDs are not valid image attachments.', 'autoalt-pro' ),
                    'invalid_ids' => array_values( $invalid_ids ),
                ),
                400
            );
        }

        // Verify per-ID edit permissions.
        $unauthorized_ids = array();
        foreach ( $ids as $id ) {
            if ( ! current_user_can( 'edit_post', $id ) ) {
                $unauthorized_ids[] = $id;
            }
        }
        if ( ! empty( $unauthorized_ids ) ) {
            return new WP_REST_Response(
                array(
                    'success'          => false,
                    'message'          => __( 'You do not have permission to edit some of the specified images.', 'autoalt-pro' ),
                    'unauthorized_ids' => array_values( $unauthorized_ids ),
                ),
                403
            );
        }

        try {
            $result = Processor::generateBulkAltText( $ids );
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'data'    => $result,
                ),
                200
            );
        } catch ( Exception $e ) {
            if ( class_exists( 'Logger' ) && method_exists( 'Logger', 'error' ) ) {
                Logger::error(
                    sprintf(
                        'AutoAlt Pro bulk generate failed for IDs [%s]: %s',
                        implode( ',', $ids ),
                        $e->getMessage()
                    ),
                    array( 'trace' => $e->getTraceAsString() )
                );
            } else {
                error_log( 'AutoAlt Pro error in generate_bulk: ' . $e->getMessage() );
            }
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => __( 'An unexpected error occurred. Please try again later.', 'autoalt-pro' ),
                ),
                500
            );
        }
    }
}