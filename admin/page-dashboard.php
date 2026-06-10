<?php
defined( 'ABSPATH' ) || exit;
require_once SEO_OUTREACH_DIR . 'admin/partials.php';

$db          = new SEO_Outreach_Database();
$stats       = $db->get_dashboard_stats();
$recent_logs = $db->get_recent_logs( 10 );
$next_sched  = $db->get_next_schedule();
$recent_runs = $db->get_runs( 5 );

seo_outreach_header( 'Dashboard', 'dashicons-chart-line', 'Run Campaign Now', admin_url( 'admin.php?page=seo-outreach-run' ) );
?>

<!-- Stat Cards -->
<div class="seo-stat-grid">
  <div class="seo-stat-card">
    <div class="seo-stat-icon blue"><span class="dashicons dashicons-groups"></span></div>
    <div class="seo-stat-body"><h3><?= number_format( $stats['total_runs'] ) ?></h3><p>Campaign Runs</p></div>
  </div>
  <div class="seo-stat-card">
    <div class="seo-stat-icon green"><span class="dashicons dashicons-email-alt"></span></div>
    <div class="seo-stat-body"><h3><?= number_format( $stats['total_sent'] ) ?></h3><p>Emails Sent</p></div>
  </div>
  <div class="seo-stat-card">
    <div class="seo-stat-icon red"><span class="dashicons dashicons-warning"></span></div>
    <div class="seo-stat-body"><h3><?= number_format( $stats['total_failed'] ) ?></h3><p>Failed</p></div>
  </div>
  <div class="seo-stat-card">
    <div class="seo-stat-icon purple"><span class="dashicons dashicons-list-view"></span></div>
    <div class="seo-stat-body"><h3><?= number_format( $stats['total_logs'] ) ?></h3><p>Total Log Entries</p></div>
  </div>
</div>

<div class="seo-two-col">
  <!-- Next Schedule -->
  <div class="seo-card">
    <div class="seo-card-header"><span class="dashicons dashicons-clock"></span> Next Scheduled Run</div>
    <div class="seo-card-body">
      <?php if ( $next_sched ): ?>
        <p class="seo-next-run-label">Active Schedule</p>
        <p><strong><?= esc_html( $next_sched['schedule_label'] ) ?></strong></p>
        <p class="seo-muted">Next run: <?= esc_html( $next_sched['next_run'] ) ?></p>
        <a href="<?= admin_url( 'admin.php?page=seo-outreach-scheduler' ) ?>" class="seo-btn seo-btn-sm seo-btn-outline">Manage Schedules</a>
      <?php else: ?>
        <p class="seo-muted">No schedule active.</p>
        <a href="<?= admin_url( 'admin.php?page=seo-outreach-scheduler' ) ?>" class="seo-btn seo-btn-sm seo-btn-primary">Set Up Schedule</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Runs -->
  <div class="seo-card">
    <div class="seo-card-header"><span class="dashicons dashicons-backup"></span> Recent Campaign Runs</div>
    <div class="seo-card-body seo-p0">
      <table class="seo-table">
        <thead><tr><th>Started</th><th>Sent</th><th>Failed</th><th>Status</th></tr></thead>
        <tbody>
          <?php if ( empty( $recent_runs ) ): ?>
            <tr><td colspan="4" class="seo-empty">No runs yet.</td></tr>
          <?php else: foreach ( $recent_runs as $r ): ?>
            <tr>
              <td><?= esc_html( $r['started_at'] ) ?></td>
              <td class="seo-green"><?= (int) $r['total_sent'] ?></td>
              <td class="seo-red"><?= (int) $r['total_failed'] ?></td>
              <td><?= seo_outreach_badge( $r['status'] ) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Recent Activity -->
<div class="seo-card">
  <div class="seo-card-header">
    <span class="dashicons dashicons-list-view"></span> Recent Activity
    <a href="<?= admin_url( 'admin.php?page=seo-outreach-logs' ) ?>" class="seo-card-link">View All Logs &rarr;</a>
  </div>
  <div class="seo-card-body seo-p0">
    <table class="seo-table">
      <thead><tr><th>Time</th><th>Lead</th><th>Action</th><th>Status</th><th>Message</th></tr></thead>
      <tbody>
        <?php if ( empty( $recent_logs ) ): ?>
          <tr><td colspan="5" class="seo-empty">No activity yet. Run your first campaign!</td></tr>
        <?php else: foreach ( $recent_logs as $log ): ?>
          <tr>
            <td><?= esc_html( $log['created_at'] ) ?></td>
            <td class="seo-truncate"><?= esc_html( $log['lead_identifier'] ) ?></td>
            <td><code><?= esc_html( $log['action'] ) ?></code></td>
            <td><?= seo_outreach_badge( $log['status'] ) ?></td>
            <td class="seo-truncate seo-muted"><?= esc_html( substr( $log['message'], 0, 80 ) ) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php seo_outreach_footer(); ?>
