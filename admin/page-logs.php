<?php
defined('ABSPATH')||exit;
require_once SEO_OUTREACH_DIR.'admin/partials.php';

$db         = new SEO_Outreach_Database();
$page       = max(1,(int)($_GET['paged']??1));
$filter     = sanitize_key($_GET['status']??'');
$result     = $db->get_logs_paginated($page,50,$filter);
$logs       = $result['rows'];
$pages      = $result['pages'];
$total      = $result['total'];
$auto_secs  = (int) SEO_Outreach_Settings::get('log_auto_refresh_secs','30');
$retention  = (int) SEO_Outreach_Settings::get('log_retention_days','7');
$base       = admin_url('admin.php?page=seo-outreach-logs');

seo_outreach_header('Activity Logs','dashicons-list-view');
?>

<div class="seo-logs-toolbar">
  <!-- Filter buttons -->
  <div class="seo-filter-bar">
    <a href="<?=$base?>" class="seo-btn seo-btn-sm <?=!$filter?'seo-btn-primary':'seo-btn-outline'?>">All</a>
    <a href="<?=$base?>&status=success" class="seo-btn seo-btn-sm <?=$filter==='success'?'seo-btn-primary':'seo-btn-outline'?>">Success</a>
    <a href="<?=$base?>&status=error"   class="seo-btn seo-btn-sm <?=$filter==='error'  ?'seo-btn-primary':'seo-btn-outline'?>">Errors</a>
    <a href="<?=$base?>&status=info"    class="seo-btn seo-btn-sm <?=$filter==='info'   ?'seo-btn-primary':'seo-btn-outline'?>">Info</a>
  </div>

  <!-- Right controls -->
  <div class="seo-logs-controls">
    <span class="seo-muted" style="font-size:12px"><?=number_format($total)?> entries &bull; Retention: <?=$retention?> days</span>

    <!-- Auto-refresh toggle -->
    <div class="seo-autorefresh-wrap">
      <label class="seo-autorefresh-label">
        <div class="seo-toggle-switch" style="width:36px;height:20px">
          <input type="checkbox" id="seo-autorefresh-toggle" onchange="seoToggleAutoRefresh(this.checked)">
          <span class="seo-slider" style="border-radius:10px"></span>
        </div>
        <span style="font-size:12px">Auto-refresh (<?=$auto_secs?>s)</span>
      </label>
    </div>

    <!-- Manual refresh -->
    <button class="seo-btn seo-btn-sm seo-btn-outline" onclick="location.reload()">
      <span class="dashicons dashicons-update" id="seo-refresh-icon"></span> Refresh
    </button>
  </div>
</div>

<!-- Auto-refresh countdown -->
<div id="seo-autorefresh-bar" style="display:none;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:8px 14px;margin-bottom:12px;font-size:12px;color:#64748b;display:none;align-items:center;gap:10px">
  <span class="dashicons dashicons-clock" style="font-size:15px"></span>
  <span>Auto-refreshing in <strong id="seo-countdown"><?=$auto_secs?></strong> seconds</span>
  <button class="seo-btn seo-btn-sm seo-btn-outline" style="margin-left:auto;padding:2px 8px" onclick="seoStopAutoRefresh()">Stop</button>
</div>

<div class="seo-card"><div class="seo-card-body seo-p0">
<table class="seo-table" id="seo-logs-table">
  <thead>
    <tr><th>Time</th><th>Lead</th><th>Action</th><th>Status</th><th>Message</th></tr>
  </thead>
  <tbody>
    <?php if(empty($logs)):?>
      <tr><td colspan="5" class="seo-empty">No logs found.</td></tr>
    <?php else: foreach($logs as $l):?>
    <tr>
      <td style="white-space:nowrap;font-size:12px"><?=esc_html($l['created_at'])?></td>
      <td class="seo-truncate" style="max-width:160px"><?=esc_html($l['lead_identifier'])?></td>
      <td><code style="font-size:11px"><?=esc_html($l['action'])?></code></td>
      <td><?=seo_outreach_badge($l['status'])?></td>
      <td class="seo-truncate seo-muted" style="max-width:240px;font-size:12px"><?=esc_html(substr($l['message']??'',0,120))?></td>
    </tr>
    <?php endforeach;endif;?>
  </tbody>
</table>
</div></div>

<?php if($pages>1):?>
<div class="seo-pagination">
  <?php for($i=1;$i<=$pages;$i++):?>
    <a href="<?=$base?>&status=<?=$filter?>&paged=<?=$i?>" class="seo-page-btn <?=$i===$page?'active':''?>"><?=$i?></a>
  <?php endfor;?>
</div>
<?php endif;?>

<script>
let seoRefreshTimer = null;
let seoCountdown    = <?=$auto_secs?>;
let seoCountdownVal = <?=$auto_secs?>;

function seoToggleAutoRefresh(on) {
  const bar = document.getElementById('seo-autorefresh-bar');
  if (on) {
    bar.style.display = 'flex';
    seoCountdownVal = <?=$auto_secs?>;
    seoStartCountdown();
  } else {
    seoStopAutoRefresh();
    bar.style.display = 'none';
  }
}

function seoStartCountdown() {
  seoCountdownVal = <?=$auto_secs?>;
  document.getElementById('seo-countdown').textContent = seoCountdownVal;
  seoRefreshTimer = setInterval(() => {
    seoCountdownVal--;
    document.getElementById('seo-countdown').textContent = seoCountdownVal;
    if (seoCountdownVal <= 0) { clearInterval(seoRefreshTimer); location.reload(); }
  }, 1000);
}

function seoStopAutoRefresh() {
  clearInterval(seoRefreshTimer);
  document.getElementById('seo-autorefresh-toggle').checked = false;
  document.getElementById('seo-autorefresh-bar').style.display = 'none';
}
</script>

<?php seo_outreach_footer();?>
