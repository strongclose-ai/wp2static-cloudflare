// CDN migration feature - currently disabled
// #[cfg(feature = "cdn-migration")]
// mod asset_detector;

mod scraper;
mod sitemap;

use anyhow::Result;
use clap::Parser;
use std::path::PathBuf;
use std::sync::atomic::{AtomicUsize, Ordering};
use std::sync::{Arc, Mutex};

#[derive(Parser, Debug)]
#[command(name = "wp2static_scraper")]
#[command(about = "A Rust scraper for WP2Static that saves static HTML pages", long_about = None)]
struct Args {
    /// Base URL of the WordPress site
    #[arg(short, long)]
    base_url: String,

    /// Output directory for static HTML files
    #[arg(short, long, default_value = "output")]
    output_dir: PathBuf,

    /// Path to sitemap_index.xml (relative to base_url)
    #[arg(short, long, default_value = "sitemap_index.xml")]
    sitemap: String,

    /// Number of concurrent workers for scraping (default: 4)
    #[arg(short, long, default_value = "4")]
    concurrency: usize,
}

fn main() -> Result<()> {
    let args = Args::parse();

    println!("WP2Static Scraper v1.0 (High Performance)");
    println!("Base URL: {}", args.base_url);
    println!("Output Directory: {}", args.output_dir.display());
    println!("Sitemap: {}", args.sitemap);
    println!("Concurrency: {} workers", args.concurrency);

    // Create output directory if it doesn't exist
    std::fs::create_dir_all(&args.output_dir)?;

    // Parse sitemap and get all URLs
    println!("\n=== Parsing sitemap...");
    let start_time = std::time::Instant::now();
    let urls = sitemap::parse_sitemap(&args.base_url, &args.sitemap)?;
    println!(
        "Found {} URLs in sitemap ({:.2}s)",
        urls.len(),
        start_time.elapsed().as_secs_f64()
    );

    if urls.is_empty() {
        println!("No URLs found to process.");
        return Ok(());
    }

    // Scrape pages in parallel
    println!("\n=== Scraping pages with {} workers...", args.concurrency);
    let scrape_start = std::time::Instant::now();

    let success_count = Arc::new(AtomicUsize::new(0));
    let error_count = Arc::new(AtomicUsize::new(0));
    let output_lock = Arc::new(Mutex::new(()));

    // Use rayon for parallel processing
    use rayon::prelude::*;
    rayon::ThreadPoolBuilder::new()
        .num_threads(args.concurrency)
        .build()
        .unwrap_or_else(|e| {
            eprintln!("Failed to create Rayon thread pool: {}", e);
            std::process::exit(1);
        })
        .install(|| {
            urls.par_iter().enumerate().for_each(|(index, url)| {
                let progress = format!("[{}/{}]", index + 1, urls.len());

                match scraper::scrape_page(url, &args.base_url, &args.output_dir) {
                    Ok(html_path) => {
                        success_count.fetch_add(1, Ordering::Relaxed);
                        // Lock stdout to prevent interleaved output
                        let _lock = output_lock.lock().unwrap();
                        println!("{} ✓ {}", progress, html_path.display());
                    }
                    Err(e) => {
                        error_count.fetch_add(1, Ordering::Relaxed);
                        // Lock stderr to prevent interleaved output
                        let _lock = output_lock.lock().unwrap();
                        eprintln!("{} ✗ {}: {}", progress, url, e);
                    }
                }
            });
        });

    let elapsed = scrape_start.elapsed().as_secs_f64();
    let total_time = start_time.elapsed().as_secs_f64();
    let success = success_count.load(Ordering::Relaxed);
    let errors = error_count.load(Ordering::Relaxed);

    println!("\n=== Complete!");
    println!("Total URLs: {}", urls.len());
    println!("Successful: {}", success);
    println!("Errors: {}", errors);
    println!("Scraping time: {:.2}s", elapsed);
    println!("Total time: {:.2}s", total_time);
    println!(
        "Average: {:.2} pages/sec",
        urls.len() as f64 / elapsed.max(0.001)
    );
    println!("HTML files saved to: {}", args.output_dir.display());

    Ok(())
}
