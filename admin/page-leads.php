<?php
defined('ABSPATH') || exit;
require_once SEO_OUTREACH_DIR . 'admin/partials.php';

// CSV download handled via AJAX endpoint
$sheet_id   = SEO_Outreach_Settings::get('google_sheet_id');
$sheet_name = SEO_Outreach_Settings::get('google_sheet_name');
$sheet_tab  = SEO_Outreach_Settings::get('google_sheet_tab','Sheet1');
$sub        = sanitize_key($_GET['sub'] ?? 'leads');
$db         = new SEO_Outreach_Database();
$counts     = $db->get_lead_counts();
$last_sync  = $db->get_last_sync();
?>

<style>
.leads-subtab-nav{display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #e2e8f0}
.leads-subtab-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;font-size:13px;font-weight:600;color:#64748b;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s}
.leads-subtab-btn:hover{color:#4f46e5}
.leads-subtab-btn.active{color:#4f46e5;border-bottom-color:#4f46e5}
.leads-subtab-btn .dashicons{font-size:15px;width:15px;height:15px}
.leads-subtab-btn .seo-badge{margin-left:4px;font-size:10px;padding:1px 6px}

/* Sync bar */
.leads-sync-bar{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.leads-sync-bar .sync-info{flex:1;min-width:200px}
.leads-sync-bar .sync-info strong{display:block;font-size:13px;color:#0f172a;margin-bottom:2px}
.leads-sync-bar .sync-info span{font-size:12px;color:#64748b}
.leads-sync-status{font-size:12px;font-weight:500}

/* Filter tabs */
.leads-filter-tabs{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
.leads-filter-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;transition:all .15s}
.leads-filter-btn:hover{border-color:#6366f1;color:#4f46e5}
.leads-filter-btn.active{background:#6366f1;color:#fff;border-color:#6366f1}
.leads-filter-btn.active-green{background:#22c55e;color:#fff;border-color:#22c55e}
.leads-filter-btn.active-red{background:#ef4444;color:#fff;border-color:#ef4444}
.leads-filter-count{background:rgba(255,255,255,.25);border-radius:10px;padding:0 5px;font-size:10px;font-weight:800}

/* Table improvements */
.seo-table .col-website a{color:#4f46e5;font-weight:500;text-decoration:none;display:flex;align-items:center;gap:4px}
.seo-table .col-website a:hover{text-decoration:underline}
.seo-table .col-delete{text-align:center;width:40px}
.seo-table td.col-status .seo-badge{font-size:11px}
.badge-sent   {background:#dcfce7;color:#15803d}
.badge-failed {background:#fee2e2;color:#dc2626}
.badge-pending{background:#fef3c7;color:#b45309}

/* Bulk delete toolbar */
.leads-bulk-bar{display:none;align-items:center;gap:10px;padding:10px 14px;background:#fff7ed;border:1.5px solid #fed7aa;border-radius:8px;margin-bottom:12px;flex-wrap:wrap}
.leads-bulk-bar.visible{display:flex}
.leads-bulk-count{font-size:13px;font-weight:600;color:#92400e;flex:1}
.col-check{width:36px;text-align:center}
.seo-table thead .col-check input[type=checkbox],
.seo-table tbody .col-check input[type=checkbox]{width:15px;height:15px;cursor:pointer;accent-color:#6366f1}

/* Empty / loading states */
.leads-state-box{padding:50px 20px;text-align:center}
.leads-state-box .dashicons{font-size:44px;width:44px;height:44px;display:block;margin:0 auto 12px}
.leads-state-box h3{font-size:16px;font-weight:600;margin:0 0 6px}
.leads-state-box p{font-size:13px;color:#94a3b8;margin:0 0 14px}

/* Column guide */
.leads-how-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:20px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.leads-how-header{background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.leads-how-header-left{display:flex;align-items:center;gap:12px}
.leads-how-header-left .dashicons{font-size:24px;width:24px;height:24px;color:#fff}
.leads-how-header h2{color:#fff;font-size:16px;font-weight:700;margin:0}
.leads-how-header p{color:rgba(255,255,255,.75);font-size:12px;margin:3px 0 0}
.btn-csv-download{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.4);border-radius:8px;padding:9px 16px;font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;white-space:nowrap}
.btn-csv-download:hover{background:rgba(255,255,255,.28);color:#fff}
.leads-steps{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid #e2e8f0}
.leads-step{padding:18px;border-right:1px solid #f1f5f9;position:relative}
.leads-step:last-child{border-right:none}
.leads-step-num{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;margin-bottom:9px}
.s1{background:rgba(99,102,241,.12);color:#4f46e5}.s2{background:rgba(20,184,166,.12);color:#0d9488}.s3{background:rgba(245,158,11,.12);color:#b45309}.s4{background:rgba(34,197,94,.12);color:#15803d}
.leads-step h4{font-size:13px;font-weight:700;color:#0f172a;margin:0 0 4px}
.leads-step p{font-size:12px;color:#64748b;margin:0;line-height:1.5}
.leads-step-arrow{position:absolute;right:-10px;top:50%;transform:translateY(-50%);width:20px;height:20px;background:#fff;border:1px solid #e2e8f0;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:1;font-size:11px;color:#94a3b8;font-weight:700}
.leads-col-section{padding:20px}
.leads-col-section-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px}
.leads-col-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.leads-col-card{border-radius:8px;overflow:hidden;border:1px solid #e2e8f0}
.leads-col-hdr{padding:10px 12px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:space-between}
.leads-col-hdr .col-letter{font-size:20px;font-weight:900;opacity:.4;line-height:1}
.col-a .leads-col-hdr{background:rgba(99,102,241,.08);color:#4f46e5}.col-b .leads-col-hdr{background:rgba(20,184,166,.08);color:#0d9488}.col-c .leads-col-hdr{background:rgba(245,158,11,.08);color:#b45309}.col-d .leads-col-hdr{background:rgba(100,116,139,.08);color:#64748b}
.leads-col-body{padding:10px 12px;background:#fafbfc}
.col-name{font-size:13px;font-weight:600;color:#0f172a;display:block;margin-bottom:4px}
.col-example{font-size:11px;color:#94a3b8;font-family:'Courier New',monospace;background:#f1f5f9;padding:3px 7px;border-radius:4px;display:block;margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.col-note{font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:3px}
.col-note .dashicons{font-size:12px;width:12px;height:12px}
.badge-req{background:#fee2e2;color:#dc2626;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700;vertical-align:middle}
.badge-opt{background:#fef3c7;color:#b45309;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700;vertical-align:middle}
.badge-rec{background:#dcfce7;color:#15803d;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700;vertical-align:middle}
.badge-auto{background:#ede9fe;color:#6d28d9;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700;vertical-align:middle}
.leads-preview{width:100%;border-collapse:collapse;font-size:12px;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0}
.leads-preview th{padding:9px 12px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:#f8fafc;border-bottom:2px solid #e2e8f0}
.leads-preview td{padding:9px 12px;border-bottom:1px solid #f8fafc;color:#374151}
.leads-preview tr:last-child td{border-bottom:none}
.leads-preview .row-p td:first-child{border-left:3px solid #6366f1}
.leads-preview .row-s td{color:#94a3b8;font-style:italic}
.leads-preview .row-s td:first-child{border-left:3px solid #22c55e}
.s-empty{color:#d1d5db;font-style:italic;font-size:11px}
.s-sent{background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}

/* Instructions */
.instr-section{background:#fff;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:14px;overflow:hidden}
.instr-section-hdr{padding:14px 18px;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;background:#fafbfc;border-bottom:1px solid transparent;transition:background .15s}
.instr-section-hdr:hover{background:#f1f5f9}
.instr-section-hdr.open{background:#fff;border-bottom-color:#e2e8f0}
.instr-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.instr-icon .dashicons{font-size:18px;width:18px;height:18px}
.instr-title{flex:1}
.instr-title strong{display:block;font-size:14px;color:#0f172a}
.instr-title span{font-size:12px;color:#64748b}
.instr-chevron{font-size:16px;color:#94a3b8;transition:transform .2s}
.instr-chevron.open{transform:rotate(180deg)}
.instr-body{display:none;padding:18px 20px}
.instr-body.open{display:block}
.instr-steps{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px}
.instr-steps li{display:flex;gap:12px;align-items:flex-start}
.instr-step-num{width:24px;height:24px;border-radius:50%;background:var(--step-bg,#ede9fe);color:var(--step-color,#6d28d9);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.instr-steps li p{font-size:13px;color:#374151;margin:0;line-height:1.6}
.instr-steps li p strong{color:#0f172a}
.instr-steps li p code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:12px;color:#4f46e5}
.instr-steps li p a{color:#4f46e5}
.instr-tip{background:#f0f9ff;border:1px solid #bae6fd;border-radius:7px;padding:10px 14px;font-size:12px;color:#0369a1;margin-top:12px;display:flex;gap:8px;align-items:flex-start}
.instr-tip .dashicons{font-size:15px;width:15px;height:15px;flex-shrink:0;margin-top:1px}
.instr-warn{background:#fff7ed;border:1px solid #fed7aa;border-radius:7px;padding:10px 14px;font-size:12px;color:#c2410c;margin-top:12px;display:flex;gap:8px;align-items:flex-start}
.instr-warn .dashicons{font-size:15px;width:15px;height:15px;flex-shrink:0;margin-top:1px}

@media(max-width:900px){.leads-steps,.leads-col-grid{grid-template-columns:1fr 1fr}.leads-step-arrow{display:none}}
@media(max-width:600px){.leads-steps,.leads-col-grid{grid-template-columns:1fr}}
</style>

<?php seo_outreach_header('Leads','dashicons-groups'); ?>

<!-- Sub-tab navigation -->
<div class="leads-subtab-nav">
    <a href="<?= esc_url(admin_url('admin.php?page=seo-outreach-leads&sub=leads')) ?>"
       class="leads-subtab-btn <?= $sub==='leads'?'active':'' ?>">
        <span class="dashicons dashicons-list-view"></span> Lead List
        <span class="seo-badge seo-badge-info"><?= $counts['total'] ?></span>
    </a>
    <a href="<?= esc_url(admin_url('admin.php?page=seo-outreach-leads&sub=instructions')) ?>"
       class="leads-subtab-btn <?= $sub==='instructions'?'active':'' ?>">
        <span class="dashicons dashicons-info-outline"></span> Setup Instructions
    </a>
    <a href="<?= esc_url(admin_url('admin.php?page=seo-outreach-leads&sub=format')) ?>"
       class="leads-subtab-btn <?= $sub==='format'?'active':'' ?>">
        <span class="dashicons dashicons-editor-table"></span> Sheet Format
    </a>
</div>

<?php if ($sub === 'leads'): ?>

<!-- Sync bar -->
<div class="leads-sync-bar">
    <div class="sync-info">
        <strong>
            <?php if ($sheet_id): ?>
                <span class="dashicons dashicons-yes-alt" style="color:#22c55e;font-size:15px;width:15px;height:15px;vertical-align:middle"></span>
                <?= esc_html($sheet_name ?: $sheet_id) ?> &rarr; <em><?= esc_html($sheet_tab) ?></em>
            <?php else: ?>
                <span class="dashicons dashicons-warning" style="color:#f59e0b;font-size:15px;width:15px;height:15px;vertical-align:middle"></span>
                No sheet connected
            <?php endif; ?>
        </strong>
        <span>
            Last synced: <span id="seo-last-sync"><?= $last_sync ? esc_html(human_time_diff(strtotime($last_sync), current_time('timestamp')).' ago') : 'Never' ?></span>
            &bull; <?= $counts['total'] ?> total &bull;
            <span style="color:#b45309"><?= $counts['pending'] ?> pending</span> &bull;
            <span style="color:#15803d"><?= $counts['sent'] ?> sent</span>
            <?php if ($counts['failed']): ?> &bull; <span style="color:#dc2626"><?= $counts['failed'] ?> failed</span><?php endif; ?>
        </span>
    </div>
    <span id="seo-sync-status" class="leads-sync-status"></span>
    <?php if ($sheet_id): ?>
    <a href="https://docs.google.com/spreadsheets/d/<?= esc_attr($sheet_id) ?>/edit" target="_blank" class="seo-btn seo-btn-sm seo-btn-outline">
        <span class="dashicons dashicons-external"></span> Open Sheet
    </a>
    <?php endif; ?>
    <a href="<?= admin_url('admin.php?page=seo-outreach-settings&tab=sheets') ?>" class="seo-btn seo-btn-sm seo-btn-outline">
        <span class="dashicons dashicons-edit"></span> Change Sheet
    </a>
    <button class="seo-btn seo-btn-outline" id="seo-debug-btn" onclick="seoDebugSync()" title="See raw data from Google Sheet">
        <span class="dashicons dashicons-visibility" style="font-size:15px;width:15px;height:15px"></span>
        Debug Sheet
    </button>
    <button class="seo-btn seo-btn-primary" id="seo-sync-btn" onclick="seoSyncLeads()">
        <span class="dashicons dashicons-update" id="seo-sync-icon" style="font-size:15px;width:15px;height:15px"></span>
        Sync from Google Sheet
    </button>
</div>

<!-- Filter tabs -->
<div class="leads-filter-tabs">
    <span style="font-size:12px;color:#64748b;font-weight:600;margin-right:4px">Filter:</span>
    <button class="leads-filter-btn active" id="filter-all"     onclick="seoSetFilter('all',     this)">All <span class="leads-filter-count"><?= $counts['total'] ?></span></button>
    <button class="leads-filter-btn"        id="filter-pending" onclick="seoSetFilter('pending', this)">Pending <span class="leads-filter-count"><?= $counts['pending'] ?></span></button>
    <button class="leads-filter-btn"        id="filter-sent"    onclick="seoSetFilter('sent',    this)">Sent <span class="leads-filter-count"><?= $counts['sent'] ?></span></button>
    <?php if ($counts['failed']): ?>
    <button class="leads-filter-btn"        id="filter-failed"  onclick="seoSetFilter('failed',  this)">Failed <span class="leads-filter-count"><?= $counts['failed'] ?></span></button>
    <?php endif; ?>
    <span style="margin-left:auto;font-size:12px;color:#94a3b8" id="seo-showing-count"></span>
</div>

<!-- Bulk delete toolbar (shown when rows selected) -->
<div class="leads-bulk-bar" id="seo-bulk-bar">
    <span class="leads-bulk-count" id="seo-bulk-count">0 selected</span>
    <button class="seo-btn seo-btn-sm seo-btn-outline" onclick="seoSelectAllLeads(false)">
        <span class="dashicons dashicons-no-alt" style="font-size:14px;width:14px;height:14px"></span> Deselect All
    </button>
    <button class="seo-btn seo-btn-sm" style="background:#ef4444;color:#fff;border-color:#ef4444" onclick="seoBulkDelete()">
        <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px"></span> Delete Selected
    </button>
</div>

<!-- Leads table -->
<div class="seo-card">
    <div class="seo-card-body seo-p0">

        <!-- Loading -->
        <div id="seo-leads-loading" style="display:none">
            <div class="leads-state-box">
                <div style="width:40px;height:40px;border:3px solid #e2e8f0;border-top-color:#6366f1;border-radius:50%;animation:seo-spin 1s linear infinite;margin:0 auto 12px"></div>
                <p>Loading leads...</p>
            </div>
        </div>

        <?php if (!$sheet_id): ?>
        <!-- No sheet connected -->
        <div class="leads-state-box">
            <span class="dashicons dashicons-warning" style="color:#f59e0b"></span>
            <h3>No Sheet Connected</h3>
            <p>Connect your Google Sheet first to import and manage leads.</p>
            <a href="<?= admin_url('admin.php?page=seo-outreach-settings&tab=sheets') ?>" class="seo-btn seo-btn-primary">
                <span class="dashicons dashicons-admin-settings"></span> Connect Google Sheet
            </a>
        </div>
        <?php else: ?>

        <!-- Empty state (shown when no leads in DB) -->
        <div class="leads-state-box" id="seo-empty-state" style="<?= $counts['total'] > 0 ? 'display:none' : '' ?>">
            <span class="dashicons dashicons-cloud-upload" style="color:#6366f1"></span>
            <h3>No Leads Yet</h3>
            <p>Click <strong>Sync from Google Sheet</strong> above to import your leads into the plugin database.</p>
        </div>

        <!-- Data table — always in DOM, shown/hidden by JS -->
        <table class="seo-table" id="seo-leads-table" style="<?= $counts['total'] === 0 ? 'display:none' : '' ?>">
            <thead>
                <tr>
                    <th class="col-check"><input type="checkbox" id="seo-check-all" title="Select all" onchange="seoSelectAllLeads(this.checked)"></th>
                    <th style="width:44px">#</th>
                    <th style="color:#4f46e5"><span class="dashicons dashicons-admin-site-alt3" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span> Website URL</th>
                    <th style="color:#0d9488"><span class="dashicons dashicons-email-alt" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span> Contact Email</th>
                    <th style="color:#b45309">Business Name</th>
                    <th>Status</th>
                    <th class="col-delete"></th>
                </tr>
            </thead>
            <tbody id="seo-leads-body">
                <?php
                $leads_data = $db->get_leads('', 1, 100);
                foreach ($leads_data['leads'] as $lead):
                    $status_class = 'badge-pending';
                    $status_label = 'Pending';
                    if (stripos($lead['status'], 'Sent') === 0) {
                        $status_class = 'badge-sent';
                        $status_label = $lead['status'];
                    } elseif (stripos($lead['status'], 'Failed') === 0) {
                        $status_class = 'badge-failed';
                        $status_label = $lead['status'];
                    }
                ?>
                <tr id="lead-row-<?= $lead['id'] ?>">
                    <td class="col-check"><input type="checkbox" class="seo-lead-check" value="<?= $lead['id'] ?>" onchange="seoUpdateBulkBar()"></td>
                    <td style="color:#94a3b8;font-size:12px"><?= (int)$lead['sheet_row'] ?></td>
                    <td class="col-website">
                        <a href="<?= esc_url($lead['website_url']) ?>" target="_blank">
                            <span class="dashicons dashicons-admin-site-alt3" style="font-size:12px;width:12px;height:12px;flex-shrink:0"></span>
                            <?= esc_html($lead['website_url']) ?>
                        </a>
                    </td>
                    <td><?= esc_html($lead['contact_email']) ?></td>
                    <td><?= esc_html($lead['business_name']) ?: '<span style="color:#d1d5db">—</span>' ?></td>
                    <td class="col-status">
                        <span class="seo-badge <?= $status_class ?>"><?= esc_html($status_label) ?></span>
                    </td>
                    <td class="col-delete">
                        <button class="seo-btn seo-btn-sm" style="background:none;border:none;color:#cbd5e1;padding:2px 6px;cursor:pointer" title="Delete lead"
                            onclick="seoDeleteLead(<?= $lead['id'] ?>, this)">
                            <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px"></span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; // sheet_id check ?>

    </div>
</div>

<?php elseif ($sub === 'instructions'): ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 style="margin:0;font-size:18px;color:#0f172a">Setup Instructions</h2>
        <p style="margin:3px 0 0;font-size:13px;color:#64748b">Complete step-by-step guide to connect Google Sheets and run your first campaign</p>
    </div>
    <a href="<?= esc_url(add_query_arg(['action'=>'seo_outreach_download_csv','nonce'=>wp_create_nonce('seo_outreach_csv')],admin_url('admin-ajax.php'))) ?>"
       class="seo-btn seo-btn-primary">
        <span class="dashicons dashicons-download"></span> Download CSV Template
    </a>
</div>

<!-- Step 1 -->
<div class="instr-section">
    <div class="instr-section-hdr open" onclick="seoToggleSection(this)">
        <div class="instr-icon" style="background:rgba(99,102,241,.1)"><span class="dashicons dashicons-cloud" style="color:#4f46e5"></span></div>
        <div class="instr-title"><strong>Step 1 — Google Cloud Console Setup</strong><span>Create a project and enable required APIs</span></div>
        <span class="dashicons dashicons-arrow-down-alt2 instr-chevron open"></span>
    </div>
    <div class="instr-body open">
        <ol class="instr-steps" style="--step-bg:rgba(99,102,241,.1);--step-color:#4f46e5">
            <li><div class="instr-step-num">1</div><p>Go to <a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a> &rarr; sign in with your Google account</p></li>
            <li><div class="instr-step-num">2</div><p>Click <strong>Select a project</strong> &rarr; <strong>New Project</strong> &rarr; name it <code>seo-outreach</code> &rarr; click <strong>Create</strong></p></li>
            <li><div class="instr-step-num">3</div><p>Go to <strong>APIs &amp; Services</strong> &rarr; <strong>Library</strong> &rarr; search <strong>Google Sheets API</strong> &rarr; <strong>Enable</strong></p></li>
            <li><div class="instr-step-num">4</div><p>Same for <strong>Google Drive API</strong> &rarr; search &rarr; <strong>Enable</strong>. Needed to list your sheets in the plugin.</p></li>
        </ol>
        <div class="instr-tip"><span class="dashicons dashicons-info"></span> Both APIs must be in the same Google Cloud project. Drive API lists sheets, Sheets API reads and writes data.</div>
    </div>
</div>

<!-- Step 2 -->
<div class="instr-section">
    <div class="instr-section-hdr" onclick="seoToggleSection(this)">
        <div class="instr-icon" style="background:rgba(20,184,166,.1)"><span class="dashicons dashicons-admin-users" style="color:#0d9488"></span></div>
        <div class="instr-title"><strong>Step 2 — Create Service Account &amp; JSON Key</strong><span>The plugin uses this to authenticate with Google</span></div>
        <span class="dashicons dashicons-arrow-down-alt2 instr-chevron"></span>
    </div>
    <div class="instr-body">
        <ol class="instr-steps" style="--step-bg:rgba(20,184,166,.1);--step-color:#0d9488">
            <li><div class="instr-step-num">1</div><p>In Google Cloud Console &rarr; <strong>IAM &amp; Admin</strong> &rarr; <strong>Service Accounts</strong></p></li>
            <li><div class="instr-step-num">2</div><p>Click <strong>+ Create Service Account</strong> &rarr; name it <code>seo-outreach-bot</code> &rarr; <strong>Create and Continue</strong> &rarr; skip roles &rarr; <strong>Done</strong></p></li>
            <li><div class="instr-step-num">3</div><p>Click the service account &rarr; <strong>Keys</strong> tab &rarr; <strong>Add Key</strong> &rarr; <strong>Create new key</strong> &rarr; select <strong>JSON</strong> &rarr; <strong>Create</strong></p></li>
            <li><div class="instr-step-num">4</div><p>A <code>.json</code> file downloads. Keep it safe — it provides Google API access.</p></li>
            <li><div class="instr-step-num">5</div><p>Go to <a href="<?= admin_url('admin.php?page=seo-outreach-settings&tab=sheets') ?>">Settings &rarr; Google Sheets</a> &rarr; click <strong>Upload JSON File</strong> &rarr; select the downloaded file</p></li>
        </ol>
        <div class="instr-tip"><span class="dashicons dashicons-info"></span> After uploading, the plugin displays the service account email. You need it in Step 3.</div>
    </div>
</div>

<!-- Step 3 -->
<div class="instr-section">
    <div class="instr-section-hdr" onclick="seoToggleSection(this)">
        <div class="instr-icon" style="background:rgba(245,158,11,.1)"><span class="dashicons dashicons-share" style="color:#b45309"></span></div>
        <div class="instr-title"><strong>Step 3 — Share Your Sheet With the Plugin</strong><span>Give the service account email Editor access</span></div>
        <span class="dashicons dashicons-arrow-down-alt2 instr-chevron"></span>
    </div>
    <div class="instr-body">
        <ol class="instr-steps" style="--step-bg:rgba(245,158,11,.1);--step-color:#b45309">
            <li><div class="instr-step-num">1</div><p>Copy the service account email from <a href="<?= admin_url('admin.php?page=seo-outreach-settings&tab=sheets') ?>">Settings &rarr; Google Sheets</a> (shown after uploading JSON)</p></li>
            <li><div class="instr-step-num">2</div><p>Open your Google Sheet &rarr; click <strong>Share</strong> (top right)</p></li>
            <li><div class="instr-step-num">3</div><p>Paste the service account email &rarr; change role to <strong>Editor</strong> &rarr; uncheck <em>Notify people</em> &rarr; click <strong>Share</strong></p></li>
            <li><div class="instr-step-num">4</div><p>Back in plugin &rarr; click <strong>Fetch My Sheets</strong> &rarr; your sheet appears &rarr; select it &rarr; select tab</p></li>
        </ol>
        <div class="instr-warn"><span class="dashicons dashicons-warning"></span> Must be <strong>Editor</strong>, not Viewer. Plugin needs Editor access to write "Sent" status back to your sheet.</div>
    </div>
</div>

<!-- Step 4 -->
<div class="instr-section">
    <div class="instr-section-hdr" onclick="seoToggleSection(this)">
        <div class="instr-icon" style="background:rgba(34,197,94,.1)"><span class="dashicons dashicons-editor-table" style="color:#15803d"></span></div>
        <div class="instr-title"><strong>Step 4 — Prepare Your Leads Sheet</strong><span>Required column headers in Row 1</span></div>
        <span class="dashicons dashicons-arrow-down-alt2 instr-chevron"></span>
    </div>
    <div class="instr-body">
        <ol class="instr-steps" style="--step-bg:rgba(34,197,94,.1);--step-color:#15803d">
            <li><div class="instr-step-num">1</div><p>Row 1 must have exactly: <code>Website URL</code> | <code>Contact Email</code> | <code>Business Name</code> | <code>Status</code></p></li>
            <li><div class="instr-step-num">2</div><p>From Row 2, add one lead per row. Website URL in Column A (include https://), email in Column B</p></li>
            <li><div class="instr-step-num">3</div><p>Leave Column D (Status) completely <strong>empty</strong> for new leads. Plugin writes "Sent - YYYY-MM-DD" automatically</p></li>
            <li><div class="instr-step-num">4</div><p>Come back here &rarr; click <strong>Sync from Google Sheet</strong> to import all leads into the plugin database</p></li>
        </ol>
        <div class="instr-tip"><span class="dashicons dashicons-info"></span> Syncing saves all leads locally — they persist even after page refresh. Sync again whenever you add new leads to your sheet.</div>
    </div>
</div>

<!-- Step 5 -->
<div class="instr-section">
    <div class="instr-section-hdr" onclick="seoToggleSection(this)">
        <div class="instr-icon" style="background:rgba(99,102,241,.1)"><span class="dashicons dashicons-controls-play" style="color:#4f46e5"></span></div>
        <div class="instr-title"><strong>Step 5 — Configure APIs &amp; Run Campaign</strong><span>Add Gemini + PageSpeed keys then launch</span></div>
        <span class="dashicons dashicons-arrow-down-alt2 instr-chevron"></span>
    </div>
    <div class="instr-body">
        <ol class="instr-steps" style="--step-bg:rgba(99,102,241,.1);--step-color:#4f46e5">
            <li><div class="instr-step-num">1</div><p><a href="<?= admin_url('admin.php?page=seo-outreach-settings&tab=api') ?>">Settings &rarr; API Keys</a> &rarr; add <strong>Gemini API key</strong> (from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>) + <strong>PageSpeed key</strong> &rarr; Test each</p></li>
            <li><div class="instr-step-num">2</div><p><a href="<?= admin_url('admin.php?page=seo-outreach-settings&tab=email') ?>">Settings &rarr; Email / SMTP</a> &rarr; add Gmail SMTP with an <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a></p></li>
            <li><div class="instr-step-num">3</div><p><a href="<?= admin_url('admin.php?page=seo-outreach-settings&tab=campaign') ?>">Settings &rarr; Campaign</a> &rarr; set your <strong>booking/calendar link</strong></p></li>
            <li><div class="instr-step-num">4</div><p><a href="<?= admin_url('admin.php?page=seo-outreach-run') ?>">Run Campaign</a> &rarr; set Max Leads to <strong>1</strong> for first test &rarr; <strong>Launch</strong></p></li>
        </ol>
        <div class="instr-tip"><span class="dashicons dashicons-info"></span> Pre-flight check on the Run Campaign page must show all green before you can launch.</div>
    </div>
</div>

<?php elseif ($sub === 'format'): ?>

<style>
.fmt-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:20px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.fmt-header{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.fmt-header h2{color:#fff;font-size:16px;font-weight:700;margin:0}
.fmt-header p{color:rgba(255,255,255,.7);font-size:12px;margin:3px 0 0}
.btn-csv-dl{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.4);border-radius:8px;padding:9px 16px;font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;white-space:nowrap}
.btn-csv-dl:hover{background:rgba(255,255,255,.28);color:#fff}

/* Column grid */
.fmt-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:20px}
.fmt-col{border-radius:8px;overflow:hidden;border:2px solid #e2e8f0;transition:border-color .15s}
.fmt-col.new-col{border-color:#fbbf24;background:#fffbeb}
.fmt-col-hdr{padding:9px 12px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:space-between}
.fmt-col-letter{font-size:22px;font-weight:900;opacity:.35;line-height:1}
.fmt-col-body{padding:10px 12px;background:#fafbfc}
.fmt-col.new-col .fmt-col-body{background:#fffbeb}
.col-name-txt{font-size:13px;font-weight:600;color:#0f172a;display:block;margin-bottom:5px}
.col-ex{font-size:11px;color:#94a3b8;font-family:'Courier New',monospace;background:#f1f5f9;padding:3px 7px;border-radius:4px;display:block;margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fmt-col.new-col .col-ex{background:#fef3c7;color:#92400e}
.col-note-txt{font-size:11px;color:#64748b;line-height:1.4}
.badge-req2{background:#fee2e2;color:#dc2626;padding:1px 5px;border-radius:8px;font-size:9px;font-weight:700}
.badge-opt2{background:#fef3c7;color:#b45309;padding:1px 5px;border-radius:8px;font-size:9px;font-weight:700}
.badge-auto2{background:#ede9fe;color:#6d28d9;padding:1px 5px;border-radius:8px;font-size:9px;font-weight:700}
.badge-new2{background:#fef9c3;color:#a16207;padding:1px 5px;border-radius:8px;font-size:9px;font-weight:700;border:1px solid #fbbf24}

/* colour per col */
.ca .fmt-col-hdr{background:rgba(99,102,241,.08);color:#4f46e5}
.cb .fmt-col-hdr{background:rgba(20,184,166,.08);color:#0d9488}
.cc .fmt-col-hdr{background:rgba(245,158,11,.08);color:#b45309}
.cd .fmt-col-hdr{background:rgba(100,116,139,.08);color:#475569}
.ce .fmt-col-hdr{background:rgba(168,85,247,.08);color:#7c3aed}
.cf .fmt-col-hdr{background:rgba(236,72,153,.08);color:#be185d}
.cg .fmt-col-hdr{background:rgba(234,179,8,.12);color:#a16207}
.ch .fmt-col-hdr{background:rgba(34,197,94,.08);color:#15803d}

/* Outreach type pills */
.otype-pill{display:inline-block;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;font-family:monospace;margin:1px}
.op-seo{background:#dbeafe;color:#1e40af}
.op-ads{background:#fce7f3;color:#9d174d}
.op-nw {background:#dcfce7;color:#166534}

/* Preview table */
.fmt-preview-wrap{padding:0 20px 20px}
.fmt-preview-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px}
.fmt-preview{width:100%;border-collapse:collapse;font-size:11px;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0}
.fmt-preview th{padding:8px 10px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;background:#f8fafc;border-bottom:2px solid #e2e8f0;white-space:nowrap}
.fmt-preview td{padding:8px 10px;border-bottom:1px solid #f1f5f9;color:#374151;white-space:nowrap}
.fmt-preview tr:last-child td{border-bottom:none}
.fp-pending td:first-child{border-left:3px solid #6366f1}
.fp-sent td{color:#94a3b8}
.fp-sent td:first-child{border-left:3px solid #22c55e}
.fp-nw td:first-child{border-left:3px solid #0d9488}
.s-empty2{color:#d1d5db;font-style:italic;font-size:10px}
.s-sent2{background:#dcfce7;color:#15803d;padding:2px 6px;border-radius:8px;font-size:10px;font-weight:600}
.th-new{color:#a16207 !important;background:#fffbeb !important}

/* Notice box */
.fmt-notice{margin:0 20px 20px;padding:12px 16px;border-radius:8px;font-size:12px;display:flex;gap:10px;align-items:flex-start}
.fmt-notice .dashicons{flex-shrink:0;margin-top:1px}
.fmt-notice.info{background:#f0f9ff;border:1px solid #bae6fd;color:#0369a1}
.fmt-notice.warn{background:#fff7ed;border:1px solid #fed7aa;color:#c2410c}
.fmt-notice.new{background:#fffbeb;border:1px solid #fbbf24;color:#92400e}

@media(max-width:900px){.fmt-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.fmt-grid{grid-template-columns:1fr}}
</style>

<div class="fmt-card">
  <div class="fmt-header">
    <div>
      <h2>&#128196; Google Sheet Column Format</h2>
      <p>Exact column headers required in Row 1 &mdash; plugin reads them by name automatically</p>
    </div>
    <a href="<?= esc_url(add_query_arg(['action'=>'seo_outreach_download_csv','nonce'=>wp_create_nonce('seo_outreach_csv')],admin_url('admin-ajax.php'))) ?>"
       class="btn-csv-dl">
      <span class="dashicons dashicons-download"></span> Download CSV Template
    </a>
  </div>

  <!-- Column grid -->
  <div class="fmt-grid">

    <!-- A -->
    <div class="fmt-col ca">
      <div class="fmt-col-hdr"><span>A &mdash; Website URL</span><span class="fmt-col-letter">A</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Website URL <span class="badge-req2">Required</span></span>
        <span class="col-ex">https://acmeplumbing.com</span>
        <span class="col-note-txt">Full URL including https://. Audited by PageSpeed API for SEO &amp; ads leads.</span>
      </div>
    </div>

    <!-- B -->
    <div class="fmt-col cb">
      <div class="fmt-col-hdr"><span>B &mdash; Contact Email</span><span class="fmt-col-letter">B</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Contact Email <span class="badge-req2">Required</span></span>
        <span class="col-ex">john@acmeplumbing.com</span>
        <span class="col-note-txt">Where the outreach email is sent. Must be a valid email.</span>
      </div>
    </div>

    <!-- C -->
    <div class="fmt-col cc">
      <div class="fmt-col-hdr"><span>C &mdash; Business Name</span><span class="fmt-col-letter">C</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Business Name <span class="badge-opt2">Recommended</span></span>
        <span class="col-ex">Acme Plumbing Co</span>
        <span class="col-note-txt">Used in email subject &amp; body. Without it, AI uses the domain name.</span>
      </div>
    </div>

    <!-- D -->
    <div class="fmt-col cd">
      <div class="fmt-col-hdr"><span>D &mdash; Status</span><span class="fmt-col-letter">D</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Status <span class="badge-auto2">Auto-filled</span></span>
        <span class="col-ex">Sent - 2026-06-08</span>
        <span class="col-note-txt">Plugin writes here after sending. Leave blank for new leads — any value = skipped.</span>
      </div>
    </div>

    <!-- E -->
    <div class="fmt-col ce">
      <div class="fmt-col-hdr"><span>E &mdash; Position</span><span class="fmt-col-letter">E</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Position <span class="badge-opt2">Optional</span></span>
        <span class="col-ex">Plumber / Owner</span>
        <span class="col-note-txt">Contact's job title or industry. Used in no_website emails as the service type.</span>
      </div>
    </div>

    <!-- F -->
    <div class="fmt-col cf">
      <div class="fmt-col-hdr"><span>F &mdash; Name</span><span class="fmt-col-letter">F</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Name <span class="badge-opt2">Optional</span></span>
        <span class="col-ex">John</span>
        <span class="col-note-txt">Contact's first name for personalisation.</span>
      </div>
    </div>

    <!-- G — NEW -->
    <div class="fmt-col cg new-col">
      <div class="fmt-col-hdr"><span>G &mdash; Outreach type ★</span><span class="fmt-col-letter">G</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Outreach type <span class="badge-new2">New</span></span>
        <div style="margin-bottom:6px">
          <span class="otype-pill op-seo">seo</span>
          <span class="otype-pill op-ads">ads</span>
          <span class="otype-pill op-nw">no_website</span>
        </div>
        <span class="col-note-txt">
          <strong>seo</strong> — has site, poor rankings<br>
          <strong>ads</strong> — running paid ads<br>
          <strong>no_website</strong> — no site at all<br>
          Blank defaults to <code style="background:#fef9c3;padding:1px 4px;border-radius:3px">seo</code>
        </span>
      </div>
    </div>

    <!-- H — NEW -->
    <div class="fmt-col ch new-col">
      <div class="fmt-col-hdr"><span>H &mdash; Location ★</span><span class="fmt-col-letter">H</span></div>
      <div class="fmt-col-body">
        <span class="col-name-txt">Location <span class="badge-new2">New</span></span>
        <span class="col-ex">Dallas, TX</span>
        <span class="col-note-txt">City or area. Used throughout the email as <code style="background:#d1fae5;padding:1px 4px;border-radius:3px">{{city}}</code> for local personalisation. Leave blank if unknown.</span>
      </div>
    </div>

    <!-- I — NEW -->
    <div class="fmt-col new-col" style="border-color:#fbbf24;background:#fffbeb">
      <div class="fmt-col-hdr" style="background:rgba(139,92,246,.12);color:#6d28d9"><span>I &mdash; Services ★</span><span class="fmt-col-letter" style="opacity:.35">I</span></div>
      <div class="fmt-col-body" style="background:#fffbeb">
        <span class="col-name-txt">Services <span class="badge-new2">New</span></span>
        <span class="col-ex">Plumbing, water heaters, repairs</span>
        <span class="col-note-txt">What the business actually offers. AI uses this to name the specific service in the email instead of saying "your services". Most important for no_website leads where there is no site to check.</span>
      </div>
    </div>

  </div><!-- /.fmt-grid -->

  <!-- Notices -->
  <div class="fmt-notice new">
    <span class="dashicons dashicons-star-filled" style="color:#f59e0b"></span>
    <div><strong>Columns G &amp; H are new</strong> — add them to your existing sheet. They are read by header name so column order doesn't matter as long as Row 1 has the correct label.</div>
  </div>
  <div class="fmt-notice warn">
    <span class="dashicons dashicons-warning"></span>
    <div><strong>Status column (D) must be blank</strong> for new leads. Any value — including a space — causes the plugin to skip that row permanently.</div>
  </div>
  <div class="fmt-notice info">
    <span class="dashicons dashicons-info"></span>
    <div>Leads with <strong>no_website</strong> in column G skip PageSpeed entirely. No PDF is generated. The AI writes a direct outreach email based on Business Name, Location, and Position only.</div>
  </div>

  <!-- Example Preview -->
  <div class="fmt-preview-wrap">
    <div class="fmt-preview-label">Example Sheet Preview — Row 1 = Headers, Row 2+ = Leads</div>
    <div style="overflow-x:auto">
    <table class="fmt-preview">
      <thead>
        <tr>
          <th style="color:#4f46e5;background:rgba(99,102,241,.05)">A — Website URL</th>
          <th style="color:#0d9488;background:rgba(20,184,166,.05)">B — Contact Email</th>
          <th style="color:#b45309;background:rgba(245,158,11,.05)">C — Business Name</th>
          <th style="color:#475569;background:rgba(100,116,139,.05)">D — Status</th>
          <th style="color:#7c3aed;background:rgba(168,85,247,.05)">E — Position</th>
          <th style="color:#be185d;background:rgba(236,72,153,.05)">F — Name</th>
          <th class="th-new">G — Outreach type</th>
          <th class="th-new">H — Location</th>
          <th class="th-new" style="background:#f5f3ff !important;color:#6d28d9 !important">I — Services</th>
        </tr>
      </thead>
      <tbody>
        <tr class="fp-pending">
          <td>https://acmeplumbing.com</td>
          <td>john@acmeplumbing.com</td>
          <td>Acme Plumbing</td>
          <td><span class="s-empty2">← leave blank</span></td>
          <td>Plumber</td>
          <td>John</td>
          <td><span class="otype-pill op-seo">seo</span></td>
          <td>Dallas, TX</td>
          <td style="color:#6d28d9;font-size:11px">Plumbing, repairs, water heaters</td>
        </tr>
        <tr class="fp-pending">
          <td>https://bestlawfirm.com</td>
          <td>info@bestlawfirm.com</td>
          <td>Best Law Firm LLC</td>
          <td><span class="s-empty2">← leave blank</span></td>
          <td>Lawyer</td>
          <td>Sarah</td>
          <td><span class="otype-pill op-ads">ads</span></td>
          <td>Austin, TX</td>
          <td style="color:#6d28d9;font-size:11px">Personal injury, car accidents</td>
        </tr>
        <tr class="fp-nw">
          <td><span class="s-empty2">— no website —</span></td>
          <td>mike@mikes-cafe.com</td>
          <td>Mike's Cafe</td>
          <td><span class="s-empty2">← leave blank</span></td>
          <td>Restaurant</td>
          <td>Mike</td>
          <td><span class="otype-pill op-nw">no_website</span></td>
          <td>Houston, TX</td>
          <td style="color:#6d28d9;font-size:11px">Breakfast, lunch, coffee, catering</td>
        </tr>
        <tr class="fp-sent">
          <td>https://done.com</td>
          <td>done@done.com</td>
          <td>Done Business</td>
          <td><span class="s-sent2">Sent - 2026-06-08</span></td>
          <td>Owner</td>
          <td>Dave</td>
          <td><span class="otype-pill op-seo">seo</span></td>
          <td>Chicago, IL</td>
          <td style="color:#6d28d9;font-size:11px">IT support, networking, repairs</td>
        </tr>
      </tbody>
    </table>
    </div>
  </div>

</div><!-- /.fmt-card -->

<?php endif; ?>

<script>
let seoCurrentFilter = 'all';

// ── Sync from Google Sheet → save to DB → refresh table in-place ───────────
async function seoSyncLeads() {
    const btn  = document.getElementById('seo-sync-btn');
    const icon = document.getElementById('seo-sync-icon');
    const stat = document.getElementById('seo-sync-status');
    if (!btn) return;

    btn.disabled   = true;
    icon.className = 'dashicons dashicons-update seo-spin';
    if (stat) { stat.style.color='#64748b'; stat.textContent='Syncing with Google Sheet...'; }

    try {
        // Step 1: Sync sheet → DB
        const syncRes = await jQuery.post(seoOutreach.ajaxUrl, {
            action: 'seo_outreach_sync_leads',
            nonce:  seoOutreach.nonce
        });

        if (!syncRes.success) {
            btn.disabled   = false;
            icon.className = 'dashicons dashicons-update';
            if (stat) { stat.style.color='#dc2626'; stat.textContent='✗ '+(syncRes.responseJSON?.data?.message||'Sync failed'); }
            return;
        }

        // Step 2: Load fresh data from DB and render table in-place
        const leadsRes = await jQuery.post(seoOutreach.ajaxUrl, {
            action: 'seo_outreach_get_leads',
            nonce:  seoOutreach.nonce,
            status: '',
            page:   1
        });

        btn.disabled   = false;
        icon.className = 'dashicons dashicons-update';

        if (leadsRes.success) {
            const d = leadsRes.data;

            // Update sync status message
            if (stat) {
                stat.style.color = '#15803d';
                stat.textContent = '✓ ' + syncRes.data.message;
            }

            // Update last sync time
            const syncEl = document.getElementById('seo-last-sync');
            if (syncEl) syncEl.textContent = 'just now';

            // Re-render the table body
            seoRenderLeadsTable(d.leads, d.counts);

            // Update filter counts
            if (d.counts) {
                const countMap = {
                    'filter-all':     d.counts.total,
                    'filter-pending': d.counts.pending,
                    'filter-sent':    d.counts.sent,
                    'filter-failed':  d.counts.failed,
                };
                Object.entries(countMap).forEach(([id, count]) => {
                    const el = document.getElementById(id);
                    if (el) {
                        const badge = el.querySelector('.leads-filter-count');
                        if (badge) badge.textContent = count;
                        if (!count && id === 'filter-failed') el.style.display = 'none';
                    }
                });
            }
        }

    } catch(e) {
        btn.disabled   = false;
        icon.className = 'dashicons dashicons-update';
        if (stat) { stat.style.color='#dc2626'; stat.textContent='✗ Request failed'; }
    }
}

// ── Render leads table rows ──────────────────────────────────────────────────
function seoRenderLeadsTable(leads, counts) {
    const emptyState = document.getElementById('seo-empty-state');
    const table      = document.getElementById('seo-leads-table');
    const tbody      = document.getElementById('seo-leads-body');

    if (!leads || leads.length === 0) {
        if (emptyState) emptyState.style.display = 'block';
        if (table)      table.style.display      = 'none';
        return;
    }

    // Hide empty state, show table
    if (emptyState) emptyState.style.display = 'none';
    if (table)      table.style.display      = '';
    if (!tbody)     return;

    tbody.innerHTML = leads.map(l => {
        let badgeClass = 'badge-pending';
        let statusLabel = 'Pending';
        const s = (l.status || '').toLowerCase();
        if (s.startsWith('sent'))   { badgeClass = 'badge-sent';   statusLabel = l.status; }
        if (s.startsWith('failed')) { badgeClass = 'badge-failed'; statusLabel = l.status; }

        const biz = l.business_name
            ? `<span style="color:#374151">${seoEsc(l.business_name)}</span>`
            : `<span style="color:#d1d5db">—</span>`;

        return `<tr id="lead-row-${l.id}">
          <td class="col-check"><input type="checkbox" class="seo-lead-check" value="${l.id}" onchange="seoUpdateBulkBar()"></td>
          <td style="color:#94a3b8;font-size:12px">${l.sheet_row||''}</td>
          <td class="col-website">
            <a href="${seoEsc(l.website_url)}" target="_blank" style="color:#4f46e5;font-weight:500;text-decoration:none;display:flex;align-items:center;gap:4px">
              <span class="dashicons dashicons-admin-site-alt3" style="font-size:12px;width:12px;height:12px;flex-shrink:0"></span>
              ${seoEsc(l.website_url)}
            </a>
          </td>
          <td style="color:#374151">${seoEsc(l.contact_email)}</td>
          <td>${biz}</td>
          <td class="col-status"><span class="seo-badge ${badgeClass}">${seoEsc(statusLabel)}</span></td>
          <td class="col-delete">
            <button class="seo-btn seo-btn-sm" style="background:none;border:none;color:#cbd5e1;padding:2px 6px;cursor:pointer" title="Delete lead"
              onclick="seoDeleteLead(${l.id}, this)">
              <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px"></span>
            </button>
          </td>
        </tr>`;
    }).join('');

    // Re-apply current filter
    seoSetFilter(seoCurrentFilter, document.querySelector('.leads-filter-btn.active, .leads-filter-btn.active-green, .leads-filter-btn.active-red'));
}

function seoEsc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Debug: show raw sheet data ──────────────────────────────────────────────
async function seoDebugSync() {
  const btn = document.getElementById('seo-debug-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="dashicons dashicons-update seo-spin" style="font-size:15px;width:15px;height:15px"></span> Loading...';

  const res = await jQuery.post(seoOutreach.ajaxUrl, { action:'seo_outreach_debug_sync', nonce:seoOutreach.nonce });
  btn.disabled = false;
  btn.innerHTML = '<span class="dashicons dashicons-visibility" style="font-size:15px;width:15px;height:15px"></span> Debug Sheet';

  if (!res.success) {
    alert('Error: ' + (res.responseJSON?.data?.message || 'Could not fetch sheet data'));
    return;
  }

  const d = res.data;
  let html = `<div style="font-family:monospace;font-size:12px;background:#0f172a;color:#e2e8f0;padding:16px;border-radius:8px;max-height:400px;overflow-y:auto">`;
  html += `<p style="color:#63cab7;margin:0 0 10px"><strong>Sheet:</strong> ${d.sheet} → ${d.tab} &nbsp;|&nbsp; <strong>${d.count} rows found</strong></p>`;
  if (!d.leads.length) {
    html += `<p style="color:#f87171">No leads returned. Check that your sheet has data and the correct tab is selected.</p>`;
  } else {
    d.leads.forEach((l, i) => {
      html += `<div style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,.08)">
        <span style="color:#94a3b8">Row ${l.row_index}:</span>
        <span style="color:#818cf8;margin-left:8px">${l.website}</span>
        <span style="color:#94a3b8"> → </span>
        <span style="color:#67e8f9">${l.email}</span>
        ${l.business_name ? `<span style="color:#94a3b8"> (${l.business_name})</span>` : ''}
        ${l.status ? `<span style="color:#4ade80;margin-left:6px">[${l.status}]</span>` : '<span style="color:#f59e0b;margin-left:6px">[pending]</span>'}
      </div>`;
    });
  }
  html += `</div>`;
  html += `<p style="font-size:12px;color:#64748b;margin-top:10px">If a lead is missing from this list, the plugin cannot read that row. Check: correct tab selected, row has both Website URL and Email, no merged cells.</p>`;

  // Show in a simple modal overlay
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px';
  overlay.innerHTML = `<div style="background:#fff;border-radius:12px;padding:24px;max-width:700px;width:100%;max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <h3 style="margin:0;font-size:16px;color:#0f172a">Raw Sheet Data (${d.count} rows read)</h3>
      <button onclick="this.closest('[style*=fixed]').remove()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;padding:0 4px">&times;</button>
    </div>
    ${html}
  </div>`;
  document.body.appendChild(overlay);
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

// ── Filter leads table client-side ─────────────────────────────────────────
function seoSetFilter(filter, btn) {
    seoCurrentFilter = filter || 'all';
    document.querySelectorAll('.leads-filter-btn').forEach(b => {
        b.className = 'leads-filter-btn';
    });
    if (btn) {
        if (filter === 'sent')        btn.className = 'leads-filter-btn active-green';
        else if (filter === 'failed') btn.className = 'leads-filter-btn active-red';
        else                          btn.className = 'leads-filter-btn active';
    }

    const rows = document.querySelectorAll('#seo-leads-body tr[id^="lead-row-"]');
    let visible = 0;
    rows.forEach(row => {
        const badge = row.querySelector('.col-status .seo-badge');
        const text  = badge ? badge.textContent.trim().toLowerCase() : '';
        let show = true;
        if (filter === 'pending')     show = text === 'pending';
        else if (filter === 'sent')   show = text.startsWith('sent');
        else if (filter === 'failed') show = text.startsWith('failed');
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const sc = document.getElementById('seo-showing-count');
    if (sc) sc.textContent = visible ? visible + ' shown' : '';
    seoUpdateBulkBar();
}

// ── Delete single lead ─────────────────────────────────────────────────────
async function seoDeleteLead(id, btn) {
    if (!confirm('Remove this lead from the plugin database? It will NOT be deleted from your Google Sheet.')) return;
    const res = await jQuery.post(seoOutreach.ajaxUrl, { action:'seo_outreach_delete_lead', nonce:seoOutreach.nonce, id });
    if (res.success) {
        document.getElementById('lead-row-'+id)?.remove();
    }
}

// ── Bulk delete helpers ────────────────────────────────────────────────────
function seoGetCheckedIds() {
    return [...document.querySelectorAll('.seo-lead-check:checked')].map(cb => cb.value);
}

function seoUpdateBulkBar() {
    const ids     = seoGetCheckedIds();
    const bar     = document.getElementById('seo-bulk-bar');
    const countEl = document.getElementById('seo-bulk-count');
    const allCb   = document.getElementById('seo-check-all');

    if (bar)     bar.classList.toggle('visible', ids.length > 0);
    if (countEl) countEl.textContent = ids.length + ' selected';

    // Sync "select all" checkbox state
    if (allCb) {
        const all = document.querySelectorAll('.seo-lead-check');
        allCb.checked       = all.length > 0 && ids.length === all.length;
        allCb.indeterminate = ids.length > 0 && ids.length < all.length;
    }
}

function seoSelectAllLeads(checked) {
    document.querySelectorAll('.seo-lead-check').forEach(cb => { cb.checked = checked; });
    const allCb = document.getElementById('seo-check-all');
    if (allCb) { allCb.checked = checked; allCb.indeterminate = false; }
    seoUpdateBulkBar();
}

async function seoBulkDelete() {
    const ids = seoGetCheckedIds();
    if (!ids.length) return;
    if (!confirm(`Remove ${ids.length} lead(s) from the plugin database? They will NOT be deleted from your Google Sheet.`)) return;

    // Delete each lead via existing single-delete endpoint
    let deleted = 0;
    for (const id of ids) {
        const res = await jQuery.post(seoOutreach.ajaxUrl, { action:'seo_outreach_delete_lead', nonce:seoOutreach.nonce, id });
        if (res.success) {
            document.getElementById('lead-row-' + id)?.remove();
            deleted++;
        }
    }

    seoSelectAllLeads(false);

    // Update count badge in sub-nav
    const badge = document.querySelector('.leads-subtab-btn.active .seo-badge');
    if (badge) {
        const remaining = document.querySelectorAll('#seo-leads-body tr[id^="lead-row-"]').length;
        badge.textContent = remaining;
    }

    // Show empty state if no rows left
    const remaining = document.querySelectorAll('#seo-leads-body tr[id^="lead-row-"]').length;
    if (remaining === 0) {
        const table      = document.getElementById('seo-leads-table');
        const emptyState = document.getElementById('seo-empty-state');
        if (table)      table.style.display      = 'none';
        if (emptyState) emptyState.style.display = 'block';
    }
}

// ── Instructions accordion ─────────────────────────────────────────────────
function seoToggleSection(hdr) {
    const body    = hdr.nextElementSibling;
    const chevron = hdr.querySelector('.instr-chevron');
    const isOpen  = hdr.classList.contains('open');
    hdr.classList.toggle('open',  !isOpen);
    body.classList.toggle('open', !isOpen);
    chevron.classList.toggle('open', !isOpen);
}
</script>

<?php seo_outreach_footer(); ?>
