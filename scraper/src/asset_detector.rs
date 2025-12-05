use anyhow::Result;
use scraper::{Html, Selector};
use std::collections::HashSet;
use url::Url;

#[derive(Debug, Clone)]
pub struct Asset {
    pub url: String,
    pub asset_type: AssetType,
}

#[derive(Debug, Clone)]
pub enum AssetType {
    JavaScript,
    Css,
}

impl std::fmt::Display for AssetType {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self {
            AssetType::JavaScript => write!(f, "JavaScript"),
            AssetType::Css => write!(f, "CSS"),
        }
    }
}

pub struct AssetDetector;

impl AssetDetector {
    /// Detect all self-hosted JavaScript and CSS assets in the HTML
    /// This is a stub implementation - the actual CDN migration API details will be provided later
    pub fn detect_assets(html: &str, base_url: &str) -> Result<Vec<Asset>> {
        let document = Html::parse_document(html);
        let mut assets = HashSet::new();

        // Detect CSS files
        let css_selector = Selector::parse("link[rel='stylesheet']")
            .expect("Failed to parse CSS selector - this should never happen");
        for element in document.select(&css_selector) {
            if let Some(href) = element.value().attr("href") {
                if let Some(absolute_url) = Self::resolve_url(href, base_url) {
                    if Self::is_self_hosted(&absolute_url, base_url) {
                        assets.insert(Asset {
                            url: absolute_url,
                            asset_type: AssetType::Css,
                        });
                    }
                }
            }
        }

        // Detect JavaScript files
        let js_selector = Selector::parse("script[src]")
            .expect("Failed to parse JS selector - this should never happen");
        for element in document.select(&js_selector) {
            if let Some(src) = element.value().attr("src") {
                if let Some(absolute_url) = Self::resolve_url(src, base_url) {
                    if Self::is_self_hosted(&absolute_url, base_url) {
                        assets.insert(Asset {
                            url: absolute_url,
                            asset_type: AssetType::JavaScript,
                        });
                    }
                }
            }
        }

        // Convert to sorted vector for consistent output
        let mut result: Vec<Asset> = assets.into_iter().collect();
        result.sort_by(|a, b| a.url.cmp(&b.url));

        Ok(result)
    }

    /// Resolve a relative URL to an absolute URL
    fn resolve_url(href: &str, base_url: &str) -> Option<String> {
        // Handle data URLs, javascript:, etc.
        if href.starts_with("data:") || href.starts_with("javascript:") || href.starts_with("#") {
            return None;
        }

        // Parse base URL
        let base = match Url::parse(base_url) {
            Ok(url) => url,
            Err(_) => return None,
        };

        // Handle absolute URLs
        if href.starts_with("http://") || href.starts_with("https://") {
            return Some(href.to_string());
        }

        // Handle protocol-relative URLs
        if href.starts_with("//") {
            return Some(format!("{}:{}", base.scheme(), href));
        }

        // Resolve relative URL
        match base.join(href) {
            Ok(url) => Some(url.to_string()),
            Err(_) => None,
        }
    }

    /// Check if a URL is self-hosted (same domain as base URL)
    fn is_self_hosted(url: &str, base_url: &str) -> bool {
        let parsed_url = match Url::parse(url) {
            Ok(u) => u,
            Err(_) => return false,
        };

        let base = match Url::parse(base_url) {
            Ok(u) => u,
            Err(_) => return false,
        };

        // Check if domains match
        parsed_url.host_str() == base.host_str()
    }

    /// Stub method for CDN migration
    /// TODO: Implement actual CDN migration logic when API details are provided
    #[allow(dead_code)]
    pub fn migrate_to_cdn(_asset: &Asset) -> Result<String> {
        // This is a stub - actual implementation will be provided later
        // Should return the new CDN URL for the asset
        unimplemented!("CDN migration API details to be provided")
    }
}

impl PartialEq for Asset {
    fn eq(&self, other: &Self) -> bool {
        self.url == other.url
    }
}

impl Eq for Asset {}

impl std::hash::Hash for Asset {
    fn hash<H: std::hash::Hasher>(&self, state: &mut H) {
        self.url.hash(state);
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_detect_css_assets() {
        let html = r#"
        <html>
            <head>
                <link rel="stylesheet" href="/wp-content/themes/mytheme/style.css">
                <link rel="stylesheet" href="https://external.com/style.css">
            </head>
        </html>
        "#;

        let base_url = "https://example.com";
        let assets = AssetDetector::detect_assets(html, base_url).unwrap();

        // Should only detect self-hosted CSS
        let css_assets: Vec<_> = assets
            .iter()
            .filter(|a| matches!(a.asset_type, AssetType::Css))
            .collect();

        assert_eq!(css_assets.len(), 1);
        assert!(css_assets[0].url.contains("example.com"));
    }

    #[test]
    fn test_detect_js_assets() {
        let html = r#"
        <html>
            <body>
                <script src="/wp-includes/js/jquery.js"></script>
                <script src="https://external.com/script.js"></script>
            </body>
        </html>
        "#;

        let base_url = "https://example.com";
        let assets = AssetDetector::detect_assets(html, base_url).unwrap();

        // Should only detect self-hosted JS
        let js_assets: Vec<_> = assets
            .iter()
            .filter(|a| matches!(a.asset_type, AssetType::JavaScript))
            .collect();

        assert_eq!(js_assets.len(), 1);
        assert!(js_assets[0].url.contains("example.com"));
    }

    #[test]
    fn test_is_self_hosted() {
        let base_url = "https://example.com";

        assert!(AssetDetector::is_self_hosted(
            "https://example.com/style.css",
            base_url
        ));
        assert!(!AssetDetector::is_self_hosted(
            "https://external.com/style.css",
            base_url
        ));
    }

    #[test]
    fn test_resolve_url() {
        let base_url = "https://example.com/blog/";

        // Absolute URL
        assert_eq!(
            AssetDetector::resolve_url("https://other.com/style.css", base_url).unwrap(),
            "https://other.com/style.css"
        );

        // Root-relative URL
        assert_eq!(
            AssetDetector::resolve_url("/style.css", base_url).unwrap(),
            "https://example.com/style.css"
        );

        // Relative URL
        assert_eq!(
            AssetDetector::resolve_url("style.css", base_url).unwrap(),
            "https://example.com/blog/style.css"
        );

        // Data URL should return None
        assert!(AssetDetector::resolve_url("data:image/png;base64,xyz", base_url).is_none());
    }
}
