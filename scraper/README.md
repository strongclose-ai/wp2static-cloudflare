# WP2Static Rust Scraper

A high-performance Rust-based web scraper that replaces the PHP scraping logic in WP2Static. This scraper extracts content from WordPress sites based on sitemap files and converts pages to Markdown format.

## Features

- **Sitemap Parsing**: Automatically parses `sitemap_index.xml` and extracts all page URLs
- **Content Extraction**: Uses the `dom-content-extraction` library to intelligently extract main content from HTML pages
- **Markdown Conversion**: Converts extracted HTML content to clean Markdown format
- **Asset Detection**: Detects self-hosted JavaScript and CSS files (with stub for future CDN migration)
- **Parallel Processing**: Concurrent scraping with configurable worker threads for maximum performance
- **PHP Integration**: Seamlessly integrates with WordPress via PHP's `exec()` function for easy deployment

## Building

```bash
cargo build --release
```

## Usage

### Standalone CLI

```bash
./target/release/wp2static_scraper \
  --base-url "https://example.com" \
  --output-dir "output" \
  --sitemap "sitemap_index.xml" \
  --concurrency 8
```

### PHP Integration

The scraper is automatically registered as a WordPress crawler and can be executed via PHP:

```php
// The RustScraper is registered in wp2static.php
// It will be used when the crawler_slug is 'rust-scraper'
WP2Static\RustScraper::wp2staticCrawl($static_site_path, 'rust-scraper');
```

### Arguments

- `--base-url` or `-b`: The base URL of the WordPress site (required)
- `--output-dir` or `-o`: Output directory for markdown files (default: "output")
- `--sitemap` or `-s`: Path to sitemap file relative to base URL (default: "sitemap_index.xml")
- `--concurrency` or `-c`: Number of concurrent workers (default: 4, recommended: 4-8)

### Example

```bash
# Scrape a WordPress site with 4 workers (default)
./target/release/wp2static_scraper \
  --base-url "https://myblog.com" \
  --output-dir "markdown_output"

# Scrape with 8 workers for better performance on large sites
./target/release/wp2static_scraper \
  --base-url "https://myblog.com" \
  --output-dir "markdown_output" \
  --concurrency 8
```

This will:
1. Fetch and parse `https://myblog.com/sitemap_index.xml`
2. Extract all URLs from the sitemap(s)
3. Scrape pages in parallel using multiple workers
4. Extract the main content using text density analysis
5. Convert to Markdown
6. Save each page as a `.md` file in `markdown_output/`

## Performance

The scraper uses parallel processing with Rayon for maximum throughput:

- **Default**: 4 concurrent workers
- **Recommended**: 4-8 workers for most sites
- **Large sites**: Up to 16 workers (adjust based on server capacity)

Performance benchmarks (approximate):
- Small site (50 pages): ~10-15 seconds
- Medium site (500 pages): ~60-90 seconds  
- Large site (5000 pages): ~8-12 minutes

*Note: Actual performance depends on page complexity, network speed, and server response time.*

## Output Structure

The scraper preserves the URL structure of your site:

```
output/
├── index.md              # Homepage (/)
├── about/
│   └── index.md          # /about/
├── blog/
│   ├── 2024/
│   │   └── my-post/
│   │       └── index.md  # /blog/2024/my-post/
│   └── index.md          # /blog/
└── contact.md            # /contact.html
```

## Asset Detection

Each markdown file includes comments with detected assets:

```markdown
<!-- Source URL: https://example.com/page/ -->
<!-- Detected Assets:
  - https://example.com/wp-content/themes/mytheme/style.css (Type: CSS)
  - https://example.com/wp-includes/js/jquery.js (Type: JavaScript)
-->

# Page Title

Page content here...
```

### CDN Migration (Stub)

The asset detector identifies self-hosted JS/CSS files. The actual CDN migration logic is stubbed out in `src/asset_detector.rs` under the `migrate_to_cdn` method. This will be implemented once the CDN API details are provided.

## Architecture

The scraper is organized into three main modules:

1. **sitemap.rs**: Parses XML sitemaps and extracts URLs
2. **scraper.rs**: Fetches pages, extracts content, and saves as Markdown
3. **asset_detector.rs**: Detects self-hosted JavaScript and CSS assets

## Testing

Run the test suite:

```bash
cargo test
```

## Example Output

When scraping a WordPress page, the scraper produces markdown with metadata:

```markdown
<!-- Source URL: https://example.com/blog/my-post/ -->
<!-- Detected Assets:
  - https://example.com/wp-content/themes/mytheme/style.css (Type: CSS)
  - https://example.com/wp-includes/js/jquery.js (Type: JavaScript)
-->

# My Blog Post

This is the main content of the blog post, extracted intelligently
using text density analysis.

The scraper automatically identifies the main content area and ignores
navigation, sidebars, footers, and other peripheral elements.

## A Subheading

More content here...
```

## Dependencies

- `reqwest`: HTTP client for fetching pages
- `scraper`: HTML parsing and CSS selector support
- `dom-content-extraction`: Intelligent content extraction via text density analysis
- `html2md`: HTML to Markdown conversion
- `quick-xml`: XML parsing for sitemaps
- `clap`: Command-line argument parsing
- `anyhow`: Error handling

## License

This project is part of WP2Static and follows the same license (UNLICENSE).

## Contributing

Contributions are welcome! When contributing to the Rust scraper:

1. Run tests before submitting: `cargo test`
2. Run clippy for lint checks: `cargo clippy`
3. Format code with: `cargo fmt`
4. Update documentation as needed

### Future Enhancements

The following features are planned for future development:

- **CDN Migration**: Implement the `migrate_to_cdn` function once API details are provided
- **Progress Bars**: Add visual progress bars using `indicatif` or similar
- **Error Recovery**: Implement retry logic for failed page fetches
- **Configuration File**: Support for configuration files to store common options

