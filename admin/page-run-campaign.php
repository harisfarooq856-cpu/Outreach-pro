<?php
defined( 'ABSPATH' ) || exit;
require_once SEO_OUTREACH_DIR . 'admin/partials.php';
seo_outreach_header( 'Run Campaign', 'dashicons-controls-play' );
?>

<div class="seo-card" id="seo-preflight-card">
  <div class="seo-card-header"><span class="dashicons dashicons-yes-alt"></span> Pre-Flight Check</div>
  <div class="seo-card-body">
    <div id="seo-preflight-list">
      <p class="seo-muted"><span class="dashicons dashicons-update seo-spin"></span> Checking configuration...</p>
    </div>
    <div id="seo-launch-area" style="display:none;margin-top:20px;border-top:1px solid #e2e8f0;padding-top:16px">
      <div class="seo-form-row">
        <div class="seo-form-group">
          <label>Max Leads for This Run</label>
          <input type="number" id="seo-max-leads" value="5" min="1" max="500" class="regular-text">
          <p class="description">Start with 5 for a test run.</p>
        </div>
      </div>
      <button class="seo-btn seo-btn-primary seo-btn-lg" id="seo-run-btn" onclick="seoRunCampaign()">
        <span class="dashicons dashicons-controls-play"></span> Launch Campaign
      </button>
    </div>
  </div>
</div>

<div class="seo-card" id="seo-progress-card" style="display:none">
  <div class="seo-card-header"><span class="dashicons dashicons-update seo-spin"></span> Campaign Running...</div>
  <div class="seo-card-body">
    <div class="seo-progress-stats">
      <div class="seo-pstat"><div class="seo-pstat-val" id="seo-p-processed">0</div><div>Processed</div></div>
      <div class="seo-pstat"><div class="seo-pstat-val seo-green" id="seo-p-sent">0</div><div>Sent</div></div>
      <div class="seo-pstat"><div class="seo-pstat-val seo-red" id="seo-p-failed">0</div><div>Failed</div></div>
    </div>
    <div class="seo-log-stream" id="seo-log-stream"></div>
  </div>
</div>

<div class="seo-card" id="seo-results-card" style="display:none">
  <div class="seo-card-header" id="seo-results-header"><span class="dashicons dashicons-yes-alt"></span> Campaign Complete</div>
  <div class="seo-card-body" id="seo-results-body"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', seoPreflight);

async function seoPreflight() {
  const res  = await jQuery.post(seoOutreach.ajaxUrl, { action: 'seo_outreach_preflight', nonce: seoOutreach.nonce });
  const list = document.getElementById('seo-preflight-list');
  if (!res.success) { list.innerHTML = '<p class="seo-red">Error loading checks.</p>'; return; }
  let allOk = true;
  list.innerHTML = res.data.checks.map(c => {
    if (!c.ok) allOk = false;
    return `<div class="seo-preflight-item ${c.ok ? 'ok' : 'fail'}">
      <span class="dashicons dashicons-${c.ok ? 'yes-alt' : 'dismiss'}"></span>
      <span><strong>${c.label}:</strong> ${c.message}</span>
    </div>`;
  }).join('');
  if (allOk) document.getElementById('seo-launch-area').style.display = 'block';
  else list.innerHTML += '<div class="seo-notice seo-notice-warning">Fix the issues above in <a href="<?= admin_url('admin.php?page=seo-outreach-settings') ?>">Settings</a>.</div>';
}

async function seoRunCampaign() {
  const maxLeads = parseInt(document.getElementById('seo-max-leads').value);
  document.getElementById('seo-preflight-card').style.display = 'none';
  document.getElementById('seo-progress-card').style.display  = 'block';

  const pollId = setInterval(seoPollLogs, 2500);
  try {
    const res = await jQuery.ajax({
      url:     seoOutreach.ajaxUrl,
      method:  'POST',
      data:    { action: 'seo_outreach_run_campaign', nonce: seoOutreach.nonce, max_leads: maxLeads },
      timeout: 600000  // 10 minutes — long enough for any campaign run
    });
    clearInterval(pollId);
    await seoPollLogs(); // final log flush
    if (res.success) {
      seoShowResults(res.data || res);
    } else {
      seoShowResults({ error: res.data?.message || 'Campaign failed. Check the Activity Log for details.' });
    }
  } catch(e) {
    clearInterval(pollId);
    await seoPollLogs(); // still flush any logs that made it in
    let msg = 'Campaign request timed out or was interrupted.';
    if (e.statusText && e.statusText !== 'error') msg = e.statusText;
    if (e.responseJSON?.data?.message) msg = e.responseJSON.data.message;
    seoShowResults({ error: msg + ' Check the Activity Log tab for partial results.' });
  }
}

async function seoPollLogs() {
  const res = await jQuery.post(seoOutreach.ajaxUrl, { action: 'seo_outreach_poll_logs', nonce: seoOutreach.nonce });
  if (!res.success) return;
  const { logs, stats } = res.data;
  document.getElementById('seo-p-processed').textContent = stats.processed || 0;
  document.getElementById('seo-p-sent').textContent      = stats.sent || 0;
  document.getElementById('seo-p-failed').textContent    = stats.failed || 0;
  const stream = document.getElementById('seo-log-stream');
  stream.innerHTML = logs.map(l => {
    // Parse "website → email (business)" format
    const ident = l.lead_identifier || '';
    let displayIdent = ident;
    const arrowIdx = ident.indexOf(' → ');
    if (arrowIdx !== -1) {
      const website = ident.substring(0, arrowIdx);
      const rest    = ident.substring(arrowIdx + 3);
      const emailEnd = rest.indexOf(' (');
      const email   = emailEnd !== -1 ? rest.substring(0, emailEnd) : rest;
      const biz     = emailEnd !== -1 ? rest.substring(emailEnd) : '';
      displayIdent  = `<span class="seo-log-url">${website}</span><span class="seo-log-sep">→</span><span class="seo-log-email">${email}</span><span class="seo-log-biz">${biz}</span>`;
    }
    const icons = { success:'dashicons-yes-alt', error:'dashicons-dismiss', info:'dashicons-info-outline' };
    const icon  = icons[l.status] || 'dashicons-minus';
    return `<div class="seo-log-line ${l.status}">
      <span class="seo-log-time">${l.created_at.substring(11,19)}</span>
      <span class="seo-log-ident">${displayIdent}</span>
      <span class="seo-log-action">${l.action.replace(/_/g,' ')}</span>
      <span class="dashicons ${icon} seo-log-icon"></span>
    </div>`;
  }).join('');
  stream.scrollTop = stream.scrollHeight;
}

function seoShowResults(data) {
  document.getElementById('seo-progress-card').style.display = 'none';
  document.getElementById('seo-results-card').style.display  = 'block';
  if (data.error) {
    document.getElementById('seo-results-header').innerHTML = '<span class="dashicons dashicons-warning" style="color:#ef4444"></span> Campaign Error';
    document.getElementById('seo-results-body').innerHTML = `
      <div class="seo-notice seo-notice-error" style="margin-bottom:16px">${data.error}</div>
      <div style="display:flex;gap:8px">
        <a href="<?= admin_url('admin.php?page=seo-outreach-logs') ?>" class="seo-btn seo-btn-outline">View Activity Log</a>
        <a href="<?= admin_url('admin.php?page=seo-outreach-run') ?>" class="seo-btn seo-btn-primary">Try Again</a>
      </div>`;
    return;
  }

  // No pending leads found
  if ( (data.processed === 0 || data.processed == null) && (data.sent === 0 || data.sent == null) ) {
    document.getElementById('seo-results-header').innerHTML = '<span class="dashicons dashicons-info" style="color:#f59e0b"></span> No Pending Leads';
    document.getElementById('seo-results-body').innerHTML = `
      <div class="seo-notice seo-notice-warning" style="margin-bottom:16px">
        <strong>No pending leads found in your Google Sheet.</strong><br>
        All rows either have a value in the Status column (already sent/failed) or the sheet is empty.
        Add new leads with a blank Status column and run again.
      </div>
      <div style="display:flex;gap:8px">
        <a href="<?= admin_url('admin.php?page=seo-outreach-leads') ?>" class="seo-btn seo-btn-outline">View Leads</a>
        <a href="<?= admin_url('admin.php?page=seo-outreach-logs') ?>" class="seo-btn seo-btn-outline">View Activity Log</a>
        <a href="<?= admin_url('admin.php?page=seo-outreach-run') ?>" class="seo-btn seo-btn-primary">Run Again</a>
      </div>`;
    return;
  }

  const errors = (data.errors||[]).length ? `<div class="seo-notice seo-notice-warning"><strong>Errors:</strong><br>${(data.errors||[]).join('<br>')}</div>` : '';
  document.getElementById('seo-results-body').innerHTML = `
    <div class="seo-results-grid">
      <div class="seo-result-stat green"><div>${data.sent}</div><p>Sent</p></div>
      <div class="seo-result-stat red"><div>${data.failed}</div><p>Failed</p></div>
      <div class="seo-result-stat blue"><div>${data.processed}</div><p>Processed</p></div>
    </div>
    ${errors}
    <div style="margin-top:16px;display:flex;gap:8px">
      <a href="<?= admin_url('admin.php?page=seo-outreach-run') ?>" class="seo-btn seo-btn-outline">Run Again</a>
      <a href="<?= admin_url('admin.php?page=seo-outreach-logs') ?>" class="seo-btn seo-btn-outline">View Activity Log</a>
      <a href="<?= admin_url('admin.php?page=seo-outreach') ?>" class="seo-btn seo-btn-primary">Dashboard</a>
    </div>`;
}
</script>

<?php seo_outreach_footer(); ?>
