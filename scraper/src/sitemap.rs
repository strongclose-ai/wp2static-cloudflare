use anyhow::{Context, Result};
use quick_xml::events::Event;
use quick_xml::Reader;
use std::collections::HashSet;

/// Parse sitemap_index.xml and extract all URLs
pub fn parse_sitemap(base_url: &str, sitemap_path: &str) -> Result<Vec<String>> {
    let sitemap_url = format!("{}/{}", base_url.trim_end_matches('/'), sitemap_path);
    
    let response = reqwest::blocking::get(&sitemap_url)
        .context(format!("Failed to fetch sitemap from {}", sitemap_url))?;
    
    let content = response.text()
        .context("Failed to read sitemap content")?;
    
    let mut urls = HashSet::new();
    
    // First, check if this is a sitemap index
    let sitemap_urls = parse_sitemap_index(&content)?;
    
    if !sitemap_urls.is_empty() {
        // This is a sitemap index, parse each referenced sitemap
        println!("Found {} sitemap(s) in index", sitemap_urls.len());
        for sitemap_url in sitemap_urls {
            match fetch_and_parse_sitemap(&sitemap_url) {
                Ok(mut page_urls) => {
                    println!("  - {} URLs from {}", page_urls.len(), sitemap_url);
                    urls.extend(page_urls.drain(..));
                }
                Err(e) => {
                    eprintln!("  - Error parsing {}: {}", sitemap_url, e);
                }
            }
        }
    } else {
        // This is a regular sitemap, parse URLs directly
        urls.extend(parse_url_set(&content)?);
    }
    
    Ok(urls.into_iter().collect())
}

/// Parse sitemap index and return list of sitemap URLs
fn parse_sitemap_index(content: &str) -> Result<Vec<String>> {
    let mut reader = Reader::from_str(content);
    reader.config_mut().trim_text(true);
    
    let mut urls = Vec::new();
    let mut buf = Vec::new();
    let mut in_loc = false;
    
    loop {
        match reader.read_event_into(&mut buf) {
            Ok(Event::Start(e)) if e.name().as_ref() == b"loc" => {
                in_loc = true;
            }
            Ok(Event::Text(e)) if in_loc => {
                let url = e.unescape()?.into_owned();
                urls.push(url);
                in_loc = false;
            }
            Ok(Event::End(e)) if e.name().as_ref() == b"loc" => {
                in_loc = false;
            }
            Ok(Event::Eof) => break,
            Err(e) => {
                return Err(anyhow::anyhow!("Error parsing sitemap index: {:?}", e));
            }
            _ => {}
        }
        buf.clear();
    }
    
    Ok(urls)
}

/// Fetch and parse a sitemap URL
fn fetch_and_parse_sitemap(url: &str) -> Result<Vec<String>> {
    let response = reqwest::blocking::get(url)
        .context(format!("Failed to fetch sitemap from {}", url))?;
    
    let content = response.text()
        .context("Failed to read sitemap content")?;
    
    parse_url_set(&content)
}

/// Parse URL set from sitemap content
fn parse_url_set(content: &str) -> Result<Vec<String>> {
    let mut reader = Reader::from_str(content);
    reader.config_mut().trim_text(true);
    
    let mut urls = Vec::new();
    let mut buf = Vec::new();
    let mut in_loc = false;
    
    loop {
        match reader.read_event_into(&mut buf) {
            Ok(Event::Start(e)) if e.name().as_ref() == b"loc" => {
                in_loc = true;
            }
            Ok(Event::Text(e)) if in_loc => {
                let url = e.unescape()?.into_owned();
                urls.push(url);
                in_loc = false;
            }
            Ok(Event::End(e)) if e.name().as_ref() == b"loc" => {
                in_loc = false;
            }
            Ok(Event::Eof) => break,
            Err(e) => {
                return Err(anyhow::anyhow!("Error parsing sitemap: {:?}", e));
            }
            _ => {}
        }
        buf.clear();
    }
    
    Ok(urls)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_parse_sitemap_index() {
        let xml = r#"<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>https://example.com/sitemap1.xml</loc>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap2.xml</loc>
    </sitemap>
</sitemapindex>"#;
        
        let urls = parse_sitemap_index(xml).unwrap();
        assert_eq!(urls.len(), 2);
        assert_eq!(urls[0], "https://example.com/sitemap1.xml");
        assert_eq!(urls[1], "https://example.com/sitemap2.xml");
    }

    #[test]
    fn test_parse_url_set() {
        let xml = r#"<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/page1/</loc>
    </url>
    <url>
        <loc>https://example.com/page2/</loc>
    </url>
</urlset>"#;
        
        let urls = parse_url_set(xml).unwrap();
        assert_eq!(urls.len(), 2);
        assert_eq!(urls[0], "https://example.com/page1/");
        assert_eq!(urls[1], "https://example.com/page2/");
    }
}
