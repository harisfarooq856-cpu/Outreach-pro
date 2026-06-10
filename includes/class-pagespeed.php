<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_PageSpeed {

    const API_BASE = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function audit( string $url ): array {
        $api_key = SEO_Outreach_Settings::get( 'pagespeed_api_key' );
        if ( empty( $api_key ) ) {
            throw new Exception( 'PageSpeed API Key not configured.' );
        }
        if ( ! preg_match( '/^https?:\/\//', $url ) ) {
            $url = 'https://' . $url;
        }

        $endpoint = add_query_arg( [
            'url'      => $url,
            'strategy' => 'mobile',
            'key'      => $api_key,
            'category' => [ 'performance', 'seo', 'best-practices' ],
        ], self::API_BASE );

        $response = wp_remote_get( $endpoint, [ 'timeout' => 60, 'sslverify' => true ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'PageSpeed request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            throw new Exception( 'PageSpeed API error: ' . ( $data['error']['message'] ?? "HTTP $code" ) );
        }

        return $this->parse( $data );
    }

    private function parse( array $data ): array {
        $cats   = $data['lighthouseResult']['categories'] ?? [];
        $audits = $data['lighthouseResult']['audits']    ?? [];

        $perf = round( ( $cats['performance']['score']     ?? 0 ) * 100 );
        $seo  = round( ( $cats['seo']['score']             ?? 0 ) * 100 );
        $bp   = round( ( $cats['best-practices']['score']  ?? 0 ) * 100 );

        $lcp    = $audits['largest-contentful-paint']['displayValue'] ?? 'N/A';
        $fcp    = $audits['first-contentful-paint']['displayValue']   ?? 'N/A';
        $cls    = $audits['cumulative-layout-shift']['displayValue']  ?? 'N/A';
        $tbt    = $audits['total-blocking-time']['displayValue']      ?? 'N/A';
        $si     = $audits['speed-index']['displayValue']              ?? 'N/A';
        $lcpMs  = $audits['largest-contentful-paint']['numericValue'] ?? 0;
        $lcpSec = round( $lcpMs / 1000, 1 );

        $oppIds = [
            'render-blocking-resources'  => 'Render-Blocking Resources',
            'uses-optimized-images'      => 'Unoptimized Images',
            'uses-responsive-images'     => 'Improperly Sized Images',
            'offscreen-images'           => 'Offscreen Images',
            'uses-text-compression'      => 'Text Compression Missing',
            'unused-javascript'          => 'Unused JavaScript',
            'unused-css-rules'           => 'Unused CSS',
        ];
        $opportunities = [];
        foreach ( $oppIds as $id => $label ) {
            $a = $audits[ $id ] ?? null;
            if ( $a && ( $a['score'] ?? 1 ) < 0.9 ) {
                $opportunities[] = [
                    'label'   => $label,
                    'savings' => $a['displayValue'] ?? '',
                    'score'   => round( ( $a['score'] ?? 0 ) * 100 ),
                ];
            }
        }

        return [
            'url'               => $data['lighthouseResult']['finalUrl'] ?? $data['id'] ?? 'Unknown',
            'performance_score' => $perf,
            'seo_score'         => $seo,
            'best_practices'    => $bp,
            'lcp'               => $lcp,
            'lcp_sec'           => $lcpSec,
            'fcp'               => $fcp,
            'cls'               => $cls,
            'tbt'               => $tbt,
            'speed_index'       => $si,
            'opportunities'     => $opportunities,
            'fetch_time'        => current_time( 'mysql' ),
        ];
    }
}
