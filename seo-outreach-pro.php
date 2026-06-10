<?php
/**
 * Plugin Name:       SEO Outreach Pro
 * Plugin URI:        https://harisfarooqseo.online
 * Description:       AI-powered cold outreach system. Audits websites, generates personalized SEO reports via Gemini AI, and sends branded PDF emails automatically.
 * Version:           2.1.0
 * Author:            Haris Farooq
 * Author URI:        https://harisfarooqseo.online
 * License:           GPL-2.0+
 * Text Domain:       seo-outreach-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'SEO_OUTREACH_VERSION',      '2.0.0' );
define( 'SEO_OUTREACH_FILE',         __FILE__ );
define( 'SEO_OUTREACH_DIR',          plugin_dir_path( __FILE__ ) );
define( 'SEO_OUTREACH_URL',          plugin_dir_url( __FILE__ ) );
define( 'SEO_OUTREACH_BRAND',        'Haris Farooq' );
define( 'SEO_OUTREACH_TAGLINE',      'AI Driven SEO Expert' );
define( 'SEO_OUTREACH_WEBSITE',      'harisfarooqseo.online' );
// Gemini model — user-selectable, defaults to gemini-2.5-flash
$_seo_gemini_model = function_exists('get_option') ? get_option('seo_outreach_gemini_model', 'gemini-2.5-flash') : 'gemini-2.5-flash';
define( 'SEO_OUTREACH_GEMINI_MODEL', $_seo_gemini_model );

require_once SEO_OUTREACH_DIR . 'includes/class-database.php';
require_once SEO_OUTREACH_DIR . 'includes/class-activator.php';
require_once SEO_OUTREACH_DIR . 'includes/class-settings.php';
require_once SEO_OUTREACH_DIR . 'includes/class-pagespeed.php';
require_once SEO_OUTREACH_DIR . 'includes/class-prompt.php';
require_once SEO_OUTREACH_DIR . 'includes/class-gemini.php';
require_once SEO_OUTREACH_DIR . 'includes/class-sheets.php';
require_once SEO_OUTREACH_DIR . 'includes/class-mailer.php';
require_once SEO_OUTREACH_DIR . 'includes/class-pdf-generator.php';
require_once SEO_OUTREACH_DIR . 'includes/class-groq.php';
require_once SEO_OUTREACH_DIR . 'includes/class-campaign-runner.php';
require_once SEO_OUTREACH_DIR . 'includes/class-scheduler.php';
require_once SEO_OUTREACH_DIR . 'admin/class-admin.php';

register_activation_hook(   __FILE__, [ 'SEO_Outreach_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SEO_Outreach_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    SEO_Outreach_Activator::maybe_upgrade(); // Run DB upgrades if version changed
    if ( is_admin() ) new SEO_Outreach_Admin();
    SEO_Outreach_Scheduler::init();
} );

// ── AJAX Handlers ─────────────────────────────────────────────────────────────
$ajax_actions = [
    'run_campaign', 'preflight', 'get_leads', 'sync_leads', 'debug_sync', 'poll_logs',
    'save_settings', 'save_schedule', 'delete_schedule', 'toggle_schedule',
    'test_api', 'fetch_sheets', 'fetch_sheet_tabs',
    'clear_logs', 'delete_lead', 'download_pdf', 'download_csv',
];
foreach ( $ajax_actions as $action ) {
    add_action( "wp_ajax_seo_outreach_{$action}", "seo_outreach_ajax_{$action}" );
}

function seo_outreach_verify() {
    if ( ! check_ajax_referer( 'seo_outreach_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }
}

function seo_outreach_ajax_run_campaign() {
    // Capture any stray PHP output (notices/warnings) so they don't corrupt JSON
    ob_start();
    seo_outreach_verify();

    // Prevent PHP from timing out during long campaign runs
    @set_time_limit( 0 );
    @ini_set( 'max_execution_time', '0' );
    ignore_user_abort( true );

    $max = intval( $_POST['max_leads'] ?? SEO_Outreach_Settings::get( 'max_leads_per_run', 10 ) );

    try {
        $runner = new SEO_Outreach_Campaign_Runner();
        $stats  = $runner->run( 'manual', $max );
        $stray  = ob_get_clean();
        // Log stray output for debugging if any
        if ( ! empty( trim( $stray ) ) ) {
            try {
                ( new SEO_Outreach_Database() )->log( 'SYSTEM', 'php_output', 'info', 'Stray PHP output captured: ' . substr( strip_tags( $stray ), 0, 500 ), 0 );
            } catch ( Throwable $ignored ) {}
        }
        wp_send_json_success( $stats );
    } catch ( Throwable $e ) {
        ob_end_clean();
        // Log the fatal error so it appears in activity log
        try {
            ( new SEO_Outreach_Database() )->log( 'SYSTEM', 'fatal', 'error', 'Campaign crashed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 0 );
        } catch ( Throwable $ignored ) {}
        wp_send_json_error( [ 'message' => $e->getMessage() ?: 'An unexpected error occurred. Check the Activity Log for details.' ] );
    }
}

function seo_outreach_ajax_preflight() {
    seo_outreach_verify();
    $sa_json = SEO_Outreach_Settings::get( 'google_service_account' );
    $active_ai    = SEO_Outreach_Settings::get( 'active_ai', 'auto' );
    $gemini_key   = SEO_Outreach_Settings::get( 'gemini_api_key' );
    $groq_key     = SEO_Outreach_Settings::get( 'groq_api_key' );
    $ai_ok        = (bool) $gemini_key || (bool) $groq_key;
    $gemini_model = SEO_Outreach_Settings::get( 'gemini_model', 'gemini-2.5-flash' );
    $ai_label     = 'AI Engine (' . ucfirst( $active_ai ) . ')';
    $ai_message   = $ai_ok
        ? ( $gemini_key && $groq_key
            ? 'Gemini (' . $gemini_model . ') + Groq both configured (auto-fallback active)'
            : ( $gemini_key ? 'Gemini configured (' . $gemini_model . ')' : 'Groq configured (' . SEO_Outreach_Groq::MODEL . ')' ) )
        : 'No AI key set — add Gemini or Groq key in Settings → API Keys';
    $checks  = [
        [ 'label' => $ai_label,               'ok' => $ai_ok,                                                  'message' => $ai_message ],
        [ 'label' => 'PageSpeed API Key',     'ok' => (bool) SEO_Outreach_Settings::get( 'pagespeed_api_key' ),'message' => SEO_Outreach_Settings::get( 'pagespeed_api_key' ) ? 'Configured' : 'Not set' ],
        [ 'label' => 'Google Service Account','ok' => SEO_Outreach_Sheets::validate_json( $sa_json ),          'message' => SEO_Outreach_Sheets::validate_json( $sa_json )    ? 'Valid JSON' : 'Not set or invalid' ],
        [ 'label' => 'Google Sheet Selected', 'ok' => (bool) SEO_Outreach_Settings::get( 'google_sheet_id' ),  'message' => SEO_Outreach_Settings::get( 'google_sheet_id' )   ? SEO_Outreach_Settings::get( 'google_sheet_name' ) : 'No sheet selected' ],
        [ 'label' => 'SMTP / Email',          'ok' => SEO_Outreach_Settings::get( 'smtp_host' ) && SEO_Outreach_Settings::get( 'smtp_from_email' ), 'message' => ( SEO_Outreach_Settings::get( 'smtp_host' ) && SEO_Outreach_Settings::get( 'smtp_from_email' ) ) ? 'Configured' : 'Not configured' ],
    ];
    wp_send_json_success( [ 'checks' => $checks ] );
}

function seo_outreach_ajax_get_leads() {
    seo_outreach_verify();
    $db     = new SEO_Outreach_Database();
    $status = sanitize_text_field( $_POST['status'] ?? '' );
    $page   = max( 1, intval( $_POST['page'] ?? 1 ) );
    $result = $db->get_leads( $status, $page, 100 );
    $counts = $db->get_lead_counts();
    $last   = $db->get_last_sync();
    wp_send_json_success( [
        'leads'     => $result['leads'],
        'total'     => $result['total'],
        'pages'     => $result['pages'],
        'counts'    => $counts,
        'last_sync' => $last,
    ] );
}

// Sync from Google Sheets → save to DB
function seo_outreach_ajax_sync_leads() {
    seo_outreach_verify();
    try {
        $sheets      = new SEO_Outreach_Sheets();
        $sheet_leads = $sheets->fetch_all_leads(); // fetch ALL rows including sent
        $db          = new SEO_Outreach_Database();
        $result      = $db->sync_leads( $sheet_leads );
        wp_send_json_success( [
            'message'  => "Synced {$result['total']} leads from Google Sheets. {$result['inserted']} new, {$result['updated']} updated.",
            'inserted' => $result['inserted'],
            'updated'  => $result['updated'],
            'total'    => $result['total'],
        ] );
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// Debug: show raw sheet data so user can see exactly what the plugin reads
function seo_outreach_ajax_debug_sync() {
    seo_outreach_verify();
    try {
        $sheets = new SEO_Outreach_Sheets();
        $all    = $sheets->fetch_all_leads();
        wp_send_json_success( [
            'count'  => count( $all ),
            'leads'  => $all,
            'sheet'  => SEO_Outreach_Settings::get('google_sheet_name'),
            'tab'    => SEO_Outreach_Settings::get('google_sheet_tab'),
        ] );
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function seo_outreach_ajax_delete_lead() {
    seo_outreach_verify();
    $id = intval( $_POST['id'] ?? 0 );
    if ( $id > 0 ) {
        ( new SEO_Outreach_Database() )->delete_lead( $id );
        wp_send_json_success( [ 'message' => 'Lead deleted.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
    }
}

function seo_outreach_ajax_poll_logs() {
    ob_start();
    seo_outreach_verify();
    $db = new SEO_Outreach_Database();
    $data = [ 'logs' => $db->get_recent_logs( 50 ), 'stats' => $db->get_run_stats_last_hour() ];
    ob_end_clean();
    wp_send_json_success( $data );
}

function seo_outreach_ajax_save_settings() {
    seo_outreach_verify();
    $text_fields = [
        'gemini_api_key','gemini_model','groq_api_key','groq_model','active_ai','pagespeed_api_key','google_service_account',
        'google_sheet_id','google_sheet_name','google_sheet_tab',
        'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name','smtp_from_email',
        'smtp_provider',
        'calendar_link','max_leads_per_run','delay_between_emails',
        'log_retention_days','log_auto_refresh','log_auto_refresh_secs',
        // ── Campaign delivery ──────────────────────────────────────
        'pdf_delivery_mode',
        // ── Outreach type toggles (sent as hidden inputs, value 1 or 0) ──
        'outreach_type_seo','outreach_type_ads','outreach_type_no_website',
    ];
    foreach ( $text_fields as $f ) {
        if ( isset( $_POST[ $f ] ) ) {
            SEO_Outreach_Settings::set( $f, sanitize_textarea_field( wp_unslash( $_POST[ $f ] ) ) );
        }
    }
    foreach ( [ 'notify_on_complete','notify_on_error','notify_daily_summary','log_auto_refresh' ] as $cb ) {
        SEO_Outreach_Settings::set( $cb, isset( $_POST[ $cb ] ) ? '1' : '0' );
    }
    // Save notification emails array
    if ( isset( $_POST['notification_emails'] ) && is_array( $_POST['notification_emails'] ) ) {
        SEO_Outreach_Settings::set_notification_emails( array_map( 'sanitize_email', wp_unslash( $_POST['notification_emails'] ) ) );
    }
    wp_send_json_success( [ 'message' => 'Settings saved.' ] );
}

function seo_outreach_ajax_save_schedule() {
    seo_outreach_verify();
    $db   = new SEO_Outreach_Database();
    $type = sanitize_text_field( $_POST['schedule_type'] ?? '' );
    $time = sanitize_text_field( $_POST['run_time'] ?? '09:00' );
    wp_send_json( SEO_Outreach_Scheduler::add_schedule( $db, $type, $time )
        ? [ 'success' => true,  'data' => [ 'message' => 'Schedule saved.' ] ]
        : [ 'success' => false, 'data' => [ 'message' => 'Invalid schedule type.' ] ]
    );
}

function seo_outreach_ajax_delete_schedule() {
    seo_outreach_verify();
    ( new SEO_Outreach_Database() )->delete_schedule( intval( $_POST['id'] ?? 0 ) );
    wp_send_json_success();
}

function seo_outreach_ajax_toggle_schedule() {
    seo_outreach_verify();
    ( new SEO_Outreach_Database() )->toggle_schedule( intval( $_POST['id'] ?? 0 ) );
    wp_send_json_success();
}

// ── API Test Handler ──────────────────────────────────────────────────────────
function seo_outreach_ajax_test_api() {
    seo_outreach_verify();
    $type = sanitize_text_field( $_POST['type'] ?? '' );

    // Read live field values sent from the browser (not saved DB values)
    // This lets users test before saving
    $gemini_key    = sanitize_text_field( $_POST['gemini_api_key']       ?? SEO_Outreach_Settings::get('gemini_api_key') );
    $groq_key      = sanitize_text_field( $_POST['groq_api_key']          ?? SEO_Outreach_Settings::get('groq_api_key') );
    $ps_key        = sanitize_text_field( $_POST['pagespeed_api_key']    ?? SEO_Outreach_Settings::get('pagespeed_api_key') );
    $sa_json       = sanitize_textarea_field( wp_unslash( $_POST['google_service_account'] ?? SEO_Outreach_Settings::get('google_service_account') ) );
    $smtp_host     = sanitize_text_field( $_POST['smtp_host']            ?? SEO_Outreach_Settings::get('smtp_host') );
    $smtp_port     = (int) ( $_POST['smtp_port']                         ?? SEO_Outreach_Settings::get('smtp_port', '587') );
    $smtp_user     = sanitize_text_field( $_POST['smtp_user']            ?? SEO_Outreach_Settings::get('smtp_user') );
    $smtp_pass     = sanitize_text_field( wp_unslash( $_POST['smtp_pass'] ?? SEO_Outreach_Settings::get('smtp_pass') ) );
    $smtp_from     = sanitize_email( $_POST['smtp_from_email']           ?? SEO_Outreach_Settings::get('smtp_from_email') );
    $notif_email   = sanitize_email( $_POST['notification_email_first']  ?? '' );
    if ( ! $notif_email ) {
        $emails = SEO_Outreach_Settings::get_notification_emails();
        $notif_email = $emails[0] ?? $smtp_from;
    }
    try {
        switch ( $type ) {

            case 'gemini':
                $key = $gemini_key;
                if ( ! $key ) throw new Exception( 'Gemini API key is empty. Enter your key and try again.' );
                $test_model = sanitize_text_field( $_POST['gemini_model'] ?? SEO_Outreach_Settings::get( 'gemini_model', 'gemini-2.5-flash' ) );
                $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . $test_model . ':generateContent?key=' . urlencode( $key );
                $resp = wp_remote_post( $url, [
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'body'    => wp_json_encode( [ 'contents' => [ [ 'parts' => [ [ 'text' => 'Say "OK" in one word.' ] ] ] ], 'generationConfig' => [ 'maxOutputTokens' => 5 ] ] ),
                    'timeout' => 20,
                ] );
                $code = wp_remote_retrieve_response_code( $resp );
                $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( $code !== 200 ) throw new Exception( $body['error']['message'] ?? "HTTP $code" );
                $reply = $body['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
                wp_send_json_success( [ 'message' => 'Gemini connected (' . esc_html($test_model) . '). Replied: "' . esc_html( trim( $reply ) ) . '"' ] );
                break;

            case 'groq':
                $key = $groq_key;
                if ( ! $key ) throw new Exception( 'Groq API key is empty. Enter your key and try again.' );
                $resp = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', [
                    'timeout' => 20,
                    'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ],
                    'body'    => wp_json_encode( [
                        'model'      => SEO_Outreach_Groq::MODEL,
                        'messages'   => [ [ 'role' => 'user', 'content' => 'Say OK in one word.' ] ],
                        'max_tokens' => 5,
                    ] ),
                ] );
                $code = wp_remote_retrieve_response_code( $resp );
                $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( $code !== 200 ) throw new Exception( $body['error']['message'] ?? "HTTP $code" );
                $reply = $body['choices'][0]['message']['content'] ?? 'No response';
                wp_send_json_success( [ 'message' => 'Groq connected. Model (' . SEO_Outreach_Groq::MODEL . ') replied: "' . esc_html( trim( $reply ) ) . '"' ] );
                break;

            case 'pagespeed':
                $key = $ps_key;
                if ( ! $key ) throw new Exception( 'PageSpeed API key is empty. Enter your key and try again.' );
                $url  = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query( [ 'url' => 'https://google.com', 'strategy' => 'mobile', 'key' => $key, 'category' => 'performance' ] );
                $resp = wp_remote_get( $url, [ 'timeout' => 30 ] );
                $code = wp_remote_retrieve_response_code( $resp );
                $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( $code !== 200 ) throw new Exception( $body['error']['message'] ?? "HTTP $code" );
                $score = round( ( $body['lighthouseResult']['categories']['performance']['score'] ?? 0 ) * 100 );
                wp_send_json_success( [ 'message' => "PageSpeed API working. Test score for google.com: {$score}/100" ] );
                break;

            case 'smtp':
                if ( ! $smtp_host ) throw new Exception( 'SMTP Host is empty. Fill in your SMTP settings first.' );
                if ( ! $smtp_from ) throw new Exception( 'From Email is empty. Fill in your SMTP settings first.' );
                $to = $notif_email ?: $smtp_from;
                // Temporarily override settings with live values for the test
                require_once SEO_OUTREACH_DIR . 'vendor/phpmailer/PHPMailer.php';
                require_once SEO_OUTREACH_DIR . 'vendor/phpmailer/SMTP.php';
                require_once SEO_OUTREACH_DIR . 'vendor/phpmailer/Exception.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtp_host;
                $mail->Port       = $smtp_port;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp_user;
                $mail->Password   = $smtp_pass;
                $mail->SMTPSecure = ($smtp_port === 465)
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->setFrom( $smtp_from, SEO_OUTREACH_BRAND );
                $mail->addAddress( $to );
                $mail->Subject = '[SEO Outreach Pro] SMTP Test';
                $mail->isHTML(false);
                $mail->Body = "Your SMTP configuration is working correctly.\n\nSent from SEO Outreach Pro plugin.";
                $mail->Timeout = 15;
                $mail->send();
                wp_send_json_success( [ 'message' => "Test email sent to {$to} — check your inbox!" ] );
                break;

            case 'service_account':
                if ( ! SEO_Outreach_Sheets::validate_json( $sa_json ) ) {
                    throw new Exception( 'Invalid JSON format. Make sure you pasted the complete Service Account key file.' );
                }
                $email = SEO_Outreach_Sheets::extract_email( $sa_json );
                // Temporarily save the JSON so list_accessible_sheets can use it
                SEO_Outreach_Settings::set( 'google_service_account', $sa_json );
                $sheets = new SEO_Outreach_Sheets();
                $list   = $sheets->list_accessible_sheets();
                wp_send_json_success( [ 'message' => "Service account valid for {$email}. Found " . count( $list ) . " accessible Google Sheet(s)." ] );
                break;

            default:
                throw new Exception( 'Unknown test type.' );
        }
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ── Sheets Discovery ──────────────────────────────────────────────────────────
function seo_outreach_ajax_fetch_sheets() {
    seo_outreach_verify();
    try {
        $sheets = new SEO_Outreach_Sheets();
        $list   = $sheets->list_accessible_sheets();
        wp_send_json_success( [ 'sheets' => $list ] );
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function seo_outreach_ajax_fetch_sheet_tabs() {
    seo_outreach_verify();
    $sheet_id = sanitize_text_field( $_POST['sheet_id'] ?? '' );
    if ( empty( $sheet_id ) ) wp_send_json_error( [ 'message' => 'No sheet ID provided.' ] );
    try {
        $sheets = new SEO_Outreach_Sheets();
        $tabs   = $sheets->list_sheet_tabs( $sheet_id );
        wp_send_json_success( [ 'tabs' => $tabs ] );
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ── Log Management ────────────────────────────────────────────────────────────
function seo_outreach_ajax_clear_logs() {
    seo_outreach_verify();
    $type      = sanitize_text_field( $_POST['clear_type'] ?? 'all' );
    $date_from = sanitize_text_field( $_POST['date_from']  ?? '' );
    $date_to   = sanitize_text_field( $_POST['date_to']    ?? '' );
    $db        = new SEO_Outreach_Database();
    $deleted   = $db->clear_logs( $type, $date_from, $date_to );
    wp_send_json_success( [ 'message' => "Deleted {$deleted} log entries.", 'deleted' => $deleted ] );
}

// ── PDF Download ──────────────────────────────────────────────────────────────
function seo_outreach_ajax_download_pdf() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'seo_outreach_nonce' ) ) wp_die( 'Unauthorized' );
    $file = sanitize_file_name( $_GET['file'] ?? '' );
    $path = SEO_Outreach_PDF_Generator::get_pdf_dir() . $file;
    if ( ! file_exists( $path ) ) wp_die( 'File not found.' );
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . $file . '"' );
    header( 'Content-Length: ' . filesize( $path ) );
    readfile( $path );
    exit;
}

// ── CSV Template Download ─────────────────────────────────────────────────────
function seo_outreach_ajax_download_csv() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'seo_outreach_csv' ) ) {
        wp_die( 'Unauthorized' );
    }
    // Force download headers
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="seo-outreach-leads-template.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'Website URL', 'Contact Email', 'Business Name', 'Status', 'Position', 'Name', 'Outreach type', 'Location', 'Services' ] );
    fputcsv( $out, [ 'https://abcplumbing.com',    'john@abcplumbing.com',  'ABC Plumbing Co',   '', 'Plumber',    'John',  'seo',        'Dallas, TX',    'Plumbing, emergency repairs, water heaters'      ] );
    fputcsv( $out, [ 'https://bestlawfirm.com',    'info@bestlawfirm.com',  'Best Law Firm LLC', '', 'Lawyer',     'Sarah', 'seo',        'Austin, TX',    'Personal injury, car accidents, slip and fall'   ] );
    fputcsv( $out, [ 'https://dentistkarachi.com', 'contact@dentistrx.com', 'DentiRx Karachi',   '', 'Dentist',    'Dr Ali','ads',        'Karachi',       'Dental implants, teeth whitening, braces'        ] );
    fputcsv( $out, [ 'https://sweetbakes.com',     'hello@sweetbakes.com',  'Sweet Bakes',       '', 'Bakery',     'Lisa',  'ads',        'Houston, TX',   'Custom cakes, cupcakes, wedding cakes'           ] );
    fputcsv( $out, [ '',                           'mike@mikescafe.com',    "Mike's Cafe",        '', 'Restaurant', 'Mike',  'no_website', 'Chicago, IL',   'Breakfast, lunch, coffee, catering'              ] );
    fputcsv( $out, [ '',                           'zara@zarasalon.com',    'Zara Salon',         '', 'Salon',      'Zara',  'no_website', 'London, UK',    'Haircuts, colouring, keratin treatment, bridal'  ] );
    fclose( $out );
    exit;
}
