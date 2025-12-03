<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

use WP2Static\CoreOptions;

// Get current options
$options = [
    'r2ApiUrl' => CoreOptions::getValue( 'r2ApiUrl' ),
    'r2JwtToken' => CoreOptions::getValue( 'r2JwtToken' ),
    'r2SiteId' => CoreOptions::getValue( 'r2SiteId' ),
    'r2SiteName' => CoreOptions::getValue( 'r2SiteName' ),
    'r2ThemesToExclude' => CoreOptions::getValue( 'r2ThemesToExclude' ),
    'r2PluginsToExclude' => CoreOptions::getValue( 'r2PluginsToExclude' ),
];

// Decrypt JWT token for display (show masked)
if ( ! empty( $options['r2JwtToken'] ) ) {
    $options['r2JwtToken'] = CoreOptions::encrypt_decrypt( 'decrypt', $options['r2JwtToken'] );
}

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
                    <p class="description">The base URL of your R2 deployment API.</p>
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
                        value="<?php echo esc_attr( $options['r2JwtToken'] ); ?>" 
                        class="regular-text"
                        placeholder="Your JWT token"
                    />
                    <p class="description">JWT token for authentication with the R2 API.</p>
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
                <th scope="row">
                    <label for="r2ThemesToExclude">Themes to Exclude</label>
                </th>
                <td>
                    <textarea 
                        id="r2ThemesToExclude" 
                        name="r2ThemesToExclude" 
                        rows="5" 
                        class="large-text"
                        placeholder="twentytwenty&#10;twentytwentyone"
                    ><?php echo esc_textarea( $options['r2ThemesToExclude'] ); ?></textarea>
                    <p class="description">List theme directory names to exclude (one per line). Leave empty to include all themes.</p>
                </td>
            </tr>
        </table>

        <h2>Plugin Export Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="r2PluginsToExclude">Plugins to Exclude</label>
                </th>
                <td>
                    <textarea 
                        id="r2PluginsToExclude" 
                        name="r2PluginsToExclude" 
                        rows="5" 
                        class="large-text"
                        placeholder="akismet&#10;hello-dolly"
                    ><?php echo esc_textarea( $options['r2PluginsToExclude'] ); ?></textarea>
                    <p class="description">List plugin directory names to exclude (one per line). Leave empty to include all plugins.</p>
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
