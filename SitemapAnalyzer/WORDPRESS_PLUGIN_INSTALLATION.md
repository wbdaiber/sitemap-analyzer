# SEO Crawler Pro - WordPress Plugin Installation Guide

## Complete Plugin Files Structure

Create the following directory structure in your WordPress plugins folder:

```
wp-content/plugins/seo-crawler-pro/
├── seo-crawler-pro.php (Main plugin file)
├── admin-crawler-admin.php (Admin interface)
├── crawler-engine.php (Crawling logic)
├── admin.css (Admin styles)
├── admin.js (Admin JavaScript - create separately)
├── frontend.css (Frontend styles - create separately)
├── frontend.js (Frontend JavaScript - create separately)
├── frontend-widget.php (Frontend shortcode display)
├── readme.txt (WordPress plugin readme)
└── LICENSE (GPL license file)
```

## Installation Steps

### Method 1: Manual Installation (Recommended for Development)

1. **Create Plugin Directory**
   ```bash
   mkdir wp-content/plugins/seo-crawler-pro
   cd wp-content/plugins/seo-crawler-pro
   ```

2. **Copy All Plugin Files**
   - Copy `seo-crawler-pro.php` (main plugin file)
   - Copy `admin-crawler-admin.php` (admin interface)
   - Copy `crawler-engine.php` (crawling engine)
   - Copy `admin.css` (admin styles)
   - Create additional files as shown below

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "SEO Crawler Pro" in the list
   - Click "Activate"

### Method 2: ZIP Upload

1. **Create Plugin ZIP**
   ```bash
   zip -r seo-crawler-pro.zip seo-crawler-pro/
   ```

2. **Upload via WordPress Admin**
   - Go to Plugins → Add New → Upload Plugin
   - Select the ZIP file
   - Click "Install Now" then "Activate"

## Required Additional Files

### Frontend Widget (frontend-widget.php)
```php
<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$user_id = isset($_COOKIE['seo_crawler_user_id']) ? $_COOKIE['seo_crawler_user_id'] : '';
if (empty($user_id)) {
    $user_id = uniqid('user_', true);
    setcookie('seo_crawler_user_id', $user_id, time() + (86400 * 365), '/');
}
?>

<div class="seo-crawler-frontend-widget" style="height: <?php echo esc_attr($atts['height']); ?>;">
    <div class="crawler-header">
        <h3>SEO Site Analyzer</h3>
        <p>Enter your sitemap URL to get a comprehensive SEO analysis</p>
    </div>
    
    <form id="frontend-crawler-form" class="crawler-form">
        <div class="form-group">
            <label for="frontend-sitemap-url">Sitemap URL:</label>
            <input type="url" id="frontend-sitemap-url" placeholder="https://yoursite.com/sitemap.xml" required>
        </div>
        
        <div class="form-group">
            <label for="frontend-delay">Crawl Delay (seconds):</label>
            <select id="frontend-delay">
                <option value="2">2 seconds</option>
                <option value="3" selected>3 seconds</option>
                <option value="4">4 seconds</option>
                <option value="5">5 seconds</option>
            </select>
        </div>
        
        <button type="submit" class="crawl-button">Start SEO Analysis</button>
        
        <div id="frontend-progress" class="progress-section" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <p class="progress-text">Analyzing your website...</p>
        </div>
    </form>
    
    <div id="frontend-results" class="results-section" style="display: none;">
        <h4>Analysis Results</h4>
        <div class="results-summary"></div>
        <div class="results-table"></div>
        <button id="frontend-export" class="export-button">Download Report (CSV)</button>
    </div>
    
    <div class="user-info">
        <p><strong>Your Analysis ID:</strong> <code><?php echo esc_html($user_id); ?></code></p>
        <p><small>Save this ID to access your results later from any device</small></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Frontend crawler initialization
    $('#frontend-crawler-form').on('submit', function(e) {
        e.preventDefault();
        startFrontendCrawl();
    });
    
    function startFrontendCrawl() {
        var sitemapUrl = $('#frontend-sitemap-url').val();
        var delay = $('#frontend-delay').val();
        
        if (!sitemapUrl) {
            alert('Please enter a sitemap URL');
            return;
        }
        
        $('#frontend-progress').show();
        $('.crawl-button').prop('disabled', true);
        
        $.post(seoCrawlerAjax.ajax_url, {
            action: 'seo_crawl_start',
            nonce: seoCrawlerAjax.nonce,
            sitemap_url: sitemapUrl,
            delay: delay,
            user_id: '<?php echo esc_js($user_id); ?>'
        })
        .done(function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                pollCrawlProgress(data.session_id);
            } else {
                alert(data.error || 'Failed to start crawl');
                $('#frontend-progress').hide();
                $('.crawl-button').prop('disabled', false);
            }
        });
    }
    
    function pollCrawlProgress(sessionId) {
        var pollInterval = setInterval(function() {
            $.post(seoCrawlerAjax.ajax_url, {
                action: 'seo_crawl_status',
                nonce: seoCrawlerAjax.nonce,
                session_id: sessionId
            })
            .done(function(response) {
                var data = JSON.parse(response);
                
                if (data.status === 'completed') {
                    clearInterval(pollInterval);
                    loadCrawlResults(sessionId);
                } else if (data.status === 'error') {
                    clearInterval(pollInterval);
                    alert('Crawl failed: ' + data.error_message);
                    resetForm();
                } else {
                    // Update progress
                    var progress = data.progress || 0;
                    $('.progress-fill').css('width', progress + '%');
                    $('.progress-text').text('Analyzed ' + data.completed_pages + ' of ' + data.total_pages + ' pages');
                }
            });
        }, 3000);
    }
    
    function loadCrawlResults(sessionId) {
        // Load and display results
        $('#frontend-progress').hide();
        $('#frontend-results').show();
        $('.crawl-button').prop('disabled', false);
        
        // Implementation for loading and displaying results
        // This would make another AJAX call to get the detailed results
    }
    
    function resetForm() {
        $('#frontend-progress').hide();
        $('.crawl-button').prop('disabled', false);
    }
});
</script>
```

### Frontend CSS (frontend.css)
```css
.seo-crawler-frontend-widget {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 10px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.crawler-header {
    text-align: center;
    margin-bottom: 30px;
}

.crawler-header h3 {
    color: #333;
    margin-bottom: 10px;
}

.crawler-form {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #0073aa;
    outline: none;
}

.crawl-button {
    background: #0073aa;
    color: white;
    padding: 15px 30px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: background 0.3s;
}

.crawl-button:hover {
    background: #005a87;
}

.crawl-button:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.progress-section {
    margin-top: 30px;
    text-align: center;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #eee;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 15px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005a87);
    width: 0%;
    transition: width 0.5s ease;
}

.progress-text {
    color: #666;
    font-style: italic;
}

.results-section {
    margin-top: 30px;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.export-button {
    background: #28a745;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 20px;
}

.user-info {
    margin-top: 20px;
    padding: 15px;
    background: #e7f3ff;
    border-radius: 5px;
    text-align: center;
}

.user-info code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

@media (max-width: 768px) {
    .seo-crawler-frontend-widget {
        padding: 15px;
    }
    
    .crawler-form {
        padding: 20px;
    }
}
```

## Database Requirements

The plugin automatically creates these database tables on activation:

1. **wp_seo_crawler_sessions** - Stores crawl session information
2. **wp_seo_crawler_results** - Stores detailed page analysis results

## Plugin Usage

### Admin Usage

1. **Access Admin Panel**
   - Go to WordPress Admin → SEO Crawler
   - Enter sitemap URL and configure settings
   - Start crawl and monitor progress
   - View detailed results and export data

2. **View Crawl History**
   - Go to SEO Crawler → History
   - View all previous crawls
   - Access detailed reports

### Frontend Usage (Shortcode)

```php
// Basic shortcode
[seo_crawler]

// With custom height
[seo_crawler height="600px"]

// In theme templates
<?php echo do_shortcode('[seo_crawler]'); ?>
```

## Configuration Options

Add these to your wp-config.php for customization:

```php
// Increase crawl limits
define('SEO_CRAWLER_MAX_PAGES', 2000);

// Adjust timeout settings
define('SEO_CRAWLER_TIMEOUT', 30);

// Enable debug mode
define('SEO_CRAWLER_DEBUG', true);
```

## Server Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 5.0 or higher
- **Memory**: 256MB minimum (512MB recommended)
- **Execution Time**: 300 seconds for background processing
- **cURL**: Required for external requests
- **DOMDocument**: Required for HTML parsing

## Troubleshooting

### Common Issues

1. **Plugin Not Activating**
   - Check PHP version (7.4+ required)
   - Verify file permissions
   - Check WordPress error logs

2. **Crawls Failing**
   - Verify sitemap URL is accessible
   - Check server timeout settings
   - Ensure target site allows crawling

3. **Background Processing Issues**
   - Enable WordPress cron jobs
   - Check server execution time limits
   - Verify database permissions

### Performance Optimization

1. **For Large Sites**
   - Increase PHP memory limit
   - Use smaller batch sizes
   - Schedule crawls during off-peak hours

2. **Server Optimization**
   ```php
   // Add to wp-config.php
   ini_set('memory_limit', '512M');
   ini_set('max_execution_time', 300);
   ```

## Security Considerations

1. **Access Control**
   - Only administrators can access the plugin by default
   - Frontend access can be disabled in settings

2. **Rate Limiting**
   - Built-in delays between requests
   - Respects robots.txt (recommended)

3. **Data Protection**
   - User data isolated by unique IDs
   - Regular cleanup of old crawl data

## Support and Updates

For support and updates:
1. Check WordPress plugin directory for updates
2. Review documentation for troubleshooting
3. Contact plugin author for technical support

## License

This plugin is licensed under GPL v2 or later, compatible with WordPress licensing requirements.