<?php
defined( 'ABSPATH' ) || exit;

class SEO_Outreach_Sheets {

    const SHEETS_BASE = 'https://sheets.googleapis.com/v4/spreadsheets/';
    const DRIVE_BASE  = 'https://www.googleapis.com/drive/v3/files';
    const TOKEN_URL   = 'https://oauth2.googleapis.com/token';

    private string $access_token = '';

    public static function extract_email( string $json ): string {
        $sa = json_decode( $json, true );
        return $sa['client_email'] ?? '';
    }

    public static function validate_json( string $json ): bool {
        $sa = json_decode( $json, true );
        return is_array( $sa )
            && isset( $sa['type'] )
            && $sa['type'] === 'service_account'
            && isset( $sa['client_email'], $sa['private_key'] );
    }

    public function list_accessible_sheets(): array {
        $token = $this->get_access_token( [
            'https://www.googleapis.com/auth/drive.readonly',
            'https://www.googleapis.com/auth/spreadsheets',
        ] );

        $url = add_query_arg( [
            'q'       => "mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
            'fields'  => 'files(id,name,modifiedTime)',
            'orderBy' => 'modifiedTime desc',
            'pageSize'=> 50,
        ], self::DRIVE_BASE );

        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Drive API error: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            if ( strpos( $msg, 'Drive API has not been used' ) !== false || $code === 403 ) {
                throw new Exception( 'Google Drive API is not enabled. Please enable it in Google Cloud Console.' );
            }
            throw new Exception( 'Drive API error: ' . $msg );
        }

        return $body['files'] ?? [];
    }

    public function list_sheet_tabs( string $sheet_id ): array {
        $token = $this->get_access_token();
        $url   = self::SHEETS_BASE . $sheet_id . '?fields=sheets.properties';

        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Sheets API error: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            throw new Exception( 'Sheets API error: ' . ( $body['error']['message'] ?? "HTTP $code" ) );
        }

        $tabs = [];
        foreach ( $body['sheets'] ?? [] as $sheet ) {
            $tabs[] = [
                'id'    => $sheet['properties']['sheetId'],
                'title' => $sheet['properties']['title'],
            ];
        }
        return $tabs;
    }

    public function fetch_all_leads(): array {
        $sheet_id = SEO_Outreach_Settings::get( 'google_sheet_id' );
        $tab      = SEO_Outreach_Settings::get( 'google_sheet_tab', 'Sheet1' );
        if ( empty( $sheet_id ) ) throw new Exception( 'No Google Sheet selected.' );

        $token    = $this->get_access_token();
        // Fetch up to column H now (added Location column)
        $range    = urlencode( $tab . '!A:I' );
        $url      = self::SHEETS_BASE . $sheet_id . '/values/' . $range;

        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            'timeout' => 30,
        ] );
        if ( is_wp_error( $response ) ) throw new Exception( 'Sheets request failed: ' . $response->get_error_message() );

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) throw new Exception( 'Sheets API error: ' . ( $body['error']['message'] ?? "HTTP $code" ) );

        $rows = $body['values'] ?? [];
        if ( empty( $rows ) ) return [];

        $header = array_map( 'strtolower', array_map( 'trim', $rows[0] ) );
        array_shift( $rows );

        $col_website  = $this->find_col( $header, ['website url','website','url','domain'] );
        $col_website  = $col_website === -1 ? 0 : $col_website;
        $col_email    = $this->find_col( $header, ['contact email','email','mail'] );
        $col_email    = $col_email === -1 ? 1 : $col_email;
        $col_business = $this->find_col( $header, ['business name','business','company'] );
        $col_status   = $this->find_col( $header, ['status'] );
        if ( $col_status === -1 ) {
            $col_status = max( $col_website, $col_email, ( $col_business !== -1 ? $col_business : 0 ) ) + 1;
        }

        $leads = [];
        foreach ( $rows as $i => $row ) {
            $website  = trim( $row[ $col_website ] ?? '' );
            $email    = trim( $row[ $col_email   ] ?? '' );
            $business = ( $col_business !== -1 ) ? trim( $row[ $col_business ] ?? '' ) : '';
            $status   = trim( $row[ $col_status  ] ?? '' );
            // Email is the only required field — website can be blank for no_website leads
            if ( empty( $email ) ) continue;
            $leads[] = [
                'row_index'     => $i + 2,
                'website'       => $website,
                'email'         => $email,
                'business_name' => $business,
                'status'        => $status,
            ];
        }
        return $leads;
    }

    /**
     * Fetch pending leads — reads columns A:H
     * Sheet layout:
     *   A = Website URL
     *   B = Contact Email
     *   C = Business Name
     *   D = Status
     *   E = Position
     *   F = Name
     *   G = Outreach Type  (seo | ads | no_website)
     *   H = Location / City
     */
    public function fetch_leads(): array {
        $sheet_id = SEO_Outreach_Settings::get( 'google_sheet_id' );
        $tab      = SEO_Outreach_Settings::get( 'google_sheet_tab', 'Sheet1' );

        if ( empty( $sheet_id ) ) {
            throw new Exception( 'No Google Sheet selected. Go to Settings → Google Sheets.' );
        }

        $token = $this->get_access_token();
        $range = urlencode( $tab . '!A:I' );
        $url   = self::SHEETS_BASE . $sheet_id . '/values/' . $range;

        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Sheets request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            throw new Exception( 'Sheets API error: ' . ( $body['error']['message'] ?? "HTTP $code" ) );
        }

        $rows = $body['values'] ?? [];
        if ( empty( $rows ) ) return [];

        $header = array_map( 'strtolower', array_map( 'trim', $rows[0] ) );
        array_shift( $rows );

        // Detect columns by header name, fall back to fixed positions
        $col_website  = $this->find_col( $header, ['website url','website','url','domain'] );
        $col_website  = $col_website === -1 ? 0 : $col_website;

        $col_email    = $this->find_col( $header, ['contact email','email','mail'] );
        $col_email    = $col_email === -1 ? 1 : $col_email;

        $col_business = $this->find_col( $header, ['business name','business','company'] );
        $col_business = $col_business === -1 ? 2 : $col_business;

        $col_status   = $this->find_col( $header, ['status'] );
        $col_status   = $col_status === -1 ? 3 : $col_status;

        $col_position = $this->find_col( $header, ['position','role','title'] );
        $col_position = $col_position === -1 ? 4 : $col_position;

        $col_name     = $this->find_col( $header, ['name','contact name','first name','fname'] );
        $col_name     = $col_name === -1 ? 5 : $col_name;

        $col_outreach = $this->find_col( $header, ['outreach type','outreach','type','service type'] );
        $col_outreach = $col_outreach === -1 ? 6 : $col_outreach;

        $col_location = $this->find_col( $header, ['location','city','area','region'] );
        $col_location = $col_location === -1 ? 7 : $col_location;

        $col_services = $this->find_col( $header, ['services','service','offerings','products'] );
        $col_services = $col_services === -1 ? 8 : $col_services;

        $status_col_letter = chr( ord('A') + $col_status );

        $leads = [];
        foreach ( $rows as $i => $row ) {
            $website       = trim( $row[ $col_website  ] ?? '' );
            $email         = trim( $row[ $col_email    ] ?? '' );
            $status        = trim( $row[ $col_status   ] ?? '' );
            $business      = trim( $row[ $col_business ] ?? '' );
            $position      = trim( $row[ $col_position ] ?? '' );
            $contact_name  = trim( $row[ $col_name     ] ?? '' );
            $outreach_type = strtolower( trim( $row[ $col_outreach ] ?? 'seo' ) );
            $location      = trim( $row[ $col_location ] ?? '' );
            $services      = trim( $row[ $col_services ] ?? '' );

            // Email is the only required field — website can be blank for no_website leads
            if ( empty( $email ) ) continue;
            if ( ! empty( $status ) ) continue; // already processed

            // Normalise outreach type
            if ( ! in_array( $outreach_type, [ 'seo', 'ads', 'no_website' ], true ) ) {
                $outreach_type = 'seo'; // default
            }

            $leads[] = [
                'row_index'         => $i + 2,
                'website'           => $website,
                'email'             => $email,
                'business_name'     => $business,
                'status'            => $status,
                'position'          => $position,
                'contact_name'      => $contact_name,
                'outreach_type'     => $outreach_type,
                'location'          => $location,
                'services'          => $services,
                'status_col_letter' => $status_col_letter,
            ];
        }
        return $leads;
    }

    public function update_status( int $row_index, string $status, string $col_letter = 'D' ): bool {
        $sheet_id = SEO_Outreach_Settings::get( 'google_sheet_id' );
        $tab      = SEO_Outreach_Settings::get( 'google_sheet_tab', 'Sheet1' );
        $token    = $this->get_access_token();
        $range    = urlencode( $tab . "!{$col_letter}{$row_index}" );
        $url      = self::SHEETS_BASE . $sheet_id . '/values/' . $range . '?valueInputOption=RAW';

        $response = wp_remote_request( $url, [
            'method'  => 'PUT',
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'values' => [ [ $status ] ] ] ),
            'timeout' => 30,
        ] );

        return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
    }

    private function find_col( array $header, array $names, int $default = -1 ): int {
        foreach ( $names as $name ) {
            $idx = array_search( strtolower( trim( $name ) ), $header );
            if ( $idx !== false ) return (int) $idx;
        }
        foreach ( $header as $i => $h ) {
            foreach ( $names as $name ) {
                $words = explode( ' ', strtolower( trim( $name ) ) );
                foreach ( $words as $word ) {
                    if ( strlen( $word ) > 2 && str_contains( $h, $word ) ) return $i;
                }
            }
        }
        return $default;
    }

    private function get_access_token( array $scopes = [] ): string {
        if ( $this->access_token ) return $this->access_token;

        if ( empty( $scopes ) ) {
            $scopes = [
                'https://www.googleapis.com/auth/spreadsheets',
                'https://www.googleapis.com/auth/drive.readonly',
            ];
        }

        $sa_json = SEO_Outreach_Settings::get( 'google_service_account' );
        if ( empty( $sa_json ) ) throw new Exception( 'Google Service Account JSON not configured.' );

        $sa = json_decode( $sa_json, true );
        if ( ! $sa ) throw new Exception( 'Invalid Service Account JSON.' );

        $now     = time();
        $header  = $this->b64url( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
        $payload = $this->b64url( wp_json_encode( [
            'iss'   => $sa['client_email'],
            'scope' => implode( ' ', $scopes ),
            'aud'   => self::TOKEN_URL,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ] ) );

        $sign_input = $header . '.' . $payload;
        $key        = openssl_pkey_get_private( $sa['private_key'] );
        if ( ! $key ) throw new Exception( 'Invalid private key in Service Account JSON.' );

        openssl_sign( $sign_input, $signature, $key, 'SHA256' );
        $jwt = $sign_input . '.' . $this->b64url( $signature );

        $response = wp_remote_post( self::TOKEN_URL, [
            'body'    => [ 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) throw new Exception( 'Token request failed: ' . $response->get_error_message() );

        $token_data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $token_data['access_token'] ) ) {
            throw new Exception( 'Failed to get access token: ' . wp_remote_retrieve_body( $response ) );
        }

        $this->access_token = $token_data['access_token'];
        return $this->access_token;
    }

    private function b64url( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }
}
