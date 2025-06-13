#!/usr/bin/env python3
"""
WordPress SEO Crawler - Command Line Interface
This script provides a command-line interface for the SEO crawler that can be called from WordPress PHP
"""

import sys
import json
import argparse
from seo_crawler import ComprehensiveSEOCrawler
from utils import validate_sitemap_url
import traceback

def main():
    parser = argparse.ArgumentParser(description='SEO Crawler for WordPress')
    parser.add_argument('--sitemap-url', required=True, help='URL of the sitemap to crawl')
    parser.add_argument('--delay', type=float, default=1.0, help='Delay between requests in seconds')
    parser.add_argument('--max-pages', type=int, default=0, help='Maximum number of pages to crawl (0 = no limit)')
    parser.add_argument('--output', choices=['json', 'csv'], default='json', help='Output format')
    parser.add_argument('--session-id', help='Session ID for tracking progress')
    parser.add_argument('--action', choices=['crawl', 'validate'], default='crawl', help='Action to perform')
    
    args = parser.parse_args()
    
    try:
        if args.action == 'validate':
            # Validate sitemap URL
            is_valid, message = validate_sitemap_url(args.sitemap_url)
            result = {
                'success': is_valid,
                'message': message,
                'urls_found': 0
            }
            print(json.dumps(result))
            return
        
        # Initialize crawler
        crawler = ComprehensiveSEOCrawler(args.sitemap_url, args.delay)
        
        # Fetch sitemap URLs
        sitemap_urls = crawler.fetch_sitemap_urls()
        
        if not sitemap_urls:
            result = {
                'success': False,
                'error': 'No URLs found in sitemap',
                'data': []
            }
            print(json.dumps(result))
            return
        
        # Limit pages if specified
        if args.max_pages > 0:
            sitemap_urls = sitemap_urls[:args.max_pages]
        
        # Progress callback for WordPress integration
        def progress_callback(current, total, current_url):
            progress_data = {
                'type': 'progress',
                'current': current,
                'total': total,
                'url': current_url,
                'session_id': args.session_id
            }
            # Send progress to stderr so WordPress can capture it separately
            print(json.dumps(progress_data), file=sys.stderr)
        
        # Crawl all pages
        crawl_results = crawler.crawl_all_pages(progress_callback)
        
        # Generate summary
        summary = crawler.generate_summary_report(crawl_results)
        
        if args.output == 'json':
            result = {
                'success': True,
                'data': crawl_results,
                'summary': summary,
                'total_pages': len(crawl_results),
                'session_id': args.session_id
            }
            print(json.dumps(result))
        
        elif args.output == 'csv':
            # Generate CSV output
            import csv
            import io
            
            output = io.StringIO()
            writer = csv.writer(output)
            
            # Write header
            if crawl_results:
                header = list(crawl_results[0].keys())
                writer.writerow(header)
                
                # Write data
                for row in crawl_results:
                    writer.writerow([row.get(col, '') for col in header])
            
            print(output.getvalue())
    
    except Exception as e:
        error_result = {
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc(),
            'session_id': args.session_id
        }
        print(json.dumps(error_result))
        sys.exit(1)

if __name__ == '__main__':
    main()