<?php

namespace WP2Static;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ZipArchive;

/**
 * GZIP Archive Creator for R2 deployment
 */
class ArchiveCreator {
    /**
     * Create a GZIP archive with static site, themes, plugins, and database
     *
     * @param string $output_path Path for the output GZIP file
     * @return string Path to the created archive
     * @throws WP2StaticException
     */
    public static function createArchive( string $output_path ) : string {
        $temp_dir = self::createTempDirectory();

        try {
            // Copy static site files
            self::copyStaticSite( $temp_dir );

            // Create themes.zip
            self::createThemesArchive( $temp_dir );

            // Create plugins.zip
            self::createPluginsArchive( $temp_dir );

            // Export database to db.sql
            self::exportDatabase( $temp_dir );

            // Create final GZIP archive
            $gzip_path = self::createGzipArchive( $temp_dir, $output_path );

            // Clean up temp directory
            FilesHelper::deleteDirWithFiles( $temp_dir );

            return $gzip_path;
        } catch ( \Exception $e ) {
            // Clean up on error
            if ( is_dir( $temp_dir ) ) {
                FilesHelper::deleteDirWithFiles( $temp_dir );
            }
            throw new WP2StaticException(
                'Failed to create archive: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create a temporary directory for archive preparation
     *
     * @return string Path to temp directory
     * @throws WP2StaticException
     */
    private static function createTempDirectory() : string {
        $temp_base = sys_get_temp_dir() . '/wp2static-archive-' . time();
        if ( ! wp_mkdir_p( $temp_base ) ) {
            throw new WP2StaticException( 'Failed to create temp directory' );
        }
        return $temp_base;
    }

    /**
     * Copy static site files to temp directory
     *
     * @param string $temp_dir Temporary directory path
     */
    private static function copyStaticSite( string $temp_dir ) : void {
        $processed_site = ProcessedSite::getPath();
        if ( ! is_dir( $processed_site ) ) {
            WsLog::l( 'No processed site found, skipping static site files' );
            return;
        }

        $static_dir = $temp_dir . '/static';
        wp_mkdir_p( $static_dir );

        self::recursiveCopy( $processed_site, $static_dir );
        WsLog::l( 'Copied static site files' );
    }

    /**
     * Create themes.zip in the archive
     *
     * @param string $temp_dir Temporary directory path
     * @throws WP2StaticException
     */
    private static function createThemesArchive( string $temp_dir ) : void {
        $themes_to_include = CoreOptions::getValue( 'r2ThemesToInclude' );
        $themes_to_exclude = CoreOptions::getValue( 'r2ThemesToExclude' );

        if ( ! $themes_to_include && ! $themes_to_exclude ) {
            // Include all themes by default
            $themes_to_include = 'all';
        }

        $themes_path = get_theme_root();
        $themes_zip_path = $temp_dir . '/themes.zip';

        $zip = new ZipArchive();
        if ( $zip->open( $themes_zip_path, ZipArchive::CREATE ) !== true ) {
            throw new WP2StaticException( 'Failed to create themes.zip' );
        }

        $exclude_list = [];
        if ( $themes_to_exclude ) {
            $exclude_list = array_map( 'trim', explode( "\n", $themes_to_exclude ) );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $themes_path, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            $file_path = $file->getRealPath();
            if ( ! $file_path ) {
                continue;
            }
            $relative_path = substr( $file_path, strlen( $themes_path ) + 1 );

            // Check if theme should be excluded
            $should_exclude = false;
            foreach ( $exclude_list as $exclude_pattern ) {
                if ( strpos( $relative_path, $exclude_pattern ) === 0 ) {
                    $should_exclude = true;
                    break;
                }
            }

            if ( ! $should_exclude && ! $file->isDir() ) {
                $zip->addFile( $file_path, $relative_path );
            }
        }

        $zip->close();
        WsLog::l( 'Created themes.zip' );
    }

    /**
     * Create plugins.zip in the archive
     *
     * @param string $temp_dir Temporary directory path
     * @throws WP2StaticException
     */
    private static function createPluginsArchive( string $temp_dir ) : void {
        $plugins_to_include = CoreOptions::getValue( 'r2PluginsToInclude' );
        $plugins_to_exclude = CoreOptions::getValue( 'r2PluginsToExclude' );

        if ( ! $plugins_to_include && ! $plugins_to_exclude ) {
            // Include all plugins by default
            $plugins_to_include = 'all';
        }

        $plugins_path = WP_PLUGIN_DIR;
        $plugins_zip_path = $temp_dir . '/plugins.zip';

        $zip = new ZipArchive();
        if ( $zip->open( $plugins_zip_path, ZipArchive::CREATE ) !== true ) {
            throw new WP2StaticException( 'Failed to create plugins.zip' );
        }

        $exclude_list = [];
        if ( $plugins_to_exclude ) {
            $exclude_list = array_map( 'trim', explode( "\n", $plugins_to_exclude ) );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $plugins_path, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            $file_path = $file->getRealPath();
            if ( ! $file_path ) {
                continue;
            }
            $relative_path = substr( $file_path, strlen( $plugins_path ) + 1 );

            // Check if plugin should be excluded
            $should_exclude = false;
            foreach ( $exclude_list as $exclude_pattern ) {
                if ( strpos( $relative_path, $exclude_pattern ) === 0 ) {
                    $should_exclude = true;
                    break;
                }
            }

            if ( ! $should_exclude && ! $file->isDir() ) {
                $zip->addFile( $file_path, $relative_path );
            }
        }

        $zip->close();
        WsLog::l( 'Created plugins.zip' );
    }

    /**
     * Export database to db.sql
     *
     * @param string $temp_dir Temporary directory path
     * @throws WP2StaticException
     */
    private static function exportDatabase( string $temp_dir ) : void {
        global $wpdb;

        $db_file = $temp_dir . '/db.sql';
        $handle = fopen( $db_file, 'w' );

        if ( ! $handle ) {
            throw new WP2StaticException( 'Failed to create db.sql file' );
        }

        // Get all table names with proper escaping
        $tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );

        foreach ( $tables as $table ) {
            $table_name = $table[0];

            // Escape table name for backtick quotes
            $escaped_table = '`' . str_replace( '`', '``', $table_name ) . '`';

            // Get CREATE TABLE statement
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $create_table = $wpdb->get_row(
                "SHOW CREATE TABLE {$escaped_table}",
                ARRAY_N
            );

            if ( $create_table ) {
                fwrite( $handle, "\n\n" . $create_table[1] . ";\n\n" );
            }

            // Get table data in chunks to prevent memory exhaustion
            $offset = 0;
            $limit = 100; // Process 100 rows at a time

            while ( true ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$escaped_table} LIMIT %d OFFSET %d",
                        $limit,
                        $offset
                    ),
                    ARRAY_A
                );

                if ( ! $rows ) {
                    break;
                }

                foreach ( $rows as $row ) {
                    $values = [];
                    foreach ( $row as $value ) {
                        if ( $value === null ) {
                            $values[] = 'NULL';
                        } else {
                            // Use esc_sql which is the recommended method
                            $values[] = "'" . esc_sql( $value ) . "'";
                        }
                    }
                    fwrite(
                        $handle,
                        "INSERT INTO {$escaped_table} VALUES (" .
                        implode( ',', $values ) . ");\n"
                    );
                }

                $offset += $limit;
            }
        }

        fclose( $handle );
        WsLog::l( 'Created db.sql' );
    }

    /**
     * Create GZIP archive from temp directory
     *
     * @param string $temp_dir Temporary directory path
     * @param string $output_path Output path for GZIP file
     * @return string Path to created GZIP file
     * @throws WP2StaticException
     */
    private static function createGzipArchive( string $temp_dir, string $output_path ) : string {
        $tar_file = $output_path . '.tar';
        $gzip_file = $output_path . '.tar.gz';

        // Create tar archive
        $phar = new \PharData( $tar_file );
        $phar->buildFromDirectory( $temp_dir );

        // Compress to gzip
        $phar->compress( \Phar::GZ );

        // Remove uncompressed tar
        unlink( $tar_file );

        WsLog::l( 'Created GZIP archive: ' . $gzip_file );
        return $gzip_file;
    }

    /**
     * Recursively copy directory
     *
     * @param string $src Source directory
     * @param string $dst Destination directory
     */
    private static function recursiveCopy( string $src, string $dst ) : void {
        $dir = opendir( $src );
        if ( ! $dir ) {
            return;
        }
        wp_mkdir_p( $dst );

        while ( false !== ( $file = readdir( $dir ) ) ) {
            if ( ( $file != '.' ) && ( $file != '..' ) ) {
                if ( is_dir( $src . '/' . $file ) ) {
                    self::recursiveCopy( $src . '/' . $file, $dst . '/' . $file );
                } else {
                    copy( $src . '/' . $file, $dst . '/' . $file );
                }
            }
        }
        closedir( $dir );
    }
}
