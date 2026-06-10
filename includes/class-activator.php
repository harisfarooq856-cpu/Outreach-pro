<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Activator {

    public static function activate() {
        $db = new SEO_Outreach_Database();
        $db->create_tables(); // Creates all tables including leads (uses IF NOT EXISTS)
        SEO_Outreach_Settings::set_defaults();
        SEO_Outreach_Scheduler::register_cron();
        // Create upload dir for PDFs
        SEO_Outreach_PDF_Generator::ensure_pdf_dir();
        update_option( 'seo_outreach_version', SEO_OUTREACH_VERSION );
        flush_rewrite_rules();
    }

    /**
     * Run on plugins_loaded to handle upgrades on existing installs
     */
    public static function maybe_upgrade() {
        $installed = get_option('seo_outreach_version','0');
        if ( version_compare($installed, SEO_OUTREACH_VERSION, '<') ) {
            $db = new SEO_Outreach_Database();
            $db->create_tables(); // dbDelta handles adding new tables safely
            update_option('seo_outreach_version', SEO_OUTREACH_VERSION);
        }
    }

    public static function deactivate() {
        SEO_Outreach_Scheduler::clear_cron();
    }
}
