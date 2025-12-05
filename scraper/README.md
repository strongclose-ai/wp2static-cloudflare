# WP2Static Rust Scraper

A Rust-based web scraper that replaces the PHP scraping logic in WP2Static. This scraper extracts content from WordPress sites based on sitemap files and converts pages to Markdown format.

## Features

- **Sitemap Parsing**: Automatically parses `sitemap_index.xml` and extracts all page URLs
- **Content Extraction**: Uses the `dom-content-extraction` library to intelligently extract main content from HTML pages
- **Markdown Conversion**: Converts extracted HTML content to clean Markdown format
- **Asset Detection**: Detects self-hosted JavaScript and CSS files (with stub for future CDN migration)

## Building

```bash
cargo build --release
```

## Usage

```bash
./target/release/wp2static_scraper \
  --base-url "https://example.com" \
  --output-dir "output" \
  --sitemap "sitemap_index.xml"
```

### Arguments

- `--base-url` or `-b`: The base URL of the WordPress site (required)
- `--output-dir` or `-o`: Output directory for markdown files (default: "output")
- `--sitemap` or `-s`: Path to sitemap file relative to base URL (default: "sitemap_index.xml")

### Example

```bash
# Scrape a WordPress site
./target/release/wp2static_scraper \
  --base-url "https://myblog.com" \
  --output-dir "markdown_output"
```

This will:
1. Fetch and parse `https://myblog.com/sitemap_index.xml`
2. Extract all URLs from the sitemap(s)
3. Scrape each page
4. Extract the main content using text density analysis
5. Convert to Markdown
6. Save each page as a `.md` file in `markdown_output/`

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
