<?php

use AutoAltPro\UI\UIRenderer;

class AuditSettingsMenuRegistrar {
    const SLUG = 'autoalt-pro';
    const SETTINGS_SLUG = 'autoalt-pro-settings';
    const TEXT_DOMAIN = 'autoalt-pro';
    const CAPABILITY = 'manage_options';
    const ICON = 'dashicons-format-image';
    const POSITION = 80;

    public function register() {
        add_action( 'admin_menu', [ $this, 'addMenuPages' ] );
    }

    public function addMenuPages() {
        add_menu_page(
            __( 'AutoAlt Pro', self::TEXT_DOMAIN ),
            __( 'AutoAlt Pro', self::TEXT_DOMAIN ),
            self::CAPABILITY,
            self::SLUG,
            [ $this, 'renderAuditPage' ],
            self::ICON,
            self::POSITION
        );

        add_submenu_page(
            self::SLUG,
            __( 'Settings', self::TEXT_DOMAIN ),
            __( 'Settings', self::TEXT_DOMAIN ),
            self::CAPABILITY,
            self::SETTINGS_SLUG,
            [ $this, 'renderSettingsPage' ]
        );
    }

    public function renderAuditPage() {
        UIRenderer::renderAuditPage();
    }

    public function renderSettingsPage() {
        UIRenderer::renderSettingsPage();
    }
}

add_action( 'plugins_loaded', function() {
    $menuRegistrar = new AuditSettingsMenuRegistrar();
    $menuRegistrar->register();
} );