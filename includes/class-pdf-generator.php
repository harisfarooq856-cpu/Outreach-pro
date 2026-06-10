<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_PDF_Generator {

    public static function get_pdf_dir(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'seo-outreach-pdfs/';
    }

    public static function get_pdf_url( string $filename ): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . 'seo-outreach-pdfs/' . $filename;
    }

    public static function ensure_pdf_dir(): void {
        $dir = self::get_pdf_dir();
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
            // Allow public read so PDFs can be linked in emails
            file_put_contents( $dir . '.htaccess', "Options -Indexes\n" );
            file_put_contents( $dir . 'index.php', "<?php // Silence is golden\n" );
        } else {
            // Migrate old deny-all to allow-public if dir already existed
            $htaccess = $dir . '.htaccess';
            if ( file_exists( $htaccess ) && strpos( file_get_contents( $htaccess ), 'deny from all' ) !== false ) {
                file_put_contents( $htaccess, "Options -Indexes\n" );
            }
        }
    }

    public function generate( array $lead, array $ps, array $report ): string {
        if ( ! defined( 'FPDF_FONTPATH' ) ) {
            define( 'FPDF_FONTPATH', SEO_OUTREACH_DIR . 'vendor/fpdf/font/' );
        }
        require_once SEO_OUTREACH_DIR . 'vendor/fpdf/fpdf.php';

        self::ensure_pdf_dir();

        // Use inner class that has Footer() baked in
        $pdf = new class( 'P', 'mm', 'A4' ) extends FPDF_RR {
            public function Footer(): void {
                $this->SetY( -18 );
                $this->SetFillColor( 15, 23, 42 );
                $this->Rect( 0, $this->GetY(), 210, 20, 'F' );
                $this->SetTextColor( 148, 163, 184 );
                $this->SetFont( 'Arial', '', 8 );
                $this->SetX( 12 );
                $this->Cell( 93, 8, 'Haris Farooq | AI Driven SEO Expert', 0, 0, 'L' );
                $this->Cell( 93, 8, 'harisfarooqseo.online | ' . date( 'Y' ), 0, 0, 'R' );
            }
        };

        $pdf->SetAutoPageBreak( true, 25 );
        $pdf->AddPage();

        $raw_name = trim( $lead['business_name'] ?? '' );
        $raw_name = str_replace( [ '_', '-' ], ' ', $raw_name );
        $raw_name = ucwords( strtolower( $raw_name ) );
        if ( empty( $raw_name ) && ! empty( $lead['website'] ) ) {
            $host     = preg_replace( '/^https?:\/\/(www\.)?/', '', $lead['website'] );
            $host     = explode( '.', $host )[0];
            $raw_name = ucfirst( strtolower( str_replace( [ '_', '-' ], ' ', $host ) ) );
        }
        $business = ! empty( $raw_name ) ? $raw_name : 'Your Business';
        $website  = $lead['website'];
        $perf     = $ps['performance_score'];
        $seo_sc   = $ps['seo_score'];
        $bp       = $ps['best_practices'];

        // ── HEADER ────────────────────────────────────────────────────────────
        $pdf->SetFillColor( 15, 23, 42 );
        $pdf->Rect( 0, 0, 210, 38, 'F' );
        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->SetFont( 'Arial', 'B', 20 );
        $pdf->SetXY( 12, 8 );
        $pdf->Cell( 0, 9, 'Haris Farooq', 0, 1 );
        $pdf->SetFont( 'Arial', '', 11 );
        $pdf->SetXY( 12, 18 );
        $pdf->Cell( 0, 6, 'AI Driven SEO Expert  |  harisfarooqseo.online', 0, 1 );
        $pdf->SetFont( 'Arial', 'B', 13 );
        $pdf->SetXY( 12, 27 );
        $pdf->SetTextColor( 99, 202, 183 );
        $pdf->Cell( 0, 7, 'SEO AUDIT REPORT', 0, 1 );
        $pdf->SetFillColor( 99, 202, 183 );
        $pdf->Rect( 0, 38, 210, 1.5, 'F' );

        // ── META ──────────────────────────────────────────────────────────────
        $pdf->SetFillColor( 248, 250, 252 );
        $pdf->Rect( 0, 39.5, 210, 24, 'F' );
        $pdf->SetTextColor( 30, 41, 59 );
        $pdf->SetFont( 'Arial', 'B', 10 );
        $pdf->SetXY( 12, 43 );
        $pdf->Cell( 50, 5, 'Prepared for:', 0, 0 );
        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->Cell( 0, 5, $this->safe( $business ), 0, 1 );
        $pdf->SetFont( 'Arial', 'B', 10 );
        $pdf->SetXY( 12, 50 );
        $pdf->Cell( 50, 5, 'Website:', 0, 0 );
        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->Cell( 0, 5, $this->safe( $website ), 0, 1 );
        $pdf->SetFont( 'Arial', 'B', 10 );
        $pdf->SetXY( 120, 43 );
        $pdf->Cell( 40, 5, 'Date:', 0, 0 );
        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->Cell( 0, 5, date( 'F j, Y' ), 0, 1 );
        $pdf->SetFont( 'Arial', 'B', 10 );
        $pdf->SetXY( 120, 50 );
        $pdf->Cell( 40, 5, 'Analyst:', 0, 0 );
        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->Cell( 0, 5, 'Haris Farooq', 0, 1 );
        $pdf->SetDrawColor( 226, 232, 240 );
        $pdf->SetLineWidth( 0.3 );
        $pdf->Line( 12, 65, 198, 65 );
        $pdf->SetY( 68 );

        // ── SCORE CARDS ───────────────────────────────────────────────────────
        $this->section_title( $pdf, 'Performance Scores' );
        $y = $pdf->GetY();
        $this->score_card( $pdf, 12,  $y, 'Performance',    $perf );
        $this->score_card( $pdf, 74,  $y, 'SEO Score',      $seo_sc );
        $this->score_card( $pdf, 136, $y, 'Best Practices', $bp );
        $pdf->SetY( $y + 36 );
        $pdf->Ln( 3 );

        // ── CORE WEB VITALS ───────────────────────────────────────────────────
        $this->section_title( $pdf, 'Core Web Vitals' );
        $vitals = [
            'Largest Contentful Paint (LCP)' => $ps['lcp'],
            'Total Blocking Time (TBT)'      => $ps['tbt'],
            'Cumulative Layout Shift (CLS)'  => $ps['cls'],
            'First Contentful Paint (FCP)'   => $ps['fcp'],
            'Speed Index'                    => $ps['speed_index'],
        ];
        foreach ( $vitals as $label => $value ) {
            $this->vital_row( $pdf, $label, $value );
        }
        $pdf->Ln( 4 );

        // ── SECTION 1 ─────────────────────────────────────────────────────────
        $this->section_title( $pdf, '1. Executive Summary' );
        $this->body_text( $pdf, $report['executive_summary'] ?? '' );

        // ── SECTION 2 ─────────────────────────────────────────────────────────
        $this->section_title( $pdf, '2. Technical Performance Bottlenecks' );
        foreach ( $report['technical_issues'] ?? [] as $issue ) {
            $this->issue_block( $pdf, $issue['title'], $issue['description'], [ 220, 38, 38 ] );
        }

        // ── SECTION 3 ─────────────────────────────────────────────────────────
        $this->section_title( $pdf, '3. Content & Strategy Gaps' );
        foreach ( $report['content_gaps'] ?? [] as $gap ) {
            $this->issue_block( $pdf, $gap['title'], $gap['description'], [ 245, 158, 11 ] );
        }

        // ── SECTION 4 ─────────────────────────────────────────────────────────
        $this->section_title( $pdf, '4. Action Plan' );
        $action_text = $this->safe( $report['action_plan'] ?? '' );

        // Estimate how tall the green box needs to be
        // Average ~62 chars per wrapped line at 9pt in 178mm
        $estimated_lines = max( 1, (int) ceil( mb_strlen( $action_text ) / 62 ) );
        $box_height      = max( 32, ( $estimated_lines * 5 ) + 16 );

        // If the box won't fit above the footer margin, push to next page
        // Auto page break margin is 25mm, so usable bottom = 297-25 = 272
        $y = $pdf->GetY();
        if ( $y + $box_height > 272 ) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }

        $pdf->SetFillColor( 240, 253, 250 );
        $pdf->SetDrawColor( 99, 202, 183 );
        $pdf->SetLineWidth( 0.5 );
        $pdf->RoundedRect( 12, $y, 186, $box_height, 3, 'DF' );
        $pdf->SetXY( 16, $y + 4 );
        $pdf->SetTextColor( 15, 118, 110 );
        $pdf->SetFont( 'Arial', 'B', 10 );
        $pdf->Cell( 0, 5, 'How We Can Help', 0, 1 );
        $pdf->SetXY( 16, $pdf->GetY() + 1 );
        $pdf->SetFont( 'Arial', '', 9 );
        $pdf->SetTextColor( 30, 60, 55 );
        $pdf->MultiCell( 178, 5, $action_text, 0, 'L' );

        // ── SAVE ──────────────────────────────────────────────────────────────
        $filename = 'seo-audit-' . sanitize_file_name( strtolower( $business ) ) . '-' . date( 'Ymd-His' ) . '.pdf';
        $filepath = self::get_pdf_dir() . $filename;
        $pdf->Output( 'F', $filepath );

        return $filepath;
    }

    // ── Drawing helpers ───────────────────────────────────────────────────────

    private function section_title( $pdf, string $title ): void {
        $pdf->SetFillColor( 15, 23, 42 );
        $pdf->SetTextColor( 255, 255, 255 );
        $pdf->SetFont( 'Arial', 'B', 9 );
        $pdf->SetX( 12 );
        $pdf->Cell( 186, 6, '  ' . strtoupper( $title ), 0, 1, 'L', true );
        $pdf->Ln( 2 );
    }

    private function body_text( $pdf, string $text ): void {
        $pdf->SetTextColor( 51, 65, 85 );
        $pdf->SetFont( 'Arial', '', 9 );
        $pdf->SetX( 12 );
        $pdf->MultiCell( 186, 5, $this->safe( $text ), 0, 'L' );
        $pdf->Ln( 3 );
    }

    private function issue_block( $pdf, string $title, string $desc, array $color ): void {
        $y = $pdf->GetY();
        $pdf->SetFillColor( $color[0], $color[1], $color[2] );
        $pdf->Rect( 12, $y, 2, 16, 'F' );
        $pdf->SetTextColor( $color[0], $color[1], $color[2] );
        $pdf->SetFont( 'Arial', 'B', 9 );
        $pdf->SetXY( 16, $y + 1 );
        $pdf->Cell( 0, 4, $this->safe( $title ), 0, 1 );
        $pdf->SetTextColor( 71, 85, 105 );
        $pdf->SetFont( 'Arial', '', 8.5 );
        $pdf->SetX( 16 );
        $pdf->MultiCell( 180, 4.5, $this->safe( $desc ), 0, 'L' );
        $pdf->Ln( 3 );
    }

    private function score_card( $pdf, float $x, float $y, string $label, int $score ): void {
        if ( $score >= 90 )      { $r = 22;  $g = 163; $b = 74;  }
        elseif ( $score >= 50 )  { $r = 234; $g = 88;  $b = 12;  }
        else                     { $r = 220; $g = 38;  $b = 38;  }
        $pdf->SetFillColor( 248, 250, 252 );
        $pdf->SetDrawColor( 226, 232, 240 );
        $pdf->SetLineWidth( 0.3 );
        $pdf->RoundedRect( $x, $y, 58, 30, 3, 'DF' );
        $pdf->SetFont( 'Arial', 'B', 18 );
        $pdf->SetTextColor( $r, $g, $b );
        $pdf->SetXY( $x + 2, $y + 4 );
        $pdf->Cell( 54, 12, $score . '/100', 0, 1, 'C' );
        $pdf->SetFont( 'Arial', '', 8 );
        $pdf->SetTextColor( 100, 116, 139 );
        $pdf->SetXY( $x + 2, $y + 19 );
        $pdf->Cell( 54, 6, $label, 0, 1, 'C' );
    }

    private function vital_row( $pdf, string $label, string $value ): void {
        $pdf->SetFillColor( 248, 250, 252 );
        $pdf->SetTextColor( 51, 65, 85 );
        $pdf->SetFont( 'Arial', '', 9 );
        $pdf->SetX( 12 );
        $pdf->Cell( 120, 6, '  ' . $label, 0, 0, 'L', true );
        $pdf->SetFont( 'Arial', 'B', 9 );
        $pdf->SetTextColor( 30, 41, 59 );
        $pdf->Cell( 66, 6, $this->safe( $value ), 0, 1, 'C', true );
        $pdf->SetFont( 'Arial', '', 9 );
        $pdf->Ln( 0.5 );
    }

    private function safe( string $text ): string {
        // Strip UTF-8 non-breaking space (0xc2 0xa0) → regular space
        $text = str_replace( "\xc2\xa0", ' ', $text );
        // Strip other stray UTF-8 continuation bytes that windows-1252 can't handle
        $text = preg_replace( '/[\xc2-\xdf][\x80-\xbf]/', ' ', $text );
        return iconv( 'UTF-8', 'windows-1252//IGNORE', $text ) ?: $text;
    }
}
