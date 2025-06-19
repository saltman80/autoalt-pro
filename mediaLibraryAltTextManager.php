<?php

class MediaLibraryAltTextManager {
    public function __construct( $apiClient = null ) {
        $this->apiClient = $apiClient ?: new VisionAPIClient();
        add_action( 'admin_menu', [ $this, 'registerAdminPage' ] );
        add_action( 'add_attachment', [ $this, 'handleNewAttachment' ] );
        add_action( 'admin_post_autoalt_bulk_generate', [ $this, 'handleBulkGenerate' ] );
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $this->registerCLICommands();
        }
    }

    public function registerAdminPage() {
        add_submenu_page(
            'tools.php',
            __( 'Alt Text Audit', 'autoalt-pro' ),
            __( 'Alt Text Audit', 'autoalt-pro' ),
            'manage_options',
            'autoalt-audit',
            [ $this, 'renderAdminPage' ]
        );
    }

    public function renderAdminPage() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'autoalt-pro' ) );
        }
        $attachments = $this->getAllAttachments();
        echo '<div class="wrap"><h1>' . esc_html( __( 'Alt Text Audit', 'autoalt-pro' ) ) . '</h1>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="autoalt_bulk_generate">';
        wp_nonce_field( 'autoalt_bulk_generate_action', 'autoalt_bulk_generate_nonce' );
        submit_button( __( 'Generate Missing Alt Text in Bulk', 'autoalt-pro' ) );
        echo '</form>';

        echo '<table class="widefat fixed"><thead><tr>';
        echo '<th>' . esc_html( __( 'ID', 'autoalt-pro' ) ) . '</th>';
        echo '<th>' . esc_html( __( 'Title', 'autoalt-pro' ) ) . '</th>';
        echo '<th>' . esc_html( __( 'Alt Text', 'autoalt-pro' ) ) . '</th>';
        echo '<th>' . esc_html( __( 'Status', 'autoalt-pro' ) ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $attachments as $attachment ) {
            $alt    = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
            $status = $this->assessAlt( $alt );
            echo '<tr>';
            echo '<td>' . esc_html( $attachment->ID ) . '</td>';
            echo '<td>' . esc_html( $attachment->post_title ) . '</td>';
            echo '<td>' . esc_html( $alt ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    public function handleNewAttachment( $attachmentId ) {
        $alt = get_post_meta( $attachmentId, '_wp_attachment_image_alt', true );
        if ( empty( $alt ) ) {
            $this->generateAndSaveAlt( $attachmentId );
        }
    }

    public function handleBulkGenerate() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'autoalt-pro' ) );
        }
        check_admin_referer( 'autoalt_bulk_generate_action', 'autoalt_bulk_generate_nonce' );
        $attachments = $this->getAllAttachments();
        foreach ( $attachments as $attachment ) {
            $alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
            if ( empty( $alt ) ) {
                $this->generateAndSaveAlt( $attachment->ID );
            }
        }
        wp_safe_redirect( admin_url( 'tools.php?page=autoalt-audit' ) );
        exit;
    }

    private function getAllAttachments() {
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'numberposts'    => -1,
        ];
        return get_posts( $args );
    }

    private function assessAlt( $alt ) {
        if ( empty( $alt ) ) {
            return 'missing';
        }
        $words = str_word_count( $alt );
        if ( $words < 3 ) {
            return 'too short';
        }
        return 'ok';
    }

    private function generateAndSaveAlt( $attachmentId ) {
        $url = wp_get_attachment_url( $attachmentId );
        if ( ! $url ) {
            return;
        }
        try {
            $description = $this->apiClient->generate_description( $url );
            if ( $description ) {
                update_post_meta( $attachmentId, '_wp_attachment_image_alt', sanitize_text_field( $description ) );
            }
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "AutoAlt Error (Attachment ID $attachmentId): " . $e->getMessage() );
            }
        }
    }

    private function registerCLICommands() {
        \WP_CLI::add_command( 'autoalt scan', [ $this, 'cliScan' ], [
            'shortdesc' => 'Scan media library and report alt text status.',
        ] );
        \WP_CLI::add_command( 'autoalt generate', [ $this, 'cliGenerate' ], [
            'shortdesc' => 'Generate missing alt text for all images.',
        ] );
        \WP_CLI::add_command( 'autoalt generate single', [ $this, 'cliGenerateSingle' ], [
            'shortdesc' => 'Generate alt text for a single attachment.',
            'synopsis'  => [
                [
                    'type'        => 'positional',
                    'name'        => 'attachment_id',
                    'description' => 'Attachment ID',
                    'required'    => true,
                ],
            ],
        ] );
    }

    public function cliScan( $args, $assocArgs ) {
        $attachments = $this->getAllAttachments();
        $report      = [];
        foreach ( $attachments as $attachment ) {
            $alt    = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
            $status = $this->assessAlt( $alt );
            $report[] = [ $attachment->ID, $attachment->post_title, $alt, $status ];
        }
        \WP_CLI\Utils\format_items( 'table', $report, [ 'ID', 'Title', 'Alt Text', 'Status' ] );
    }

    public function cliGenerate( $args, $assocArgs ) {
        $attachments = $this->getAllAttachments();
        foreach ( $attachments as $attachment ) {
            $alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
            if ( empty( $alt ) ) {
                $this->generateAndSaveAlt( $attachment->ID );
                \WP_CLI::success( sprintf( __( 'Generated alt for %d', 'autoalt-pro' ), $attachment->ID ) );
            }
        }
    }

    public function cliGenerateSingle( $args, $assocArgs ) {
        list( $attachmentId ) = $args;
        if ( ! get_post( $attachmentId ) ) {
            \WP_CLI::error( sprintf( __( 'Attachment %d not found.', 'autoalt-pro' ), $attachmentId ) );
        }
        $this->generateAndSaveAlt( $attachmentId );
        \WP_CLI::success( sprintf( __( 'Generated alt for %d', 'autoalt-pro' ), $attachmentId ) );
    }
}

class VisionAPIClient {
    private $endpoint;
    private $apiKey;

    public function __construct() {
        $this->endpoint = defined( 'AUTOALT_VISION_API_ENDPOINT' ) ? AUTOALT_VISION_API_ENDPOINT : '';
        $this->apiKey   = defined( 'AUTOALT_VISION_API_KEY' ) ? AUTOALT_VISION_API_KEY : '';
    }

    public function generate_description( $imageUrl ) {
        if ( empty( $this->endpoint ) || empty( $this->apiKey ) ) {
            throw new \Exception( 'Vision API credentials not configured.' );
        }
        $response = wp_remote_post( $this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [ 'url' => $imageUrl ] ),
            'timeout' => 30,
        ] );
        if ( is_wp_error( $response ) ) {
            throw new \Exception( $response->get_error_message() );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['description'] ) ) {
            throw new \Exception( 'No description returned.' );
        }
        return $body['description'];
    }
}

new MediaLibraryAltTextManager();