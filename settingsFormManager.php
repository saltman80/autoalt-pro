<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutoAltPro_SettingsFormManager {
    /**
     * Option name in wp_options.
     *
     * @var string
     */
    protected $option_name = 'autoalt_pro_settings';

    /**
     * Loaded options.
     *
     * @var array
     */
    protected $options = array();

    /**
     * Constructor: hook into admin.
     */
    public function __construct() {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
        }
    }

    /**
     * Register settings page under Settings menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'AutoAlt Pro Settings', 'autoalt-pro' ),
            __( 'AutoAlt Pro', 'autoalt-pro' ),
            'manage_options',
            $this->option_name,
            array( $this, 'settings_page' )
        );
    }

    /**
     * Handle form submission: verify nonce, sanitize, validate, save, redirect.
     */
    public function handle_form_submission() {
        if ( empty( $_POST['autoalt_pro_settings_submit'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'autoalt_pro_settings_verify' );

        $defaults = $this->get_default_options();
        $new_options = array();

        // AI API Key
        $new_options['ai_api_key'] = sanitize_text_field( wp_unslash( $_POST['ai_api_key'] ?? '' ) );

        // Enable bulk actions
        $new_options['enable_bulk_actions'] = isset( $_POST['enable_bulk_actions'] ) ? 1 : 0;

        // Default caption length: clamp between 1 and 100
        $caption_length = isset( $_POST['default_caption_length'] ) ? intval( $_POST['default_caption_length'] ) : $defaults['default_caption_length'];
        $new_options['default_caption_length'] = max( 1, min( 100, $caption_length ) );

        // Alt quality threshold: clamp between 0 and 100
        $threshold = isset( $_POST['alt_quality_threshold'] ) ? intval( $_POST['alt_quality_threshold'] ) : $defaults['alt_quality_threshold'];
        $new_options['alt_quality_threshold'] = max( 0, min( 100, $threshold ) );

        update_option( $this->option_name, $new_options );

        add_settings_error(
            'autoalt_pro_messages',
            'autoalt_pro_message',
            __( 'Settings saved.', 'autoalt-pro' ),
            'updated'
        );

        // Redirect to avoid resubmission
        $redirect_url = add_query_arg(
            array(
                'page' => $this->option_name,
            ),
            admin_url( 'options-general.php' )
        );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Default plugin options.
     *
     * @return array
     */
    private function get_default_options() {
        return array(
            'ai_api_key'             => '',
            'enable_bulk_actions'    => 1,
            'default_caption_length' => 20,
            'alt_quality_threshold'  => 50,
        );
    }

    /**
     * Load saved options merged with defaults.
     */
    private function load_options() {
        $saved = get_option( $this->option_name, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }
        $this->options = wp_parse_args( $saved, $this->get_default_options() );
    }

    /**
     * Render the settings page.
     */
    public function settings_page() {
        $this->load_options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AutoAlt Pro Settings', 'autoalt-pro' ); ?></h1>
            <?php settings_errors( 'autoalt_pro_messages' ); ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'autoalt_pro_settings_verify' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="ai_api_key"><?php esc_html_e( 'AI API Key', 'autoalt-pro' ); ?></label>
                        </th>
                        <td>
                            <input
                                name="ai_api_key"
                                type="text"
                                id="ai_api_key"
                                value="<?php echo esc_attr( $this->options['ai_api_key'] ); ?>"
                                class="regular-text"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Bulk Actions', 'autoalt-pro' ); ?></th>
                        <td>
                            <label for="enable_bulk_actions">
                                <input
                                    name="enable_bulk_actions"
                                    type="checkbox"
                                    id="enable_bulk_actions"
                                    value="1"
                                    <?php checked( 1, $this->options['enable_bulk_actions'], true ); ?>
                                />
                                <?php esc_html_e( 'Enable bulk generation of alt text for multiple images.', 'autoalt-pro' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_caption_length"><?php esc_html_e( 'Default Caption Length', 'autoalt-pro' ); ?></label>
                        </th>
                        <td>
                            <input
                                name="default_caption_length"
                                type="number"
                                id="default_caption_length"
                                value="<?php echo esc_attr( $this->options['default_caption_length'] ); ?>"
                                class="small-text"
                                min="1"
                                max="100"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="alt_quality_threshold"><?php esc_html_e( 'Alt Quality Threshold', 'autoalt-pro' ); ?></label>
                        </th>
                        <td>
                            <input
                                name="alt_quality_threshold"
                                type="number"
                                id="alt_quality_threshold"
                                value="<?php echo esc_attr( $this->options['alt_quality_threshold'] ); ?>"
                                class="small-text"
                                min="0"
                                max="100"
                            />
                            <p class="description">
                                <?php esc_html_e( 'Threshold below which alt text is considered poor quality.', 'autoalt-pro' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Changes', 'autoalt-pro' ), 'primary', 'autoalt_pro_settings_submit' ); ?>
            </form>
        </div>
        <?php
    }
}

new AutoAltPro_SettingsFormManager();