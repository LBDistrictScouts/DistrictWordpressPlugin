<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

class CoordinatorIssueMessageValidator {
    public function validate( string $body ): array {
        $message = json_decode( $body, true );
        if ( ! is_array( $message ) || JSON_ERROR_NONE !== json_last_error() ) {
            throw new CoordinatorIssueValidationException( array( 'Message body must contain valid JSON: ' . json_last_error_msg() . '.' ) );
        }
        $required = array( 'schema_version', 'external_id', 'title', 'issue_date', 'issue_year', 'issue_month', 'pdf_url' );
        $allowed = array_merge( $required, array( 'description', 'preview_image_url' ) );
        $errors = array();
        $missing = array_diff( $required, array_keys( $message ) );
        $unexpected = array_diff( array_keys( $message ), $allowed );
        if ( $missing ) {
            $errors[] = 'Missing required properties: ' . implode( ', ', $missing ) . '.';
        }
        if ( $unexpected ) {
            $errors[] = 'Unexpected properties: ' . implode( ', ', $unexpected ) . '.';
        }
        if ( ! array_key_exists( 'schema_version', $message ) || '1.0' !== $message['schema_version'] ) {
            $errors[] = 'schema_version must be "1.0".';
        }
        if ( ! isset( $message['external_id'] ) || ! is_string( $message['external_id'] ) || ! preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $message['external_id'] ) ) {
            $errors[] = 'external ID (external_id) must be a UUID.';
        }
        if ( ! isset( $message['title'] ) || ! is_string( $message['title'] ) || '' === trim( $message['title'] ) || strlen( $message['title'] ) > 255 ) {
            $errors[] = 'title must be a non-empty string of at most 255 characters.';
        }
        if ( ! isset( $message['issue_year'] ) || ! is_int( $message['issue_year'] ) || $message['issue_year'] < 1900 || $message['issue_year'] > 9999 ) {
            $errors[] = 'issue_year must be an integer from 1900 to 9999.';
        }
        if ( ! isset( $message['issue_month'] ) || ! is_int( $message['issue_month'] ) || $message['issue_month'] < 1 || $message['issue_month'] > 12 ) {
            $errors[] = 'issue_month must be an integer from 1 to 12.';
        }
        $date = isset( $message['issue_date'] ) ? (string) $message['issue_date'] : '';
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
            $errors[] = 'issue_date must be a valid date in YYYY-MM-DD format.';
        }
        $url = isset( $message['pdf_url'] ) ? wp_parse_url( (string) $message['pdf_url'] ) : false;
        if ( ! is_array( $url ) || 'https' !== ( $url['scheme'] ?? '' ) || empty( $url['host'] ) ) {
            $errors[] = 'pdf_url must be an HTTPS URL.';
        }
        if ( isset( $message['description'] ) && ( ! is_string( $message['description'] ) || strlen( $message['description'] ) > 5000 ) ) {
            $errors[] = 'description must be a string of at most 5000 characters.';
        }
        if ( isset( $message['preview_image_url'] ) ) {
            $preview_url = is_string( $message['preview_image_url'] ) ? wp_parse_url( $message['preview_image_url'] ) : false;
            if ( ! is_array( $preview_url ) || 'https' !== ( $preview_url['scheme'] ?? '' ) || empty( $preview_url['host'] ) ) {
                $errors[] = 'preview image URL (preview_image_url) must be an HTTPS URL.';
            }
        }
        if ( $errors ) {
            throw new CoordinatorIssueValidationException( $errors );
        }
        return $message;
    }
}
