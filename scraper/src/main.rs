mod sitemap;
mod scraper;
mod asset_detector;

use anyhow::Result;
use clap::Parser;
use std::path::PathBuf;

#[derive(Parser, Debug)]
#[command(name = "wp2static_scraper")]
#[command(about = "A Rust scraper for WP2Static that converts pages to markdown", long_about = None)]
struct Args {
    /// Base URL of the WordPress site
    #[arg(short, long)]
    base_url: String,

    /// Output directory for markdown files
    #[arg(short, long, default_value = "output")]
    output_dir: PathBuf,

    /// Path to sitemap_index.xml (relative to base_url)
    #[arg(short, long, default_value = "sitemap_index.xml")]
    sitemap: String,
}

fn main() -> Result<()> {
    let args = Args::parse();

    println!("WP2Static Scraper");
    println!("Base URL: {}", args.base_url);
    println!("Output Directory: {}", args.output_dir.display());
    println!("Sitemap: {}", args.sitemap);

    // Create output directory if it doesn't exist
    std::fs::create_dir_all(&args.output_dir)?;

    // Parse sitemap and get all URLs
    println!("\n=== Parsing sitemap...");
    let urls = sitemap::parse_sitemap(&args.base_url, &args.sitemap)?;
    println!("Found {} URLs in sitemap", urls.len());

    // Scrape each page and convert to markdown
    println!("\n=== Scraping pages...");
    for (index, url) in urls.iter().enumerate() {
        println!("[{}/{}] Processing: {}", index + 1, urls.len(), url);
        
        match scraper::scrape_page(url, &args.base_url, &args.output_dir) {
            Ok(markdown_path) => {
                println!("  ✓ Saved to: {}", markdown_path.display());
            }
            Err(e) => {
                eprintln!("  ✗ Error: {}", e);
            }
        }
    }

    println!("\n=== Complete!");
    println!("Markdown files saved to: {}", args.output_dir.display());

    Ok(())
}
