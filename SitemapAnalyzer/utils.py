import pandas as pd
import plotly.express as px
import plotly.graph_objects as go
from plotly.subplots import make_subplots
import streamlit as st
from urllib.parse import urlparse
import re

def validate_sitemap_url(url):
    """Validate if the provided URL is a valid sitemap URL"""
    if not url:
        return False, "Please enter a sitemap URL"
    
    # Basic URL validation
    try:
        parsed = urlparse(url)
        if not parsed.scheme or not parsed.netloc:
            return False, "Invalid URL format"
        
        if parsed.scheme not in ['http', 'https']:
            return False, "URL must start with http:// or https://"
        
        # Check if it looks like a sitemap
        if not (url.lower().endswith('.xml') or 'sitemap' in url.lower()):
            return False, "URL should be an XML sitemap (typically ends with .xml or contains 'sitemap')"
        
        return True, "Valid sitemap URL"
        
    except Exception as e:
        return False, f"Invalid URL: {str(e)}"

def create_summary_metrics(summary_data):
    """Create summary metrics display"""
    if not summary_data:
        return
    
    col1, col2, col3, col4 = st.columns(4)
    
    with col1:
        st.metric("Total Pages", summary_data.get('total_pages_crawled', 0))
        st.metric("Successful Crawls", summary_data.get('successful_pages', 0))
    
    with col2:
        st.metric("Pages with Redirects", summary_data.get('pages_with_redirects', 0))
        st.metric("HTTPS Pages", summary_data.get('https_pages', 0))
    
    with col3:
        st.metric("Avg Load Time (ms)", summary_data.get('avg_load_time', 0))
        st.metric("Avg Page Size (KB)", summary_data.get('avg_page_size', 0))
    
    with col4:
        st.metric("Avg Word Count", summary_data.get('avg_word_count', 0))
        st.metric("Images w/o Alt Text", summary_data.get('images_without_alt', 0))

def create_seo_issues_chart(summary_data, chart_key="seo_issues_chart"):
    """Create a bar chart showing SEO issues"""
    if not summary_data:
        return
    
    issues = {
        'Missing Title': summary_data.get('pages_without_title', 0),
        'Missing Meta Description': summary_data.get('pages_without_meta_description', 0),
        'Missing H1': summary_data.get('pages_without_h1', 0),
        'Duplicate Titles': summary_data.get('duplicate_titles', 0),
        'Duplicate Meta Descriptions': summary_data.get('duplicate_meta_descriptions', 0),
        'Missing Viewport': summary_data.get('pages_without_viewport', 0),
        'Missing Favicon': summary_data.get('pages_without_favicon', 0),
        'Incomplete Social Tags': summary_data.get('pages_incomplete_social_tags', 0)
    }
    
    # Filter out zero values
    issues = {k: v for k, v in issues.items() if v > 0}
    
    if issues:
        fig = px.bar(
            x=list(issues.values()),
            y=list(issues.keys()),
            orientation='h',
            title="SEO Issues Found",
            labels={'x': 'Number of Pages', 'y': 'Issue Type'}
        )
        fig.update_layout(height=400)
        st.plotly_chart(fig, use_container_width=True, key=chart_key)
    else:
        st.success("🎉 No major SEO issues found!")

def create_status_code_chart(crawl_data):
    """Create a pie chart showing status code distribution"""
    if not crawl_data:
        return
    
    status_codes = {}
    for page in crawl_data:
        status = str(page.get('status_code', 'Unknown'))
        status_codes[status] = status_codes.get(status, 0) + 1
    
    if len(status_codes) > 1:
        fig = px.pie(
            values=list(status_codes.values()),
            names=list(status_codes.keys()),
            title="HTTP Status Code Distribution"
        )
        st.plotly_chart(fig, use_container_width=True, key="status_code_chart")

def create_load_time_chart(crawl_data):
    """Create a histogram showing load time distribution"""
    if not crawl_data:
        return
    
    load_times = [page.get('load_time_ms', 0) for page in crawl_data if page.get('load_time_ms')]
    
    if load_times:
        fig = px.histogram(
            x=load_times,
            nbins=20,
            title="Page Load Time Distribution",
            labels={'x': 'Load Time (ms)', 'y': 'Number of Pages'}
        )
        
        # Configure toolbar to remove zoom tool but keep zoom in/out
        fig.update_layout(
            showlegend=False,
            dragmode=False
        )
        
        config = {
            'modeBarButtonsToRemove': ['zoom2d', 'select2d', 'lasso2d', 'autoScale2d'],
            'displayModeBar': True,
            'displaylogo': False
        }
        
        st.plotly_chart(fig, use_container_width=True, key="load_time_chart", config=config)

def format_crawl_data_for_display(crawl_data):
    """Format crawl data for display in data tables"""
    if not crawl_data:
        return pd.DataFrame()
    
    # Select comprehensive columns for display including schema and social media
    display_columns = [
        'original_url', 'status_code', 'title', 'title_length', 
        'meta_description', 'meta_description_length', 'h1', 'h1_count', 'word_count',
        'internal_links', 'external_links', 'images', 'images_without_alt',
        'load_time_ms', 'response_size_kb', 'is_https', 'has_redirects',
        'flesch_reading_ease', 'canonical_url', 'robots_content',
        # Schema markup fields
        'has_schema', 'schema_count', 'schema_types', 'schema_types_count',
        # Social media fields
        'has_og_tags', 'has_twitter_cards', 'social_tags_count', 'social_tags_complete',
        # Technical SEO
        'has_viewport', 'has_favicon', 'has_amp', 'has_hreflang', 'hreflang_count',
        # Content dates
        'date_published', 'date_modified', 'date_source'
    ]
    
    df = pd.DataFrame(crawl_data)
    
    # Filter to only include columns that exist in the data
    available_columns = [col for col in display_columns if col in df.columns]
    df_display = df[available_columns].copy()
    
    # Rename columns for better display
    new_column_names = []
    for col in df_display.columns:
        if col == 'original_url':
            new_column_names.append('URL')
        elif col == 'status_code':
            new_column_names.append('Status')
        elif col == 'title':
            new_column_names.append('Title')
        elif col == 'title_length':
            new_column_names.append('Title Length')
        elif col == 'meta_description':
            new_column_names.append('Meta Description')
        elif col == 'meta_description_length':
            new_column_names.append('Meta Desc Length')
        elif col == 'h1':
            new_column_names.append('H1 Tag')
        elif col == 'h1_count':
            new_column_names.append('H1 Count')
        elif col == 'word_count':
            new_column_names.append('Word Count')
        elif col == 'internal_links':
            new_column_names.append('Internal Links')
        elif col == 'external_links':
            new_column_names.append('External Links')
        elif col == 'images':
            new_column_names.append('Images')
        elif col == 'images_without_alt':
            new_column_names.append('Images w/o Alt')
        elif col == 'load_time_ms':
            new_column_names.append('Load Time (ms)')
        elif col == 'response_size_kb':
            new_column_names.append('Size (KB)')
        elif col == 'is_https':
            new_column_names.append('HTTPS')
        elif col == 'has_redirects':
            new_column_names.append('Has Redirects')
        elif col == 'flesch_reading_ease':
            new_column_names.append('Readability Score')
        elif col == 'canonical_url':
            new_column_names.append('Canonical URL')
        elif col == 'robots_content':
            new_column_names.append('Robots Meta')
        elif col == 'has_schema':
            new_column_names.append('Has Schema')
        elif col == 'schema_count':
            new_column_names.append('Schema Count')
        elif col == 'schema_types':
            new_column_names.append('Schema Types')
        elif col == 'schema_types_count':
            new_column_names.append('Schema Types Count')
        elif col == 'has_og_tags':
            new_column_names.append('Has Open Graph')
        elif col == 'has_twitter_cards':
            new_column_names.append('Has Twitter Cards')
        elif col == 'social_tags_count':
            new_column_names.append('Social Tags Count')
        elif col == 'social_tags_complete':
            new_column_names.append('Social Tags Complete')
        elif col == 'has_viewport':
            new_column_names.append('Has Viewport')
        elif col == 'has_favicon':
            new_column_names.append('Has Favicon')
        elif col == 'has_amp':
            new_column_names.append('Has AMP')
        elif col == 'has_hreflang':
            new_column_names.append('Has Hreflang')
        elif col == 'hreflang_count':
            new_column_names.append('Hreflang Count')
        elif col == 'date_published':
            new_column_names.append('Published Date')
        elif col == 'date_modified':
            new_column_names.append('Modified Date')
        elif col == 'date_source':
            new_column_names.append('Date Source')
        else:
            new_column_names.append(col)
    
    df_display.columns = new_column_names
    
    return df_display

def identify_critical_issues(crawl_data):
    """Identify and return critical SEO issues"""
    if not crawl_data:
        return []
    
    issues = []
    
    # Check for pages with critical issues
    for page in crawl_data:
        url = page.get('original_url', 'Unknown URL')
        page_issues = []
        
        # Critical issues
        if not page.get('title'):
            page_issues.append("Missing title tag")
        elif page.get('title_length', 0) > 60:
            page_issues.append("Title tag too long (>60 characters)")
        elif page.get('title_length', 0) < 10:
            page_issues.append("Title tag too short (<10 characters)")
        
        if not page.get('meta_description'):
            page_issues.append("Missing meta description")
        elif page.get('meta_description_length', 0) > 160:
            page_issues.append("Meta description too long (>160 characters)")
        
        if not page.get('h1'):
            page_issues.append("Missing H1 tag")
        elif page.get('h1_count', 0) > 1:
            page_issues.append("Multiple H1 tags")
        
        if page.get('status_code') not in [200, '200']:
            page_issues.append(f"Non-200 status code: {page.get('status_code')}")
        
        if not page.get('is_https'):
            page_issues.append("Not using HTTPS")
        
        if page.get('images_without_alt', 0) > 0:
            page_issues.append(f"{page.get('images_without_alt')} images without alt text")
        
        if page.get('load_time_ms', 0) > 3000:
            page_issues.append("Slow loading page (>3 seconds)")
        
        # Schema markup issues
        if not page.get('has_schema'):
            page_issues.append("Missing schema markup")
        
        # Social media issues
        if not page.get('has_og_tags'):
            page_issues.append("Missing Open Graph tags")
        
        if not page.get('has_twitter_cards'):
            page_issues.append("Missing Twitter Card tags")
        
        if not page.get('social_tags_complete'):
            page_issues.append("Incomplete social media tags")
        
        # Technical SEO issues
        if not page.get('has_viewport'):
            page_issues.append("Missing mobile viewport meta tag")
        
        if not page.get('has_favicon'):
            page_issues.append("Missing favicon")
        
        if not page.get('canonical_url'):
            page_issues.append("Missing canonical URL")
        
        if page_issues:
            issues.append({
                'url': url,
                'issues': page_issues,
                'issue_count': len(page_issues)
            })
    
    # Sort by number of issues
    issues.sort(key=lambda x: x['issue_count'], reverse=True)
    
    return issues

def export_to_csv(crawl_data, filename="seo_report.csv"):
    """Export crawl data to CSV format"""
    if not crawl_data:
        return None
    
    df = pd.DataFrame(crawl_data)
    return df.to_csv(index=False)

def create_recommendations(summary_data, crawl_data):
    """Generate SEO recommendations based on the crawl data"""
    if not summary_data or not crawl_data:
        return []
    
    recommendations = []
    total_pages = summary_data.get('successful_pages', 0)
    
    if total_pages == 0:
        return ["No successful page crawls to analyze"]
    
    # Title recommendations
    missing_titles = summary_data.get('pages_without_title', 0)
    if missing_titles > 0:
        recommendations.append(f"🚨 {missing_titles} pages are missing title tags. Add unique, descriptive titles to all pages.")
    
    duplicate_titles = summary_data.get('duplicate_titles', 0)
    if duplicate_titles > 0:
        recommendations.append(f"⚠️ {duplicate_titles} pages have duplicate title tags. Ensure each page has a unique title.")
    
    # Meta description recommendations
    missing_meta_desc = summary_data.get('pages_without_meta_description', 0)
    if missing_meta_desc > 0:
        recommendations.append(f"📝 {missing_meta_desc} pages are missing meta descriptions. Add compelling meta descriptions to improve click-through rates.")
    
    duplicate_meta_desc = summary_data.get('duplicate_meta_descriptions', 0)
    if duplicate_meta_desc > 0:
        recommendations.append(f"🔄 {duplicate_meta_desc} pages have duplicate meta descriptions. Write unique descriptions for each page.")
    
    # H1 recommendations
    missing_h1 = summary_data.get('pages_without_h1', 0)
    if missing_h1 > 0:
        recommendations.append(f"📋 {missing_h1} pages are missing H1 tags. Add a single, descriptive H1 tag to each page.")
    
    # Technical recommendations
    non_https = total_pages - summary_data.get('https_pages', 0)
    if non_https > 0:
        recommendations.append(f"🔒 {non_https} pages are not using HTTPS. Migrate to HTTPS for better security and SEO.")
    
    missing_viewport = summary_data.get('pages_without_viewport', 0)
    if missing_viewport > 0:
        recommendations.append(f"📱 {missing_viewport} pages are missing viewport meta tags. Add viewport tags for mobile optimization.")
    
    missing_favicon = summary_data.get('pages_without_favicon', 0)
    if missing_favicon > 0:
        recommendations.append(f"🎨 {missing_favicon} pages are missing favicons. Add favicons to improve branding and user experience.")
    
    # Image recommendations
    images_without_alt = summary_data.get('images_without_alt', 0)
    if images_without_alt > 0:
        recommendations.append(f"🖼️ {images_without_alt} images are missing alt text. Add descriptive alt text for accessibility and SEO.")
    
    # Performance recommendations
    avg_load_time = summary_data.get('avg_load_time', 0)
    if avg_load_time > 3000:
        recommendations.append(f"⚡ Average page load time is {avg_load_time}ms. Optimize page speed for better user experience and SEO.")
    
    # Social media recommendations
    incomplete_social = summary_data.get('pages_incomplete_social_tags', 0)
    if incomplete_social > 0:
        recommendations.append(f"📱 {incomplete_social} pages have incomplete social media tags. Add Open Graph and Twitter Card tags for better social sharing.")
    
    if not recommendations:
        recommendations.append("🎉 Great job! Your site follows most SEO best practices.")
    
    return recommendations
