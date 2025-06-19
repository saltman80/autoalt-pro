<?php

if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

if ( file_exists( $autoload = __DIR__ . '/../vendor/autoload.php' ) ) {
    require_once $autoload;
}

class AutoAlt_Add_Missing_Ai_Files_CLI {

    public function __invoke( $args, $assoc_args ) {
        global $wp_filesystem;
        if ( ! WP_Filesystem() ) {
            WP_CLI::error( 'Unable to initialize WP Filesystem.' );
            return;
        }

        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'autoalt-ai';
        $base_url   = trailingslashit( $upload_dir['baseurl'] ) . 'autoalt-ai';

        // Ensure base directory exists.
        if ( ! $wp_filesystem->exists( $base_dir ) ) {
            if ( ! $wp_filesystem->mkdir( $base_dir, FS_CHMOD_DIR ) ) {
                WP_CLI::error( "Unable to create AI metadata directory: {$base_dir}" );
                return;
            }
        }

        $per_page        = 100;
        $page            = 1;
        $api             = \AutoAlt\Api\Client::get_instance();
        $total_processed = 0;

        do {
            $query = new WP_Query( array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'meta_query'     => array(
                    array(
                        'key'     => '_autoalt_ai_file',
                        'compare' => 'NOT EXISTS',
                    ),
                ),
                'mime_type'      => 'image',
                'fields'         => 'ids',
            ) );

            $attachments = $query->posts;

            if ( empty( $attachments ) ) {
                if ( 1 === $page ) {
                    WP_CLI::success( 'No image attachments missing AI metadata.' );
                }
                break;
            }

            foreach ( $attachments as $attachment_id ) {
                $file_path = get_attached_file( $attachment_id );
                if ( ! $file_path || ! $wp_filesystem->exists( $file_path ) ) {
                    WP_CLI::warning( "Attachment ID {$attachment_id} has no valid file." );
                    continue;
                }

                $file_basename = wp_basename( $file_path );
                // Create subdirectory by year/month.
                $subdir     = date_i18n( 'Y/m' );
                $target_dir = trailingslashit( $base_dir ) . $subdir;
                if ( ! $wp_filesystem->exists( $target_dir ) ) {
                    if ( ! $wp_filesystem->mkdir( $target_dir, FS_CHMOD_DIR ) ) {
                        WP_CLI::warning( "Unable to create subdirectory: {$target_dir}" );
                        continue;
                    }
                }

                // Prepare unique filename.
                $json_basename    = $attachment_id . '-' . $file_basename . '.json';
                $unique_name      = wp_unique_filename( $target_dir, $json_basename );
                $json_full_path   = trailingslashit( $target_dir ) . $unique_name;
                $relative_path    = ltrim( str_replace( trailingslashit( $upload_dir['basedir'] ), '', $json_full_path ), '/' );
                $json_public_url  = trailingslashit( $base_url . '/' . $subdir ) . $unique_name;

                // If already exists, update meta and continue.
                if ( $wp_filesystem->exists( $json_full_path ) ) {
                    update_post_meta( $attachment_id, '_autoalt_ai_file', $relative_path );
                    continue;
                }

                try {
                    $response = $api->describe_image( $file_path );
                } catch ( Exception $e ) {
                    WP_CLI::warning( "AI API error for {$file_basename}: " . $e->getMessage() );
                    continue;
                }

                $data = array(
                    'alt'          => isset( $response['alt'] ) ? sanitize_text_field( $response['alt'] ) : '',
                    'caption'      => isset( $response['caption'] ) ? sanitize_text_field( $response['caption'] ) : '',
                    'tags'         => isset( $response['tags'] ) ? array_map( 'sanitize_text_field', (array) $response['tags'] ) : array(),
                    'generated_at' => current_time( 'mysql' ),
                );

                $encoded = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
                if ( false === $wp_filesystem->put_contents( $json_full_path, $encoded, FS_CHMOD_FILE ) ) {
                    WP_CLI::warning( "Failed to write AI metadata JSON for {$file_basename}." );
                    continue;
                }

                update_post_meta( $attachment_id, '_autoalt_ai_file', $relative_path );

                $existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
                if ( empty( $existing_alt ) && ! empty( $data['alt'] ) ) {
                    update_post_meta( $attachment_id, '_wp_attachment_image_alt', $data['alt'] );
                }

                WP_CLI::log( "Generated AI metadata for {$file_basename}." );
                $total_processed++;
                sleep( 1 );
            }

            $page++;
        } while ( count( $attachments ) === $per_page );

        WP_CLI::success( "AI metadata files created for {$total_processed} attachment(s)." );
    }
}

WP_CLI::add_command( 'autoalt add-missing-ai-files', 'AutoAlt_Add_Missing_Ai_Files_CLI' );