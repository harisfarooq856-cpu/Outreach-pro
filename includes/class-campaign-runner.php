<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Campaign_Runner {

    private SEO_Outreach_Database      $db;
    private SEO_Outreach_Sheets        $sheets;
    private SEO_Outreach_PageSpeed     $pagespeed;
    private SEO_Outreach_Gemini        $gemini;
    private SEO_Outreach_Groq          $groq;
    private SEO_Outreach_PDF_Generator $pdf;
    private SEO_Outreach_Mailer        $mailer;

    public function __construct() {
        $this->db        = new SEO_Outreach_Database();
        $this->sheets    = new SEO_Outreach_Sheets();
        $this->pagespeed = new SEO_Outreach_PageSpeed();
        $this->gemini    = new SEO_Outreach_Gemini();
        $this->groq      = new SEO_Outreach_Groq();
        $this->pdf       = new SEO_Outreach_PDF_Generator();
        $this->mailer    = new SEO_Outreach_Mailer();
    }

    /**
     * Generate content using active AI with automatic fallback.
     */
    private function ai_generate( array $lead, array $ps_data, string $ident, int $run_id ): array {
        $active = SEO_Outreach_Settings::get( 'active_ai', 'auto' );

        if ( $active === 'gemini' ) {
            $this->db->log( $ident, 'ai_generate', 'info', 'Generating content via Gemini (selected)...', $run_id );
            try {
                $result = $this->gemini->generate( $lead, $ps_data );
            } catch ( Exception $e ) {
                $this->db->log( $ident, 'ai_generate', 'error', 'Gemini failed: ' . $e->getMessage(), $run_id );
                throw $e;
            }
            $this->db->log( $ident, 'ai_generate', 'success', 'Content generated via Gemini.', $run_id );
            return $result;
        }

        if ( $active === 'groq' ) {
            $this->db->log( $ident, 'ai_generate', 'info', 'Generating content via Groq (selected)...', $run_id );
            try {
                $result = $this->groq->generate( $lead, $ps_data );
            } catch ( Exception $e ) {
                $this->db->log( $ident, 'ai_generate', 'error', 'Groq failed: ' . $e->getMessage(), $run_id );
                throw $e;
            }
            $this->db->log( $ident, 'ai_generate', 'success', 'Content generated via Groq.', $run_id );
            return $result;
        }

        // Auto: try Gemini first, fall back to Groq
        try {
            $this->db->log( $ident, 'ai_generate', 'info', 'Generating via Gemini (auto)...', $run_id );
            $result = $this->gemini->generate( $lead, $ps_data );
            $this->db->log( $ident, 'ai_generate', 'success', 'Content generated via Gemini.', $run_id );
            return $result;
        } catch ( Exception $e ) {
            $this->db->log( $ident, 'ai_generate', 'info', 'Gemini failed: ' . $e->getMessage() . ' — falling back to Groq...', $run_id );
        }

        $result = $this->groq->generate( $lead, $ps_data );
        $this->db->log( $ident, 'ai_generate', 'success', 'Content generated via Groq (fallback).', $run_id );
        return $result;
    }

    public function run( string $trigger = 'manual', int $max_leads = 0 ): array {
        if ( $max_leads <= 0 ) {
            $max_leads = (int) SEO_Outreach_Settings::get( 'max_leads_per_run', '10' );
        }
        $delay  = max( 1, (int) SEO_Outreach_Settings::get( 'delay_between_emails', '5' ) );
        $run_id = $this->db->start_run( $trigger );
        $stats  = [ 'processed' => 0, 'sent' => 0, 'failed' => 0, 'errors' => [] ];

        // PDF delivery mode: attach | link | both | none
        $pdf_mode = SEO_Outreach_Settings::get( 'pdf_delivery_mode', 'attach' );

        $this->db->log( 'SYSTEM', 'campaign_start', 'info', "Campaign started. Trigger: {$trigger}, Max leads: {$max_leads}, PDF mode: {$pdf_mode}", $run_id );

        try {
            $this->db->log( 'SYSTEM', 'fetch_leads', 'info', 'Fetching leads from Google Sheets...', $run_id );
            $leads = $this->sheets->fetch_leads();

            if ( empty( $leads ) ) {
                $this->db->log( 'SYSTEM', 'fetch_leads', 'info', 'No pending leads found.', $run_id );
                $this->db->finish_run( $run_id, $stats );
                return $stats;
            }

            $leads = array_slice( $leads, 0, $max_leads );
            $this->db->log( 'SYSTEM', 'fetch_leads', 'success', 'Found ' . count( $leads ) . ' pending leads.', $run_id );

            foreach ( $leads as $lead ) {
                $outreach_type = $lead['outreach_type'] ?? 'seo';
                $ident = ( $lead['website'] ?: 'no-website' ) . ' → ' . $lead['email']
                    . ( $lead['business_name'] ? ' (' . $lead['business_name'] . ')' : '' )
                    . " [{$outreach_type}]";

                $pdf_path = null;
                $pdf_url  = '';

                try {
                    $stats['processed']++;

                    // ── Check outreach type enabled ──────────────────────────
                    $type_setting_key = 'outreach_type_' . $outreach_type;
                    if ( SEO_Outreach_Settings::get( $type_setting_key, '1' ) !== '1' ) {
                        $this->db->log( $ident, 'skip', 'info', "Outreach type '{$outreach_type}' is disabled in Campaign Settings. Skipping.", $run_id );
                        $stats['processed']--;
                        continue;
                    }

                    // ── Route by outreach type ───────────────────────────────
                    if ( $outreach_type === 'no_website' ) {
                        // No PageSpeed, no PDF — email only
                        $this->db->log( $ident, 'route', 'info', 'no_website lead — skipping PageSpeed & PDF, email only.', $run_id );

                        $dummy_ps = [
                            'performance_score' => 0,
                            'seo_score'         => 0,
                            'lcp'               => 'N/A',
                            'lcp_sec'           => '0',
                            'fcp'               => 'N/A',
                            'cls'               => '0',
                            'tbt'               => 'N/A',
                            'opportunities'     => [],
                        ];

                        $generated = $this->ai_generate( $lead, $dummy_ps, $ident, $run_id );

                        // Send email — no PDF
                        $this->db->log( $ident, 'send_email', 'info', 'Sending email to ' . $lead['email'], $run_id );
                        $this->mailer->send_outreach( $lead, $generated['email_subject'], $generated['email_body'], '', '' );
                        $this->db->log( $ident, 'send_email', 'success', 'Email sent (no website — email only).', $run_id );

                    } else {
                        // seo or ads — full process

                        // Safety guard: if website is empty on a seo/ads lead, treat as no_website
                        if ( empty( $lead['website'] ) ) {
                            $this->db->log( $ident, 'route', 'info', "seo/ads lead has no website URL — falling back to email-only (no PageSpeed, no PDF).", $run_id );
                            $dummy_ps = [
                                'performance_score' => 0, 'seo_score' => 0,
                                'lcp' => 'N/A', 'lcp_sec' => '0', 'fcp' => 'N/A',
                                'cls' => '0', 'tbt' => 'N/A', 'opportunities' => [],
                            ];
                            $generated = $this->ai_generate( $lead, $dummy_ps, $ident, $run_id );
                            $this->db->log( $ident, 'send_email', 'info', 'Sending email to ' . $lead['email'], $run_id );
                            $this->mailer->send_outreach( $lead, $generated['email_subject'], $generated['email_body'], '', '' );
                            $this->db->log( $ident, 'send_email', 'success', 'Email sent (no website URL — email only).', $run_id );
                        } else {

                        // Phase 2: PageSpeed
                        $this->db->log( $ident, 'pagespeed_audit', 'info', 'Auditing: ' . $lead['website'], $run_id );
                        $ps_data = $this->pagespeed->audit( $lead['website'] );
                        $this->db->log( $ident, 'pagespeed_audit', 'success', 'Score: ' . $ps_data['performance_score'] . '/100', $run_id );

                        // Phase 3: AI
                        $generated = $this->ai_generate( $lead, $ps_data, $ident, $run_id );

                        // Phase 4: PDF (if mode is not 'none')
                        if ( $pdf_mode !== 'none' ) {
                            $this->db->log( $ident, 'pdf_generate', 'info', 'Creating branded PDF report...', $run_id );
                            $pdf_path = $this->pdf->generate( $lead, $ps_data, $generated['report'] );
                            $pdf_url  = SEO_Outreach_PDF_Generator::get_pdf_url( basename( $pdf_path ) );
                            $this->db->log( $ident, 'pdf_generate', 'success', 'PDF: ' . basename( $pdf_path ), $run_id );
                        } else {
                            $this->db->log( $ident, 'pdf_generate', 'info', 'PDF skipped (mode: none).', $run_id );
                        }

                        // Phase 5: Send email
                        // Decide what to pass based on pdf_mode
                        $attach_path = '';
                        $link_url    = '';

                        if ( $pdf_mode === 'attach' ) {
                            $attach_path = $pdf_path ?? '';
                            $link_url    = '';
                        } elseif ( $pdf_mode === 'link' ) {
                            $attach_path = '';
                            $link_url    = $pdf_url;
                        } elseif ( $pdf_mode === 'both' ) {
                            $attach_path = $pdf_path ?? '';
                            $link_url    = $pdf_url;
                        }
                        // 'none' — both empty

                        $this->db->log( $ident, 'send_email', 'info', 'Sending email to ' . $lead['email'] . " (PDF mode: {$pdf_mode})", $run_id );
                        $this->mailer->send_outreach( $lead, $generated['email_subject'], $generated['email_body'], $attach_path, $link_url );
                        $this->db->log( $ident, 'send_email', 'success', 'Email sent.', $run_id );
                        } // end: has website URL
                    }

                    // Phase 6: Update Sheet + local DB
                    $sent_status = 'Sent - ' . date( 'Y-m-d' );
                    $this->sheets->update_status( $lead['row_index'], $sent_status, $lead['status_col_letter'] ?? 'D' );
                    $this->db->wpdb_update_lead_status( $lead['email'], $lead['website'], $sent_status );
                    $this->db->log( $ident, 'update_sheet', 'success', 'Google Sheet + local DB updated.', $run_id );

                    $stats['sent']++;

                } catch ( Exception $e ) {
                    $msg = $e->getMessage();
                    $this->db->log( $ident, 'error', 'error', $msg, $run_id );
                    $stats['failed']++;
                    $stats['errors'][] = $ident . ': ' . $msg;

                    try {
                        $failed_status = 'Failed - ' . date( 'Y-m-d' );
                        $this->sheets->update_status( $lead['row_index'], $failed_status, $lead['status_col_letter'] ?? 'D' );
                        $this->db->wpdb_update_lead_status( $lead['email'], $lead['website'], $failed_status );
                    } catch ( Exception $ignored ) {}

                    if ( SEO_Outreach_Settings::get( 'notify_on_error', '1' ) === '1' ) {
                        $this->mailer->send_notification( 'error', 'Campaign Error',
                            "Lead: {$ident}\nError: {$msg}\nTime: " . current_time( 'mysql' ) );
                    }
                }

                if ( $stats['processed'] < count( $leads ) ) {
                    sleep( $delay );
                }

                // ── Batch pause (rate limit protection) ──────────────────────
                $batch_pause_enabled = SEO_Outreach_Settings::get( 'batch_pause_enabled', '0' );
                if ( $batch_pause_enabled === '1' ) {
                    $pause_after   = max( 1, (int) SEO_Outreach_Settings::get( 'batch_pause_after', '5' ) );
                    $pause_minutes = max( 1, (int) SEO_Outreach_Settings::get( 'batch_pause_minutes', '5' ) );
                    // Trigger pause after every Nth sent email (not on the very last one)
                    if ( $stats['sent'] > 0
                        && $stats['sent'] % $pause_after === 0
                        && $stats['sent'] < count( $leads )
                    ) {
                        $pause_secs = $pause_minutes * 60;
                        $this->db->log(
                            'SYSTEM', 'batch_pause', 'info',
                            "Batch pause: sent {$stats['sent']} emails. Waiting {$pause_minutes} minute(s) before continuing...",
                            $run_id
                        );
                        sleep( $pause_secs );
                        $this->db->log( 'SYSTEM', 'batch_pause', 'info', 'Batch pause complete. Resuming campaign.', $run_id );
                    }
                }
            }

        } catch ( Exception $e ) {
            $this->db->log( 'SYSTEM', 'fatal', 'error', $e->getMessage(), $run_id );
            $stats['errors'][] = 'Fatal: ' . $e->getMessage();
        }

        $this->db->finish_run( $run_id, $stats );

        if ( SEO_Outreach_Settings::get( 'notify_on_complete', '1' ) === '1' ) {
            $summary = "Campaign complete.\n\nProcessed: {$stats['processed']}\nSent: {$stats['sent']}\nFailed: {$stats['failed']}\nTrigger: {$trigger}\nTime: " . current_time( 'mysql' );
            if ( ! empty( $stats['errors'] ) ) {
                $summary .= "\n\nErrors:\n" . implode( "\n", $stats['errors'] );
            }
            $this->mailer->send_notification( 'complete', 'Campaign Completed', $summary );
        }

        return $stats;
    }
}
