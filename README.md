# WP2Static

A WordPress plugin for static site generation and deployment.

**Latest: WP2Static joins Strattic, the leading WordPress to headless and static site end-to-end publishing platform!**

Strattic is generously keeping the WP2Static plugin available and maintained for open source users!

[Read Announcement](https://www.strattic.com/wp2static-joins-strattic/)

## Features

- Generate static HTML from your WordPress site
- Deploy to various platforms via add-ons
- **NEW: R2 API Deployment** - Deploy to Cloudflare R2 via custom API with complete site export (themes, plugins, database)
- **NEW: Rust Scraper** - High-performance Rust-based scraper that converts pages to Markdown
- URL detection and crawling
- Post-processing and optimization
- Caching for efficient rebuilds

## Installation options

 - from this source code `git clone https://github.com/wp2static/wp2static.git` (run `composer install` afterwards)
 - via [Composer](https://github.com/composer/composer) `composer require wp2static/wp2static`
 - get installer zip from [wp2static.com](https://wp2static.com/download/)
 - [compile your own installer zip from source code](https://wp2static.com/compiling-from-source/)


## [Docs](https://wp2static.com)

## [Support Forum](https://staticword.press/c/wordpress-static-site-generators/wp2static/)

## R2 API Deployment

Deploy your static WordPress site to Cloudflare R2 via a custom API. This feature includes:

- JWT authentication for secure API access
- Complete site export (static files, themes, plugins, database)
- Configurable theme and plugin inclusion/exclusion
- Automatic GZIP archive creation

[Read the R2 API Deployment Guide](./R2_API_DEPLOYMENT.md)

## Rust Scraper

A high-performance Rust-based scraper that **replaces the PHP scraping logic entirely**. The scraper:

- Parses sitemap files (`sitemap_index.xml`) to discover all pages
- Fetches pages and saves them as static HTML files
- Supports parallel processing for high performance
- Fully integrates with the WP2Static workflow

[Read the Rust Scraper Documentation](./scraper/README.md)

### Quick Start

```bash
# Build the scraper
cd scraper
cargo build --release

# Run the scraper
./target/release/wp2static_scraper \
  --base-url "https://your-site.com" \
  --output-dir "output"
```

### Contributing

[See `CONTRIBUTING.md`](./CONTRIBUTING.md)

### Testing

WP2Static includes various types of code quality and functionality tests.

Tests are defined as Composer scripts within the `composer.json` file.

`composer run-script test` will run the main linting, static analysis and unit tests. It will not run code coverage by default. To run code coverage, use `composer run-script coverage`, this will require XDebug installed.

`composer run-script test-integration` will run end to end tests. This requires that you have the `nix-shell` command available from [NixOS](https://nixos.org/download.html). More info on the intgration tests can be found in the README within the `integration-tests` directory.

You can run individual test stages by specifying any of the defined scripts within `composer.json` with a command like `composer run-script phpunit`. You can pass arguments, such as to skip slow external request making phpunit tests, run `composer run-script phpunit -- --exclude-group ExternalRequests`.

Continuous Integration is provided by GitHub Actions, which run code quality, unit and end to end tests.


