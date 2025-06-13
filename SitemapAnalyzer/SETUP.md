# SEO Crawler Setup Guide

## System Requirements

- Python 3.8+
- 2GB RAM minimum
- Stable internet connection
- Modern web browser

## Installation

### Quick Setup
```bash
# Clone or download the project files
# Install required packages
pip install streamlit beautifulsoup4 pandas plotly requests trafilatura

# Run the application
streamlit run app.py --server.port 5000
```

### Using Requirements File
```bash
# Install from pyproject.toml
pip install -e .

# Or install individual packages
pip install streamlit==1.28.0 beautifulsoup4==4.12.2 pandas==2.1.0 plotly==5.15.0 requests==2.31.0 trafilatura==1.6.0
```

## Configuration

### Server Settings
The app runs on port 5000 by default. For custom configuration, create `.streamlit/config.toml`:

```toml
[server]
headless = true
address = "0.0.0.0"
port = 5000
maxUploadSize = 200

[theme]
base = "light"
```

### Environment Variables
No external API keys required. The app works with publicly accessible sitemaps.

## File Structure

```
seo-crawler/
├── app.py                 # Main Streamlit application
├── seo_crawler.py         # Core crawling logic
├── utils.py              # Utility functions for charts and data processing
├── README.md             # Comprehensive documentation
├── USER_GUIDE.md         # User instructions
├── SETUP.md              # This file
├── pyproject.toml        # Python dependencies
└── crawl_history_*.json  # User-specific crawl data (auto-generated)
```

## Running the Application

### Local Development
```bash
streamlit run app.py --server.port 5000
```

### Production Deployment
```bash
streamlit run app.py --server.headless true --server.address 0.0.0.0 --server.port 5000
```

## Data Storage

- Crawl histories stored as JSON files: `crawl_history_{user_id}.json`
- Each user gets unique access codes for data isolation
- No database required - file-based storage for simplicity
- Data persists between sessions

## Security Considerations

### Access Control
- User data isolated by unique 8-character codes
- No authentication system - access codes provide basic privacy
- Suitable for internal tools and trusted environments

### Rate Limiting
- Built-in delays between requests (2-5 seconds configurable)
- Respectful crawling to avoid overwhelming target servers
- No concurrent request limiting implemented

## Performance Notes

### Crawl Speed
- Depends on sitemap size and delay settings
- Typical performance: 10-20 pages per minute with 3-second delays
- Large sitemaps (1000+ pages) may take 1+ hours

### Memory Usage
- Stores all crawl data in memory during processing
- RAM requirements scale with sitemap size
- Consider pagination for very large sites

## Troubleshooting

### Common Startup Issues
```bash
# ModuleNotFoundError
pip install --upgrade streamlit beautifulsoup4 pandas plotly requests trafilatura

# Port already in use
streamlit run app.py --server.port 8501

# Permission issues
python -m streamlit run app.py
```

### Crawling Problems
- Verify target sitemap is accessible
- Check firewall/proxy settings
- Increase delay for rate-limited sites
- Some sites block automated requests

### Performance Issues
- Reduce delay for faster crawling (if appropriate)
- Monitor system resources during large crawls
- Consider crawling in smaller batches

## Development

### Adding Features
- Core crawling logic in `seo_crawler.py`
- UI components in `app.py`
- Chart generation in `utils.py`

### Testing
```bash
# Test with small sample sitemaps first
# Verify all dependencies installed correctly
python -c "import streamlit, beautifulsoup4, pandas, plotly, requests, trafilatura"
```

## Deployment Options

### Local Network
- Run on internal server
- Access via IP address: `http://192.168.1.100:5000`
- Suitable for team use

### Cloud Hosting
- Deploy to Streamlit Cloud, Heroku, or similar platforms
- Ensure sufficient memory allocation
- Configure appropriate timeouts for large crawls

### Docker (Optional)
```dockerfile
FROM python:3.9-slim
WORKDIR /app
COPY . .
RUN pip install streamlit beautifulsoup4 pandas plotly requests trafilatura
EXPOSE 5000
CMD ["streamlit", "run", "app.py", "--server.port", "5000", "--server.address", "0.0.0.0"]
```

## Monitoring

### Logs
- Streamlit generates basic access logs
- Crawl progress shown in real-time UI
- No persistent logging implemented

### Health Checks
- App responds on configured port
- Check `/health` endpoint availability (if implemented)
- Monitor memory usage during large crawls

---

*For production use, consider implementing additional authentication, logging, and monitoring based on your specific requirements.*