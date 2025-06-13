# SEO Crawler WordPress Plugin

A comprehensive WordPress plugin that duplicates the functionality of the Streamlit SEO Crawler application, providing both admin dashboard and public shortcode interfaces.

## Features

- **Complete Streamlit Interface Recreation**: Identical look, feel, and functionality to the original Streamlit app
- **Admin Dashboard**: Full-featured SEO analysis interface in WordPress admin
- **Public Shortcodes**: Frontend widgets for public SEO analysis
- **Persistent User Sessions**: Cross-browser session persistence with unique user IDs
- **Comprehensive SEO Analysis**: All features from the original Streamlit app
- **Background Processing**: Non-blocking crawl execution using Python scripts
- **Export Functionality**: CSV export of detailed results
- **Crawl History**: Complete session management and history tracking

## Installation

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Python 3.7+ with required packages:
  - requests
  - beautifulsoup4
  - trafilatura
  - pandas (optional)

### Installation Steps

1. **Upload Plugin Files**
   ```bash
   # Upload the entire wordpress-plugin folder to:
   /wp-content/plugins/seo-crawler-plugin/
   ```

2. **Install Python Dependencies**
   ```bash
   cd /wp-content/plugins/seo-crawler-plugin/python/
   pip install requests beautifulsoup4 trafilatura
   ```

3. **Set File Permissions**
   ```bash
   chmod +x python/wp_crawler.py
   chmod 755 python/
   ```

4. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "SEO Crawler Plugin"
   - Click "Activate"

## Usage

### Admin Interface

Access the admin interface at:
- **Main Crawler**: `/wp-admin/admin.php?page=seo-crawler`
- **Crawl History**: `/wp-admin/admin.php?page=seo-crawler-history`

The admin interface provides:
- Streamlit-style configuration sidebar
- Real-time progress monitoring
- Comprehensive results display
- Summary metrics and charts
- Critical issues identification
- SEO recommendations
- Detailed data tables
- CSV export functionality

### Frontend Shortcodes

Use shortcodes to embed the SEO crawler in posts/pages:

```php
// Basic shortcode
[seo_crawler]

// With custom height
[seo_crawler height="800px"]

// Hide history section
[seo_crawler show_history="false"]

// Custom default max pages
[seo_crawler max_pages_default="50"]
```

### PHP Template Usage

```php
// In theme templates
echo do_shortcode('[seo_crawler]');

// Or use the frontend class directly
$frontend = new SEO_Crawler_Frontend();
echo $frontend->render_streamlit_interface('frontend');
```

## Configuration

### Plugin Settings

The plugin automatically creates database tables on activation:
- `wp_seo_crawler_sessions` - Crawl session management
- `wp_seo_crawler_results` - Detailed page analysis results

### Python Path Configuration

If Python is not in the default path, you can modify the path in:
`includes/class-seo-crawler-engine.php`

```php
private function get_python_path() {
    return '/usr/local/bin/python3'; // Your Python path
}
```

### WordPress Constants

Add these to `wp-config.php` for customization:

```php
// Increase crawl limits
define('SEO_CRAWLER_MAX_PAGES', 2000);

// Adjust timeout settings
define('SEO_CRAWLER_TIMEOUT', 60);

// Enable debug mode
define('SEO_CRAWLER_DEBUG', true);
```

## Architecture

### File Structure

```
wordpress-plugin/
├── seo-crawler-plugin.php              # Main plugin file
├── includes/
│   ├── class-seo-crawler-admin.php     # Admin functionality
│   ├── class-seo-crawler-engine.php    # Python execution & crawling
│   └── class-seo-crawler-frontend.php  # Public interface
├── python/
│   ├── wp_crawler.py                   # Command-line crawler
│   ├── seo_crawler.py                  # Core crawler (copy)
│   └── utils.py                        # Utilities (copy)
├── assets/
│   ├── css/
│   │   ├── streamlit-style.css         # Streamlit UI recreation
│   │   └── admin.css                   # Admin enhancements
│   └── js/
│       ├── streamlit-ui.js             # Streamlit interactions
│       └── admin.js                    # Admin-specific features
├── templates/
│   ├── streamlit-interface.php         # Main interface template
│   ├── crawler-form.php                # Shortcode template
│   └── history-page.php                # History management
└── README.md                           # This file
```

### How It Works

1. **Interface Layer**: WordPress templates render Streamlit-style HTML/CSS
2. **Processing Layer**: PHP classes manage sessions and execute Python scripts
3. **Data Layer**: Python scripts perform actual crawling using existing code
4. **Storage Layer**: WordPress database stores sessions and results
5. **Presentation Layer**: JavaScript recreates Streamlit's interactive behavior

### Python Integration

The plugin executes the original Python crawler via:
1. Command-line interface (`wp_crawler.py`)
2. Background processing (WordPress cron or exec)
3. JSON communication between PHP and Python
4. Database storage of results

## API Reference

### WordPress Hooks

```php
// Filter crawl settings before execution
apply_filters('seo_crawler_settings', $settings, $session_id);

// Action after crawl completion
do_action('seo_crawler_completed', $session_id, $results);

// Filter export data before CSV generation
apply_filters('seo_crawler_export_data', $data, $session_id);
```

### JavaScript Events

```javascript
// Listen for crawl completion
$(document).on('seo_crawler_completed', function(event, results) {
    // Handle completion
});

// Listen for progress updates
$(document).on('seo_crawler_progress', function(event, progress) {
    // Handle progress
});
```

### AJAX Endpoints

- `seo_crawler_start` - Start new crawl
- `seo_crawler_status` - Get crawl status
- `seo_crawler_results` - Get crawl results
- `seo_crawler_export` - Export results to CSV
- `seo_crawler_history` - Get user history
- `seo_delete_session` - Delete crawl session

## Customization

### Styling

Override styles by adding CSS to your theme:

```css
/* Customize primary color */
.streamlit-button.primary {
    background-color: your-brand-color !important;
}

/* Adjust sidebar width */
.streamlit-sidebar {
    flex: 0 0 350px;
}
```

### Functionality

Extend functionality by hooking into plugin actions:

```php
// Add custom analysis
add_action('seo_crawler_completed', function($session_id, $results) {
    // Your custom post-processing
});

// Modify crawl settings
add_filter('seo_crawler_settings', function($settings) {
    $settings['custom_option'] = 'value';
    return $settings;
});
```

## Troubleshooting

### Common Issues

1. **Python Not Found**
   - Verify Python installation: `which python3`
   - Update path in `class-seo-crawler-engine.php`

2. **Permission Errors**
   - Check file permissions on Python scripts
   - Ensure WordPress can execute Python

3. **Background Processing Fails**
   - Enable WordPress cron: `define('DISABLE_WP_CRON', false);`
   - Check server execution time limits

4. **Database Errors**
   - Verify table creation during activation
   - Check database permissions

### Debug Mode

Enable debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SEO_CRAWLER_DEBUG', true);
```

Check logs in `/wp-content/debug.log`

## Performance

### Optimization Tips

1. **Large Sites**: Use `max_pages` limit for initial analysis
2. **Server Resources**: Increase PHP memory and execution time
3. **Background Processing**: Use proper cron setup for large crawls
4. **Database**: Regular cleanup of old crawl sessions

### Resource Usage

- **Memory**: ~50MB per 100 pages crawled
- **Storage**: ~1KB per page in database
- **CPU**: Moderate during active crawling
- **Network**: Respectful crawling with delays

## Security

### Data Protection

- User sessions isolated by unique IDs
- No sensitive data stored in browser
- CSRF protection on all AJAX requests
- Capability checks for admin functions

### Best Practices

- Regular cleanup of old crawl data
- Rate limiting on public shortcode usage
- Input validation and sanitization
- Secure Python script execution

## Support

### Getting Help

1. Check this documentation
2. Review WordPress error logs
3. Verify Python script execution manually
4. Test with simple sitemap first

### Reporting Issues

When reporting issues, include:
- WordPress version
- PHP version
- Python version
- Error messages from logs
- Sitemap URL (if public)

## License

GPL v2 or later - Compatible with WordPress licensing.