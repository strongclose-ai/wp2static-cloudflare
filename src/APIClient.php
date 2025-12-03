<?php

namespace WP2Static;

use WP2StaticGuzzle\Client;

/**
 * API Client for R2 deployment
 */
class APIClient {
    /**
     * @var string
     */
    private $api_url;

    /**
     * @var string
     */
    private $jwt_token;

    /**
     * @var Client
     */
    private $client;

    public function __construct( string $api_url, string $jwt_token ) {
        $this->api_url = rtrim( $api_url, '/' );
        $this->jwt_token = $jwt_token;
        $this->client = new Client();
    }

    /**
     * Create a new site
     *
     * @param string $domain Site domain
     * @param string $site_name Site name
     * @return array<string,mixed> Response data
     * @throws WP2StaticException
     */
    public function createSite( string $domain, string $site_name ) : array {
        $response = $this->request(
            'POST',
            '/api/sites/create',
            [
                'auth' => $this->jwt_token,
                'domain' => $domain,
                'site_name' => $site_name,
            ]
        );

        return $response;
    }

    /**
     * List all sites
     *
     * @return array<string,mixed> Response data
     * @throws WP2StaticException
     */
    public function listSites() : array {
        $response = $this->request(
            'GET',
            '/api/sites/list',
            [
                'auth' => $this->jwt_token,
            ]
        );

        return $response;
    }

    /**
     * Update site with GZIP file
     *
     * @param string $site_id Site ID
     * @param string $gzip_path Path to GZIP file
     * @return array<string,mixed> Response data
     * @throws WP2StaticException
     */
    public function updateSite( string $site_id, string $gzip_path ) : array {
        if ( ! file_exists( $gzip_path ) ) {
            throw new WP2StaticException( 'GZIP file not found: ' . $gzip_path );
        }

        $response = $this->request(
            'POST',
            '/api/sites/update',
            [
                'auth' => $this->jwt_token,
                'site_id' => $site_id,
            ],
            $gzip_path
        );

        return $response;
    }

    /**
     * Make an API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array<string,string> $params Request parameters
     * @param string|null $file_path File path for upload
     * @return array<string,mixed> Response data
     * @throws WP2StaticException
     */
    private function request(
        string $method,
        string $endpoint,
        array $params = [],
        ?string $file_path = null
    ) : array {
        $url = $this->api_url . $endpoint;

        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->jwt_token,
                ],
            ];

            if ( $method === 'GET' ) {
                $options['query'] = $params;
            } elseif ( $file_path ) {
                // Multipart upload
                $multipart = [];
                foreach ( $params as $key => $value ) {
                    $multipart[] = [
                        'name' => $key,
                        'contents' => $value,
                    ];
                }

                $file_handle = fopen( $file_path, 'r' );
                if ( ! $file_handle ) {
                    throw new WP2StaticException( 'Failed to open file: ' . $file_path );
                }

                // Note: Guzzle will close the file handle after the request completes
                $multipart[] = [
                    'name' => 'file',
                    'contents' => $file_handle,
                    'filename' => basename( $file_path ),
                ];
                $options['multipart'] = $multipart;
            } else {
                $options['json'] = $params;
            }

            $response = $this->client->request( $method, $url, $options );
            $body = (string) $response->getBody();
            $data = json_decode( $body, true );

            if ( ! is_array( $data ) || json_last_error() !== JSON_ERROR_NONE ) {
                throw new WP2StaticException( 'Invalid JSON response from API' );
            }

            return $data;
        } catch ( WP2StaticException $e ) {
            throw $e;
        } catch ( \Exception $e ) {
            WsLog::l( 'API request failed: ' . $e->getMessage() );
            throw new WP2StaticException(
                'API request failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
