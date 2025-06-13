<?php
/**
 * SEO Crawler Frontend Class
 * Handles public-facing functionality and shortcode display
 */

class SEO_Crawler_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_inline_scripts'));
    }
    
    public function enqueue_scripts() {
        if ($this->should_load_scripts()) {
            wp_enqueue_style('seo-crawler-streamlit', SEO_CRAWLER_PLUGIN_URL . 'assets/css/streamlit-style.css', array(), SEO_CRAWLER_PLUGIN_VERSION);
            wp_enqueue_script('seo-crawler-streamlit-js', SEO_CRAWLER_PLUGIN_URL . 'assets/js/streamlit-ui.js', array('jquery'), SEO_CRAWLER_PLUGIN_VERSION, true);
            
            wp_localize_script('seo-crawler-streamlit-js', 'seoCrawlerAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('seo_crawler_nonce')
            ));
        }
    }
    
    private function should_load_scripts() {
        global $post;
        
        // Load on pages with shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'seo_crawler')) {
            return true;
        }
        
        // Load on admin pages
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'seo-crawler') !== false) {
            return true;
        }
        
        return false;
    }
    
    public function add_inline_scripts() {
        if ($this->should_load_scripts()) {
            ?>
            <script type="text/javascript">
            // User ID management for persistent sessions
            function getOrCreateUserId() {
                let userId = localStorage.getItem('seo_crawler_user_id');
                if (!userId) {
                    userId = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    localStorage.setItem('seo_crawler_user_id', userId);
                }
                return userId;
            }
            
            // Set global user ID
            window.seoCrawlerUserId = getOrCreateUserId();
            </script>
            <?php
        }
    }
    
    public function get_user_crawl_history($user_id, $limit = 10) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_sessions 
             WHERE user_id = %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
        
        return $sessions;
    }
    
    public function render_streamlit_interface($context = 'admin') {
        $user_id = $context === 'frontend' ? 'frontend_user' : get_current_user_id();
        
        ob_start();
        ?>
        
        <div class="streamlit-container" id="seo-crawler-app">
            
            <!-- Header Section - Mimics Streamlit's title and description -->
            <div class="streamlit-header">
                <h1 class="streamlit-title">🔍 SEO Site Analyzer</h1>
                <div class="streamlit-caption">
                    Comprehensive SEO analysis tool that crawls your sitemap and provides detailed insights into your website's search engine optimization.
                </div>
            </div>
            
            <!-- Sidebar Configuration - Mimics Streamlit sidebar -->
            <div class="streamlit-layout">
                <div class="streamlit-sidebar">
                    <div class="sidebar-content">
                        <h3>Configuration</h3>
                        
                        <div class="streamlit-form">
                            <label class="streamlit-label">📋 Sitemap URL</label>
                            <input type="url" 
                                   id="sitemap-url-input" 
                                   class="streamlit-text-input" 
                                   placeholder="https://yoursite.com/sitemap.xml"
                                   value="">
                            <div class="help-text">Enter your website's XML sitemap URL</div>
                            
                            <label class="streamlit-label">⏱️ Crawl Delay (seconds)</label>
                            <select id="crawl-delay-select" class="streamlit-selectbox">
                                <option value="1.0">1 second</option>
                                <option value="2.0">2 seconds</option>
                                <option value="3.0" selected>3 seconds</option>
                                <option value="4.0">4 seconds</option>
                                <option value="5.0">5 seconds</option>
                            </select>
                            <div class="help-text">Delay between page requests to be respectful</div>
                            
                            <label class="streamlit-label">📄 Max Pages to Crawl</label>
                            <input type="number" 
                                   id="max-pages-input" 
                                   class="streamlit-number-input" 
                                   value="100"
                                   min="1"
                                   max="1000">
                            <div class="help-text">Limit the number of pages to analyze (1-1000)</div>
                            
                            <button id="start-crawl-btn" class="streamlit-button primary">
                                🚀 Start SEO Analysis
                            </button>
                            
                            <button id="validate-sitemap-btn" class="streamlit-button secondary">
                                ✅ Validate Sitemap
                            </button>
                        </div>
                        
                        <!-- User Session Info -->
                        <div class="streamlit-info-box">
                            <h4>📊 Your Session</h4>
                            <div id="user-session-info">
                                <small>User ID: <code id="display-user-id"></code></small>
                                <p class="help-text">Your crawl history is saved and accessible across sessions</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Area -->
                <div class="streamlit-main">
                    
                    <!-- Progress Section -->
                    <div id="crawl-progress-section" class="streamlit-section" style="display: none;">
                        <h2>🔄 Crawling in Progress</h2>
                        <div class="streamlit-progress-container">
                            <div class="progress-bar">
                                <div id="progress-fill" class="progress-fill"></div>
                            </div>
                            <div id="progress-text" class="progress-text">Initializing crawl...</div>
                            <div id="progress-details" class="progress-details"></div>
                        </div>
                    </div>
                    
                    <!-- Results Section -->
                    <div id="results-section" class="streamlit-section" style="display: none;">
                        
                        <!-- Summary Metrics -->
                        <div class="streamlit-subsection">
                            <h2>📈 Summary Report</h2>
                            <div id="summary-metrics" class="metric-grid">
                                <!-- Metrics will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Charts Section -->
                        <div class="streamlit-subsection">
                            <h3>📊 SEO Issues Overview</h3>
                            <div id="seo-issues-chart" class="chart-container">
                                <!-- Chart will be rendered here -->
                            </div>
                        </div>
                        
                        <div class="streamlit-subsection">
                            <h3>🔍 Status Code Distribution</h3>
                            <div id="status-code-chart" class="chart-container">
                                <!-- Chart will be rendered here -->
                            </div>
                        </div>
                        
                        <div class="streamlit-subsection">
                            <h3>⚡ Load Time Distribution</h3>
                            <div id="load-time-chart" class="chart-container">
                                <!-- Chart will be rendered here -->
                            </div>
                        </div>
                        
                        <!-- Critical Issues -->
                        <div class="streamlit-subsection">
                            <h3>🚨 Critical Issues Requiring Attention</h3>
                            <div id="critical-issues-container">
                                <!-- Critical issues will be populated here -->
                            </div>
                        </div>
                        
                        <!-- Detailed Data Table -->
                        <div class="streamlit-subsection">
                            <h3>📋 Detailed Analysis Results</h3>
                            <div class="table-controls">
                                <input type="search" id="table-search" placeholder="🔍 Filter results..." class="streamlit-text-input">
                                <button id="export-csv-btn" class="streamlit-button secondary">📥 Download CSV</button>
                            </div>
                            <div id="detailed-results-table" class="table-container">
                                <!-- Table will be populated here -->
                            </div>
                        </div>
                        
                        <!-- Recommendations -->
                        <div class="streamlit-subsection">
                            <h3>💡 SEO Recommendations</h3>
                            <div id="recommendations-container">
                                <!-- Recommendations will be populated here -->
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Crawl History Section -->
                    <div class="streamlit-subsection">
                        <h3>📚 Recent Crawl History</h3>
                        <div id="crawl-history-container">
                            <!-- History will be loaded here -->
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <!-- Loading Overlay -->
        <div id="loading-overlay" class="loading-overlay" style="display: none;">
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <p>Processing your request...</p>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    public function render_shortcode_widget($atts) {
        $attributes = shortcode_atts(array(
            'height' => 'auto',
            'show_history' => 'true',
            'max_pages_default' => '100'
        ), $atts);
        
        ob_start();
        ?>
        
        <div class="seo-crawler-widget" style="height: <?php echo esc_attr($attributes['height']); ?>;">
            <?php echo $this->render_streamlit_interface('frontend'); ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
}