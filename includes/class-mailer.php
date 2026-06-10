<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Mailer {

    private function get_phpmailer(): object {
        require_once SEO_OUTREACH_DIR . 'vendor/phpmailer/PHPMailer.php';
        require_once SEO_OUTREACH_DIR . 'vendor/phpmailer/SMTP.php';
        require_once SEO_OUTREACH_DIR . 'vendor/phpmailer/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer( true );
        $mail->isSMTP();

        $provider = SEO_Outreach_Settings::get( 'smtp_provider', 'gmail' );
        // Migrate legacy provider values (hostinger/cpanel) to 'other'
        if ( ! in_array( $provider, [ 'gmail', 'other' ], true ) ) {
            $provider = 'other';
        }

        if ( $provider === 'gmail' ) {
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 587;
        } else {
            $mail->Host = SEO_Outreach_Settings::get( 'smtp_host' );
            $mail->Port = (int) SEO_Outreach_Settings::get( 'smtp_port', '587' );
        }

        $mail->SMTPAuth   = true;
        $mail->Username   = SEO_Outreach_Settings::get( 'smtp_user' );
        $mail->Password   = SEO_Outreach_Settings::get( 'smtp_pass' );
        $mail->SMTPSecure = ( $mail->Port === 465 )
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        return $mail;
    }

    public function send_outreach( array $lead, string $subject, string $body, string $pdf_path = '', string $pdf_url = '' ): bool {
        $from_name  = SEO_Outreach_Settings::get( 'smtp_from_name', SEO_OUTREACH_BRAND );
        $from_email = SEO_Outreach_Settings::get( 'smtp_from_email' );
        if ( empty( SEO_Outreach_Settings::get( 'smtp_host' ) ) || empty( $from_email ) ) {
            throw new Exception( 'SMTP not configured.' );
        }
        $mail = $this->get_phpmailer();
        $mail->Timeout    = 30;
        $mail->SMTPDebug  = 0;
        $mail->setFrom( $from_email, $from_name );
        $mail->addAddress( $lead['email'], $lead['business_name'] ?? '' );
        $mail->addReplyTo( $from_email, $from_name );
        $mail->Subject = $subject;
        $mail->isHTML( true );
        $mail->Body    = $this->build_email_html( $body, $pdf_url );
        $mail->AltBody = strip_tags( $body );
        if ( ! empty( $pdf_path ) && file_exists( $pdf_path ) ) {
            $mail->addAttachment( $pdf_path, 'SEO-Audit-Report-' . date( 'Y-m-d' ) . '.pdf' );
        }
        try {
            $mail->send();
        } catch ( PHPMailer\PHPMailer\Exception $e ) {
            throw new Exception( 'SMTP send failed: ' . $e->getMessage() );
        }
        return true;
    }

    /**
     * Send notification to ALL configured recipient emails
     */
    public function send_notification( string $type, string $subject, string $body ): bool {
        $emails     = SEO_Outreach_Settings::get_notification_emails();
        $from_email = SEO_Outreach_Settings::get( 'smtp_from_email' );
        if ( empty( $emails ) || empty( $from_email ) ) return false;

        $db  = new SEO_Outreach_Database();
        $any = false;

        foreach ( $emails as $email ) {
            try {
                $mail = $this->get_phpmailer();
                $mail->setFrom( $from_email, SEO_OUTREACH_BRAND . ' [System]' );
                $mail->addAddress( $email );
                $mail->Subject = '[SEO Outreach] ' . $subject;
                $mail->isHTML( true );
                $mail->Body    = $this->build_notification_html( $type, $subject, $body );
                $mail->AltBody = strip_tags( $body );
                $mail->send();
                $db->log_notification( $type, $subject, $email, 'sent' );
                $any = true;
            } catch ( Exception $e ) {
                $db->log_notification( $type, $subject, $email, 'failed' );
            }
        }
        return $any;
    }

    /**
     * Test SMTP connection only — no email sent
     */
    public function test_smtp_connection(): bool {
        $mail = $this->get_phpmailer();
        $mail->SMTPDebug  = 0;
        $mail->Timeout    = 10;
        return $mail->SmtpConnect();
    }

    private function clean_text( string $text ): string {
        $patterns = [
            "\xc3\xa2\xe2\x80\x9e\xe2\x80\x9d" => ' - ',
            "\xc3\xa2\xe2\x80\x9e\xc2\x9d"     => ' - ',
            'â€"' => ' - ',
            'â€™' => "'",
            'â€˜' => "'",
            'â€œ' => '"',
            'â€'  => '"',
            "\xe2\x80\x93" => ' - ',
            "\xe2\x80\x94" => ' - ',
            "\xc2\xa0"     => ' ',
        ];
        $text = str_replace( array_keys( $patterns ), array_values( $patterns ), $text );
        $text = preg_replace( '/\xc3\xa2[\x80-\xff][\x80-\xff]/', ' - ', $text );
        $text = preg_replace( '/â[^\x20-\x7e]+/', ' - ', $text );
        return $text;
    }

    private function format_body_html( string $body ): string {
        // Strip sign-off line
        $body = preg_replace( '/\n?Haris Farooq\s*\|[^\n]*/i', '', $body );
        // Strip accidental section labels / instruction leakage
        $body = preg_replace( '/^(SECTION\s*\d[^\n]*|OPENING[^\n]*|PAIN POINTS[^\n]*|PARAGRAPH\s*\d[^\n]*|BLOCK\s*\d[^\n]*|INSIGHT[^\n]*|RESULTS[^\n]*|CTA[^\n]*|THE HOOK[^\n]*|WHAT I DO[^\n]*)$/mi', '', $body );
        // Convert any bullet/dash lines into plain prose (no lists in email)
        $body = preg_replace( '/^[\-•\*]\s+/m', '', $body );
        // Nuclear clean of encoding artefacts
        $body = $this->clean_text( $body );
        // Normalise line endings, collapse 3+ blank lines
        $body = preg_replace( '/\r\n|\r/', "\n", $body );
        $body = preg_replace( '/\n{3,}/', "\n\n", trim( $body ) );

        // Split into paragraphs on blank lines
        $paragraphs = preg_split( '/\n{2,}/', $body );
        $html = '';
        foreach ( $paragraphs as $para ) {
            $para = trim( $para );
            if ( $para === '' ) continue;
            // Join any hard-wrapped lines within a paragraph into one line
            $para = preg_replace( '/\n/', ' ', $para );
            $para = $this->clean_text( $para );
            $html .= '<p style="color:#374151;font-size:15px;line-height:1.8;margin:0 0 18px;">' . esc_html( $para ) . '</p>';
        }
        return $html;
    }

    private function build_email_html( string $body, string $pdf_url = '' ): string {
        $body_html = $this->format_body_html( $body );

        // ── View Report button (shown when pdf_url is provided) ───────────────
        $report_btn = '';
        if ( ! empty( $pdf_url ) ) {
            $report_btn = '
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 0;">
              <tr><td align="center">
                <a href="' . esc_url( $pdf_url ) . '" target="_blank"
                   style="display:inline-block;background:#f8fafc;color:#0f766e;text-decoration:none;
                          font-size:14px;font-weight:700;padding:11px 28px;border-radius:8px;
                          border:2px solid #0f766e;font-family:Arial,sans-serif;">
                  &#128196; View Your Free SEO Report
                </a>
              </td></tr>
            </table>';
        }

        // CTA button — schedule meeting or simple reply (no hardcoded paragraph — email body handles the CTA copy)
        $cal_link   = trim( SEO_Outreach_Settings::get( 'calendar_link', '' ) );
        $from_email = SEO_Outreach_Settings::get( 'smtp_from_email', 'hello@harisfarooqseo.online' );
        if ( ! empty( $cal_link ) ) {
            $cta_html = '
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
              <tr><td align="center">
                <a href="' . esc_url( $cal_link ) . '" target="_blank"
                   style="display:inline-block;background:linear-gradient(135deg,#0f766e,#0d9488);color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;font-family:Arial,sans-serif;letter-spacing:0.2px;">
                  &#128197; Schedule a Free 15-Min Call
                </a>
              </td></tr>
              <tr><td align="center" style="padding-top:10px;color:#94a3b8;font-size:12px;font-family:Arial,sans-serif;">
                No commitment &mdash; just a quick look at what\'s holding your site back
              </td></tr>
            </table>';
        } else {
            $cta_html = '
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
              <tr><td align="center">
                <a href="mailto:' . esc_attr( $from_email ) . '?subject=Re: SEO Audit for Your Website"
                   style="display:inline-block;background:linear-gradient(135deg,#0f172a,#1e3a5f);color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;font-family:Arial,sans-serif;letter-spacing:0.2px;">
                  &#9993; Reply to Discuss
                </a>
              </td></tr>
              <tr><td align="center" style="padding-top:10px;color:#94a3b8;font-size:12px;font-family:Arial,sans-serif;">
                Just reply &mdash; happy to walk you through it at a time that suits you
              </td></tr>
            </table>';
        }
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SEO Audit from Haris Farooq</title>
<!--[if mso]><style>table{border-collapse:collapse}td{font-family:Arial,sans-serif}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:24px 0;">
<tr><td align="center">

  <!-- Outer wrapper -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;margin:0 auto;">

    <!-- ── HEADER ── -->
    <tr><td style="background:linear-gradient(135deg,#0f172a 0%,#1a2e4a 100%);border-radius:12px 12px 0 0;padding:28px 32px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td width="100%" style="color:#fff;font-size:22px;font-weight:700;font-family:Arial,sans-serif;letter-spacing:-0.3px;">
            Haris Farooq
            <div style="color:#63cab7;font-size:11px;font-weight:400;margin-top:4px;letter-spacing:0.8px;text-transform:uppercase;">AI Driven SEO Expert &nbsp;&bull;&nbsp; harisfarooqseo.online</div>
          </td>
        </tr>
        <tr><td style="padding-top:16px;">
          <span style="background:rgba(99,202,183,0.18);border:1px solid rgba(99,202,183,0.45);border-radius:20px;padding:5px 14px;color:#63cab7;font-size:11px;font-weight:600;">&#128269; Free Website Audit Enclosed</span>
        </td></tr>
      </table>
    </td></tr>

    <!-- ── ALERT BAR ── -->
    <tr><td style="background:#dc2626;padding:12px 32px;">
      <span style="color:#fff;font-size:13px;font-weight:700;letter-spacing:0.2px;">&#9888; Action Required: Your website is losing customers right now</span>
    </td></tr>

    <!-- ── BODY ── -->
    <tr><td style="background:#ffffff;padding:36px 32px 28px;">
      <div style="font-family:Arial,sans-serif;">
        {$body_html}
      </div>

      {$report_btn}

      {$cta_html}

      <!-- Divider -->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 20px;">
        <tr><td style="border-top:1px solid #e2e8f0;height:1px;font-size:0;line-height:0;">&nbsp;</td></tr>
      </table>

      <!-- ── SIGNATURE (bottom only) ── -->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf9;border-radius:10px;border:1px solid #d1fae5;">
        <tr><td style="padding:16px 18px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr valign="top">
          <!-- Avatar -->
          <td width="64" style="padding-right:16px;">
            <img src="https://lh3.googleusercontent.com/d/1JQh7R7TD9hI0M0urnFZ8OoqEFA17rrSV"
                 alt="Haris Farooq"
                 width="58" height="58"
                 style="width:58px;height:58px;border-radius:50%;border:2px solid #63cab7;display:block;object-fit:cover;" />
          </td>
          <!-- Info -->
          <td style="vertical-align:top;">
            <div style="color:#0f172a;font-size:15px;font-weight:700;font-family:Arial,sans-serif;">Haris Farooq</div>
            <div style="color:#64748b;font-size:12px;margin-top:2px;font-family:Arial,sans-serif;">AI Driven SEO Expert</div>
            <div style="color:#64748b;font-size:12px;margin-top:2px;font-family:Arial,sans-serif;">harisfarooqseo.online</div>

            <!-- Links row -->
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:10px;">
              <tr>
                <td style="padding-right:14px;">
                  <a href="https://harisfarooqseo.online" target="_blank"
                     style="color:#0f766e;font-size:12px;font-weight:600;text-decoration:none;font-family:Arial,sans-serif;">&#127760; Website</a>
                </td>
                <td style="padding-right:14px;">
                  <a href="mailto:hello@harisfarooqseo.online"
                     style="color:#0f766e;font-size:12px;font-weight:600;text-decoration:none;font-family:Arial,sans-serif;">&#9993; Email</a>
                </td>
                <td style="padding-right:14px;">
                  <a href="https://www.linkedin.com/in/harisfarooqofficial/" target="_blank"
                     style="color:#0f766e;font-size:12px;font-weight:600;text-decoration:none;font-family:Arial,sans-serif;">&#128279; LinkedIn</a>
                </td>
                <td>
                  <a href="https://wa.me/923025164731" target="_blank"
                     style="color:#0f766e;font-size:12px;font-weight:600;text-decoration:none;font-family:Arial,sans-serif;">&#128172; WhatsApp</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        </table>
        </td></tr>
      </table>

      <!-- Disclaimer -->
      <div style="color:#94a3b8;font-size:10px;line-height:1.6;margin-top:16px;padding-top:12px;border-top:1px solid #f1f5f9;font-family:Arial,sans-serif;">
        The content of this email is confidential and intended for the recipient specified in message only. The SEO audit report attached is based on publicly available data from Google PageSpeed Insights.
      </div>

    </td></tr>

    <!-- ── FOOTER ── -->
    <tr><td style="background:#0f172a;border-radius:0 0 12px 12px;padding:16px 32px;text-align:center;">
      <a href="https://harisfarooqseo.online" style="color:#63cab7;font-size:12px;text-decoration:none;font-family:Arial,sans-serif;">harisfarooqseo.online</a>
      <div style="color:#475569;font-size:11px;margin-top:5px;font-family:Arial,sans-serif;">&copy; Haris Farooq | AI Driven SEO Expert</div>
    </td></tr>

  </table>
</td></tr>
</table>

</body>
</html>
HTML;
    }

    private function build_notification_html( string $type, string $subject, string $body ): string {
        $colors = [ 'complete' => '#22c55e', 'error' => '#ef4444', 'summary' => '#3b82f6', 'warning' => '#f59e0b' ];
        $color  = $colors[ $type ] ?? '#6366f1';
        $body_h = nl2br( esc_html( $body ) );
        $time   = current_time( 'mysql' );
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;background:#f1f5f9;padding:20px}.wrap{max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden}.top{background:{$color};padding:20px 28px;color:#fff}.top h2{margin:0;font-size:18px}.top p{margin:4px 0 0;font-size:12px;opacity:.85}.content{padding:28px;color:#334155;font-size:14px;line-height:1.7}.footer{background:#f8fafc;padding:12px 28px;font-size:11px;color:#94a3b8;text-align:center}</style>
</head><body><div class="wrap">
<div class="top"><h2>{$subject}</h2><p>SEO Outreach &mdash; {$time}</p></div>
<div class="content">{$body_h}</div>
<div class="footer">Haris Farooq AI SEO Outreach | harisfarooqseo.online</div>
</div></body></html>
HTML;
    }
}
