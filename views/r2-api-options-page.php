<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

use WP2Static\CoreOptions;

// Check user capability before accessing sensitive settings
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'wp2static' ) );
}

// Get current options
$options = [
    'r2ApiUrl' => CoreOptions::getValue( 'r2ApiUrl' ),
    'r2JwtToken' => CoreOptions::getValue( 'r2JwtToken' ),
    'r2SiteId' => CoreOptions::getValue( 'r2SiteId' ),
    'r2SiteName' => CoreOptions::getValue( 'r2SiteName' ),
    'r2ThemesToExclude' => CoreOptions::getValue( 'r2ThemesToExclude' ),
    'r2PluginsToExclude' => CoreOptions::getValue( 'r2PluginsToExclude' ),
];

?>

<div class="wrap">
    <h1>R2 API Deployment Settings</h1>

    <?php
    $settings_updated = isset( $_GET['settings-updated'] ) ?
        sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) : '';
    if ( $settings_updated === 'true' ) :
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Settings saved successfully!</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wp2static-r2-api-options' ); ?>
        <input type="hidden" name="action" value="wp2static_r2_api_save_options" />

        <h2>API Configuration</h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="r2ApiUrl">API URL</label>
                </th>
                <td>
                    <input 
                        type="url" 
                        id="r2ApiUrl" 
                        name="r2ApiUrl" 
                        value="<?php echo esc_attr( $options['r2ApiUrl'] ); ?>" 
                        class="regular-text"
                        placeholder="https://api.example.com"
                    />
                    <p class="description">The base URL of your R2 deployment API (must use HTTPS).</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="r2JwtToken">JWT Token</label>
                </th>
                <td>
                    <input 
                        type="password" 
                        id="r2JwtToken" 
                        name="r2JwtToken" 
                        value="" 
                        class="regular-text"
                        placeholder="<?php echo ! empty( $options['r2JwtToken'] ) ? '••••••••••••' : 'Your JWT token'; ?>"
                    />
                    <p class="description">JWT token for authentication with the R2 API. Leave blank to keep current token.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="r2SiteName">Site Name</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="r2SiteName" 
                        name="r2SiteName" 
                        value="<?php echo esc_attr( $options['r2SiteName'] ? $options['r2SiteName'] : get_bloginfo( 'name' ) ); ?>" 
                        class="regular-text"
                    />
                    <p class="description">Name for your site in the R2 API. Defaults to WordPress site name.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="r2SiteId">Site ID</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="r2SiteId" 
                        name="r2SiteId" 
                        value="<?php echo esc_attr( $options['r2SiteId'] ); ?>" 
                        class="regular-text"
                        readonly
                    />
                    <p class="description">Site ID returned from the API (auto-populated on first deployment).</p>
                </td>
            </tr>
        </table>

        <h2>Theme Export Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Themes to Exclude</th>
                <td>
                    <?php
                    $all_themes = wp_get_themes();
                    $excluded_themes = array_filter( array_map( 'trim', explode( "\n", $options['r2ThemesToExclude'] ) ) );
                    
                    if ( ! empty( $all_themes ) ) :
                        foreach ( $all_themes as $theme_key => $theme ) :
                            $is_checked = in_array( $theme_key, $excluded_themes );
                            ?>
                            <fieldset>
                                <label for="r2ThemesToExclude_<?php echo esc_attr( $theme_key ); ?>">
                                    <input 
                                        type="checkbox" 
                                        id="r2ThemesToExclude_<?php echo esc_attr( $theme_key ); ?>" 
                                        name="r2ThemesToExclude[]" 
                                        value="<?php echo esc_attr( $theme_key ); ?>"
                                        <?php checked( $is_checked ); ?>
                                    />
                                    <?php echo esc_html( $theme->get( 'Name' ) ); ?> 
                                    <code>(<?php echo esc_html( $theme_key ); ?>)</code>
                                </label>
                            </fieldset>
                            <?php
                        endforeach;
                    else :
                        echo '<p>No themes found.</p>';
                    endif;
                    ?>
                    <p class="description">Select themes to exclude from the deployment.</p>
                </td>
            </tr>
        </table>

        <h2>Plugin Export Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Plugins to Exclude</th>
                <td>
                    <?php
                    $all_plugins = get_plugins();
                    $excluded_plugins = array_filter( array_map( 'trim', explode( "\n", $options['r2PluginsToExclude'] ) ) );
                    
                    if ( ! empty( $all_plugins ) ) :
                        foreach ( $all_plugins as $plugin_path => $plugin ) :
                            $plugin_dir = dirname( $plugin_path );
                            if ( $plugin_dir === '.' ) {
                                $plugin_dir = $plugin_path;
                            }
                            $is_checked = in_array( $plugin_dir, $excluded_plugins );
                            $field_id = 'r2PluginsToExclude_' . md5( $plugin_dir );
                            ?>
                            <fieldset>
                                <label for="<?php echo esc_attr( $field_id ); ?>">
                                    <input 
                                        type="checkbox" 
                                        id="<?php echo esc_attr( $field_id ); ?>" 
                                        name="r2PluginsToExclude[]" 
                                        value="<?php echo esc_attr( $plugin_dir ); ?>"
                                        <?php checked( $is_checked ); ?>
                                    />
                                    <?php echo esc_html( $plugin['Name'] ); ?> 
                                    <code>(<?php echo esc_html( $plugin_dir ); ?>)</code>
                                </label>
                            </fieldset>
                            <?php
                        endforeach;
                    else :
                        echo '<p>No plugins found.</p>';
                    endif;
                    ?>
                    <p class="description">Select plugins to exclude from the deployment.</p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>

    <hr />

    <h2>API Endpoints</h2>
    <p>The following endpoints will be used by the plugin:</p>
    <ul>
        <li><strong>POST</strong> <code>/api/sites/create</code> - Create a new site configuration</li>
        <li><strong>GET</strong> <code>/api/sites/list</code> - List all sites for this account</li>
        <li><strong>POST</strong> <code>/api/sites/update</code> - Upload GZIP archive with static files</li>
    </ul>

    <h2>Archive Contents</h2>
    <p>The GZIP archive will contain:</p>
    <ul>
        <li><strong>static/</strong> - All static site files</li>
        <li><strong>themes.zip</strong> - Selected WordPress themes</li>
        <li><strong>plugins.zip</strong> - Selected WordPress plugins</li>
        <li><strong>db.sql</strong> - MySQL database export</li>
    </ul>
</div>
