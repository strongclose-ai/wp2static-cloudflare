use anyhow::{Context, Result};
use dom_content_extraction::{scraper::Html, DensityTree};
use std::path::{Path, PathBuf};
use url::Url;

use crate::asset_detector::AssetDetector;

/// Scrape a page and save it as markdown
pub fn scrape_page(url: &str, base_url: &str, output_dir: &Path) -> Result<PathBuf> {
    // Fetch the page content
    let response =
        reqwest::blocking::get(url).context(format!("Failed to fetch page from {}", url))?;

    let html = response.text().context("Failed to read page content")?;

    // Extract main content using dom-content-extraction
    let document = Html::parse_document(&html);
    let mut dtree =
        DensityTree::from_document(&document).context("Failed to create density tree")?;

    dtree
        .calculate_density_sum()
        .context("Failed to calculate density sum")?;

    let content = dtree
        .extract_content(&document)
        .context("Failed to extract content")?;

    // Convert to markdown
    let markdown = html2md::parse_html(&content);

    // Detect assets (JS/CSS) - stubbed for now
    let assets = AssetDetector::detect_assets(&html, base_url)?;

    // Add asset information to markdown as a comment
    let mut full_markdown = String::new();
    full_markdown.push_str(&format!("<!-- Source URL: {} -->\n", url));

    if !assets.is_empty() {
        full_markdown.push_str("<!-- Detected Assets:\n");
        for asset in &assets {
            full_markdown.push_str(&format!("  - {} (Type: {})\n", asset.url, asset.asset_type));
        }
        full_markdown.push_str("-->\n\n");
    }

    full_markdown.push_str(&markdown);

    // Generate output filename from URL
    let output_path = generate_output_path(url, base_url, output_dir)?;

    // Create parent directories if needed
    if let Some(parent) = output_path.parent() {
        std::fs::create_dir_all(parent)?;
    }

    // Write markdown to file
    std::fs::write(&output_path, full_markdown)
        .context(format!("Failed to write markdown to {:?}", output_path))?;

    Ok(output_path)
}

/// Generate output file path from URL
fn generate_output_path(url: &str, _base_url: &str, output_dir: &Path) -> Result<PathBuf> {
    let parsed_url = Url::parse(url).context(format!("Failed to parse URL: {}", url))?;

    // Get the path component
    let path = parsed_url.path();

    // Remove leading slash and convert to filesystem path
    let relative_path = path.trim_start_matches('/');

    // If path is empty or ends with /, use index.md
    let filename = if relative_path.is_empty() || relative_path.ends_with('/') {
        format!("{}index.md", relative_path)
    } else {
        // Replace file extension with .md or add .md if no extension
        let path_without_ext = if let Some(pos) = relative_path.rfind('.') {
            &relative_path[..pos]
        } else {
            relative_path
        };
        format!("{}.md", path_without_ext)
    };

    Ok(output_dir.join(filename))
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::path::PathBuf;

    #[test]
    fn test_generate_output_path_root() {
        let base_url = "https://example.com";
        let url = "https://example.com/";
        let output_dir = PathBuf::from("/tmp/output");

        let path = generate_output_path(url, base_url, &output_dir).unwrap();
        assert_eq!(path, PathBuf::from("/tmp/output/index.md"));
    }

    #[test]
    fn test_generate_output_path_page() {
        let base_url = "https://example.com";
        let url = "https://example.com/about/";
        let output_dir = PathBuf::from("/tmp/output");

        let path = generate_output_path(url, base_url, &output_dir).unwrap();
        assert_eq!(path, PathBuf::from("/tmp/output/about/index.md"));
    }

    #[test]
    fn test_generate_output_path_nested() {
        let base_url = "https://example.com";
        let url = "https://example.com/blog/2024/my-post/";
        let output_dir = PathBuf::from("/tmp/output");

        let path = generate_output_path(url, base_url, &output_dir).unwrap();
        assert_eq!(
            path,
            PathBuf::from("/tmp/output/blog/2024/my-post/index.md")
        );
    }

    #[test]
    fn test_generate_output_path_with_file() {
        let base_url = "https://example.com";
        let url = "https://example.com/page.html";
        let output_dir = PathBuf::from("/tmp/output");

        let path = generate_output_path(url, base_url, &output_dir).unwrap();
        assert_eq!(path, PathBuf::from("/tmp/output/page.md"));
    }
}
