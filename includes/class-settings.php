<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Settings {

    const OPTION_KEY       = 'seo_outreach_settings';
    const NOTIF_EMAILS_KEY = 'seo_outreach_notification_emails';

    private static array $cache = [];

    public static function get( string $key, string $default = '' ): string {
        if ( empty( self::$cache ) ) {
            self::$cache = (array) get_option( self::OPTION_KEY, [] );
        }
        return (string) ( self::$cache[ $key ] ?? $default );
    }

    public static function set( string $key, string $value ): void {
        if ( empty( self::$cache ) ) {
            self::$cache = (array) get_option( self::OPTION_KEY, [] );
        }
        self::$cache[ $key ] = $value;
        update_option( self::OPTION_KEY, self::$cache, false );
    }

    public static function get_all(): array {
        return (array) get_option( self::OPTION_KEY, [] );
    }

    public static function get_notification_emails(): array {
        $stored = get_option( self::NOTIF_EMAILS_KEY, [] );
        if ( empty( $stored ) ) {
            $legacy = self::get( 'notification_email' );
            if ( $legacy ) return [ $legacy ];
        }
        return is_array( $stored ) ? array_filter( $stored ) : [];
    }

    public static function set_notification_emails( array $emails ): void {
        $clean = array_values( array_filter( array_map( 'sanitize_email', $emails ) ) );
        update_option( self::NOTIF_EMAILS_KEY, $clean, false );
    }

    public static function set_defaults(): void {
        $existing = (array) get_option( self::OPTION_KEY, [] );
        $defaults = [
            'gemini_api_key'          => '',
            'pagespeed_api_key'       => '',
            'google_service_account'  => '',
            'google_sheet_id'         => '',
            'google_sheet_name'       => '',
            'google_sheet_tab'        => 'Sheet1',
            'smtp_host'               => '',
            'smtp_port'               => '587',
            'smtp_provider'           => 'gmail',
            'smtp_user'               => '',
            'smtp_pass'               => '',
            'smtp_from_name'          => SEO_OUTREACH_BRAND,
            'smtp_from_email'         => '',
            'notify_on_complete'      => '1',
            'notify_on_error'         => '1',
            'notify_daily_summary'    => '1',
            'calendar_link'           => '',
            'max_leads_per_run'       => '10',
            'delay_between_emails'    => '5',
            // ── Batch pause settings ───────────────────────────────────────
            'batch_pause_enabled'     => '0',   // '1' = on, '0' = off
            'batch_pause_after'       => '5',   // pause after every N emails
            'batch_pause_minutes'     => '5',   // wait X minutes
            'log_retention_days'      => '7',
            'log_auto_refresh'        => '0',
            'log_auto_refresh_secs'   => '30',
            // ── Campaign delivery settings ─────────────────────────────────
            'pdf_delivery_mode'       => 'attach',  // 'attach' | 'link' | 'both' | 'none'
            // ── Outreach type toggles ──────────────────────────────────────
            'outreach_type_seo'       => '1',
            'outreach_type_ads'       => '1',
            'outreach_type_no_website'=> '1',
        ];
        update_option( self::OPTION_KEY, array_merge( $defaults, $existing ), false );
        self::$cache = array_merge( $defaults, $existing );
    }
}
