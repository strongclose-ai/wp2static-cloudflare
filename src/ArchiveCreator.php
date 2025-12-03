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
     * @param string $processed_site_path Path to processed site (optional, uses ProcessedSite::getPath() if not provided)
     * @return string Path to the created archive
     * @throws WP2StaticException
     */
    public static function createArchive( string $output_path, string $processed_site_path = '' ) : string {
        $temp_dir = self::createTempDirectory();

        try {
            // Copy static site files
            self::copyStaticSite( $temp_dir, $processed_site_path );

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
        // Use uniqid for better security (avoids predictable paths)
        $temp_base = sys_get_temp_dir() . '/wp2static-archive-' . uniqid( '', true );
        if ( ! wp_mkdir_p( $temp_base ) ) {
            throw new WP2StaticException( 'Failed to create temp directory' );
        }
        return $temp_base;
    }

    /**
     * Copy static site files to temp directory
     *
     * @param string $temp_dir Temporary directory path
     * @param string $processed_site_path Path to processed site (optional)
     */
    private static function copyStaticSite( string $temp_dir, string $processed_site_path = '' ) : void {
        // Use provided path or fall back to ProcessedSite::getPath()
        $processed_site = $processed_site_path ? $processed_site_path : ProcessedSite::getPath();
        
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
        // Get exclusion list from settings
        $themes_to_exclude = CoreOptions::getValue( 'r2ThemesToExclude' );

        $themes_path = get_theme_root();
        $themes_zip_path = $temp_dir . '/themes.zip';

        $zip = new ZipArchive();
        if ( $zip->open( $themes_zip_path, ZipArchive::CREATE ) !== true ) {
            throw new WP2StaticException( 'Failed to create themes.zip' );
        }

        $exclude_list = [];
        if ( $themes_to_exclude ) {
            $patterns = array_map( 'trim', explode( "\n", $themes_to_exclude ) );
            // Validate patterns to prevent path traversal
            foreach ( $patterns as $pattern ) {
                // Only allow simple directory names, no path traversal or slashes
                if ( strpos( $pattern, '..' ) === false && 
                     strpos( $pattern, '/' ) === false && 
                     strpos( $pattern, '\\' ) === false &&
                     ! empty( $pattern ) ) {
                    $exclude_list[] = $pattern;
                }
            }
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
        // Get exclusion list from settings
        $plugins_to_exclude = CoreOptions::getValue( 'r2PluginsToExclude' );

        $plugins_path = WP_PLUGIN_DIR;
        $plugins_zip_path = $temp_dir . '/plugins.zip';

        $zip = new ZipArchive();
        if ( $zip->open( $plugins_zip_path, ZipArchive::CREATE ) !== true ) {
            throw new WP2StaticException( 'Failed to create plugins.zip' );
        }

        $exclude_list = [];
        if ( $plugins_to_exclude ) {
            $patterns = array_map( 'trim', explode( "\n", $plugins_to_exclude ) );
            // Validate patterns to prevent path traversal
            foreach ( $patterns as $pattern ) {
                // Only allow simple directory names, no path traversal or slashes
                if ( strpos( $pattern, '..' ) === false && 
                     strpos( $pattern, '/' ) === false && 
                     strpos( $pattern, '\\' ) === false &&
                     ! empty( $pattern ) ) {
                    $exclude_list[] = $pattern;
                }
            }
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
        // Note: This uses WordPress's SHOW TABLES, so table names are from WP database
        $tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );

        foreach ( $tables as $table ) {
            $table_name = $table[0];

            // Escape table name for backtick quotes
            // (backticks are doubled to escape them in MySQL identifiers)
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

            // Get column names for this table
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $columns_result = $wpdb->get_results(
                "SHOW COLUMNS FROM {$escaped_table}",
                ARRAY_A
            );
            
            $column_names = [];
            foreach ( $columns_result as $column ) {
                $column_names[] = '`' . $column['Field'] . '`';
            }
            $columns_sql = implode( ', ', $column_names );

            while ( true ) {
                // Note: Table name is manually escaped with backticks above
                // and comes from SHOW TABLES (WordPress's own tables)
                // This is safe as the table name is from WordPress DB, not user input
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
                    // Include column names in INSERT for robustness
                    fwrite(
                        $handle,
                        "INSERT INTO {$escaped_table} ({$columns_sql}) VALUES (" .
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

        try {
            // Create tar archive
            $phar = new \PharData( $tar_file );
            $phar->buildFromDirectory( $temp_dir );

            // Compress to gzip
            $phar->compress( \Phar::GZ );

            // Remove uncompressed tar
            unlink( $tar_file );

            WsLog::l( 'Created GZIP archive: ' . $gzip_file );
            return $gzip_file;
        } catch ( \Exception $e ) {
            // Clean up on error
            if ( file_exists( $tar_file ) ) {
                unlink( $tar_file );
            }
            throw new WP2StaticException(
                'Failed to create GZIP archive: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Recursively copy directory
     *
     * @param string $src Source directory
     * @param string $dst Destination directory
     */
    private static function recursiveCopy( string $src, string $dst ) : void {
        wp_mkdir_p( $dst );

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            $dest_path = $dst . DIRECTORY_SEPARATOR .
                $iterator->getSubPathName();

            if ( $item->isDir() ) {
                wp_mkdir_p( $dest_path );
            } else {
                copy( $item->getRealPath(), $dest_path );
            }
        }
    }
}
