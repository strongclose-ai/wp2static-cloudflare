# WP2Static Rust Scraper

A high-performance Rust-based web scraper that **completely replaces the PHP scraping logic** in WP2Static. This scraper fetches pages from WordPress sites and generates both static HTML files and markdown content.

## Features

- **Sitemap Parsing**: Automatically parses `sitemap_index.xml` and extracts all page URLs
- **Dual Output**: Generates both static HTML files and markdown content from each page
  - **HTML files**: Saved to output root for the static site
  - **Markdown files**: Saved to `markdown/` subdirectory with extracted content and asset metadata
- **Content Extraction**: Uses intelligent text density analysis to extract main content
- **Asset Detection**: Detects self-hosted JavaScript and CSS files with metadata in markdown
- **Parallel Processing**: Concurrent scraping with configurable worker threads for maximum performance
- **PHP Integration**: Seamlessly integrates with WordPress via PHP's `exec()` function for easy deployment
- **Compatible with WP2Static**: Follows the same file structure conventions as the PHP Crawler

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
- `--output-dir` or `-o`: Output directory for static files (default: "output")
  - HTML files go to the root of this directory
  - Markdown files go to `markdown/` subdirectory
- `--sitemap` or `-s`: Path to sitemap file relative to base URL (default: "sitemap_index.xml")
- `--concurrency` or `-c`: Number of concurrent workers (default: 4, recommended: 4-8)

### Example

```bash
# Scrape a WordPress site with 4 workers (default)
./target/release/wp2static_scraper \
  --base-url "https://myblog.com" \
  --output-dir "static_output"

# Scrape with 8 workers for better performance on large sites
./target/release/wp2static_scraper \
  --base-url "https://myblog.com" \
  --output-dir "static_output" \
  --concurrency 8
```

This will:
1. Fetch and parse `https://myblog.com/sitemap_index.xml`
2. Extract all URLs from the sitemap(s)
3. Scrape pages in parallel using multiple workers
4. Save HTML files to `static_output/`
5. Save markdown files to `static_output/markdown/`

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

The scraper generates two types of output:

### Static HTML Files
Located in the output root, matching the PHP Crawler behavior:

```
output/
├── index.html            # Homepage (/)
├── about/
│   └── index.html        # /about/
├── blog/
│   ├── 2024/
│   │   └── my-post/
│   │       └── index.html  # /blog/2024/my-post/
│   └── index.html        # /blog/
└── page.html             # /page.html
```

### Markdown Files
Located in the `markdown/` subdirectory with extracted content and metadata:

```
output/markdown/
├── index.md              # Homepage content
├── about/
│   └── index.md          # /about/ content
├── blog/
│   ├── 2024/
│   │   └── my-post.md    # /blog/2024/my-post/ content
│   └── index.md          # /blog/ content
└── page.md               # /page.html content
```

Each markdown file includes:
- Source URL metadata
- Detected self-hosted assets (CSS/JS)
- Extracted main content converted to markdown

### Example Markdown Output

```markdown
<!-- Source URL: https://example.com/blog/my-post/ -->
<!-- Detected Assets:
  - https://example.com/wp-content/themes/mytheme/style.css (Type: CSS)
  - https://example.com/wp-includes/js/jquery.js (Type: JavaScript)
-->

# My Blog Post

This is the main content of the blog post, extracted intelligently
using text density analysis...
```

## Architecture

The scraper is organized into three main modules:

1. **sitemap.rs**: Parses XML sitemaps and extracts URLs
2. **scraper.rs**: Fetches pages and generates both HTML and markdown files
3. **asset_detector.rs**: Detects self-hosted JavaScript and CSS assets

## Testing

Run the test suite:

```bash
cargo test
```

## Dependencies

- `reqwest`: HTTP client for fetching pages
- `scraper`: HTML parsing and CSS selector support
- `dom-content-extraction`: Intelligent content extraction via text density analysis
- `html2md`: HTML to Markdown conversion
- `url`: URL parsing and manipulation
- `quick-xml`: XML parsing for sitemaps
- `clap`: Command-line argument parsing
- `anyhow`: Error handling
- `rayon`: Parallel processing

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

- **CDN Migration**: Implement actual CDN upload and migration using the asset detection data
- **Progress Bars**: Add visual progress bars using `indicatif` or similar
- **Error Recovery**: Implement retry logic for failed page fetches
- **Configuration File**: Support for configuration files to store common options
- **Crawl Caching**: Add support for crawl caching to skip unchanged pages

