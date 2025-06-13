# ReactPress Integration Guide

## Installation Steps

1. **Install ReactPress Plugin**
```bash
# In WordPress admin
# Plugins > Add New > Search "ReactPress"
# Install and activate ReactPress
```

2. **Create React Component Directory**
```
wp-content/themes/your-theme/reactpress/
├── components/
│   └── SEOCrawler.jsx
├── assets/
│   └── SEOCrawler.css
└── package.json
```

3. **Setup Component Registration**
```javascript
// wp-content/themes/your-theme/reactpress/index.js
import React from 'react';
import ReactDOM from 'react-dom';
import SEOCrawler from './components/SEOCrawler';

// Register component with ReactPress
window.ReactPress.registerComponent('SEOCrawler', SEOCrawler);

// Auto-mount on pages with specific class
document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.seo-crawler-mount');
    containers.forEach(container => {
        ReactDOM.render(<SEOCrawler />, container);
    });
});
```

4. **WordPress Integration**
```php
// In your theme's functions.php
function enqueue_seo_crawler_scripts() {
    wp_enqueue_script(
        'seo-crawler-react',
        get_template_directory_uri() . '/reactpress/dist/seo-crawler.js',
        array('reactpress'),
        '1.0.0',
        true
    );
    
    wp_localize_script('seo-crawler-react', 'seoAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('seo_crawler_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_seo_crawler_scripts');

// Shortcode to embed component
function seo_crawler_shortcode($atts) {
    return '<div class="seo-crawler-mount"></div>';
}
add_shortcode('seo_crawler', 'seo_crawler_shortcode');
```

5. **Usage in Posts/Pages**
```
[seo_crawler]
```