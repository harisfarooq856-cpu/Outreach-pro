<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Groq {

    const API_BASE = 'https://api.groq.com/openai/v1/chat/completions';
    const MODEL    = 'meta-llama/llama-4-scout-17b-16e-instruct'; // default model

    const MODELS = [
        'meta-llama/llama-4-scout-17b-16e-instruct'  => 'Llama 4 Scout 17B (Default)',
        'meta-llama/llama-4-maverick-17b-128e-instruct' => 'Llama 4 Maverick 17B',
        'llama-3.3-70b-versatile'                    => 'Llama 3.3 70B Versatile',
        'llama-3.1-70b-versatile'                    => 'Llama 3.1 70B Versatile',
        'llama-3.1-8b-instant'                       => 'Llama 3.1 8B Instant',
        'llama3-70b-8192'                             => 'Llama 3 70B',
        'llama3-8b-8192'                              => 'Llama 3 8B',
        'mixtral-8x7b-32768'                          => 'Mixtral 8x7B',
        'gemma2-9b-it'                                => 'Gemma 2 9B',
    ];

    public static function get_model(): string {
        $saved = SEO_Outreach_Settings::get( 'groq_model', '' );
        return ! empty( $saved ) ? $saved : self::MODEL;
    }

    public function generate( array $lead, array $ps_data ): array {
        $api_key = SEO_Outreach_Settings::get( 'groq_api_key' );
        if ( empty( $api_key ) ) {
            throw new Exception( 'Groq API Key not configured. Add it in Settings → API Keys.' );
        }

        $cal_link     = trim( SEO_Outreach_Settings::get( 'calendar_link', '' ) );
        $cal_link_str = ! empty( $cal_link )
            ? "or grab 15 mins here: {$cal_link}"
            : "or just reply and we can find a quick time to chat";

        $prompt = SEO_Outreach_Prompt::build( $lead, $ps_data, $cal_link_str );

        $body = wp_json_encode( [
            'model'    => self::get_model(),
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'You are a cold email copywriter. Respond ONLY with valid JSON — no markdown, no backticks, no explanation. The email_body must be plain conversational text with NO section headings or labels. Output raw JSON only.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature'     => 0.7,
            'max_tokens'      => 8192,
            'response_format' => [ 'type' => 'json_object' ],
        ] );

        $response = wp_remote_post( self::API_BASE, [
            'timeout' => 120,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Groq request failed: ' . $response->get_error_message() );
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $raw_body = str_replace( 'â€"', ' \u2014 ', $raw_body );
        $raw_body = preg_replace( '/\xc3\xa2[\x80-\xff][\x80-\xff]/', ' \u2014 ', $raw_body );
        $raw_body = preg_replace( '/â[^\x20-\x7e"\\\\]+/', ' \u2014 ', $raw_body );
        $data     = json_decode( $raw_body, true );

        if ( $code !== 200 ) {
            throw new Exception( 'Groq API error: ' . ( $data['error']['message'] ?? "HTTP $code" ) );
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        if ( empty( $text ) ) {
            throw new Exception( 'Groq returned empty response. Raw: ' . substr( $raw_body, 0, 300 ) );
        }

        $parsed = SEO_Outreach_Gemini::robust_json_decode( $text );
        if ( ! $parsed ) {
            throw new Exception( 'Could not parse Groq JSON. Raw: ' . substr( $text, 0, 300 ) );
        }

        if ( ! isset( $parsed['report'] ) ) {
            $parsed['report'] = [];
        }

        if ( empty( $parsed['email_subject'] ) || empty( $parsed['email_body'] ) ) {
            throw new Exception( 'Groq JSON missing required fields. Keys: ' . implode( ', ', array_keys( $parsed ) ) );
        }

        return $parsed;
    }
}
