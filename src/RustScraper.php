<?php

namespace WP2Static;

/**
 * RustScraper - High-performance scraper using Rust binary
 *
 * This class provides an interface to execute the Rust-based scraper
 * which converts WordPress pages to Markdown format.
 */
class RustScraper {

    /**
     * Path to the Rust scraper binary
     *
     * @var string
     */
    private static $binary_path;

    /**
     * Execute the Rust scraper
     *
     * @param string $static_site_path Path where static site files are stored
     * @param string $crawler_slug The crawler slug identifier
     * @return void
     * @throws WP2StaticException
     */
    public static function wp2staticCrawl( string $static_site_path, string $crawler_slug ) : void {
        if ( 'rust-scraper' !== $crawler_slug ) {
            return;
        }

        WsLog::l( 'Starting Rust scraper for markdown conversion' );

        // Get the base URL of the site
        $base_url = SiteInfo::getURL( 'site' );

        // Prepare output directory for markdown files
        $output_dir = $static_site_path . '/markdown';
        if ( ! file_exists( $output_dir ) ) {
            wp_mkdir_p( $output_dir );
        }

        // Build the command to execute the Rust scraper
        $binary = self::getBinaryPath();
        
        // Validate binary path exists and is executable
        if ( ! file_exists( $binary ) ) {
            WsLog::l( 'Rust scraper binary not found. Building from source...' );
            self::buildBinary();
        }

        // Check if binary exists after build attempt
        if ( ! file_exists( $binary ) ) {
            throw new WP2StaticException( 'Rust scraper binary not found at: ' . $binary );
        }

        if ( ! is_executable( $binary ) ) {
            throw new WP2StaticException( 'Rust scraper binary is not executable: ' . $binary );
        }

        // Detect sitemap URL dynamically
        $sitemap_path = self::detectSitemapPath( $base_url );

        // Construct the command with proper escaping
        $cmd = sprintf(
            '%s --base-url %s --output-dir %s --sitemap %s 2>&1',
            escapeshellcmd( escapeshellarg( $binary ) ),
            escapeshellarg( $base_url ),
            escapeshellarg( $output_dir ),
            escapeshellarg( $sitemap_path )
        );

        WsLog::l( 'Executing Rust scraper: ' . $cmd );

        // Execute the Rust scraper
        $output = [];
        $return_var = 0;
        exec( $cmd, $output, $return_var );

        // Log the output
        foreach ( $output as $line ) {
            WsLog::l( $line );
        }

        if ( $return_var !== 0 ) {
            throw new WP2StaticException( 
                'Rust scraper failed with exit code: ' . $return_var 
            );
        }

        WsLog::l( 'Rust scraper completed successfully' );
        WsLog::l( 'Markdown files saved to: ' . $output_dir );

        // Post-process: Copy markdown files to the static site path if needed
        self::postProcessMarkdown( $output_dir, $static_site_path );
    }

    /**
     * Detect the sitemap path for the WordPress site
     *
     * @param string $base_url Base URL of the site
     * @return string Sitemap path
     */
    private static function detectSitemapPath( string $base_url ) : string {
        // Try common sitemap locations
        $sitemap_candidates = [
            'sitemap_index.xml',  // Yoast SEO
            'wp-sitemap.xml',     // WordPress core (5.5+)
            'sitemap.xml',        // Generic
        ];

        foreach ( $sitemap_candidates as $candidate ) {
            $sitemap_url = rtrim( $base_url, '/' ) . '/' . $candidate;
            $response = wp_remote_head( $sitemap_url );
            
            if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
                WsLog::l( 'Found sitemap at: ' . $candidate );
                return $candidate;
            }
        }

        // Default to sitemap_index.xml if none found
        WsLog::l( 'No sitemap found, defaulting to sitemap_index.xml' );
        return 'sitemap_index.xml';
    }

    /**
     * Get the path to the Rust scraper binary
     *
     * @return string Path to the binary
     */
    private static function getBinaryPath() : string {
        if ( self::$binary_path ) {
            return self::$binary_path;
        }

        // Get the plugin directory
        $plugin_dir = dirname( __DIR__ );
        
        // Path to the release binary
        self::$binary_path = $plugin_dir . '/scraper/target/release/wp2static_scraper';

        // Append .exe on Windows systems
        if ( PHP_OS_FAMILY === 'Windows' ) {
            self::$binary_path .= '.exe';
        }

        return self::$binary_path;
    }

    /**
     * Build the Rust binary if it doesn't exist
     *
     * @return void
     */
    private static function buildBinary() : void {
        // Check if Cargo is installed
        $cargo_check = [];
        $cargo_return = 0;
        exec( 'cargo --version 2>&1', $cargo_check, $cargo_return );
        
        if ( $cargo_return !== 0 ) {
            WsLog::l( 'Cargo is not installed or not in PATH. Please install Rust from https://rustup.rs/' );
            return;
        }

        $plugin_dir = dirname( __DIR__ );
        $scraper_dir = $plugin_dir . '/scraper';

        if ( ! file_exists( $scraper_dir . '/Cargo.toml' ) ) {
            WsLog::l( 'Scraper source not found at: ' . $scraper_dir );
            return;
        }

        WsLog::l( 'Building Rust scraper binary...' );

        // Change to scraper directory and build
        $cmd = sprintf(
            'cd %s && cargo build --release 2>&1',
            escapeshellarg( $scraper_dir )
        );

        $output = [];
        $return_var = 0;
        exec( $cmd, $output, $return_var );

        // Log build output
        foreach ( $output as $line ) {
            WsLog::l( $line );
        }

        if ( $return_var === 0 ) {
            WsLog::l( 'Rust scraper binary built successfully' );
        } else {
            WsLog::l( 'Failed to build Rust scraper binary. Exit code: ' . $return_var );
        }
    }

    /**
     * Post-process markdown files
     *
     * Optionally convert markdown back to HTML or move files as needed
     *
     * @param string $markdown_dir Directory containing markdown files
     * @param string $static_site_path Static site path
     * @return void
     */
    private static function postProcessMarkdown( string $markdown_dir, string $static_site_path ) : void {
        // For now, just log the location
        // Future enhancement: convert markdown back to HTML if needed
        WsLog::l( 'Post-processing markdown files from: ' . $markdown_dir );
        
        // Check if directory exists and is readable
        if ( ! is_dir( $markdown_dir ) || ! is_readable( $markdown_dir ) ) {
            WsLog::l( 'Markdown directory not found or not readable: ' . $markdown_dir );
            return;
        }

        // Count files
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $markdown_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
        );
        
        $count = 0;
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'md' ) {
                $count++;
            }
        }
        
        WsLog::l( 'Generated ' . $count . ' markdown files' );
    }

    /**
     * Register the Rust scraper as a crawler option
     *
     * @return void
     */
    public static function registerCrawler() : void {
        add_action(
            'wp2static_crawl',
            [ self::class, 'wp2staticCrawl' ],
            10,
            2
        );
    }
}
