<?php

namespace WP2Static;

/**
 * R2 API Deployer
 */
class R2Deployer {
    /**
     * Deploy to R2 via API
     *
     * @param string $processed_site_path Path to processed site
     * @throws WP2StaticException
     */
    public static function deploy( string $processed_site_path ) : void {
        WsLog::l( 'Starting R2 API deployment' );

        try {
            // Get configuration
            $api_url = CoreOptions::getValue( 'r2ApiUrl' );
            $jwt_token = CoreOptions::getValue( 'r2JwtToken' );
            $site_id = CoreOptions::getValue( 'r2SiteId' );
            $domain = CoreOptions::getValue( 'deploymentURL' );
            $site_name = CoreOptions::getValue( 'r2SiteName' );

            if ( ! $api_url || ! $jwt_token ) {
                throw new WP2StaticException(
                    'R2 API URL and JWT Token are required. Please configure them in settings.'
                );
            }

            // Decrypt JWT token if encrypted
            $jwt_token = CoreOptions::encrypt_decrypt( 'decrypt', $jwt_token );

            // Initialize API client
            $client = new APIClient( $api_url, $jwt_token );

            // Create site if no site_id exists
            if ( ! $site_id ) {
                if ( ! $site_name ) {
                    $site_name = get_bloginfo( 'name' );
                }

                WsLog::l( 'Creating new site: ' . $site_name );
                $response = $client->createSite( $domain, $site_name );

                if ( isset( $response['site_id'] ) ) {
                    $site_id = $response['site_id'];
                    CoreOptions::save( 'r2SiteId', $site_id );
                    WsLog::l( 'Site created with ID: ' . $site_id );
                } else {
                    throw new WP2StaticException( 'Failed to create site: Invalid response' );
                }
            }

            // Create archive
            WsLog::l( 'Creating deployment archive' );
            $upload_dir = wp_upload_dir();
            $output_path = $upload_dir['basedir'] . '/wp2static-deploy-' . time();
            $archive_path = ArchiveCreator::createArchive( $output_path );

            // Upload to API
            WsLog::l( 'Uploading archive to R2 API' );
            $response = $client->updateSite( $site_id, $archive_path );

            // Clean up archive
            if ( file_exists( $archive_path ) ) {
                unlink( $archive_path );
            }

            if ( isset( $response['status'] ) && $response['status'] === 200 ) {
                WsLog::l( 'Successfully deployed to R2 via API' );
            } else {
                throw new WP2StaticException(
                    'Deployment failed: ' . json_encode( $response )
                );
            }
        } catch ( \Exception $e ) {
            WsLog::l( 'R2 deployment error: ' . $e->getMessage() );
            throw $e;
        }
    }

    /**
     * Register deployment hooks
     */
    public static function registerHooks() : void {
        add_action(
            'wp2static_deploy',
            function( $processed_site_path, $deployer ) {
                if ( $deployer !== 'wp2static-addon-r2-api' ) {
                    return;
                }
                self::deploy( $processed_site_path );
            },
            10,
            2
        );
    }

    /**
     * Register options page
     */
    public static function registerOptionsPage() : void {
        $title = 'R2 API Deployment';
        $slug = 'wp2static-addon-r2-api';
        $parent_slug = 'wp2static';

        add_submenu_page(
            $parent_slug,
            $title,
            $title,
            'manage_options',
            $slug,
            [ 'WP2Static\R2Deployer', 'renderOptionsPage' ]
        );
    }

    /**
     * Render options page
     */
    public static function renderOptionsPage() : void {
        include WP2STATIC_PATH . 'views/r2-api-options-page.php';
    }

    /**
     * Save options
     */
    public static function saveOptions() : void {
        check_admin_referer( 'wp2static-r2-api-options' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        // Sanitize and save options
        $options_to_save = [
            'r2ApiUrl',
            'r2JwtToken',
            'r2SiteId',
            'r2SiteName',
            'r2ThemesToInclude',
            'r2ThemesToExclude',
            'r2PluginsToInclude',
            'r2PluginsToExclude',
        ];

        foreach ( $options_to_save as $option ) {
            $value = isset( $_POST[ $option ] ) ?
                sanitize_text_field( $_POST[ $option ] ) : '';

            // Encrypt JWT token
            if ( $option === 'r2JwtToken' && ! empty( $value ) ) {
                $value = CoreOptions::encrypt_decrypt( 'encrypt', $value );
            }

            // Handle textarea fields
            if ( in_array(
                $option,
                [
                    'r2ThemesToInclude',
                    'r2ThemesToExclude',
                    'r2PluginsToInclude',
                    'r2PluginsToExclude',
                ]
            ) ) {
                $value = isset( $_POST[ $option ] ) ?
                    sanitize_textarea_field( $_POST[ $option ] ) : '';
            }

            CoreOptions::save( $option, $value );
        }

        wp_safe_redirect(
            admin_url(
                'admin.php?page=wp2static-addon-r2-api&settings-updated=true'
            )
        );
        exit;
    }
}
