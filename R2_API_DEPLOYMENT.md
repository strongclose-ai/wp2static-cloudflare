# R2 API Deployment for WP2Static

This feature allows you to deploy your static WordPress site to Cloudflare R2 via a custom API, including themes, plugins, and database export.

## Features

- **JWT Authentication**: Secure API access using JSON Web Tokens
- **Complete Site Export**: Includes static files, themes, plugins, and database
- **Flexible Configuration**: Choose which themes and plugins to include/exclude
- **Automatic GZIP Archive**: Creates compressed archives for efficient transfer
- **Site Management**: Create and update sites via API endpoints

## Installation

1. Ensure WP2Static is installed and activated
2. The R2 API deployment addon is automatically registered

## Configuration

### API Settings

Navigate to **WP2Static → R2 API Deployment** to configure:

1. **API URL**: Your R2 deployment API base URL (e.g., `https://api.example.com`)
2. **JWT Token**: Authentication token for your API
3. **Site Name**: Name for your site (defaults to WordPress site name)
4. **Site ID**: Auto-populated after first deployment

### Theme Export Settings

- **Themes to Exclude**: List theme directory names to exclude (one per line)
- Leave empty to include all themes

### Plugin Export Settings

- **Plugins to Exclude**: List plugin directory names to exclude (one per line)
- Leave empty to include all plugins

## Usage

### Via WordPress Admin

1. Go to **WP2Static → Add-ons**
2. Enable "R2 API Deployment"
3. Configure settings in **WP2Static → R2 API Deployment**
4. Run a deployment from **WP2Static → Run**

### Via WP-CLI

```bash
# Enable the R2 API deployer
wp wp2static addons toggle wp2static-addon-r2-api

# Configure API settings
wp wp2static options set r2ApiUrl https://api.example.com
wp wp2static options set r2JwtToken your-jwt-token-here
wp wp2static options set r2SiteName "My WordPress Site"

# Run deployment
wp wp2static deploy
```

## API Endpoints

Your API must implement the following endpoints:

### POST /api/sites/create

Creates a new site configuration.

**Request:**
```json
{
  "auth": "jwt-token",
  "domain": "https://example.com",
  "site_name": "My Site"
}
```

**Response:**
```json
{
  "site_id": "unique-site-id"
}
```

### GET /api/sites/list

Lists all sites for the authenticated account.

**Request:**
```
GET /api/sites/list?auth=jwt-token
```

**Response:**
```json
{
  "sites": [
    {
      "site_id": "unique-site-id",
      "domain": "https://example.com",
      "site_name": "My Site"
    }
  ]
}
```

### POST /api/sites/update

Updates a site with a GZIP archive containing static files.

**Request:**
- Method: POST (multipart/form-data)
- Fields:
  - `auth`: JWT token
  - `site_id`: Site identifier
  - `file`: GZIP archive (.tar.gz)

**Response:**
```json
{
  "status": 200,
  "message": "Site updated successfully"
}
```

## Archive Structure

The GZIP archive contains:

```
archive.tar.gz
├── static/                 # Static site files
│   ├── index.html
│   ├── wp-content/
│   └── ...
├── themes.zip             # WordPress themes
├── plugins.zip            # WordPress plugins
└── db.sql                 # MySQL database export
```

## Security

- JWT tokens are encrypted before storage in the database
- All API requests use HTTPS (ensure your API URL uses https://)
- Database exports contain all WordPress tables - ensure your API securely stores these

## Troubleshooting

### "R2 API URL and JWT Token are required"

Configure the API URL and JWT token in the settings page before deploying.

### "Failed to create site"

Check that your API is accessible and returns the expected `site_id` field.

### "Deployment failed"

Check the WP2Static logs (**WP2Static → Logs**) for detailed error messages.

### Archive creation fails

Ensure PHP has sufficient memory and disk space. Large sites may require:
```php
// Add to wp-config.php
define('WP_MEMORY_LIMIT', '512M');
```

## Development

### Adding Custom Filters

```php
// Modify archive output path
add_filter('wp2static_r2_archive_path', function($path) {
    return '/custom/path/archive';
});

// Modify API request parameters
add_filter('wp2static_r2_api_params', function($params, $endpoint) {
    // Add custom parameters
    $params['custom_field'] = 'value';
    return $params;
}, 10, 2);
```

## Support

For issues and questions:
- GitHub Issues: https://github.com/strongclose-ai/wp2static-cloudflare/issues
- WP2Static Documentation: https://wp2static.com

## License

This feature is part of WP2Static and follows the same license.
