<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Scheduler {

    const CRON_HOOK         = 'seo_outreach_run_scheduled';
    const DAILY_HOOK        = 'seo_outreach_daily_summary';
    const VALID_TYPES       = [
        'hourly'         => 'Every Hour',
        'every_2_hours'  => 'Every 2 Hours',
        'every_6_hours'  => 'Every 6 Hours',
        'every_12_hours' => 'Every 12 Hours',
        'daily'          => 'Daily',
        'every_2_days'   => 'Every 2 Days',
        'weekly_mon'     => 'Weekly — Monday',
        'weekly_wed'     => 'Weekly — Wednesday',
        'weekly_fri'     => 'Weekly — Friday',
        'weekdays'       => 'Weekdays (Mon–Fri)',
    ];

    public static function init(): void {
        add_action( self::CRON_HOOK,  [ __CLASS__, 'run_due_schedules' ] );
        add_action( self::DAILY_HOOK, [ __CLASS__, 'send_daily_summary' ] );
        add_action( self::DAILY_HOOK, [ __CLASS__, 'purge_old_logs' ] );
        // Register custom intervals
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );
    }

    public static function register_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'every_minute', self::CRON_HOOK );
        }
        if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 08:00' ), 'daily', self::DAILY_HOOK );
        }
    }

    public static function clear_cron(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
        wp_clear_scheduled_hook( self::DAILY_HOOK );
    }

    public static function add_cron_intervals( array $schedules ): array {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => 'Every Minute',
        ];
        return $schedules;
    }

    public static function run_due_schedules(): void {
        $db  = new SEO_Outreach_Database();
        $due = $db->get_due_schedules();
        foreach ( $due as $schedule ) {
            $runner = new SEO_Outreach_Campaign_Runner();
            $runner->run( 'scheduled' );
            $next_run = self::calculate_next_run( $schedule['schedule_type'], $schedule['run_time'] );
            $db->update_schedule_after_run( $schedule['id'], $next_run );
        }
    }

    public static function send_daily_summary(): void {
        if ( SEO_Outreach_Settings::get( 'notify_daily_summary', '1' ) !== '1' ) return;
        $db    = new SEO_Outreach_Database();
        $stats = $db->get_dashboard_stats();
        $body  = "Daily Summary — " . date( 'Y-m-d' ) . "\n\n"
               . "Total Runs: {$stats['total_runs']}\n"
               . "Total Emails Sent: {$stats['total_sent']}\n"
               . "Total Failed: {$stats['total_failed']}\n\n"
               . "Login to your dashboard: " . admin_url( 'admin.php?page=seo-outreach' );
        $mailer = new SEO_Outreach_Mailer();
        $mailer->send_notification( 'summary', 'Daily Summary', $body );
    }

    public static function add_schedule( SEO_Outreach_Database $db, string $type, string $time ): bool {
        if ( ! array_key_exists( $type, self::VALID_TYPES ) ) return false;
        $label    = self::VALID_TYPES[ $type ];
        if ( in_array( $type, [ 'daily', 'every_2_days', 'weekly_mon', 'weekly_wed', 'weekly_fri', 'weekdays' ], true ) ) {
            $label .= ' at ' . $time;
        }
        $next_run = self::calculate_next_run( $type, $time );
        $db->insert_schedule( [
            'schedule_type'  => $type,
            'schedule_label' => $label,
            'run_time'       => $time,
            'next_run'       => $next_run,
            'is_active'      => 1,
            'created_at'     => current_time( 'mysql' ),
        ] );
        return true;
    }

    public static function calculate_next_run( string $type, string $time = '09:00' ): string {
        [ $h, $m ] = array_pad( explode( ':', $time ), 2, '00' );
        $now  = current_time( 'timestamp' );

        switch ( $type ) {
            case 'hourly':        return date( 'Y-m-d H:i:s', strtotime( '+1 hour', $now ) );
            case 'every_2_hours': return date( 'Y-m-d H:i:s', strtotime( '+2 hours', $now ) );
            case 'every_6_hours': return date( 'Y-m-d H:i:s', strtotime( '+6 hours', $now ) );
            case 'every_12_hours':return date( 'Y-m-d H:i:s', strtotime( '+12 hours', $now ) );
            case 'every_2_days':
                $next = mktime( (int)$h, (int)$m, 0, date('n',$now), date('j',$now) + 2, date('Y',$now) );
                return date( 'Y-m-d H:i:s', $next );
            case 'daily':
                $next = mktime( (int)$h, (int)$m, 0, date('n',$now), date('j',$now), date('Y',$now) );
                if ( $next <= $now ) $next = strtotime( '+1 day', $next );
                return date( 'Y-m-d H:i:s', $next );
            case 'weekly_mon': return date( 'Y-m-d H:i:s', strtotime( 'next Monday '    . $h . ':' . $m, $now ) );
            case 'weekly_wed': return date( 'Y-m-d H:i:s', strtotime( 'next Wednesday ' . $h . ':' . $m, $now ) );
            case 'weekly_fri': return date( 'Y-m-d H:i:s', strtotime( 'next Friday '    . $h . ':' . $m, $now ) );
            case 'weekdays':
                $next = mktime( (int)$h, (int)$m, 0, date('n',$now), date('j',$now) + 1, date('Y',$now) );
                while ( (int) date( 'N', $next ) >= 6 ) $next = strtotime( '+1 day', $next );
                return date( 'Y-m-d H:i:s', $next );
            default:
                return date( 'Y-m-d H:i:s', strtotime( '+1 day', $now ) );
        }
    }

    public static function purge_old_logs(): void {
        $days = (int) SEO_Outreach_Settings::get( 'log_retention_days', '7' );
        if ( $days < 1 ) return;
        $db = new SEO_Outreach_Database();
        $db->purge_old_logs( $days );
    }

}