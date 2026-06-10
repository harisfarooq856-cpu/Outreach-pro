<?php
defined( 'ABSPATH' ) || exit;
require_once SEO_OUTREACH_DIR . 'admin/partials.php';

$tab          = sanitize_key( $_GET['tab'] ?? 'api' );
$settings     = SEO_Outreach_Settings::get_all();
$s            = fn( $k, $d = '' ) => esc_attr( $settings[ $k ] ?? $d );
$checked      = fn( $k, $d = '1' ) => ( ( $settings[ $k ] ?? $d ) === '1' ) ? 'checked' : '';
$notif_emails = SEO_Outreach_Settings::get_notification_emails();

$sa_json  = $settings['google_service_account'] ?? '';
$sa_email = $sa_json ? SEO_Outreach_Sheets::extract_email( $sa_json ) : '';

seo_outreach_header( 'Settings', 'dashicons-admin-settings' );
?>

<!-- Tab Navigation -->
<div class="seo-tab-nav">
<?php
$tabs = [
    'api'      => [ 'dashicons-admin-network', 'API Keys' ],
    'sheets'   => [ 'dashicons-editor-table',  'Google Sheets' ],
    'email'    => [ 'dashicons-email-alt',      'Email / SMTP' ],
    'notify'   => [ 'dashicons-bell',           'Notifications' ],
    'campaign' => [ 'dashicons-controls-play',  'Campaign' ],
    'logs'     => [ 'dashicons-list-view',      'Log Settings' ],
];
foreach ( $tabs as $key => [ $icon, $label ] ): ?>
  <a href="<?= admin_url( 'admin.php?page=seo-outreach-settings&tab=' . $key ) ?>"
     class="seo-tab-btn <?= $tab === $key ? 'active' : '' ?>">
    <span class="dashicons <?= $icon ?>"></span> <?= $label ?>
  </a>
<?php endforeach; ?>
</div>

<div id="seo-settings-notice" style="display:none;margin-bottom:12px"></div>

<form id="seo-settings-form">
<input type="hidden" name="google_sheet_id"   id="inp-sheet-id"   value="<?= $s('google_sheet_id') ?>">
<input type="hidden" name="google_sheet_name" id="inp-sheet-name" value="<?= $s('google_sheet_name') ?>">
<input type="hidden" name="google_sheet_tab"  id="inp-sheet-tab"  value="<?= $s('google_sheet_tab','Sheet1') ?>">

<?php /* ═══════════════ API KEYS TAB ═══════════════ */ if ( $tab === 'api' ): ?>
<style>
.seo-ai-option{display:flex;flex-direction:column;align-items:center;gap:5px;padding:16px 22px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all .15s;min-width:130px;text-align:center;background:#fff}
.seo-ai-option:hover{border-color:#6366f1;background:#fafbff}
.seo-ai-option.selected{border-color:#6366f1;background:rgba(99,102,241,.07);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.seo-ai-option strong{font-size:14px;color:#0f172a;display:block}
.seo-ai-option span:last-child{font-size:11px;color:#64748b}
</style>

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-admin-network"></span> API Keys Configuration</div>
  <div class="seo-card-body">
    <div class="seo-info-box">All keys stored securely in WordPress options. Click <strong>Test</strong> to verify each key live before saving.</div>

    <!-- Gemini -->
    <div class="seo-form-section">
      <h3><span class="dashicons dashicons-format-chat" style="color:#6366f1"></span> Gemini AI</h3>
      <p class="description">Get your key at <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio ↗</a></p>

      <div class="seo-form-group">
        <label>Gemini API Key</label>
        <div class="seo-api-test-row">
          <div class="seo-input-group">
            <input type="password" name="gemini_api_key" id="field-gemini-key" value="<?= $s('gemini_api_key') ?>" class="regular-text" placeholder="AIzaSy...">
            <button type="button" class="seo-btn seo-btn-outline seo-reveal-btn" tabindex="-1"><span class="dashicons dashicons-visibility"></span></button>
          </div>
          <button type="button" class="seo-btn seo-btn-teal" onclick="seoTestApi('gemini')">
            <span class="dashicons dashicons-controls-play"></span> Test Connection
          </button>
        </div>
        <div class="seo-test-result-box" id="result-gemini"></div>
      </div>

      <div class="seo-form-group" style="margin-top:14px">
        <label for="field-gemini-model"><strong>Gemini Model</strong></label>
        <p class="description" style="margin-bottom:8px">Choose the model to use for email &amp; report generation. Newer = better quality, but may have stricter rate limits.</p>
        <?php $cur_model = $s('gemini_model','gemini-2.5-flash'); ?>
        <select name="gemini_model" id="field-gemini-model" class="regular-text" style="max-width:420px;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">

          <optgroup label="⚡ Recommended — Latest Stable">
            <option value="gemini-2.5-flash"        <?= $cur_model==='gemini-2.5-flash'?'selected':'' ?>>Gemini 2.5 Flash (Default — fast, smart, 1M tokens)</option>
            <option value="gemini-2.5-flash-lite"   <?= $cur_model==='gemini-2.5-flash-lite'?'selected':'' ?>>Gemini 2.5 Flash-Lite (Lightest, lowest cost)</option>
            <option value="gemini-2.5-pro"          <?= $cur_model==='gemini-2.5-pro'?'selected':'' ?>>Gemini 2.5 Pro (Most capable — best quality)</option>
          </optgroup>

          <optgroup label="🔥 Newest Models">
            <option value="gemini-3.5-flash"        <?= $cur_model==='gemini-3.5-flash'?'selected':'' ?>>Gemini 3.5 Flash (May 2026)</option>
            <option value="gemini-3.1-flash-lite"   <?= $cur_model==='gemini-3.1-flash-lite'?'selected':'' ?>>Gemini 3.1 Flash Lite (Stable)</option>
            <option value="gemini-3.1-flash-lite-preview" <?= $cur_model==='gemini-3.1-flash-lite-preview'?'selected':'' ?>>Gemini 3.1 Flash Lite Preview</option>
            <option value="gemini-3.1-pro-preview"  <?= $cur_model==='gemini-3.1-pro-preview'?'selected':'' ?>>Gemini 3.1 Pro Preview (Jan 2026)</option>
            <option value="gemini-3-flash-preview"  <?= $cur_model==='gemini-3-flash-preview'?'selected':'' ?>>Gemini 3 Flash Preview</option>
            <option value="gemini-3-pro-preview"    <?= $cur_model==='gemini-3-pro-preview'?'selected':'' ?>>Gemini 3 Pro Preview</option>
          </optgroup>

          <optgroup label="⚙ Gemini 2.0 Series">
            <option value="gemini-2.0-flash"        <?= $cur_model==='gemini-2.0-flash'?'selected':'' ?>>Gemini 2.0 Flash</option>
            <option value="gemini-2.0-flash-001"    <?= $cur_model==='gemini-2.0-flash-001'?'selected':'' ?>>Gemini 2.0 Flash 001 (Stable pinned)</option>
            <option value="gemini-2.0-flash-lite"   <?= $cur_model==='gemini-2.0-flash-lite'?'selected':'' ?>>Gemini 2.0 Flash-Lite</option>
            <option value="gemini-2.0-flash-lite-001" <?= $cur_model==='gemini-2.0-flash-lite-001'?'selected':'' ?>>Gemini 2.0 Flash-Lite 001 (Stable pinned)</option>
          </optgroup>

          <optgroup label="🔗 Latest Aliases (always up-to-date)">
            <option value="gemini-flash-latest"     <?= $cur_model==='gemini-flash-latest'?'selected':'' ?>>Gemini Flash Latest</option>
            <option value="gemini-flash-lite-latest" <?= $cur_model==='gemini-flash-lite-latest'?'selected':'' ?>>Gemini Flash-Lite Latest</option>
            <option value="gemini-pro-latest"       <?= $cur_model==='gemini-pro-latest'?'selected':'' ?>>Gemini Pro Latest</option>
          </optgroup>

        </select>
        <p class="description" style="margin-top:6px;font-size:11px">
          <span class="dashicons dashicons-info" style="color:#6366f1;font-size:13px;width:13px;height:13px"></span>
          <strong>Tip:</strong> If you hit rate limits on 2.5 Flash, switch to <strong>Gemini 2.0 Flash</strong> (higher free-tier quota) or enable <strong>Groq fallback</strong> below.
        </p>
      </div>
    </div>

    <!-- Active AI Selector -->
    <div class="seo-form-section" style="background:linear-gradient(135deg,rgba(99,102,241,.04) 0%,rgba(168,85,247,.04) 100%);border:1.5px solid #e0e7ff;border-radius:12px;padding:20px">
      <h3 style="margin-top:0"><span class="dashicons dashicons-superhero" style="color:#6366f1"></span> Active AI Engine</h3>
      <p class="description" style="margin-bottom:14px">Choose which AI generates emails and reports. The other AI acts as automatic fallback if the primary fails.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap" id="ai-selector">
        <?php $active_ai = $s('active_ai','auto'); ?>
        <label class="seo-ai-option <?= $active_ai==='auto'?'selected':'' ?>" onclick="seoSelectAI('auto',this)">
          <input type="radio" name="active_ai" value="auto" <?= $active_ai==='auto'?'checked':'' ?> style="display:none">
          <span class="dashicons dashicons-update" style="color:#6366f1;font-size:22px;width:22px;height:22px"></span>
          <strong>Auto</strong><span>Gemini first, Groq fallback</span>
        </label>
        <label class="seo-ai-option <?= $active_ai==='gemini'?'selected':'' ?>" onclick="seoSelectAI('gemini',this)">
          <input type="radio" name="active_ai" value="gemini" <?= $active_ai==='gemini'?'checked':'' ?> style="display:none">
          <span class="dashicons dashicons-format-chat" style="color:#4285f4;font-size:22px;width:22px;height:22px"></span>
          <strong>Gemini</strong><span>Google Gemini 2.5 Flash</span>
        </label>
        <label class="seo-ai-option <?= $active_ai==='groq'?'selected':'' ?>" onclick="seoSelectAI('groq',this)">
          <input type="radio" name="active_ai" value="groq" <?= $active_ai==='groq'?'checked':'' ?> style="display:none">
          <span class="dashicons dashicons-cloud" style="color:#f55036;font-size:22px;width:22px;height:22px"></span>
          <strong>Groq</strong><span>Llama 3.3 70B (Ultra-fast)</span>
        </label>
      </div>
      <p class="description" style="margin-top:10px;font-size:11px">
        <span class="dashicons dashicons-info" style="color:#6366f1;font-size:13px;width:13px;height:13px"></span>
        <strong>Auto</strong> is recommended — if Gemini hits rate limits, Groq kicks in automatically with no interruption.
      </p>
    </div>

    <!-- Groq -->
    <div class="seo-form-section">
      <h3><span class="dashicons dashicons-cloud" style="color:#f55036"></span> Groq AI &mdash; <small>Model: <?= esc_html( SEO_Outreach_Groq::get_model() ) ?></small></h3>
      <p class="description">Get your free API key at <a href="https://console.groq.com/keys" target="_blank">console.groq.com ↗</a> &mdash; free tier is generous and much faster than Gemini.</p>
      <div class="seo-form-group">
        <label>Groq API Key</label>
        <div class="seo-api-test-row">
          <div class="seo-input-group">
            <input type="password" name="groq_api_key" id="field-groq-key" value="<?= $s('groq_api_key') ?>" class="regular-text" placeholder="gsk_...">
            <button type="button" class="seo-btn seo-btn-outline seo-reveal-btn" tabindex="-1"><span class="dashicons dashicons-visibility"></span></button>
          </div>
          <button type="button" class="seo-btn seo-btn-teal" onclick="seoTestApi('groq')">
            <span class="dashicons dashicons-controls-play"></span> Test Connection
          </button>
        </div>
        <div class="seo-test-result-box" id="result-groq"></div>
      </div>
      <div class="seo-form-group">
        <label for="field-groq-model">Groq Model</label>
        <select name="groq_model" id="field-groq-model" class="regular-text">
          <?php foreach ( SEO_Outreach_Groq::MODELS as $value => $label ) : ?>
            <option value="<?= esc_attr( $value ) ?>" <?= selected( SEO_Outreach_Settings::get( 'groq_model', SEO_Outreach_Groq::MODEL ), $value, false ) ?>>
              <?= esc_html( $label ) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="description">Select the Groq model to use for email generation. Default: <strong>Llama 4 Scout 17B</strong>.</p>
      </div>
    </div>

    <!-- PageSpeed -->
    <div class="seo-form-section">
      <h3><span class="dashicons dashicons-performance" style="color:#f59e0b"></span> Google PageSpeed Insights API</h3>
      <p class="description">Enable at <a href="https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com" target="_blank">Google Cloud Console ↗</a></p>
      <div class="seo-form-group">
        <label>PageSpeed API Key</label>
        <div class="seo-api-test-row">
          <div class="seo-input-group">
            <input type="password" name="pagespeed_api_key" id="field-pagespeed-key" value="<?= $s('pagespeed_api_key') ?>" class="regular-text" placeholder="AIzaSy...">
            <button type="button" class="seo-btn seo-btn-outline seo-reveal-btn" tabindex="-1"><span class="dashicons dashicons-visibility"></span></button>
          </div>
          <button type="button" class="seo-btn seo-btn-teal" onclick="seoTestApi('pagespeed')">
            <span class="dashicons dashicons-controls-play"></span> Test Connection
          </button>
        </div>
        <div class="seo-test-result-box" id="result-pagespeed"></div>
        <p class="description" style="margin-top:4px">⚠ Test audits <strong>google.com</strong> — takes 15–30 seconds.</p>
      </div>
    </div>
  </div>
</div>
<button type="button" class="seo-btn seo-btn-primary seo-btn-lg" onclick="seoSaveSettings()">
  <span class="dashicons dashicons-saved"></span> Save API Keys
</button>

<?php /* ═══════════════ GOOGLE SHEETS TAB ════════════════ */ elseif ( $tab === 'sheets' ): ?>

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-editor-table"></span> Google Sheets Integration</div>
  <div class="seo-card-body">

    <div class="seo-info-box">
      <strong>Required APIs — both must be enabled in Google Cloud Console:</strong><br>
      &bull; <a href="https://console.cloud.google.com/apis/library/sheets.googleapis.com" target="_blank">Google Sheets API ↗</a>
      &nbsp;&bull; <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank">Google Drive API ↗</a> (used to list your sheets)
    </div>

    <!-- STEP 1: Paste JSON or Upload file -->
    <div class="seo-step-box">
      <div class="seo-step-num">1</div>
      <div class="seo-step-body">
        <h4>Service Account JSON Key</h4>
        <p class="description" style="margin-bottom:10px">
          Create a Service Account at <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">Google Cloud Console ↗</a>,
          download the JSON key, then paste or upload it below.
        </p>

        <!-- Upload button -->
        <div class="seo-json-upload-bar">
          <button type="button" class="seo-btn seo-btn-outline" onclick="document.getElementById('seo-json-file').click()">
            <span class="dashicons dashicons-upload"></span> Upload JSON File
          </button>
          <span class="seo-muted" style="font-size:12px" id="seo-upload-filename">or paste JSON text below</span>
          <input type="file" id="seo-json-file" accept=".json,application/json" style="display:none" onchange="seoHandleJsonUpload(this)">
        </div>

        <textarea name="google_service_account" id="seo-sa-json" rows="7"
          class="large-text code-textarea" style="margin-top:8px"
          placeholder='{"type":"service_account","project_id":"...","private_key":"-----BEGIN RSA PRIVATE KEY-----\n...","client_email":"your-sa@project.iam.gserviceaccount.com",...}'
          oninput="seoExtractEmail()"><?= esc_textarea( $sa_json ) ?></textarea>

        <div class="seo-api-test-row" style="margin-top:10px">
          <button type="button" class="seo-btn seo-btn-teal" onclick="seoTestApi('service_account')">
            <span class="dashicons dashicons-controls-play"></span> Test Service Account
          </button>
          <div class="seo-test-result-box" id="result-service_account" style="flex:1"></div>
        </div>
      </div>
    </div>

    <!-- STEP 2: Show extracted email -->
    <div class="seo-step-box" id="seo-step-email" style="<?= $sa_email ? '' : 'opacity:.4;pointer-events:none' ?>">
      <div class="seo-step-num">2</div>
      <div class="seo-step-body">
        <h4>Share Your Google Sheet With This Email</h4>
        <div class="seo-email-display" id="seo-email-display-box">
          <?php if ( $sa_email ): ?>
            <code id="seo-sa-email-text"><?= esc_html( $sa_email ) ?></code>
            <button type="button" class="seo-btn seo-btn-sm seo-btn-outline" onclick="seoCopyEmail()">
              <span class="dashicons dashicons-clipboard"></span> Copy Email
            </button>
            <span class="seo-muted" id="seo-copy-confirm" style="font-size:12px;display:none">✓ Copied!</span>
          <?php else: ?>
            <span class="seo-muted" id="seo-email-placeholder">Paste or upload the JSON key above to see the service account email</span>
          <?php endif; ?>
        </div>
        <p class="description">Open your Google Sheet &rarr; <strong>Share</strong> &rarr; paste this email &rarr; set role to <strong>Editor</strong> &rarr; click <strong>Share</strong>.</p>
      </div>
    </div>

    <!-- STEP 3: Fetch & Select Sheet -->
    <div class="seo-step-box" id="seo-step-fetch" style="<?= $sa_email ? '' : 'opacity:.4;pointer-events:none' ?>">
      <div class="seo-step-num">3</div>
      <div class="seo-step-body">
        <h4>Fetch &amp; Select Your Sheet</h4>
        <div class="seo-api-test-row" style="flex-wrap:wrap;gap:10px">
          <button type="button" class="seo-btn seo-btn-primary" id="seo-fetch-btn" onclick="seoFetchSheets()">
            <span class="dashicons dashicons-update"></span> Fetch My Sheets
          </button>
          <span id="seo-fetch-status" style="font-size:13px"></span>
        </div>

        <div id="seo-sheet-selectors" style="display:<?= $settings['google_sheet_id'] ? 'block' : 'none' ?>;margin-top:14px">
          <div class="seo-two-col">
            <div class="seo-form-group">
              <label>Select Google Sheet</label>
              <select id="seo-sheet-dd" class="regular-text" onchange="seoOnSheetSelect(this)">
                <option value="">— Select a sheet —</option>
                <?php if ( $settings['google_sheet_id'] ): ?>
                  <option value="<?= esc_attr($settings['google_sheet_id']) ?>"
                    data-name="<?= esc_attr($settings['google_sheet_name'] ?? '') ?>" selected>
                    <?= esc_html( $settings['google_sheet_name'] ?: $settings['google_sheet_id'] ) ?>
                  </option>
                <?php endif; ?>
              </select>
            </div>
            <div class="seo-form-group" id="seo-tab-group" style="display:<?= $settings['google_sheet_id'] ? 'block' : 'none' ?>">
              <label>Sheet Tab (sub-sheet)</label>
              <select id="seo-tab-dd" class="regular-text" onchange="document.getElementById('inp-sheet-tab').value=this.value">
                <?php if ( $settings['google_sheet_tab'] ): ?>
                  <option value="<?= esc_attr($settings['google_sheet_tab']) ?>" selected>
                    <?= esc_html( $settings['google_sheet_tab'] ) ?>
                  </option>
                <?php endif; ?>
              </select>
            </div>
          </div>

          <?php if ( $settings['google_sheet_id'] ): ?>
          <div class="seo-sheet-selected-info">
            <span class="dashicons dashicons-yes-alt"></span>
            <strong><?= esc_html( $settings['google_sheet_name'] ?: $settings['google_sheet_id'] ) ?></strong>
            &rarr; Tab: <strong><?= esc_html( $settings['google_sheet_tab'] ?? 'Sheet1' ) ?></strong>
            <a href="https://docs.google.com/spreadsheets/d/<?= esc_attr($settings['google_sheet_id']) ?>/edit"
               target="_blank" style="font-size:12px;margin-left:8px">Open Sheet ↗</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
<button type="button" class="seo-btn seo-btn-primary seo-btn-lg" onclick="seoSaveSettings()">
  <span class="dashicons dashicons-saved"></span> Save Sheet Settings
</button>

<?php
/* ═══════════════ EMAIL / SMTP TAB ══════════════════ */
elseif ( $tab === 'email' ):
    // Migrate legacy 'hostinger' / 'cpanel' to 'other'
    $smtp_provider = $s('smtp_provider', 'gmail');
    if ( ! in_array( $smtp_provider, ['gmail', 'other'], true ) ) {
        $smtp_provider = 'other';
    }
?>

<input type="hidden" name="smtp_provider" id="field-smtp-provider" value="<?= esc_attr($smtp_provider) ?>">

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-email-alt"></span> Email / SMTP Configuration</div>
  <div class="seo-card-body" style="padding-bottom:0">

    <?php /* ── Main Sub-Tabs: Gmail | Other SMTP | How to Configure ── */ ?>
    <div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:24px;flex-wrap:wrap;" id="seo-email-maintabs">
      <button type="button" id="seo-mtab-gmail" onclick="seoEmailTab('gmail')"
        style="<?= $smtp_provider==='gmail' ? 'background:#6366f1;color:#fff;border-bottom:2px solid #6366f1;' : 'background:transparent;color:#64748b;border-bottom:2px solid transparent;' ?>border-left:none;border-right:none;border-top:none;padding:10px 22px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:-2px;border-radius:6px 6px 0 0;transition:all .15s;">
        <span class="dashicons dashicons-email-alt" style="font-size:15px;width:15px;height:15px;"></span> Gmail
      </button>
      <button type="button" id="seo-mtab-other" onclick="seoEmailTab('other')"
        style="<?= $smtp_provider==='other' ? 'background:#6366f1;color:#fff;border-bottom:2px solid #6366f1;' : 'background:transparent;color:#64748b;border-bottom:2px solid transparent;' ?>border-left:none;border-right:none;border-top:none;padding:10px 22px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:-2px;border-radius:6px 6px 0 0;transition:all .15s;">
        <span class="dashicons dashicons-admin-generic" style="font-size:15px;width:15px;height:15px;"></span> Other SMTP
      </button>
      <button type="button" id="seo-mtab-guide" onclick="seoEmailTab('guide')"
        style="background:transparent;color:#64748b;border-left:none;border-right:none;border-top:none;border-bottom:2px solid transparent;padding:10px 22px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:-2px;border-radius:6px 6px 0 0;transition:all .15s;">
        <span class="dashicons dashicons-book-alt" style="font-size:15px;width:15px;height:15px;"></span> How to Configure
      </button>
    </div>

    <?php /* ══════════ GMAIL PANEL ══════════ */ ?>
    <div id="seo-smtp-panel-gmail" class="seo-smtp-panel" style="display:<?= $smtp_provider==='gmail' ? 'block' : 'none' ?>">
      <div class="seo-info-box" style="margin-bottom:20px">
        <strong>Gmail Setup:</strong> You must use an <a href="https://myaccount.google.com/apppasswords" target="_blank"><strong>App Password ↗</strong></a> — your regular Gmail password will NOT work.<br>
        Go to: <strong>Google Account → Security → 2-Step Verification (enable) → App Passwords → generate one for "Mail"</strong>.
      </div>
      <div class="seo-two-col">
        <div class="seo-form-group">
          <label>SMTP Host</label>
          <input type="text" name="smtp_host" id="field-smtp-host-gmail" value="smtp.gmail.com" class="regular-text" placeholder="smtp.gmail.com" readonly style="background:#f8fafc;color:#64748b;cursor:not-allowed">
        </div>
        <div class="seo-form-group">
          <label>SMTP Port</label>
          <select name="smtp_port" id="field-smtp-port-gmail" class="regular-text" disabled style="background:#f8fafc;color:#64748b;cursor:not-allowed">
            <option value="587" selected>587 — TLS (Recommended)</option>
            <option value="465">465 — SSL</option>
          </select>
        </div>
      </div>
      <div class="seo-two-col">
        <div class="seo-form-group">
          <label>Gmail Address (Username)</label>
          <input type="text" name="smtp_user" id="field-smtp-user-gmail" value="<?= $s('smtp_user') ?>" class="regular-text" placeholder="yourname@gmail.com">
        </div>
        <div class="seo-form-group">
          <label>App Password <span style="font-weight:400;color:#94a3b8">(not your Gmail password)</span></label>
          <div class="seo-input-group">
            <input type="password" name="smtp_pass" id="field-smtp-pass-gmail" value="<?= $s('smtp_pass') ?>" class="regular-text" placeholder="16-character App Password">
            <button type="button" class="seo-btn seo-btn-outline seo-reveal-btn" tabindex="-1"><span class="dashicons dashicons-visibility"></span></button>
          </div>
        </div>
      </div>
      <div class="seo-two-col">
        <div class="seo-form-group">
          <label>From Name</label>
          <input type="text" name="smtp_from_name" id="field-smtp-from-name-gmail" value="<?= $s('smtp_from_name','Haris Farooq') ?>" class="regular-text">
        </div>
        <div class="seo-form-group">
          <label>From Email Address</label>
          <input type="email" name="smtp_from_email" id="field-smtp-from-gmail" value="<?= $s('smtp_from_email') ?>" class="regular-text" placeholder="yourname@gmail.com">
        </div>
      </div>
    </div>

    <?php /* ══════════ OTHER SMTP PANEL ══════════ */ ?>
    <div id="seo-smtp-panel-other" class="seo-smtp-panel" style="display:<?= $smtp_provider==='other' ? 'block' : 'none' ?>">
      <div class="seo-info-box" style="margin-bottom:20px">
        <strong>Other SMTP Setup:</strong> Works with any mail provider — Outlook, Yahoo, Zoho, cPanel, Hostinger, SendGrid, Mailgun, etc.<br>
        Enter the SMTP Host and Port from your email provider's documentation. Username is usually your full email address.
      </div>
      <div class="seo-two-col">
        <div class="seo-form-group">
          <label>SMTP Host</label>
          <input type="text" name="smtp_host" id="field-smtp-host" value="<?= $s('smtp_host') ?>" class="regular-text" placeholder="e.g. smtp.yourdomain.com or mail.yourdomain.com">
        </div>
        <div class="seo-form-group">
          <label>SMTP Port</label>
          <select name="smtp_port" id="field-smtp-port" class="regular-text">
            <option value="587" <?= $smtp_provider==='other' ? selected($s('smtp_port','587'),'587',false) : 'selected' ?>>587 — TLS (Recommended)</option>
            <option value="465" <?= $smtp_provider==='other' ? selected($s('smtp_port','587'),'465',false) : '' ?>>465 — SSL</option>
            <option value="25"  <?= $smtp_provider==='other' ? selected($s('smtp_port','587'),'25', false) : '' ?>>25  — Plain (not recommended)</option>
          </select>
        </div>
      </div>
      <div class="seo-two-col">
        <div class="seo-form-group">
          <label>SMTP Username</label>
          <input type="text" name="smtp_user" id="field-smtp-user" value="<?= $s('smtp_user') ?>" class="regular-text" placeholder="hello@yourdomain.com">
        </div>
        <div class="seo-form-group">
          <label>SMTP Password</label>
          <div class="seo-input-group">
            <input type="password" name="smtp_pass" id="field-smtp-pass" value="<?= $s('smtp_pass') ?>" class="regular-text" placeholder="Your email account password">
            <button type="button" class="seo-btn seo-btn-outline seo-reveal-btn" tabindex="-1"><span class="dashicons dashicons-visibility"></span></button>
          </div>
        </div>
      </div>
      <div class="seo-two-col">
        <div class="seo-form-group">
          <label>From Name</label>
          <input type="text" name="smtp_from_name" id="field-smtp-from-name" value="<?= $s('smtp_from_name','Haris Farooq') ?>" class="regular-text">
        </div>
        <div class="seo-form-group">
          <label>From Email Address</label>
          <input type="email" name="smtp_from_email" id="field-smtp-from" value="<?= $s('smtp_from_email') ?>" class="regular-text" placeholder="hello@yourdomain.com">
        </div>
      </div>
    </div>

    <?php /* ══════════ HOW TO CONFIGURE PANEL ══════════ */ ?>
    <div id="seo-smtp-panel-guide" class="seo-smtp-panel" style="display:none">

      <!-- Guide Provider Sub-Tabs -->
      <div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:24px;flex-wrap:wrap;" id="seo-guide-tabs">
        <button type="button" onclick="seoShowGuide('gmail')" id="seo-gtab-gmail"
          style="background:#6366f1;color:#fff;border:none;border-bottom:2px solid #6366f1;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;margin-bottom:-2px;border-radius:6px 6px 0 0;">
          <span class="dashicons dashicons-email-alt" style="font-size:14px;width:14px;height:14px;"></span> Gmail
        </button>
        <button type="button" onclick="seoShowGuide('outlook')" id="seo-gtab-outlook"
          style="background:transparent;color:#64748b;border:none;border-bottom:2px solid transparent;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;margin-bottom:-2px;border-radius:6px 6px 0 0;">
          <span class="dashicons dashicons-email" style="font-size:14px;width:14px;height:14px;"></span> Outlook
        </button>
        <button type="button" onclick="seoShowGuide('yahoo')" id="seo-gtab-yahoo"
          style="background:transparent;color:#64748b;border:none;border-bottom:2px solid transparent;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;margin-bottom:-2px;border-radius:6px 6px 0 0;">
          <span class="dashicons dashicons-admin-site" style="font-size:14px;width:14px;height:14px;"></span> Yahoo
        </button>
        <button type="button" onclick="seoShowGuide('zoho')" id="seo-gtab-zoho"
          style="background:transparent;color:#64748b;border:none;border-bottom:2px solid transparent;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;margin-bottom:-2px;border-radius:6px 6px 0 0;">
          <span class="dashicons dashicons-admin-generic" style="font-size:14px;width:14px;height:14px;"></span> Zoho
        </button>
        <button type="button" onclick="seoShowGuide('hostinger')" id="seo-gtab-hostinger"
          style="background:transparent;color:#64748b;border:none;border-bottom:2px solid transparent;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;margin-bottom:-2px;border-radius:6px 6px 0 0;">
          <span class="dashicons dashicons-admin-site-alt3" style="font-size:14px;width:14px;height:14px;"></span> Hostinger
        </button>
        <button type="button" onclick="seoShowGuide('cpanel')" id="seo-gtab-cpanel"
          style="background:transparent;color:#64748b;border:none;border-bottom:2px solid transparent;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;margin-bottom:-2px;border-radius:6px 6px 0 0;">
          <span class="dashicons dashicons-admin-tools" style="font-size:14px;width:14px;height:14px;"></span> cPanel
        </button>
      </div>

      <!-- Gmail Guide -->
      <div id="seo-guide-gmail" class="seo-guide-panel">
        <h3 style="margin:0 0 14px;font-size:15px;color:#0f172a;">&#128231; Gmail SMTP Setup</h3>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">1</div>
            <div><strong>Enable 2-Step Verification</strong><br><span style="color:#64748b;font-size:13px;">Go to <a href="https://myaccount.google.com/security" target="_blank">myaccount.google.com/security</a> → turn on <strong>2-Step Verification</strong>. This is required before App Passwords become available.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">2</div>
            <div><strong>Generate an App Password</strong><br><span style="color:#64748b;font-size:13px;">Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a> → App: <strong>Mail</strong>, Device: <strong>Other</strong> → click <strong>Generate</strong>. Copy the 16-character password shown.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">3</div>
            <div><strong>Fill in the Gmail tab above</strong><br><span style="color:#64748b;font-size:13px;">Enter your Gmail address as both Username and From Email → paste the 16-character App Password. Host and Port are auto-filled.</span></div>
          </div>
          <div style="background:#f1f5f9;border-radius:8px;padding:12px 14px;font-size:12px;color:#475569;">
            <strong>SMTP:</strong> <code>smtp.gmail.com : 587 (TLS)</code>
          </div>
          <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:12px 14px;font-size:13px;color:#713f12;">
            <strong>&#9888; Common issue:</strong> "Username and Password not accepted" means you used your real Gmail password. You must use the App Password. Also ensure 2-Step Verification is fully active first.
          </div>
        </div>
      </div>

      <!-- Outlook Guide -->
      <div id="seo-guide-outlook" class="seo-guide-panel" style="display:none;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#0f172a;">&#128231; Outlook / Hotmail SMTP Setup</h3>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">1</div>
            <div><strong>Enable SMTP AUTH</strong><br><span style="color:#64748b;font-size:13px;">Go to <a href="https://outlook.live.com/mail/options/mail/accounts" target="_blank">Outlook Settings → Mail → Sync email</a> → under POP and IMAP, ensure <strong>SMTP AUTH is enabled</strong>. For Microsoft 365, an admin may need to enable this at the tenant level.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">2</div>
            <div><strong>Use the Other SMTP tab above with these settings:</strong><br>
              <div style="background:#f1f5f9;border-radius:8px;padding:10px 14px;font-size:12px;color:#475569;margin-top:8px;line-height:2;">
                <strong>Host:</strong> <code>smtp-mail.outlook.com</code><br>
                <strong>Port:</strong> <code>587 (TLS)</code><br>
                <strong>Username:</strong> your full Outlook/Hotmail email address<br>
                <strong>Password:</strong> your account password
              </div>
            </div>
          </div>
          <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:12px 14px;font-size:13px;color:#713f12;">
            <strong>&#9888; Note:</strong> Microsoft 365 / corporate Outlook accounts may require an admin to enable SMTP AUTH at the tenant level. Personal @outlook.com and @hotmail.com accounts work directly.
          </div>
        </div>
      </div>

      <!-- Yahoo Guide -->
      <div id="seo-guide-yahoo" class="seo-guide-panel" style="display:none;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#0f172a;">&#128231; Yahoo Mail SMTP Setup</h3>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">1</div>
            <div><strong>Generate a Yahoo App Password</strong><br><span style="color:#64748b;font-size:13px;">Go to <a href="https://login.yahoo.com/account/security" target="_blank">Yahoo Account Security</a> → enable <strong>2-Step Verification</strong> → then <strong>Generate app password</strong> → select <strong>Other App</strong> → copy the password.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">2</div>
            <div><strong>Use the Other SMTP tab above with these settings:</strong><br>
              <div style="background:#f1f5f9;border-radius:8px;padding:10px 14px;font-size:12px;color:#475569;margin-top:8px;line-height:2;">
                <strong>Host:</strong> <code>smtp.mail.yahoo.com</code><br>
                <strong>Port:</strong> <code>587 (TLS)</code><br>
                <strong>Username:</strong> your full Yahoo email address<br>
                <strong>Password:</strong> the App Password (NOT your regular Yahoo login password)
              </div>
            </div>
          </div>
          <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:12px 14px;font-size:13px;color:#713f12;">
            <strong>&#9888; Important:</strong> Yahoo blocks regular passwords for SMTP entirely. You must use an App Password or sending will always fail.
          </div>
        </div>
      </div>

      <!-- Zoho Guide -->
      <div id="seo-guide-zoho" class="seo-guide-panel" style="display:none;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#0f172a;">&#128231; Zoho Mail SMTP Setup</h3>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">1</div>
            <div><strong>Enable SMTP access</strong><br><span style="color:#64748b;font-size:13px;">Log in to <a href="https://mail.zoho.com" target="_blank">Zoho Mail</a> → Settings → Mail Accounts → click your account → scroll to <strong>IMAP/POP/SMTP Access</strong> → enable <strong>SMTP</strong>.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">2</div>
            <div><strong>App-Specific Password (if 2FA is enabled)</strong><br><span style="color:#64748b;font-size:13px;">Go to <a href="https://accounts.zoho.com/home#security" target="_blank">Zoho Account Security</a> → Two-Factor Auth → <strong>App-Specific Passwords</strong> → Generate and copy it.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">3</div>
            <div><strong>Use the Other SMTP tab above with these settings:</strong><br>
              <div style="background:#f1f5f9;border-radius:8px;padding:10px 14px;font-size:12px;color:#475569;margin-top:8px;line-height:2;">
                <strong>Host:</strong> <code>smtp.zoho.com</code><br>
                <strong>Port:</strong> <code>587 (TLS)</code><br>
                <strong>Username:</strong> your full Zoho email address<br>
                <strong>Password:</strong> your password or App-Specific Password if 2FA is on
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Hostinger Guide -->
      <div id="seo-guide-hostinger" class="seo-guide-panel" style="display:none;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#0f172a;">&#128231; Hostinger Email SMTP Setup</h3>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">1</div>
            <div><strong>Create an email account in hPanel</strong><br><span style="color:#64748b;font-size:13px;">Log in to <a href="https://hpanel.hostinger.com" target="_blank">hPanel</a> → <strong>Emails → Email Accounts → Create Email Account</strong>. Set a username (e.g. <code>hello@yourdomain.com</code>) and a strong password.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">2</div>
            <div><strong>Use the Other SMTP tab above with these settings:</strong><br>
              <div style="background:#f1f5f9;border-radius:8px;padding:10px 14px;font-size:12px;color:#475569;margin-top:8px;line-height:2;">
                <strong>Host:</strong> <code>smtp.hostinger.com</code><br>
                <strong>Port:</strong> <code>587 (TLS)</code><br>
                <strong>Username:</strong> your full email address e.g. <code>hello@yourdomain.com</code><br>
                <strong>Password:</strong> the email account password you set above
              </div>
            </div>
          </div>
          <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:12px 14px;font-size:13px;color:#713f12;">
            <strong>&#9888; Important:</strong> Use the <strong>email account password</strong>, NOT your hPanel login password — they are different.
          </div>
        </div>
      </div>

      <!-- cPanel Guide -->
      <div id="seo-guide-cpanel" class="seo-guide-panel" style="display:none;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#0f172a;">&#128231; cPanel Email SMTP Setup</h3>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">1</div>
            <div><strong>Create an email account in cPanel</strong><br><span style="color:#64748b;font-size:13px;">Log in to <strong>cPanel</strong> → <strong>Email Accounts → Create</strong>. Set a username and password. The full email will be <code>you@yourdomain.com</code>.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">2</div>
            <div><strong>Find your exact SMTP settings</strong><br><span style="color:#64748b;font-size:13px;">In cPanel → Email Accounts → click <strong>Connect Devices</strong> next to your email → look at the <strong>Outgoing Server (SMTP)</strong> section for your exact host and port.</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div style="min-width:28px;height:28px;background:#6366f1;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">3</div>
            <div><strong>Use the Other SMTP tab above with these settings:</strong><br>
              <div style="background:#f1f5f9;border-radius:8px;padding:10px 14px;font-size:12px;color:#475569;margin-top:8px;line-height:2;">
                <strong>Host:</strong> <code>mail.yourdomain.com</code><br>
                <strong>Port:</strong> <code>587 (TLS)</code> or <code>465 (SSL)</code><br>
                <strong>Username:</strong> your full email address<br>
                <strong>Password:</strong> your email account password
              </div>
            </div>
          </div>
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;font-size:13px;color:#14532d;">
            <strong>&#128161; Tip:</strong> If port 587 fails, try 465 with SSL. Some shared hosts block 587. Check "Connect Devices" in cPanel for the confirmed port.
          </div>
        </div>
      </div>

    </div><!-- /.seo-smtp-panel guide -->

    <div id="seo-email-config-actions" style="<?= $smtp_provider==='guide' ? 'display:none' : '' ?>">
      <div class="seo-api-test-row" style="margin-top:8px">
        <button type="button" class="seo-btn seo-btn-teal" onclick="seoTestApi('smtp')">
          <span class="dashicons dashicons-email"></span> Send Test Email
        </button>
        <div class="seo-test-result-box" id="result-smtp" style="flex:1"></div>
      </div>
      <p class="description" style="margin-top:4px">Test sends a real email to your first notification address. Saves current SMTP settings first.</p>
    </div>

  </div>
</div>
<button type="button" class="seo-btn seo-btn-primary seo-btn-lg" onclick="seoSaveSettings()" id="seo-email-save-btn">
  <span class="dashicons dashicons-saved"></span> Save Email Settings
</button>

<script>
  // ── Main Email Tab Switcher (Gmail | Other SMTP | How to Configure) ─────────
  window.seoEmailTab = function(tab) {
    // Hide all smtp panels
    document.querySelectorAll('.seo-smtp-panel').forEach(function(p) { p.style.display = 'none'; });
    var panel = document.getElementById('seo-smtp-panel-' + tab);
    if (panel) panel.style.display = 'block';

    // Update main tab styles
    ['gmail', 'other', 'guide'].forEach(function(t) {
      var btn = document.getElementById('seo-mtab-' + t);
      if (!btn) return;
      var active = t === tab;
      btn.style.background   = active ? '#6366f1' : 'transparent';
      btn.style.color        = active ? '#fff'    : '#64748b';
      btn.style.borderBottom = active ? '2px solid #6366f1' : '2px solid transparent';
    });

    // If switching to a config tab (gmail/other), update the hidden provider field
    if (tab !== 'guide') {
      var pf = document.getElementById('field-smtp-provider');
      if (pf) pf.value = tab;
    }

    // Show/hide test+save for guide tab
    var actions = document.getElementById('seo-email-config-actions');
    var saveBtn  = document.getElementById('seo-email-save-btn');
    if (actions) actions.style.display = tab === 'guide' ? 'none' : '';
    if (saveBtn)  saveBtn.style.display  = tab === 'guide' ? 'none' : '';
  };

  // Keep old seoSwitchSmtpProvider alias working (used by mailer test save flow)
  window.seoSwitchSmtpProvider = window.seoEmailTab;

  // ── Guide Provider Sub-Tab Switcher ─────────────────────────────────────────
  window.seoShowGuide = function(tab) {
    document.querySelectorAll('.seo-guide-panel').forEach(function(p) { p.style.display = 'none'; });
    var panel = document.getElementById('seo-guide-' + tab);
    if (panel) panel.style.display = 'block';

    document.querySelectorAll('#seo-guide-tabs button').forEach(function(btn) {
      var active = btn.id === 'seo-gtab-' + tab;
      btn.style.background   = active ? '#6366f1' : 'transparent';
      btn.style.color        = active ? '#fff'    : '#64748b';
      btn.style.borderBottom = active ? '2px solid #6366f1' : '2px solid transparent';
    });
  };
</script>

<?php /* ═══════════════ NOTIFICATIONS TAB ════════════════ */ elseif ( $tab === 'notify' ): ?>

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-bell"></span> Notification Recipients</div>
  <div class="seo-card-body">
    <div class="seo-form-group">
      <label>Notification Email Addresses</label>
      <p class="description">All addresses below receive system notifications. Add as many as needed.</p>
      <div id="seo-email-list" style="display:flex;flex-direction:column;gap:8px;max-width:480px">
        <?php
        $emails_to_show = empty($notif_emails) ? [''] : $notif_emails;
        foreach ( $emails_to_show as $i => $em ): ?>
          <div class="seo-notif-email-row">
            <input type="email" name="notification_emails[]" value="<?= esc_attr($em) ?>"
              class="regular-text" placeholder="email@example.com" style="flex:1">
            <button type="button" class="seo-btn seo-btn-sm seo-btn-danger"
              onclick="seoRemoveEmailRow(this)" <?= $i === 0 ? 'style="display:none"' : '' ?>>
              <span class="dashicons dashicons-trash"></span>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="seo-btn seo-btn-outline" style="margin-top:10px" onclick="seoAddEmailRow()">
        <span class="dashicons dashicons-plus-alt"></span> Add Email Address
      </button>
    </div>

    <h3 style="margin:20px 0 10px;font-size:14px">Notification Triggers</h3>
    <div class="seo-toggle-list">
      <label class="seo-toggle-row">
        <div class="seo-toggle-info"><strong>Campaign Completed</strong><p>Summary after each run finishes.</p></div>
        <div class="seo-toggle-switch"><input type="checkbox" name="notify_on_complete" <?= $checked('notify_on_complete') ?>><span class="seo-slider"></span></div>
      </label>
      <label class="seo-toggle-row">
        <div class="seo-toggle-info"><strong>Error Alerts</strong><p>Instant alert when any lead fails.</p></div>
        <div class="seo-toggle-switch"><input type="checkbox" name="notify_on_error" <?= $checked('notify_on_error') ?>><span class="seo-slider"></span></div>
      </label>
      <label class="seo-toggle-row">
        <div class="seo-toggle-info"><strong>Daily Summary</strong><p>Morning digest every day at 8 AM.</p></div>
        <div class="seo-toggle-switch"><input type="checkbox" name="notify_daily_summary" <?= $checked('notify_daily_summary') ?>><span class="seo-slider"></span></div>
      </label>
    </div>
  </div>
</div>
<button type="button" class="seo-btn seo-btn-primary seo-btn-lg" onclick="seoSaveSettings()">
  <span class="dashicons dashicons-saved"></span> Save Notification Settings
</button>

<?php /* ═══════════════ CAMPAIGN TAB ════════════════════ */ elseif ( $tab === 'campaign' ): ?>

<?php
$pdf_mode        = $s('pdf_delivery_mode','attach');
$type_seo        = ( ($settings['outreach_type_seo']        ?? '1') === '1' );
$type_ads        = ( ($settings['outreach_type_ads']        ?? '1') === '1' );
$type_no_website = ( ($settings['outreach_type_no_website'] ?? '1') === '1' );
?>

<style>
.seo-campaign-section{margin-bottom:28px}
.seo-campaign-section h3{font-size:14px;font-weight:700;color:#0f172a;margin:0 0 6px;display:flex;align-items:center;gap:7px}
.seo-campaign-section p.desc{color:#64748b;font-size:12px;margin:0 0 14px}

/* PDF Mode cards */
.seo-pdf-options{display:flex;gap:12px;flex-wrap:wrap}
.seo-pdf-option{display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 20px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all .15s;min-width:120px;text-align:center;background:#fff;position:relative}
.seo-pdf-option:hover{border-color:#6366f1;background:#fafbff}
.seo-pdf-option.selected{border-color:#6366f1;background:rgba(99,102,241,.07);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.seo-pdf-option .pdf-icon{font-size:24px;line-height:1}
.seo-pdf-option strong{font-size:13px;color:#0f172a}
.seo-pdf-option span.sub{font-size:11px;color:#64748b}

/* Outreach type cards */
.seo-outreach-types{display:flex;gap:12px;flex-wrap:wrap}
.seo-outreach-type{display:flex;flex-direction:column;gap:6px;padding:16px 20px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all .15s;min-width:200px;background:#fff}
.seo-outreach-type:hover{border-color:#0d9488;background:#f0fdf9}
.seo-outreach-type.active{border-color:#0d9488;background:rgba(13,148,136,.06);box-shadow:0 0 0 3px rgba(13,148,136,.15)}
.seo-outreach-type .type-header{display:flex;align-items:center;gap:8px}
.seo-outreach-type .type-icon{font-size:20px;line-height:1}
.seo-outreach-type strong{font-size:13px;color:#0f172a}
.seo-outreach-type p{font-size:12px;color:#64748b;margin:4px 0 0}
.seo-outreach-type .type-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-top:6px;width:fit-content}
.seo-outreach-type.active .type-badge{background:#0d9488;color:#fff}
.seo-outreach-type:not(.active) .type-badge{background:#e2e8f0;color:#64748b}

/* Sheet column guide */
.seo-sheet-guide{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-top:14px}
.seo-sheet-guide table{width:100%;border-collapse:collapse;font-size:12px}
.seo-sheet-guide th{background:#0f172a;color:#fff;padding:8px 12px;text-align:left;font-size:11px;font-weight:700;letter-spacing:.5px}
.seo-sheet-guide td{padding:7px 12px;border-bottom:1px solid #e2e8f0;color:#374151}
.seo-sheet-guide tr:last-child td{border-bottom:none}
.seo-sheet-guide tr:nth-child(even) td{background:#f8fafc}
.seo-sheet-guide .col-letter{font-family:monospace;font-weight:700;color:#6366f1;font-size:13px}
.seo-sheet-guide .col-req{color:#dc2626;font-weight:700}
.seo-sheet-guide .col-new{background:#fefce8 !important}
.seo-sheet-guide .col-new td{color:#92400e}
.seo-type-pill{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;font-family:monospace}
.pill-seo{background:#dbeafe;color:#1e40af}
.pill-ads{background:#fce7f3;color:#9d174d}
.pill-nw{background:#dcfce7;color:#166534}
</style>

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-controls-play"></span> Campaign Settings</div>
  <div class="seo-card-body">

    <!-- ── General ─────────────────────────────────────────────────── -->
    <div class="seo-campaign-section">
      <h3><span class="dashicons dashicons-admin-links" style="color:#6366f1"></span> Booking / Calendar Link</h3>
      <div class="seo-form-group">
        <input type="url" name="calendar_link" value="<?= $s('calendar_link') ?>" class="large-text" placeholder="https://calendly.com/harisfarooq/30min">
        <p class="description">Used as the CTA button link in every outreach email and PDF report.</p>
      </div>
    </div>

    <!-- ── Volume ──────────────────────────────────────────────────── -->
    <div class="seo-campaign-section">
      <h3><span class="dashicons dashicons-chart-bar" style="color:#6366f1"></span> Volume & Timing</h3>
      <div class="seo-two-col">
        <div class="seo-form-group">
          <label>Max Leads Per Campaign Run</label>
          <input type="number" name="max_leads_per_run" value="<?= $s('max_leads_per_run','10') ?>" min="1" max="500" class="small-text">
          <p class="description">Limit per run to avoid API rate limits.</p>
        </div>
        <div class="seo-form-group">
          <label>Delay Between Emails (seconds)</label>
          <input type="number" name="delay_between_emails" value="<?= $s('delay_between_emails','5') ?>" min="1" max="300" class="small-text">
          <p class="description">Min 3s recommended to avoid spam filters.</p>
        </div>
      </div>

      <!-- ── Batch Pause ─────────────────────────────────────────────── -->
      <div style="margin-top:20px;padding:18px 20px;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
          <label class="seo-toggle" style="margin:0;">
            <input type="checkbox" name="batch_pause_enabled" value="1" id="batch_pause_toggle"
              <?= $s('batch_pause_enabled','0')==='1' ? 'checked' : '' ?>
              onchange="document.getElementById('batch_pause_fields').style.display=this.checked?'flex':'none'">
            <span class="seo-toggle-slider"></span>
          </label>
          <div>
            <strong style="font-size:14px;color:#0f172a;">&#9201; Batch Pause (Rate Limit Protection)</strong>
            <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Pause the campaign after sending N emails to avoid Groq / API rate limits.</p>
          </div>
        </div>

        <div id="batch_pause_fields" style="display:<?= $s('batch_pause_enabled','0')==='1'?'flex':'none' ?>;gap:24px;flex-wrap:wrap;align-items:flex-end;">
          <div class="seo-form-group" style="margin:0;">
            <label style="font-size:13px;">Pause After Every</label>
            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
              <input type="number" name="batch_pause_after" value="<?= $s('batch_pause_after','5') ?>" min="1" max="100" class="small-text">
              <span style="font-size:13px;color:#475569;">emails</span>
            </div>
          </div>
          <div class="seo-form-group" style="margin:0;">
            <label style="font-size:13px;">Wait For</label>
            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
              <input type="number" name="batch_pause_minutes" value="<?= $s('batch_pause_minutes','5') ?>" min="1" max="60" class="small-text">
              <span style="font-size:13px;color:#475569;">minutes</span>
            </div>
          </div>
          <div style="padding:8px 12px;background:#fef3c7;border:1px solid #fbbf24;border-radius:6px;font-size:12px;color:#92400e;max-width:280px;">
            <strong>Example:</strong> Set to 5 emails / 5 min &rarr; sends 5 emails, waits 5 minutes, sends next 5, and so on. Fixes Groq TPM rate limit errors.
          </div>
        </div>
      </div>
    </div>

    <hr style="border:none;border-top:1px solid #e2e8f0;margin:8px 0 24px">

    <!-- ── PDF Delivery Mode ───────────────────────────────────────── -->
    <div class="seo-campaign-section">
      <h3><span class="dashicons dashicons-media-document" style="color:#0d9488"></span> PDF Delivery Mode</h3>
      <p class="desc">Choose how the SEO audit PDF is delivered with each outreach email. Does not apply to <strong>no_website</strong> leads (no PDF generated).</p>

      <input type="hidden" name="pdf_delivery_mode" id="pdf_delivery_mode_input" value="<?= esc_attr($pdf_mode) ?>">

      <div class="seo-pdf-options">

        <label class="seo-pdf-option <?= $pdf_mode==='attach'?'selected':'' ?>" onclick="seoPdfMode('attach',this)">
          <span class="pdf-icon">📎</span>
          <strong>Attach PDF</strong>
          <span class="sub">PDF attached to email</span>
        </label>

        <label class="seo-pdf-option <?= $pdf_mode==='link'?'selected':'' ?>" onclick="seoPdfMode('link',this)">
          <span class="pdf-icon">🔗</span>
          <strong>Link PDF</strong>
          <span class="sub">Upload to site, button in email</span>
        </label>

        <label class="seo-pdf-option <?= $pdf_mode==='both'?'selected':'' ?>" onclick="seoPdfMode('both',this)">
          <span class="pdf-icon">📎🔗</span>
          <strong>Both</strong>
          <span class="sub">Attach + link button in email</span>
        </label>

        <label class="seo-pdf-option <?= $pdf_mode==='none'?'selected':'' ?>" onclick="seoPdfMode('none',this)">
          <span class="pdf-icon">✉️</span>
          <strong>No PDF</strong>
          <span class="sub">Email only, no PDF</span>
        </label>

      </div>

      <div style="margin-top:12px;padding:10px 14px;border-radius:8px;font-size:12px;color:#475569;background:#f0f4f8;display:flex;gap:8px;align-items:flex-start">
        <span class="dashicons dashicons-info" style="color:#6366f1;margin-top:1px;flex-shrink:0"></span>
        <span>
          <strong>Link / Both mode:</strong> PDFs are auto-uploaded to <code>/wp-content/uploads/seo-outreach-pdfs/</code> and made publicly accessible so the link works in emails. A <strong>📄 View Your Free SEO Report</strong> button is added above the CTA button.<br>
          <strong>Attach mode:</strong> PDF is attached directly. Higher spam risk — use Link mode for better inbox delivery.
        </span>
      </div>
    </div>

    <hr style="border:none;border-top:1px solid #e2e8f0;margin:8px 0 24px">

    <!-- ── Outreach Types ──────────────────────────────────────────── -->    <div class="seo-campaign-section">
      <h3><span class="dashicons dashicons-tag" style="color:#0d9488"></span> Active Outreach Types</h3>
      <p class="desc">
        Enable or disable outreach types. The type is read from column <strong>G (Outreach type)</strong> in your Google Sheet per lead.
        Disabled types are skipped during campaign runs.
      </p>

      <div class="seo-outreach-types">

        <!-- SEO -->
        <div class="seo-outreach-type <?= $type_seo?'active':'' ?>" id="otype-seo" onclick="seoToggleType('seo',this)">
          <input type="hidden" name="outreach_type_seo" id="otype-seo-input" value="<?= $type_seo?'1':'0' ?>">
          <div class="type-header">
            <span class="type-icon">🔍</span>
            <strong>SEO Services</strong>
          </div>
          <p>Has a website with poor rankings & performance. Full audit + SEO pitch email.</p>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <span class="type-pill pill-seo">PageSpeed audit</span>
            <span class="type-pill pill-seo">PDF report</span>
            <span class="type-pill pill-seo">SEO pitch</span>
          </div>
          <span class="type-badge"><?= $type_seo?'✓ Enabled':'✗ Disabled' ?></span>
        </div>

        <!-- ADS -->
        <div class="seo-outreach-type <?= $type_ads?'active':'' ?>" id="otype-ads" onclick="seoToggleType('ads',this)">
          <input type="hidden" name="outreach_type_ads" id="otype-ads-input" value="<?= $type_ads?'1':'0' ?>">
          <div class="type-header">
            <span class="type-icon">📢</span>
            <strong>Running Ads</strong>
          </div>
          <p>Spending on paid ads. Pitch SEO as better long-term ROI — organic traffic compounds for free.</p>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <span class="type-pill pill-ads">PageSpeed audit</span>
            <span class="type-pill pill-ads">PDF report</span>
            <span class="type-pill pill-ads">Ad ROI pitch</span>
          </div>
          <span class="type-badge"><?= $type_ads?'✓ Enabled':'✗ Disabled' ?></span>
        </div>

        <!-- NO WEBSITE -->
        <div class="seo-outreach-type <?= $type_no_website?'active':'' ?>" id="otype-no_website" onclick="seoToggleType('no_website',this)">
          <input type="hidden" name="outreach_type_no_website" id="otype-no_website-input" value="<?= $type_no_website?'1':'0' ?>">
          <div class="type-header">
            <span class="type-icon">🌐</span>
            <strong>No Website</strong>
          </div>
          <p>Has no website at all. Pitch building a professional site + SEO ranking from scratch.</p>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <span class="type-pill pill-nw">Email only</span>
            <span class="type-pill pill-nw">No PageSpeed</span>
            <span class="type-pill pill-nw">Website pitch</span>
          </div>
          <span class="type-badge"><?= $type_no_website?'✓ Enabled':'✗ Disabled' ?></span>
        </div>

      </div>
    </div>


  </div><!-- /.seo-card-body -->
</div><!-- /.seo-card -->

<button type="button" class="seo-btn seo-btn-primary seo-btn-lg" onclick="seoSaveSettings()">
  <span class="dashicons dashicons-saved"></span> Save Campaign Settings
</button>

<script>
window.seoPdfMode = function(mode, el) {
    document.querySelectorAll('.seo-pdf-option').forEach(function(o) { o.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('pdf_delivery_mode_input').value = mode;
};

window.seoToggleType = function(type, el) {
    var input  = document.getElementById('otype-' + type + '-input');
    var badge  = el.querySelector('.type-badge');
    var active = el.classList.toggle('active');
    input.value = active ? '1' : '0';
    if (badge) badge.textContent = active ? '✓ Enabled' : '✗ Disabled';
};
</script>

<?php /* ═══════════════ LOG SETTINGS TAB ═════════════════ */ elseif ( $tab === 'logs' ): ?>

<div class="seo-card">
  <div class="seo-card-header"><span class="dashicons dashicons-list-view"></span> Log Retention &amp; Auto-Refresh</div>
  <div class="seo-card-body">
    <div class="seo-two-col">
      <div class="seo-form-group">
        <label>Auto-Delete Logs Older Than (days)</label>
        <input type="number" name="log_retention_days" value="<?= $s('log_retention_days','7') ?>" min="1" max="365" class="small-text">
        <p class="description">Runs automatically every day. Default: 7 days.</p>
      </div>
      <div class="seo-form-group">
        <label>Auto-Refresh Interval (seconds)</label>
        <input type="number" name="log_auto_refresh_secs" value="<?= $s('log_auto_refresh_secs','30') ?>" min="5" max="300" class="small-text">
        <p class="description">How often the Logs page refreshes when auto-refresh is enabled.</p>
      </div>
    </div>

    <div class="seo-form-section" style="margin-top:16px">
      <h3 style="font-size:14px">Manual Log Cleanup</h3>
      <p class="description">These actions delete logs immediately and cannot be undone.</p>
      <div class="seo-log-actions-grid">
        <div class="seo-log-action-card">
          <div class="seo-log-action-icon red"><span class="dashicons dashicons-trash"></span></div>
          <div>
            <strong>Clear All Logs</strong>
            <p>Delete every log entry.</p>
            <button type="button" class="seo-btn seo-btn-sm seo-btn-danger" onclick="seoClearLogs('all')">
              <span class="dashicons dashicons-trash"></span> Clear All
            </button>
          </div>
        </div>
        <div class="seo-log-action-card">
          <div class="seo-log-action-icon orange"><span class="dashicons dashicons-warning"></span></div>
          <div>
            <strong>Clear Error Logs</strong>
            <p>Delete only error entries.</p>
            <button type="button" class="seo-btn seo-btn-sm" style="background:#f59e0b;color:#fff;border-color:#f59e0b" onclick="seoClearLogs('errors')">
              <span class="dashicons dashicons-dismiss"></span> Clear Errors
            </button>
          </div>
        </div>
        <div class="seo-log-action-card">
          <div class="seo-log-action-icon blue"><span class="dashicons dashicons-calendar-alt"></span></div>
          <div>
            <strong>Clear by Date Range</strong>
            <div style="display:flex;gap:6px;margin:6px 0;align-items:center;flex-wrap:wrap">
              <input type="date" id="seo-date-from" class="regular-text" style="width:auto">
              <span class="seo-muted">to</span>
              <input type="date" id="seo-date-to" class="regular-text" style="width:auto">
            </div>
            <button type="button" class="seo-btn seo-btn-sm seo-btn-outline" onclick="seoClearLogs('date_range')">
              <span class="dashicons dashicons-trash"></span> Clear Range
            </button>
          </div>
        </div>
      </div>
      <div id="seo-clear-result" style="display:none;margin-top:12px"></div>
    </div>

    <div class="seo-form-section" style="margin-top:16px">
      <h3 style="font-size:14px">Run Retention Purge Now</h3>
      <p class="description">Immediately delete logs older than your configured retention days.</p>
      <button type="button" class="seo-btn seo-btn-outline" onclick="seoPurgeNow()">
        <span class="dashicons dashicons-update"></span> Purge Old Logs Now
      </button>
    </div>
  </div>
</div>
<button type="button" class="seo-btn seo-btn-primary seo-btn-lg" onclick="seoSaveSettings()">
  <span class="dashicons dashicons-saved"></span> Save Log Settings
</button>

<?php endif; ?>
</form>

<script>
jQuery(document).ready(function($) {

  // ── SMTP Provider Toggle (Gmail / Other) ───────────────────────────────────
  window.seoSwitchSmtpProvider = function(provider) {
    // Hide all panels, show selected
    document.querySelectorAll('.seo-smtp-panel').forEach(function(p) { p.style.display = 'none'; });
    var panel = document.getElementById('seo-smtp-panel-' + provider);
    if (panel) panel.style.display = 'block';

    // Update toggle button styles
    ['gmail', 'other'].forEach(function(p) {
      var btn = document.getElementById('seo-toggle-' + p);
      if (!btn) return;
      var active = p === provider;
      btn.style.background  = active ? '#6366f1' : 'transparent';
      btn.style.color       = active ? '#fff'    : '#64748b';
      btn.style.boxShadow   = active ? '0 2px 8px rgba(99,102,241,.35)' : 'none';
    });

    // Update hidden provider field
    var providerField = document.getElementById('field-smtp-provider');
    if (providerField) providerField.value = provider;

    // Update active label
    var labels = { gmail: 'Gmail', other: 'Other SMTP' };
    var labelEl = document.getElementById('seo-active-provider-label');
    if (labelEl) labelEl.textContent = labels[provider] || provider;
  };

  // ── Save settings ───────────────────────────────────────────────────────────
  window.seoSelectAI = function(value, label) {
    document.querySelectorAll('.seo-ai-option').forEach(el => el.classList.remove('selected'));
    if (label) label.classList.add('selected');
    // Sync the hidden radio
    const radio = label ? label.querySelector('input[type=radio]') : null;
    if (radio) radio.checked = true;
  };

  window.seoSaveSettings = function() {
    const form = document.getElementById('seo-settings-form');
    const data = { action: 'seo_outreach_save_settings', nonce: seoOutreach.nonce };
    const fd   = new FormData(form);
    const notifEmails = [];

    fd.forEach((v, k) => {
      if (k === 'notification_emails[]') {
        notifEmails.push(v);
      } else {
        data[k] = v;
      }
    });
    data['notification_emails'] = notifEmails;

    // Handle unchecked checkboxes explicitly
    ['notify_on_complete','notify_on_error','notify_daily_summary'].forEach(k => {
      if (!(k in data)) data[k] = '0';
    });

    $.post(seoOutreach.ajaxUrl, data, function(res) {
      const el = document.getElementById('seo-settings-notice');
      el.style.display = 'block';
      el.className = 'seo-notice seo-notice-' + (res.success ? 'success' : 'error');
      el.textContent = res.success ? '✓ Settings saved successfully.' : '✗ Error: ' + (res.data?.message || 'Unknown error');
      setTimeout(() => { el.style.display = 'none'; }, 4000);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  };

  // ── API Test — reads CURRENT field values, saves first, then tests ──────────
  window.seoTestApi = function(type) {
    const resultEl = document.getElementById('result-' + type);
    if (!resultEl) return;

    resultEl.className = 'seo-test-result-box testing';
    resultEl.innerHTML = '<span class="dashicons dashicons-update seo-spin"></span> '
      + (type === 'pagespeed' ? 'Testing (this takes ~20 seconds)...' : 'Testing...');

    // Collect current live field values from the page
    const liveData = {
      action: 'seo_outreach_test_api',
      nonce:  seoOutreach.nonce,
      type:   type,
    };

    // Grab each field value directly — don't rely on saved DB values
    const fieldMap = {
      gemini_api_key:          '#field-gemini-key',
      gemini_model:            '#field-gemini-model',
      groq_api_key:            '#field-groq-key',
      groq_model:              '#field-groq-model',
      pagespeed_api_key:       '#field-pagespeed-key',
      google_service_account:  '#seo-sa-json',
      // SMTP fields: read from whichever panel is currently visible
      smtp_host:               null,
      smtp_port:               null,
      smtp_user:               null,
      smtp_pass:               null,
      smtp_from_email:         null,
    };
    // Determine active SMTP panel suffix
    const activePanel = document.querySelector('.seo-smtp-panel[style*="display: block"], .seo-smtp-panel:not([style*="display: none"])');
    const isGmail = activePanel && activePanel.id === 'seo-smtp-panel-gmail';
    const sfx = isGmail ? '-gmail' : '';
    const smtpFields = {
      smtp_host:      '#field-smtp-host' + sfx,
      smtp_port:      '#field-smtp-port' + sfx,
      smtp_user:      '#field-smtp-user' + sfx,
      smtp_pass:      '#field-smtp-pass' + sfx,
      smtp_from_email:'#field-smtp-from' + sfx,
      smtp_from_name: '#field-smtp-from-name' + sfx,
    };
    Object.entries(fieldMap).forEach(([key, sel]) => {
      if (sel === null) return; // handled below
      const el = document.querySelector(sel);
      if (el) liveData[key] = el.value;
    });
    Object.entries(smtpFields).forEach(([key, sel]) => {
      const el = document.querySelector(sel);
      if (el) liveData[key] = el.value;
    });

    // Also collect notification emails for SMTP test
    const emailInputs = document.querySelectorAll('input[name="notification_emails[]"]');
    if (emailInputs.length) liveData['notification_email_first'] = emailInputs[0].value;

    $.post(seoOutreach.ajaxUrl, liveData, function(res) {
      resultEl.className = 'seo-test-result-box ' + (res.success ? 'ok' : 'fail');
      resultEl.innerHTML = res.success
        ? '<span class="dashicons dashicons-yes-alt"></span> ' + res.data.message
        : '<span class="dashicons dashicons-dismiss"></span> ' + (res.responseJSON?.data?.message || res.data?.message || 'Test failed');
    }).fail(function(xhr) {
      resultEl.className = 'seo-test-result-box fail';
      resultEl.innerHTML = '<span class="dashicons dashicons-dismiss"></span> Request failed — check your browser console';
    });
  };

  // ── Reveal password toggle ──────────────────────────────────────────────────
  $(document).on('click', '.seo-reveal-btn', function() {
    const input = $(this).closest('.seo-input-group').find('input').first();
    if (!input.length) return;
    const isPassword = input.attr('type') === 'password';
    input.attr('type', isPassword ? 'text' : 'password');
    const icon = $(this).find('.dashicons');
    if (isPassword) {
      icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
    } else {
      icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
    }
  });

  // ── JSON file upload ────────────────────────────────────────────────────────
  window.seoHandleJsonUpload = function(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('seo-upload-filename').textContent = '📄 ' + file.name;
    const reader = new FileReader();
    reader.onload = function(e) {
      const json = e.target.result;
      try {
        JSON.parse(json); // validate
        document.getElementById('seo-sa-json').value = json;
        seoExtractEmail();
      } catch(err) {
        alert('Invalid JSON file. Please upload a valid Google Service Account key file.');
      }
    };
    reader.readAsText(file);
  };

  // ── Extract SA email live from JSON ─────────────────────────────────────────
  window.seoExtractEmail = function() {
    const json = document.getElementById('seo-sa-json')?.value || '';
    const stepEmail = document.getElementById('seo-step-email');
    const stepFetch = document.getElementById('seo-step-fetch');
    const box       = document.getElementById('seo-email-display-box');
    if (!box) return;
    try {
      const sa    = JSON.parse(json);
      const email = sa.client_email || '';
      if (!email) throw new Error('No client_email');
      box.innerHTML = `<code id="seo-sa-email-text">${email}</code>
        <button type="button" class="seo-btn seo-btn-sm seo-btn-outline" onclick="seoCopyEmail()">
          <span class="dashicons dashicons-clipboard"></span> Copy Email
        </button>
        <span id="seo-copy-confirm" style="font-size:12px;color:#15803d;display:none">✓ Copied!</span>`;
      if (stepEmail) { stepEmail.style.opacity='1'; stepEmail.style.pointerEvents='auto'; }
      if (stepFetch) { stepFetch.style.opacity='1'; stepFetch.style.pointerEvents='auto'; }
    } catch(e) {
      if (box) box.innerHTML = '<span class="seo-muted" style="font-size:13px">Paste or upload the JSON key above to see the service account email</span>';
      if (stepEmail) { stepEmail.style.opacity='0.4'; stepEmail.style.pointerEvents='none'; }
      if (stepFetch) { stepFetch.style.opacity='0.4'; stepFetch.style.pointerEvents='none'; }
    }
  };

  window.seoCopyEmail = function() {
    const email = document.getElementById('seo-sa-email-text')?.textContent || '';
    navigator.clipboard.writeText(email).then(() => {
      const el = document.getElementById('seo-copy-confirm');
      if (el) { el.style.display='inline'; setTimeout(() => el.style.display='none', 2500); }
    });
  };

  // ── Fetch sheets ─────────────────────────────────────────────────────────────
  window.seoFetchSheets = async function() {
    const btn    = document.getElementById('seo-fetch-btn');
    const status = document.getElementById('seo-fetch-status');
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update seo-spin"></span> Fetching...';
    status.style.color = '#64748b';
    status.textContent = '';

    // Save SA JSON first so the AJAX handler can read it
    const saJson = document.getElementById('seo-sa-json')?.value || '';
    await $.post(seoOutreach.ajaxUrl, {
      action: 'seo_outreach_save_settings',
      nonce:  seoOutreach.nonce,
      google_service_account: saJson,
    });

    $.post(seoOutreach.ajaxUrl, { action:'seo_outreach_fetch_sheets', nonce:seoOutreach.nonce }, function(res) {
      btn.disabled = false;
      btn.innerHTML = '<span class="dashicons dashicons-update"></span> Fetch My Sheets';

      if (!res.success) {
        status.style.color = '#dc2626';
        status.textContent = '✗ ' + (res.responseJSON?.data?.message || 'Error fetching sheets');
        return;
      }

      const sheets  = res.data.sheets || [];
      const selArea = document.getElementById('seo-sheet-selectors');
      const dd      = document.getElementById('seo-sheet-dd');
      selArea.style.display = 'block';

      const savedId = document.getElementById('inp-sheet-id').value;
      dd.innerHTML  = '<option value="">— Select a sheet —</option>' +
        sheets.map(s => `<option value="${s.id}" data-name="${s.name}" ${s.id===savedId?'selected':''}>${s.name}</option>`).join('');

      status.style.color = '#15803d';
      status.textContent = `✓ Found ${sheets.length} sheet(s)`;

      if (savedId) seoOnSheetSelect(dd); else if (sheets.length === 1) { dd.selectedIndex = 1; seoOnSheetSelect(dd); }
    }).fail(function() {
      btn.disabled = false;
      btn.innerHTML = '<span class="dashicons dashicons-update"></span> Fetch My Sheets';
      status.style.color = '#dc2626';
      status.textContent = '✗ Request failed';
    });
  };

  window.seoOnSheetSelect = async function(dd) {
    if (!dd) dd = document.getElementById('seo-sheet-dd');
    const opt     = dd.options[dd.selectedIndex];
    const sheetId = opt.value;
    const name    = opt.dataset.name || opt.text;

    document.getElementById('inp-sheet-id').value   = sheetId;
    document.getElementById('inp-sheet-name').value = name;
    document.getElementById('inp-sheet-tab').value  = '';

    const tabGroup = document.getElementById('seo-tab-group');
    const tabDd    = document.getElementById('seo-tab-dd');
    tabGroup.style.display = sheetId ? 'block' : 'none';
    if (!sheetId) return;

    tabDd.innerHTML = '<option>Loading tabs...</option>';
    const savedTab  = document.getElementById('inp-sheet-tab').value;

    const res = await $.post(seoOutreach.ajaxUrl, { action:'seo_outreach_fetch_sheet_tabs', nonce:seoOutreach.nonce, sheet_id:sheetId });
    if (!res.success) { tabDd.innerHTML = '<option>Error loading tabs</option>'; return; }

    tabDd.innerHTML = res.data.tabs.map(t =>
      `<option value="${t.title}" ${t.title===savedTab?'selected':''}>${t.title}</option>`
    ).join('');
    document.getElementById('inp-sheet-tab').value = tabDd.value;

    // Auto-save sheet selection immediately so it persists on tab switches
    tabDd.onchange = () => {
      document.getElementById('inp-sheet-tab').value = tabDd.value;
      seoSaveSheetSelection(sheetId, name, tabDd.value);
    };
    // Save current selection right away
    seoSaveSheetSelection(sheetId, name, tabDd.value);
  };

  // Save just the sheet fields — called whenever selection changes
  function seoSaveSheetSelection(sheetId, sheetName, tab) {
    $.post(seoOutreach.ajaxUrl, {
      action:           'seo_outreach_save_settings',
      nonce:            seoOutreach.nonce,
      google_sheet_id:   sheetId,
      google_sheet_name: sheetName,
      google_sheet_tab:  tab,
    });
  }

  // ── Notification email rows ──────────────────────────────────────────────────
  window.seoAddEmailRow = function() {
    const list = document.getElementById('seo-email-list');
    const row  = document.createElement('div');
    row.className = 'seo-notif-email-row';
    row.style.cssText = 'display:flex;align-items:center;gap:8px';
    row.innerHTML = `<input type="email" name="notification_emails[]" value="" class="regular-text" placeholder="email@example.com" style="flex:1">
      <button type="button" class="seo-btn seo-btn-sm seo-btn-danger" onclick="seoRemoveEmailRow(this)">
        <span class="dashicons dashicons-trash"></span>
      </button>`;
    list.appendChild(row);
    row.querySelector('input').focus();
    seoSyncRemoveBtns();
  };

  window.seoRemoveEmailRow = function(btn) {
    btn.closest('.seo-notif-email-row').remove();
    seoSyncRemoveBtns();
  };

  function seoSyncRemoveBtns() {
    const rows = document.querySelectorAll('.seo-notif-email-row');
    rows.forEach((row, i) => {
      const btn = row.querySelector('.seo-btn-danger');
      if (btn) btn.style.display = rows.length > 1 ? '' : 'none';
    });
  }

  // ── Log management ───────────────────────────────────────────────────────────
  window.seoClearLogs = function(type) {
    const labels = { all:'ALL log entries', errors:'all error log entries', date_range:'log entries in the selected date range' };
    if (!confirm('Are you sure you want to permanently delete ' + labels[type] + '? This cannot be undone.')) return;

    const data = { action:'seo_outreach_clear_logs', nonce:seoOutreach.nonce, clear_type:type };
    if (type === 'date_range') {
      data.date_from = document.getElementById('seo-date-from').value;
      data.date_to   = document.getElementById('seo-date-to').value;
      if (!data.date_from || !data.date_to) { alert('Please select both a from and to date.'); return; }
    }

    $.post(seoOutreach.ajaxUrl, data, function(res) {
      const el = document.getElementById('seo-clear-result');
      el.style.display = 'block';
      el.className = 'seo-notice seo-notice-' + (res.success ? 'success' : 'error');
      el.textContent = res.success ? '✓ ' + res.data.message : '✗ ' + (res.responseJSON?.data?.message || 'Error');
    });
  };

  window.seoPurgeNow = function() {
    const days = document.querySelector('[name="log_retention_days"]')?.value || 7;
    if (!confirm(`Delete all logs older than ${days} days right now?`)) return;
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - parseInt(days));
    $.post(seoOutreach.ajaxUrl, {
      action:'seo_outreach_clear_logs', nonce:seoOutreach.nonce,
      clear_type:'date_range',
      date_from:'2000-01-01',
      date_to: cutoff.toISOString().split('T')[0],
    }, function(res) {
      const el = document.getElementById('seo-clear-result');
      el.style.display = 'block';
      el.className = 'seo-notice seo-notice-' + (res.success ? 'success' : 'error');
      el.textContent = res.success ? '✓ ' + res.data.message : '✗ ' + (res.responseJSON?.data?.message || 'Error');
    });
  };

}); // end jQuery ready
</script>

<?php seo_outreach_footer(); ?>
