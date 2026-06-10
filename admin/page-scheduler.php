<?php
// admin/page-scheduler.php
defined( 'ABSPATH' ) || exit;
require_once SEO_OUTREACH_DIR . 'admin/partials.php';

$db        = new SEO_Outreach_Database();
$schedules = $db->get_all_schedules();
$runs      = $db->get_runs( 10 );

seo_outreach_header( 'Campaign Scheduler', 'dashicons-calendar-alt' );
?>
<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-plus-alt"></span> Add New Schedule</div>
  <div class="seo-card-body">
    <div class="seo-info-box">
      <strong>WP-Cron is active</strong> — schedules run automatically via WordPress cron. For more reliable scheduling on low-traffic sites, add a real server cron:
      <br><code>* * * * * curl -s <?= esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ) ?> &gt; /dev/null 2&gt;&amp;1</code>
    </div>
    <div class="seo-form-row">
      <div class="seo-form-group">
        <label>Schedule Type</label>
        <select id="seo-sched-type" class="regular-text" onchange="seoToggleTime()">
          <optgroup label="Frequent">
            <option value="hourly">Every Hour</option>
            <option value="every_2_hours">Every 2 Hours</option>
            <option value="every_6_hours">Every 6 Hours</option>
            <option value="every_12_hours">Every 12 Hours</option>
          </optgroup>
          <optgroup label="Daily">
            <option value="daily" selected>Daily</option>
            <option value="every_2_days">Every 2 Days</option>
            <option value="weekdays">Weekdays (Mon–Fri)</option>
          </optgroup>
          <optgroup label="Weekly">
            <option value="weekly_mon">Every Monday</option>
            <option value="weekly_wed">Every Wednesday</option>
            <option value="weekly_fri">Every Friday</option>
          </optgroup>
        </select>
      </div>
      <div class="seo-form-group" id="seo-time-field">
        <label>Run Time</label>
        <input type="time" id="seo-run-time" value="09:00" class="regular-text">
      </div>
      <div class="seo-form-group" style="align-self:flex-end">
        <button class="seo-btn seo-btn-primary" onclick="seoAddSchedule()">
          <span class="dashicons dashicons-plus"></span> Add Schedule
        </button>
      </div>
    </div>
    <div id="seo-sched-notice" style="display:none"></div>
  </div>
</div>

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-list-view"></span> Active Schedules</div>
  <div class="seo-card-body seo-p0">
    <table class="seo-table" id="seo-schedules-table">
      <thead><tr><th>Schedule</th><th>Next Run</th><th>Last Run</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="seo-schedules-body">
        <?php if ( empty( $schedules ) ): ?>
          <tr><td colspan="5" class="seo-empty">No schedules yet. Add one above.</td></tr>
        <?php else: foreach ( $schedules as $s ): ?>
          <tr id="seo-sched-<?= $s['id'] ?>">
            <td><strong><?= esc_html( $s['schedule_label'] ) ?></strong></td>
            <td><?= $s['is_active'] ? esc_html( $s['next_run'] ) : '—' ?></td>
            <td><?= $s['last_run'] ? esc_html( $s['last_run'] ) : 'Never' ?></td>
            <td><?= seo_outreach_badge( $s['is_active'] ? 'Active' : 'Paused' ) ?></td>
            <td class="seo-actions">
              <button class="seo-btn seo-btn-sm seo-btn-outline" onclick="seoToggleSched(<?= $s['id'] ?>)">
                <span class="dashicons dashicons-<?= $s['is_active'] ? 'controls-pause' : 'controls-play' ?>"></span>
              </button>
              <button class="seo-btn seo-btn-sm seo-btn-danger" onclick="seoDeleteSched(<?= $s['id'] ?>)">
                <span class="dashicons dashicons-trash"></span>
              </button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-backup"></span> Campaign Run History</div>
  <div class="seo-card-body seo-p0">
    <table class="seo-table">
      <thead><tr><th>Started</th><th>Finished</th><th>Processed</th><th>Sent</th><th>Failed</th><th>Trigger</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ( empty( $runs ) ): ?>
          <tr><td colspan="7" class="seo-empty">No runs yet.</td></tr>
        <?php else: foreach ( $runs as $r ): ?>
          <tr>
            <td><?= esc_html( $r['started_at'] ) ?></td>
            <td><?= esc_html( $r['finished_at'] ?? '—' ) ?></td>
            <td><?= (int) $r['total_processed'] ?></td>
            <td class="seo-green"><?= (int) $r['total_sent'] ?></td>
            <td class="seo-red"><?= (int) $r['total_failed'] ?></td>
            <td><?= seo_outreach_badge( $r['trigger_type'] ) ?></td>
            <td><?= seo_outreach_badge( $r['status'] ) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function seoToggleTime() {
  const t = document.getElementById('seo-sched-type').value;
  const show = ['daily','every_2_days','weekly_mon','weekly_wed','weekly_fri','weekdays'].includes(t);
  document.getElementById('seo-time-field').style.display = show ? '' : 'none';
}
seoToggleTime();

async function seoAddSchedule() {
  const res = await jQuery.post(seoOutreach.ajaxUrl, {
    action: 'seo_outreach_save_schedule', nonce: seoOutreach.nonce,
    schedule_type: document.getElementById('seo-sched-type').value,
    run_time: document.getElementById('seo-run-time').value,
  });
  const el = document.getElementById('seo-sched-notice');
  el.style.display = 'block';
  el.className = 'seo-notice seo-notice-' + (res.success ? 'success' : 'error');
  el.textContent = res.success ? 'Schedule added. Reload to see it.' : (res.data?.message || 'Error');
  if (res.success) setTimeout(() => location.reload(), 1500);
}

async function seoToggleSched(id) {
  await jQuery.post(seoOutreach.ajaxUrl, { action: 'seo_outreach_toggle_schedule', nonce: seoOutreach.nonce, id });
  location.reload();
}

async function seoDeleteSched(id) {
  if (!confirm('Delete this schedule?')) return;
  await jQuery.post(seoOutreach.ajaxUrl, { action: 'seo_outreach_delete_schedule', nonce: seoOutreach.nonce, id });
  document.getElementById('seo-sched-' + id)?.remove();
}
</script>

<?php seo_outreach_footer(); ?>
