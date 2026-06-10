<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Gemini {

    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function generate( array $lead, array $ps_data ): array {
        $api_key = SEO_Outreach_Settings::get( 'gemini_api_key' );
        if ( empty( $api_key ) ) {
            throw new Exception( 'Gemini API Key not configured.' );
        }

        $cal_link     = trim( SEO_Outreach_Settings::get( 'calendar_link', '' ) );
        $cal_link_str = ! empty( $cal_link )
            ? "or grab 15 mins here: {$cal_link}"
            : "or just reply and we can find a quick time to chat";

        $prompt   = SEO_Outreach_Prompt::build( $lead, $ps_data, $cal_link_str );
        $model    = SEO_Outreach_Settings::get( 'gemini_model', 'gemini-2.5-flash' );
        $endpoint = self::API_BASE . $model . ':generateContent?key=' . urlencode( $api_key );

        $body = wp_json_encode( [
            'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [
                'temperature'      => 0.7,
                'maxOutputTokens'  => 8192,
                'responseMimeType' => 'application/json',
            ],
            'safetySettings' => [
                [ 'category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE' ],
                [ 'category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE' ],
                [ 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE' ],
                [ 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE' ],
            ],
        ] );

        // Retry up to 3 times on rate limit / overload errors
        $max_attempts = 3;
        $last_error   = '';

        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {

            $response = wp_remote_post( $endpoint, [
                'timeout' => 120,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => $body,
            ] );

            if ( is_wp_error( $response ) ) {
                throw new Exception( 'Gemini request failed: ' . $response->get_error_message() );
            }

            $code     = wp_remote_retrieve_response_code( $response );
            $raw_body = wp_remote_retrieve_body( $response );
            $raw_body = str_replace( 'â€"', ' \u2014 ', $raw_body );
            $raw_body = preg_replace( '/\xc3\xa2[\x80-\xff][\x80-\xff]/', ' \u2014 ', $raw_body );
            $raw_body = preg_replace( '/â[^\x20-\x7e"\\\\]+/', ' \u2014 ', $raw_body );
            $data     = json_decode( $raw_body, true );

            // Rate limit / overload — wait and retry
            if ( $code === 429 || $code === 503 ) {
                $err_msg = $data['error']['message'] ?? "HTTP $code";
                $last_error = "Gemini rate limit/overload (attempt $attempt): $err_msg";
                if ( $attempt < $max_attempts ) {
                    sleep( $attempt * 5 ); // 5s, 10s between retries
                    continue;
                }
                throw new Exception( $last_error . ' — all retries exhausted.' );
            }

            if ( $code !== 200 ) {
                throw new Exception( 'Gemini API error: ' . ( $data['error']['message'] ?? "HTTP $code" ) );
            }

            $finish_reason = $data['candidates'][0]['finishReason'] ?? '';
            if ( in_array( $finish_reason, [ 'SAFETY', 'RECITATION', 'BLOCKED' ], true ) ) {
                throw new Exception( 'Gemini blocked the response (finishReason: ' . $finish_reason . ').' );
            }

            // MAX_TOKENS means response was truncated — retry with same prompt
            if ( $finish_reason === 'MAX_TOKENS' && $attempt < $max_attempts ) {
                $last_error = "Gemini response truncated (MAX_TOKENS) on attempt $attempt — retrying...";
                sleep( 2 );
                continue;
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ( empty( $text ) ) {
                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                foreach ( $parts as $part ) {
                    if ( ! empty( $part['text'] ) ) { $text = $part['text']; break; }
                }
            }
            if ( empty( $text ) ) {
                throw new Exception( 'Gemini returned empty response. Raw: ' . substr( $raw_body, 0, 300 ) );
            }

            return $this->parse_and_validate( $text );
        }

        throw new Exception( 'Gemini failed after ' . $max_attempts . ' attempts. Last error: ' . $last_error );
    }

    private function parse_and_validate( string $text ): array {
        $parsed = self::robust_json_decode( $text );
        if ( ! $parsed ) {
            throw new Exception( 'Could not parse Gemini JSON. Raw: ' . substr( $text, 0, 300 ) );
        }

        // no_website type only needs email_subject + email_body
        if ( ! isset( $parsed['report'] ) ) {
            $parsed['report'] = [];
        }

        if ( empty( $parsed['email_subject'] ) || empty( $parsed['email_body'] ) ) {
            throw new Exception( 'Gemini JSON missing email_subject or email_body. Keys: ' . implode( ', ', array_keys( $parsed ) ) );
        }

        return $parsed;
    }

    public static function robust_json_decode( string $text ): ?array {
        // Strip <think>...</think> blocks (Gemini thinking models leak these)
        $text = preg_replace( '/<think>.*?<\/think>/si', '', $text );

        // Fix common encoding artefacts
        $text = str_replace(
            [ 'â€"', 'â€"', "â\x80\x9c", "â\x80\x9d", "â\x80\x99", "â\x80\x98" ],
            [ ' - ',  ' - ',  '"',         '"',          "'",          "'"         ],
            $text
        );
        $text = preg_replace( '/â[^\x20-\x7e]{1,4}/', ' - ', $text );
        $text = str_replace( [ "\xe2\x80\x93", "\xe2\x80\x94" ], ' - ', $text );

        // Strip markdown code fences
        $cleaned = preg_replace( '/```json\s*/i', '', $text );
        $cleaned = preg_replace( '/```\s*/i', '', $cleaned );
        $cleaned = trim( $cleaned );

        // Direct parse
        $parsed = json_decode( $cleaned, true );
        if ( is_array( $parsed ) ) return $parsed;

        // Extract first JSON object if there's preamble text
        $start = strpos( $cleaned, '{' );
        $end   = strrpos( $cleaned, '}' );
        if ( $start !== false && $end !== false && $end > $start ) {
            $parsed = json_decode( substr( $cleaned, $start, $end - $start + 1 ), true );
            if ( is_array( $parsed ) ) return $parsed;
        }

        // Last resort: fix unescaped newlines inside JSON string values
        $fixed = preg_replace_callback(
            '/"((?:[^"\\\\]|\\\\.)*)"/s',
            function( $m ) {
                $inner = $m[1];
                $inner = str_replace( [ "\n", "\r", "\t" ], [ '\n', '\r', '\t' ], $inner );
                return '"' . $inner . '"';
            },
            $cleaned
        );
        $parsed = json_decode( $fixed, true );
        if ( is_array( $parsed ) ) return $parsed;

        return null;
    }
}
