<?php
/**
 * Frontend shortcode form template
 * Provides a compact version of the Streamlit interface for public use
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$frontend = new SEO_Crawler_Frontend();
?>

<div class="seo-crawler-shortcode-widget">
    <?php echo $frontend->render_streamlit_interface('frontend'); ?>
</div>

<style>
/* Shortcode-specific styling */
.seo-crawler-shortcode-widget .streamlit-container {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #e1e5e9;
}

.seo-crawler-shortcode-widget .streamlit-header {
    text-align: center;
    padding: 1rem 0;
}

.seo-crawler-shortcode-widget .streamlit-title {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.seo-crawler-shortcode-widget .streamlit-layout {
    gap: 1.5rem;
}

.seo-crawler-shortcode-widget .streamlit-sidebar {
    flex: 0 0 280px;
}

/* Responsive for shortcode */
@media (max-width: 768px) {
    .seo-crawler-shortcode-widget .streamlit-layout {
        flex-direction: column;
    }
    
    .seo-crawler-shortcode-widget .streamlit-sidebar {
        flex: none;
    }
    
    .seo-crawler-shortcode-widget .streamlit-title {
        font-size: 1.5rem;
    }
}

/* Theme integration */
.seo-crawler-shortcode-widget {
    margin: 2rem 0;
    clear: both;
}

/* Ensure compatibility with most themes */
.seo-crawler-shortcode-widget * {
    font-family: inherit !important;
}

.seo-crawler-shortcode-widget .streamlit-button.primary {
    background-color: var(--theme-primary, #ff4b4b) !important;
}
</style>