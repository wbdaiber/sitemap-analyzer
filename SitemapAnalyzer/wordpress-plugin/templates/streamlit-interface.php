<?php
/**
 * Main Streamlit-style interface template for WordPress admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$frontend = new SEO_Crawler_Frontend();
?>

<div class="wrap">
    <div class="seo-crawler-admin-header">
        <h1><?php _e('SEO Crawler', 'seo-crawler'); ?></h1>
        <p class="description"><?php _e('Comprehensive SEO analysis tool that crawls your sitemap and provides detailed insights.', 'seo-crawler'); ?></p>
    </div>

    <?php echo $frontend->render_streamlit_interface('admin'); ?>
</div>

<style>
/* Additional admin-specific styles */
.seo-crawler-admin-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e1e5e9;
}

.seo-crawler-admin-header h1 {
    margin: 0 0 0.5rem 0;
    color: #23282d;
}

.seo-crawler-admin-header .description {
    margin: 0;
    color: #666;
    font-size: 14px;
}

/* WordPress admin adjustments */
.wrap .streamlit-container {
    background: transparent;
}

.wrap .streamlit-layout {
    margin-top: 1rem;
}

/* Make sure charts work in admin */
.chart-container {
    background: #fff;
    border: 1px solid #c3c4c7;
}

/* Responsive adjustments for admin */
@media screen and (max-width: 782px) {
    .streamlit-layout {
        flex-direction: column;
    }
    
    .streamlit-sidebar {
        flex: none;
        margin-bottom: 1rem;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Admin-specific JavaScript enhancements
    
    // Auto-save form data for admin users
    function autoSaveFormData() {
        $('input, select').on('change', function() {
            if (typeof saveFormData === 'function') {
                saveFormData();
            }
        });
    }
    
    autoSaveFormData();
    
    // Admin notification system
    function showAdminNotice(message, type = 'info') {
        const noticeClass = type === 'error' ? 'notice-error' : 
                          type === 'success' ? 'notice-success' : 'notice-info';
        
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
        
        // Manual dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut();
        });
    }
    
    // Override global error/success functions for admin context
    window.showError = function(message) {
        showAdminNotice(message, 'error');
    };
    
    window.showSuccess = function(message) {
        showAdminNotice(message, 'success');
    };
    
    // Add admin-specific features
    $('#start-crawl-btn').on('click', function() {
        showAdminNotice('Starting SEO crawl... This may take several minutes.', 'info');
    });
});
</script>