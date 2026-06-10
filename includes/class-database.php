<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Database {

    private $wpdb;

    const TABLE_LEADS     = 'seo_outreach_leads';
    const TABLE_LOGS      = 'seo_outreach_logs';
    const TABLE_SCHEDULES = 'seo_outreach_schedules';
    const TABLE_RUNS      = 'seo_outreach_runs';
    const TABLE_NOTIFS    = 'seo_outreach_notifications';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function leads()     { return $this->wpdb->prefix . self::TABLE_LEADS; }
    public function logs()      { return $this->wpdb->prefix . self::TABLE_LOGS; }
    public function schedules() { return $this->wpdb->prefix . self::TABLE_SCHEDULES; }
    public function runs()      { return $this->wpdb->prefix . self::TABLE_RUNS; }
    public function notifs()    { return $this->wpdb->prefix . self::TABLE_NOTIFS; }

    public function create_tables() {
        $charset = $this->wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE IF NOT EXISTS {$this->leads()} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            website_url    VARCHAR(500)    NOT NULL,
            contact_email  VARCHAR(255)    NOT NULL,
            business_name  VARCHAR(255)    DEFAULT '',
            status         VARCHAR(100)    DEFAULT '',
            sheet_row      INT             DEFAULT 0,
            synced_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_email_url (contact_email(100), website_url(100)),
            KEY idx_status (status)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$this->logs()} (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_identifier  VARCHAR(500)    DEFAULT '',
            action           VARCHAR(100)    NOT NULL,
            status           VARCHAR(50)     NOT NULL,
            message          TEXT,
            run_id           BIGINT UNSIGNED DEFAULT 0,
            created_at       DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status     (status),
            KEY idx_action     (action),
            KEY idx_created_at (created_at),
            KEY idx_run_id     (run_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$this->schedules()} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            schedule_type   VARCHAR(100)    NOT NULL,
            schedule_label  VARCHAR(255)    NOT NULL,
            run_time        VARCHAR(10)     DEFAULT '09:00',
            next_run        DATETIME        NOT NULL,
            last_run        DATETIME        NULL,
            is_active       TINYINT(1)      DEFAULT 1,
            created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active   (is_active),
            KEY idx_next_run (next_run)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$this->runs()} (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trigger_type     VARCHAR(50)     DEFAULT 'manual',
            status           VARCHAR(50)     DEFAULT 'running',
            total_processed  INT             DEFAULT 0,
            total_sent       INT             DEFAULT 0,
            total_failed     INT             DEFAULT 0,
            started_at       DATETIME        DEFAULT CURRENT_TIMESTAMP,
            finished_at      DATETIME        NULL,
            PRIMARY KEY (id),
            KEY idx_status  (status),
            KEY idx_started (started_at)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$this->notifs()} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type       VARCHAR(50)     NOT NULL,
            subject    VARCHAR(255)    NOT NULL,
            sent_to    VARCHAR(255)    NOT NULL,
            status     VARCHAR(50)     NOT NULL,
            created_at DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type    (type),
            KEY idx_created (created_at)
        ) $charset;" );
    }

    public function drop_tables() {
        foreach ( [ $this->leads(), $this->logs(), $this->schedules(), $this->runs(), $this->notifs() ] as $t ) {
            $this->wpdb->query( "DROP TABLE IF EXISTS `$t`" );
        }
    }

    // ── LEADS ─────────────────────────────────────────────────────────────────

    /**
     * Sync leads from Google Sheets into local DB.
     * Inserts new, updates existing, marks removed as 'removed'.
     */
    public function sync_leads( array $sheet_leads ): array {
        $inserted = 0;
        $updated  = 0;

        // Build map of existing leads by email+url
        $existing = $this->wpdb->get_results(
            "SELECT id, contact_email, website_url, status, sheet_row FROM {$this->leads()}",
            ARRAY_A
        ) ?: [];
        $existing_map = [];
        foreach ( $existing as $row ) {
            $key = strtolower( trim( $row['contact_email'] ) ) . '|' . strtolower( trim( $row['website_url'] ) );
            $existing_map[ $key ] = $row;
        }

        foreach ( $sheet_leads as $lead ) {
            $key = strtolower( trim( $lead['email'] ) ) . '|' . strtolower( trim( $lead['website'] ) );
            if ( isset( $existing_map[ $key ] ) ) {
                // Update status and row index if changed
                $ex = $existing_map[ $key ];
                if ( $ex['status'] !== $lead['status'] || (int)$ex['sheet_row'] !== (int)$lead['row_index'] ) {
                    $this->wpdb->update( $this->leads(), [
                        'status'    => $lead['status'],
                        'sheet_row' => $lead['row_index'],
                        'synced_at' => current_time('mysql'),
                    ], [ 'id' => $ex['id'] ] );
                    $updated++;
                }
            } else {
                // Insert new lead
                $this->wpdb->insert( $this->leads(), [
                    'website_url'   => $lead['website'],
                    'contact_email' => $lead['email'],
                    'business_name' => $lead['business_name'],
                    'status'        => $lead['status'],
                    'sheet_row'     => $lead['row_index'],
                    'synced_at'     => current_time('mysql'),
                ] );
                $inserted++;
            }
        }

        return [ 'inserted' => $inserted, 'updated' => $updated, 'total' => count( $sheet_leads ) ];
    }

    /**
     * Get all leads from local DB with optional status filter
     */
    public function get_leads( string $status = '', int $page = 1, int $per = 100 ): array {
        $offset = ( $page - 1 ) * $per;
        $where  = '';
        if ( $status === 'pending' ) {
            $where = "WHERE (status = '' OR status IS NULL)";
        } elseif ( $status === 'sent' ) {
            $where = "WHERE status LIKE 'Sent%'";
        } elseif ( $status === 'failed' ) {
            $where = "WHERE status LIKE 'Failed%'";
        }
        $rows  = $this->wpdb->get_results( "SELECT * FROM {$this->leads()} $where ORDER BY id ASC LIMIT $per OFFSET $offset", ARRAY_A ) ?: [];
        $total = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->leads()} $where" );
        return [ 'leads' => $rows, 'total' => $total, 'pages' => (int) ceil( $total / $per ) ];
    }

    public function get_lead_counts(): array {
        $total   = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->leads()}" );
        $pending = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->leads()} WHERE status = '' OR status IS NULL" );
        $sent    = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->leads()} WHERE status LIKE 'Sent%'" );
        $failed  = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->leads()} WHERE status LIKE 'Failed%'" );
        return [ 'total' => $total, 'pending' => $pending, 'sent' => $sent, 'failed' => $failed ];
    }

    public function delete_lead( int $id ): void {
        $this->wpdb->delete( $this->leads(), [ 'id' => $id ] );
    }

    public function clear_all_leads(): void {
        $this->wpdb->query( "TRUNCATE TABLE {$this->leads()}" );
    }

    public function wpdb_update_lead_status( string $email, string $website, string $status ): void {
        $this->wpdb->query( $this->wpdb->prepare(
            "UPDATE {$this->leads()} SET status = %s, synced_at = %s WHERE contact_email = %s AND website_url = %s",
            $status, current_time('mysql'), $email, $website
        ) );
    }

    public function get_last_sync(): string {
        $ts = $this->wpdb->get_var( "SELECT MAX(synced_at) FROM {$this->leads()}" );
        return $ts ?: '';
    }

    // ── LOGS ──────────────────────────────────────────────────────────────────

    public function log( string $identifier, string $action, string $status, string $message = '', int $run_id = 0 ) {
        $this->wpdb->insert( $this->logs(), [
            'lead_identifier' => $identifier,
            'action'          => $action,
            'status'          => $status,
            'message'         => $message,
            'run_id'          => $run_id,
            'created_at'      => current_time( 'mysql' ),
        ] );
    }

    public function get_recent_logs( int $limit = 30, string $status_filter = '' ): array {
        $where = $status_filter ? $this->wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->logs()} $where ORDER BY created_at DESC LIMIT $limit", ARRAY_A
        ) ?: [];
    }

    public function get_logs_paginated( int $page = 1, int $per = 50, string $filter = '' ): array {
        $offset = ( $page - 1 ) * $per;
        $where  = $filter ? $this->wpdb->prepare( 'WHERE status = %s', $filter ) : '';
        $rows   = $this->wpdb->get_results( "SELECT * FROM {$this->logs()} $where ORDER BY created_at DESC LIMIT $per OFFSET $offset", ARRAY_A ) ?: [];
        $total  = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs()} $where" );
        return [ 'rows' => $rows, 'total' => $total, 'pages' => (int) ceil( $total / $per ) ];
    }

    public function get_run_stats_last_hour(): array {
        $ago = gmdate( 'Y-m-d H:i:s', time() - 3600 );
        return [
            'sent'      => (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->logs()} WHERE action='send_email' AND status='success' AND created_at > %s", $ago ) ),
            'failed'    => (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->logs()} WHERE status='error' AND created_at > %s", $ago ) ),
            'processed' => (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->logs()} WHERE action='send_email' AND created_at > %s", $ago ) ),
        ];
    }

    /**
     * Auto-purge logs older than N days (retention policy)
     */
    public function purge_old_logs( int $days ): int {
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->logs()} WHERE created_at < %s", $cutoff ) );
        return (int) $this->wpdb->rows_affected;
    }

    public function clear_logs( string $type = 'all', string $date_from = '', string $date_to = '' ): int {
        if ( $type === 'errors' ) {
            $this->wpdb->query( "DELETE FROM {$this->logs()} WHERE status = 'error'" );
        } elseif ( $type === 'date_range' && $date_from && $date_to ) {
            $this->wpdb->query( $this->wpdb->prepare(
                "DELETE FROM {$this->logs()} WHERE created_at BETWEEN %s AND %s",
                $date_from . ' 00:00:00', $date_to . ' 23:59:59'
            ) );
        } else {
            $this->wpdb->query( "DELETE FROM {$this->logs()}" );
        }
        return (int) $this->wpdb->rows_affected;
    }

    public function get_log_count(): int {
        return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs()}" );
    }

    // ── RUNS ──────────────────────────────────────────────────────────────────

    public function start_run( string $trigger ): int {
        $this->wpdb->insert( $this->runs(), [ 'trigger_type' => $trigger, 'status' => 'running', 'started_at' => current_time( 'mysql' ) ] );
        return (int) $this->wpdb->insert_id;
    }

    public function finish_run( int $run_id, array $stats ) {
        $this->wpdb->update( $this->runs(), [
            'status' => 'complete', 'total_processed' => $stats['processed'],
            'total_sent' => $stats['sent'], 'total_failed' => $stats['failed'],
            'finished_at' => current_time( 'mysql' ),
        ], [ 'id' => $run_id ] );
    }

    public function get_runs( int $limit = 20 ): array {
        return $this->wpdb->get_results( "SELECT * FROM {$this->runs()} ORDER BY started_at DESC LIMIT $limit", ARRAY_A ) ?: [];
    }

    // ── SCHEDULES ─────────────────────────────────────────────────────────────

    public function insert_schedule( array $data ): int {
        $this->wpdb->insert( $this->schedules(), $data );
        return (int) $this->wpdb->insert_id;
    }

    public function get_all_schedules(): array {
        return $this->wpdb->get_results( "SELECT * FROM {$this->schedules()} ORDER BY created_at DESC", ARRAY_A ) ?: [];
    }

    public function get_due_schedules(): array {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->schedules()} WHERE is_active = 1 AND next_run <= '" . current_time( 'mysql' ) . "' ORDER BY next_run ASC", ARRAY_A
        ) ?: [];
    }

    public function get_next_schedule(): ?array {
        return $this->wpdb->get_row( "SELECT * FROM {$this->schedules()} WHERE is_active = 1 ORDER BY next_run ASC LIMIT 1", ARRAY_A ) ?: null;
    }

    public function update_schedule_after_run( int $id, string $next_run ) {
        $this->wpdb->update( $this->schedules(), [ 'last_run' => current_time( 'mysql' ), 'next_run' => $next_run ], [ 'id' => $id ] );
    }

    public function toggle_schedule( int $id ) {
        $cur = (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT is_active FROM {$this->schedules()} WHERE id = %d", $id ) );
        $this->wpdb->update( $this->schedules(), [ 'is_active' => $cur ? 0 : 1 ], [ 'id' => $id ] );
    }

    public function delete_schedule( int $id ) {
        $this->wpdb->delete( $this->schedules(), [ 'id' => $id ] );
    }

    // ── NOTIFICATIONS ─────────────────────────────────────────────────────────

    public function log_notification( string $type, string $subject, string $sent_to, string $status ) {
        $this->wpdb->insert( $this->notifs(), [
            'type' => $type, 'subject' => $subject,
            'sent_to' => $sent_to, 'status' => $status,
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    public function get_notifications( int $limit = 50 ): array {
        return $this->wpdb->get_results( "SELECT * FROM {$this->notifs()} ORDER BY created_at DESC LIMIT $limit", ARRAY_A ) ?: [];
    }

    // ── DASHBOARD ─────────────────────────────────────────────────────────────

    public function get_dashboard_stats(): array {
        return [
            'total_runs'   => (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->runs()}" ),
            'total_sent'   => (int) $this->wpdb->get_var( "SELECT COALESCE(SUM(total_sent),0) FROM {$this->runs()}" ),
            'total_failed' => (int) $this->wpdb->get_var( "SELECT COALESCE(SUM(total_failed),0) FROM {$this->runs()}" ),
            'total_logs'   => $this->get_log_count(),
        ];
    }
}
