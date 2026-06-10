<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            'SEO Outreach Pro',
            'SEO Outreach',
            'manage_options',
            'seo-outreach',
            [ $this, 'page_dashboard' ],
            'dashicons-email-alt',
            30
        );
        add_submenu_page( 'seo-outreach', 'Dashboard',    'Dashboard',    'manage_options', 'seo-outreach',           [ $this, 'page_dashboard' ] );
        add_submenu_page( 'seo-outreach', 'Run Campaign', 'Run Campaign', 'manage_options', 'seo-outreach-run',       [ $this, 'page_run' ] );
        add_submenu_page( 'seo-outreach', 'Leads',        'Leads',        'manage_options', 'seo-outreach-leads',     [ $this, 'page_leads' ] );
        add_submenu_page( 'seo-outreach', 'Scheduler',    'Scheduler',    'manage_options', 'seo-outreach-scheduler', [ $this, 'page_scheduler' ] );
        add_submenu_page( 'seo-outreach', 'Logs',         'Activity Logs','manage_options', 'seo-outreach-logs',      [ $this, 'page_logs' ] );
        add_submenu_page( 'seo-outreach', 'Reports',      'PDF Reports',  'manage_options', 'seo-outreach-reports',   [ $this, 'page_reports' ] );
        add_submenu_page( 'seo-outreach', 'Notifications','Notifications','manage_options', 'seo-outreach-notifs',    [ $this, 'page_notifications' ] );
        add_submenu_page( 'seo-outreach', 'Settings',     'Settings',     'manage_options', 'seo-outreach-settings',  [ $this, 'page_settings' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'seo-outreach' ) === false ) return;
        wp_enqueue_style(
            'seo-outreach-admin',
            SEO_OUTREACH_URL . 'assets/admin.css',
            [],
            SEO_OUTREACH_VERSION
        );
        wp_enqueue_script(
            'seo-outreach-admin',
            SEO_OUTREACH_URL . 'assets/admin.js',
            [ 'jquery' ],
            SEO_OUTREACH_VERSION,
            true
        );
        wp_localize_script( 'seo-outreach-admin', 'seoOutreach', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'seo_outreach_nonce' ),
            'pluginUrl'   => SEO_OUTREACH_URL,
            'settingsUrl' => admin_url( 'admin.php?page=seo-outreach-settings&tab=sheets' ),
        ] );
    }

    // ── Page routing ──────────────────────────────────────────────────────────
    public function page_dashboard():    void { require SEO_OUTREACH_DIR . 'admin/page-dashboard.php'; }
    public function page_run():         void { require SEO_OUTREACH_DIR . 'admin/page-run-campaign.php'; }
    public function page_leads():       void { require SEO_OUTREACH_DIR . 'admin/page-leads.php'; }
    public function page_scheduler():   void { require SEO_OUTREACH_DIR . 'admin/page-scheduler.php'; }
    public function page_logs():        void { require SEO_OUTREACH_DIR . 'admin/page-logs.php'; }
    public function page_reports():     void { require SEO_OUTREACH_DIR . 'admin/page-reports.php'; }
    public function page_notifications():void { require SEO_OUTREACH_DIR . 'admin/page-notifications.php'; }
    public function page_settings():    void { require SEO_OUTREACH_DIR . 'admin/page-settings.php'; }
}
