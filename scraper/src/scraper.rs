use anyhow::{Context, Result};
use std::path::{Path, PathBuf};
use url::Url;

/// Scrape a page and save it as static HTML
pub fn scrape_page(url: &str, base_url: &str, output_dir: &Path) -> Result<PathBuf> {
    // Fetch the page content
    let response =
        reqwest::blocking::get(url).context(format!("Failed to fetch page from {}", url))?;

    let html = response.text().context("Failed to read page content")?;

    // Generate output filename from URL
    let output_path = generate_output_path(url, base_url, output_dir)?;

    // Create parent directories if needed
    if let Some(parent) = output_path.parent() {
        std::fs::create_dir_all(parent)?;
    }

    // Write HTML to file
    std::fs::write(&output_path, html)
        .context(format!("Failed to write HTML to {:?}", output_path))?;

    Ok(output_path)
}

/// Generate output file path from URL
/// Matches the PHP Crawler::transformPath logic
fn generate_output_path(url: &str, _base_url: &str, output_dir: &Path) -> Result<PathBuf> {
    let parsed_url = Url::parse(url).context(format!("Failed to parse URL: {}", url))?;

    // Get the path component
    let path = parsed_url.path();

    // Remove leading slash
    let relative_path = path.trim_start_matches('/');

    // Transform path to match PHP Crawler::transformPath logic:
    // - URLs ending with / become /index.html
    // - Other URLs stay as-is
    let filename = if relative_path.is_empty() || relative_path.ends_with('/') {
        format!("{}index.html", relative_path)
    } else {
        relative_path.to_string()
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
        assert_eq!(path, PathBuf::from("/tmp/output/index.html"));
    }

    #[test]
    fn test_generate_output_path_page() {
        let base_url = "https://example.com";
        let url = "https://example.com/about/";
        let output_dir = PathBuf::from("/tmp/output");

        let path = generate_output_path(url, base_url, &output_dir).unwrap();
        assert_eq!(path, PathBuf::from("/tmp/output/about/index.html"));
    }

    #[test]
    fn test_generate_output_path_nested() {
        let base_url = "https://example.com";
        let url = "https://example.com/blog/2024/my-post/";
        let output_dir = PathBuf::from("/tmp/output");

        let path = generate_output_path(url, base_url, &output_dir).unwrap();
        assert_eq!(
            path,
            PathBuf::from("/tmp/output/blog/2024/my-post/index.html")
        );
    }

    #[test]
    fn test_generate_output_path_with_file() {
        let base_url = "https://example.com";
        let url = "https://example.com/page.html";
        let output_dir = PathBuf::from("/tmp/output");

        let path = generate_output_path(url, base_url, &output_dir).unwrap();
        assert_eq!(path, PathBuf::from("/tmp/output/page.html"));
    }
}
