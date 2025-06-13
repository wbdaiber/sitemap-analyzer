<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('SEO Crawler Pro', 'seo-crawler-pro'); ?></h1>
    
    <div class="seo-crawler-admin-container">
        
        <!-- Crawl Settings Form -->
        <div class="seo-crawler-form-section">
            <h2><?php _e('Start New SEO Crawl', 'seo-crawler-pro'); ?></h2>
            
            <form id="seo-crawler-form" method="post">
                <?php wp_nonce_field('seo_crawler_nonce', 'seo_crawler_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sitemap_url"><?php _e('Sitemap URL', 'seo-crawler-pro'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sitemap_url" 
                                   name="sitemap_url" 
                                   class="regular-text" 
                                   placeholder="https://example.com/sitemap.xml"
                                   required />
                            <p class="description">
                                <?php _e('Enter the URL of the XML sitemap you want to analyze', 'seo-crawler-pro'); ?>
                            </p>
                            <div id="sitemap-validation" class="validation-message"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="crawl_delay"><?php _e('Delay Between Requests', 'seo-crawler-pro'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="crawl_delay" 
                                   name="crawl_delay" 
                                   min="2" 
                                   max="10" 
                                   step="0.5" 
                                   value="3" />
                            <span><?php _e('seconds', 'seo-crawler-pro'); ?></span>
                            <p class="description">
                                <?php _e('Time to wait between each page request to be respectful to the server', 'seo-crawler-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_pages"><?php _e('Maximum Pages', 'seo-crawler-pro'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="max_pages" 
                                   name="max_pages" 
                                   min="1" 
                                   max="1000" 
                                   value="100" />
                            <p class="description">
                                <?php _e('Maximum number of pages to crawl (leave empty for no limit)', 'seo-crawler-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" 
                           id="start-crawl-btn" 
                           class="button-primary" 
                           value="<?php _e('Start SEO Crawl', 'seo-crawler-pro'); ?>" />
                    <span id="crawl-spinner" class="spinner" style="display: none;"></span>
                </p>
            </form>
        </div>
        
        <!-- Progress Section -->
        <div id="crawl-progress-section" class="seo-crawler-progress" style="display: none;">
            <h3><?php _e('Crawl Progress', 'seo-crawler-pro'); ?></h3>
            <div id="progress-bar-container">
                <div id="progress-bar"></div>
            </div>
            <div id="progress-info">
                <span id="progress-text">0 / 0 pages crawled</span>
                <span id="current-url"></span>
            </div>
        </div>
        
        <!-- Results Section -->
        <div id="crawl-results-section" class="seo-crawler-results" style="display: none;">
            <h3><?php _e('Crawl Results', 'seo-crawler-pro'); ?></h3>
            
            <div class="results-tabs">
                <button class="tab-button active" data-tab="summary"><?php _e('Summary', 'seo-crawler-pro'); ?></button>
                <button class="tab-button" data-tab="detailed"><?php _e('Detailed Data', 'seo-crawler-pro'); ?></button>
                <button class="tab-button" data-tab="issues"><?php _e('Key Issues', 'seo-crawler-pro'); ?></button>
            </div>
            
            <div class="tab-content">
                <!-- Summary Tab -->
                <div id="summary-tab" class="tab-panel active">
                    <div class="summary-stats">
                        <div class="stat-box">
                            <h4><?php _e('Total Pages Crawled', 'seo-crawler-pro'); ?></h4>
                            <span id="total-pages" class="stat-number">0</span>
                        </div>
                        <div class="stat-box">
                            <h4><?php _e('Pages with Issues', 'seo-crawler-pro'); ?></h4>
                            <span id="pages-with-issues" class="stat-number">0</span>
                        </div>
                        <div class="stat-box">
                            <h4><?php _e('Average Load Time', 'seo-crawler-pro'); ?></h4>
                            <span id="avg-load-time" class="stat-number">0ms</span>
                        </div>
                        <div class="stat-box">
                            <h4><?php _e('Schema Markup Usage', 'seo-crawler-pro'); ?></h4>
                            <span id="schema-usage" class="stat-number">0%</span>
                        </div>
                    </div>
                    
                    <div class="export-section">
                        <button id="export-csv-btn" class="button"><?php _e('Export to CSV', 'seo-crawler-pro'); ?></button>
                        <button id="export-pdf-btn" class="button"><?php _e('Generate PDF Report', 'seo-crawler-pro'); ?></button>
                    </div>
                </div>
                
                <!-- Detailed Data Tab -->
                <div id="detailed-tab" class="tab-panel">
                    <div class="table-controls">
                        <input type="search" id="data-search" placeholder="<?php _e('Search URLs, titles, or issues...', 'seo-crawler-pro'); ?>" />
                        <select id="status-filter">
                            <option value=""><?php _e('All Status Codes', 'seo-crawler-pro'); ?></option>
                            <option value="200">200 (OK)</option>
                            <option value="404">404 (Not Found)</option>
                            <option value="301">301 (Redirect)</option>
                            <option value="302">302 (Redirect)</option>
                        </select>
                    </div>
                    
                    <div id="detailed-data-table" class="data-table-container">
                        <!-- Table will be populated via JavaScript -->
                    </div>
                </div>
                
                <!-- Issues Tab -->
                <div id="issues-tab" class="tab-panel">
                    <div class="issues-summary">
                        <h4><?php _e('Critical Issues Found', 'seo-crawler-pro'); ?></h4>
                        <div id="critical-issues-list">
                            <!-- Issues will be populated via JavaScript -->
                        </div>
                    </div>
                    
                    <div class="recommendations">
                        <h4><?php _e('Recommendations', 'seo-crawler-pro'); ?></h4>
                        <div id="recommendations-list">
                            <!-- Recommendations will be populated via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Crawls -->
        <div class="seo-crawler-recent">
            <h3><?php _e('Recent Crawls', 'seo-crawler-pro'); ?></h3>
            <div id="recent-crawls-list">
                <!-- Will be populated via AJAX -->
            </div>
        </div>
        
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize the crawler admin interface
    SEOCrawlerAdmin.init();
});
</script>