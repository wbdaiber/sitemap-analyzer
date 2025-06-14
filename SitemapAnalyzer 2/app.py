import streamlit as st
import streamlit.components.v1 as components
import pandas as pd
import time
from datetime import datetime
import io
import json
from urllib.parse import urlparse
from seo_crawler import ComprehensiveSEOCrawler
from utils import (
    validate_sitemap_url, create_summary_metrics, create_seo_issues_chart,
    create_status_code_chart, create_load_time_chart, format_crawl_data_for_display,
    identify_critical_issues, export_to_csv, create_recommendations
)

# Page config
st.set_page_config(
    page_title="SEO Crawler - Comprehensive Site Analysis",
    page_icon="🔍",
    layout="wide",
    initial_sidebar_state="expanded"
)

# Custom CSS for better styling
st.markdown("""
<style>
    .main-header {
        text-align: center;
        padding: 2rem 0;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    .metric-card {
        background: #f0f2f6;
        padding: 1rem;
        border-radius: 10px;
        border-left: 4px solid #667eea;
    }
    .issue-card {
        background: #fff3cd;
        padding: 1rem;
        border-radius: 10px;
        border-left: 4px solid #ffc107;
        margin: 0.5rem 0;
        color: #000;
    }
    .success-card {
        background: #d4edda;
        padding: 1rem;
        border-radius: 10px;
        border-left: 4px solid #28a745;
        color: #000;
    }
    .error-card {
        background: #f8d7da;
        padding: 1rem;
        border-radius: 10px;
        border-left: 4px solid #dc3545;
    }
</style>
""", unsafe_allow_html=True)

# Header
st.markdown("""
<div class="main-header">
    <h1>🔍 SEO Crawler</h1>
    <p>Comprehensive Site Analysis & SEO Report Generator</p>
</div>
""", unsafe_allow_html=True)

# User-based storage functions with persistent browser identification
def get_user_id():
    """Get or create a persistent user ID"""
    if 'user_id' not in st.session_state:
        # Check if user has entered a custom identifier
        if 'custom_user_id' in st.session_state and st.session_state.custom_user_id:
            st.session_state.user_id = st.session_state.custom_user_id
        else:
            # Generate a unique user ID
            import uuid
            st.session_state.user_id = str(uuid.uuid4())[:8]  # Shorter ID for display
    
    return st.session_state.user_id

def load_crawl_history():
    """Load crawl history from user-specific file"""
    user_id = get_user_id()
    filename = f'crawl_history_{user_id}.json'
    try:
        with open(filename, 'r') as f:
            return json.load(f)
    except (FileNotFoundError, json.JSONDecodeError):
        return []

def save_crawl_history(history):
    """Save crawl history to user-specific file"""
    user_id = get_user_id()
    filename = f'crawl_history_{user_id}.json'
    try:
        with open(filename, 'w') as f:
            json.dump(history, f, indent=2)
    except Exception as e:
        st.error(f"Could not save crawl history: {str(e)}")

# Initialize session state and user persistence
if 'crawl_data' not in st.session_state:
    st.session_state.crawl_data = None
if 'summary_data' not in st.session_state:
    st.session_state.summary_data = None

# Initialize user ID for persistent storage
get_user_id()
if 'crawl_in_progress' not in st.session_state:
    st.session_state.crawl_in_progress = False
if 'crawl_history' not in st.session_state:
    st.session_state.crawl_history = load_crawl_history()

# Main crawl settings section (moved from sidebar)
st.header("🛠️ Crawl Settings")

col1, col2 = st.columns([2, 1])

with col1:
    # Sitemap URL input
    sitemap_url = st.text_input(
        "Sitemap URL",
        placeholder="https://example.com/sitemap.xml",
        help="Enter the URL of the XML sitemap you want to analyze"
    )

with col2:
    # Crawl delay
    delay = st.slider(
        "Delay between requests (seconds)",
        min_value=2.0,
        max_value=5.0,
        value=3.0,
        step=0.5,
        help="Time to wait between each page request to be respectful to the server"
    )

# Validate URL and show status
if sitemap_url:
    is_valid, message = validate_sitemap_url(sitemap_url)
    if is_valid:
        st.success("✅ Valid sitemap URL")
    else:
        st.error(f"❌ {message}")

# Start crawl button
start_crawl = st.button(
    "🚀 Start SEO Crawl",
    disabled=not sitemap_url or not validate_sitemap_url(sitemap_url)[0] or st.session_state.crawl_in_progress,
    type="primary"
)

st.markdown("---")

# Sidebar for user identification and crawl history
with st.sidebar:
    # User Identification Section
    st.header("👤 Your Access Code")
    current_user_id = get_user_id()
    
    # Display user's access code prominently
    st.success(f"**Your Access Code: `{current_user_id}`**")
    st.caption("Save this code to access your crawl history from any device/browser")
    
    # Instructions
    with st.expander("📋 How to Access Your Crawl History"):
        st.markdown("""
        **To access your crawl history later:**
        1. Copy your Access Code: `{}`
        2. Save it somewhere safe (notes, email, etc.)
        3. When you return, enter this code below
        4. Click "Load My History"
        
        **Your crawl history is private** - only someone with your exact Access Code can see it.
        """.format(current_user_id))
    
    # Access code input for returning users
    st.subheader("Returning User?")
    saved_code = st.text_input(
        "Enter Your Access Code",
        placeholder="Paste your saved access code here",
        help="Enter the access code you saved from a previous session"
    )
    
    col1, col2 = st.columns(2)
    with col1:
        if st.button("Load My History") and saved_code:
            cleaned_code = saved_code.strip()
            if len(cleaned_code) >= 8:
                st.session_state.user_id = cleaned_code
                st.session_state.crawl_history = load_crawl_history()
                st.success(f"Loaded history for: {cleaned_code}")
                st.rerun()
            else:
                st.error("Access code must be at least 8 characters")
    
    with col2:
        if st.button("Generate New Code"):
            import uuid
            st.session_state.user_id = str(uuid.uuid4())[:8]
            st.session_state.crawl_history = load_crawl_history()
            st.rerun()
    
    st.markdown("---")
    
    # Crawl History - always show if there is history
    if st.session_state.crawl_history:
        st.header("📚 Crawl History")
        
        history_options = []
        for i, entry in enumerate(reversed(st.session_state.crawl_history)):
            domain = urlparse(entry['sitemap_url']).netloc
            history_options.append(f"{entry['timestamp']} - {domain} ({entry['pages_crawled']} pages)")
        
        selected_history = st.selectbox(
            "Load Previous Crawl",
            options=["Current Results"] + history_options,
            help="Select a previous crawl to view its results"
        )
        
        if selected_history != "Current Results":
            # Find the selected entry
            selected_index = history_options.index(selected_history)
            actual_index = len(st.session_state.crawl_history) - 1 - selected_index
            selected_entry = st.session_state.crawl_history[actual_index]
            
            if st.button("📂 Load Selected Crawl"):
                st.session_state.crawl_data = selected_entry['crawl_data']
                st.session_state.summary_data = selected_entry['summary_data']
                st.rerun()
        
        # Clear history
        if st.button("🗑️ Clear History"):
            st.session_state.crawl_history = []
            save_crawl_history([])  # Clear persistent storage too
            st.rerun()
        
        st.markdown("---")
    
    # Export options - show if there's current data
    if st.session_state.crawl_data:
        st.header("📥 Export Options")
        
        # Export to CSV
        csv_data = export_to_csv(st.session_state.crawl_data)
        if csv_data:
            st.download_button(
                label="📊 Download CSV Report",
                data=csv_data,
                file_name=f"seo_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv",
                mime="text/csv"
            )
        
        # Clear current results
        if st.button("🗑️ Clear Current Results"):
            st.session_state.crawl_data = None
            st.session_state.summary_data = None
            st.rerun()
    
    # Show info message only if no history and no current data
    if not st.session_state.crawl_history and not st.session_state.crawl_data:
        st.info("Complete a crawl to access export options and build history")

# Main content area
if start_crawl and sitemap_url:
    st.session_state.crawl_in_progress = True
    
    # Create progress tracking
    progress_bar = st.progress(0)
    status_text = st.empty()
    current_url_text = st.empty()
    
    # Initialize crawler
    crawler = ComprehensiveSEOCrawler(sitemap_url, delay=delay)
    
    # Progress callback function
    def update_progress(current, total, current_url):
        progress = current / total
        progress_bar.progress(progress)
        status_text.text(f"Crawling page {current} of {total}")
        current_url_text.text(f"Current URL: {current_url}")
    
    try:
        with st.spinner("Starting crawl..."):
            # Start crawling
            crawl_data = crawler.crawl_all_pages(progress_callback=update_progress)
            
            if crawl_data:
                # Generate summary
                summary_data = crawler.generate_summary_report(crawl_data)
                
                # Store in session state
                st.session_state.crawl_data = crawl_data
                st.session_state.summary_data = summary_data
                
                # Save to crawl history
                crawl_entry = {
                    'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                    'sitemap_url': sitemap_url,
                    'pages_crawled': len(crawl_data),
                    'delay_used': delay,
                    'crawl_data': crawl_data,
                    'summary_data': summary_data
                }
                st.session_state.crawl_history.append(crawl_entry)
                
                # Keep only last 10 crawls to manage memory
                if len(st.session_state.crawl_history) > 10:
                    st.session_state.crawl_history = st.session_state.crawl_history[-10:]
                
                # Save to persistent storage
                save_crawl_history(st.session_state.crawl_history)
                
                st.success(f"✅ Crawl completed! Analyzed {len(crawl_data)} pages.")
                st.rerun()  # Force page refresh to update sidebar
            else:
                st.error("❌ No data could be crawled. Please check the sitemap URL.")
    
    except Exception as e:
        st.error(f"❌ An error occurred during crawling: {str(e)}")
    
    finally:
        st.session_state.crawl_in_progress = False
        # Clear progress indicators
        progress_bar.empty()
        status_text.empty()
        current_url_text.empty()

# Display results if available
if st.session_state.crawl_data and st.session_state.summary_data:
    st.header("📊 SEO Analysis Results")
    
    # Summary metrics
    st.subheader("📈 Overview Metrics")
    create_summary_metrics(st.session_state.summary_data)
    
    # Create tabs for different sections
    tab1, tab2, tab3, tab4, tab5 = st.tabs([
        "📋 Detailed Data", "🎯 Key Issues", "⚡ Page Speed", 
        "⚠️ Critical Issues", "💡 Recommendations"
    ])
    
    with tab1:
        st.subheader("📋 Detailed Crawl Data")
        
        # Format data for display
        df_display = format_crawl_data_for_display(st.session_state.crawl_data)
        
        if not df_display.empty:
            # Search/filter functionality
            search_term = st.text_input("🔍 Search URLs", placeholder="Enter URL or keyword to filter")
            
            if search_term:
                mask = df_display['URL'].str.contains(search_term, case=False, na=False)
                df_filtered = df_display[mask]
                st.write(f"Showing {len(df_filtered)} results for '{search_term}'")
            else:
                df_filtered = df_display
            
            # Display data with formatting
            st.dataframe(
                df_filtered,
                use_container_width=True,
                hide_index=True,
                column_config={
                    "URL": st.column_config.LinkColumn("URL"),
                    "HTTPS": st.column_config.CheckboxColumn("HTTPS"),
                    "Has Redirects": st.column_config.CheckboxColumn("Has Redirects"),
                }
            )
        else:
            st.warning("No data available to display")
    
    with tab2:
        st.subheader("🎯 SEO Issues Overview")
        create_seo_issues_chart(st.session_state.summary_data, "tab2_seo_issues_chart")
        
        # Quick stats
        col1, col2 = st.columns(2)
        
        with col1:
            st.markdown("### 📝 Content Issues")
            content_issues = []
            if st.session_state.summary_data.get('pages_without_title', 0) > 0:
                content_issues.append(f"• {st.session_state.summary_data['pages_without_title']} pages without titles")
            if st.session_state.summary_data.get('pages_without_meta_description', 0) > 0:
                content_issues.append(f"• {st.session_state.summary_data['pages_without_meta_description']} pages without meta descriptions")
            if st.session_state.summary_data.get('duplicate_titles', 0) > 0:
                content_issues.append(f"• {st.session_state.summary_data['duplicate_titles']} duplicate titles")
            
            if content_issues:
                for issue in content_issues:
                    st.markdown(f'<div class="issue-card">{issue}</div>', unsafe_allow_html=True)
            else:
                st.markdown('<div class="success-card">✅ No major content issues found!</div>', unsafe_allow_html=True)
        
        with col2:
            st.markdown("### 🔧 Technical Issues")
            tech_issues = []
            non_https = st.session_state.summary_data.get('successful_pages', 0) - st.session_state.summary_data.get('https_pages', 0)
            if non_https > 0:
                tech_issues.append(f"• {non_https} pages not using HTTPS")
            if st.session_state.summary_data.get('pages_without_viewport', 0) > 0:
                tech_issues.append(f"• {st.session_state.summary_data['pages_without_viewport']} pages without viewport meta tag")
            if st.session_state.summary_data.get('images_without_alt', 0) > 0:
                tech_issues.append(f"• {st.session_state.summary_data['images_without_alt']} images without alt text")
            
            if tech_issues:
                for issue in tech_issues:
                    st.markdown(f'<div class="issue-card">{issue}</div>', unsafe_allow_html=True)
            else:
                st.markdown('<div class="success-card">✅ No major technical issues found!</div>', unsafe_allow_html=True)
    
    with tab3:
        # Status Code chart in single column
        create_status_code_chart(st.session_state.crawl_data)
        
        # Load time chart gets full width
        st.subheader("⚡ Page Load Time Analysis")
        create_load_time_chart(st.session_state.crawl_data)
    
    with tab4:
        st.subheader("⚠️ Critical Issues by Page")
        
        critical_issues = identify_critical_issues(st.session_state.crawl_data)
        
        if critical_issues:
            # Show top 10 most problematic pages
            st.write(f"Found {len(critical_issues)} pages with issues. Showing the most critical:")
            
            for i, issue_data in enumerate(critical_issues[:10], 1):
                with st.expander(f"#{i} {issue_data['url']} ({issue_data['issue_count']} issues)"):
                    for issue in issue_data['issues']:
                        st.markdown(f"• {issue}")
        else:
            st.success("🎉 No critical issues found! Your site is in great shape.")
    
    with tab5:
        st.subheader("💡 SEO Recommendations")
        
        recommendations = create_recommendations(st.session_state.summary_data, st.session_state.crawl_data)
        
        if recommendations:
            for i, recommendation in enumerate(recommendations, 1):
                st.markdown(f"**{i}.** {recommendation}")
        else:
            st.success("🎉 No recommendations needed - your SEO is on point!")

# Instructions/Help section
elif not st.session_state.crawl_data:
    st.header("🎯 How to Use This SEO Crawler")
    
    col1, col2 = st.columns(2)
    
    with col1:
        st.markdown("""
        ### 📋 Getting Started
        
        1. **Enter your sitemap URL** in the sidebar
           - Usually found at: `yoursite.com/sitemap.xml`
           - Must be a valid XML sitemap
        
        2. **Adjust crawl settings**
           - Set delay between requests (be respectful!)
           - Default 1 second delay is recommended
        
        3. **Click "Start SEO Crawl"**
           - Watch the progress in real-time
           - Analysis begins automatically after crawling
        
        4. **Review your results**
           - Overview metrics and charts
           - Detailed page-by-page analysis
           - Critical issues and recommendations
        """)
    
    with col2:
        st.markdown("""
        ### 🔍 What We Analyze
        
        **Technical SEO:**
        - Title tags and meta descriptions
        - Header structure (H1, H2, etc.)
        - HTTPS usage and redirects
        - Mobile viewport configuration
        
        **Content Quality:**
        - Word count and readability
        - Image alt text usage
        - Internal/external link structure
        - Duplicate content detection
        
        **Performance:**
        - Page load times
        - Response sizes
        - HTTP status codes
        
        **Social Media:**
        - Open Graph tags
        - Twitter Card implementation
        """)
    
    st.markdown("---")
    
    # Example sitemaps
    st.subheader("📝 Example Sitemap URLs")
    st.markdown("""
    If you're not sure where to find your sitemap, try these common locations:
    - `https://yourwebsite.com/sitemap.xml`
    - `https://yourwebsite.com/sitemap_index.xml`
    - `https://yourwebsite.com/wp-sitemap.xml` (WordPress)
    - Check your `robots.txt` file for sitemap declarations
    """)
    
    st.info("💡 **Tip:** Always respect website crawling policies and use appropriate delays between requests.")

# Footer
st.markdown("---")
st.markdown("""
<div style='text-align: center; color: #666; padding: 2rem;'>
    <p>🔍 SEO Crawler - Comprehensive Site Analysis Tool</p>
    <p>Built with Streamlit • Analyze responsibly</p>
</div>
""", unsafe_allow_html=True)
