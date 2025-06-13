<?php
/**
 * SEO Crawler Admin Class
 * Handles admin interface functionality that mirrors Streamlit app
 */

class SEO_Crawler_Admin {
    
    public function __construct() {
        add_action('admin_init', array($this, 'init'));
    }
    
    public function init() {
        // Admin initialization
    }
    
    public function get_user_sessions($user_id = null) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        
        if ($user_id) {
            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_sessions WHERE user_id = %s ORDER BY created_at DESC",
                $user_id
            ));
        } else {
            $sessions = $wpdb->get_results(
                "SELECT * FROM $table_sessions ORDER BY created_at DESC LIMIT 50"
            );
        }
        
        return $sessions;
    }
    
    public function get_session_summary($session_id) {
        global $wpdb;
        
        $table_results = $wpdb->prefix . 'seo_crawler_results';
        
        // Get basic counts
        $total_pages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_results WHERE session_id = %d",
            $session_id
        ));
        
        // Get SEO issues count
        $seo_issues = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_results 
             WHERE session_id = %d 
             AND (title = '' OR title IS NULL 
                  OR meta_description = '' OR meta_description IS NULL 
                  OR has_schema = 0 
                  OR has_og_tags = 0)",
            $session_id
        ));
        
        // Get status code distribution
        $status_codes = $wpdb->get_results($wpdb->prepare(
            "SELECT status_code, COUNT(*) as count 
             FROM $table_results 
             WHERE session_id = %d 
             GROUP BY status_code",
            $session_id
        ));
        
        // Average load time
        $avg_load_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(load_time_ms) FROM $table_results WHERE session_id = %d",
            $session_id
        ));
        
        // Schema markup stats
        $schema_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(has_schema) as pages_with_schema,
                SUM(schema_count) as total_schema_count,
                AVG(schema_count) as avg_schema_per_page
             FROM $table_results 
             WHERE session_id = %d",
            $session_id
        ));
        
        // Social media stats
        $social_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(has_og_tags) as pages_with_og,
                SUM(has_twitter_cards) as pages_with_twitter,
                SUM(social_tags_complete) as pages_with_complete_social
             FROM $table_results 
             WHERE session_id = %d",
            $session_id
        ));
        
        // Technical SEO stats
        $technical_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(is_https) as https_pages,
                SUM(has_viewport) as pages_with_viewport,
                SUM(has_favicon) as pages_with_favicon,
                AVG(images_without_alt) as avg_missing_alt
             FROM $table_results 
             WHERE session_id = %d",
            $session_id
        ));
        
        return array(
            'total_pages' => intval($total_pages),
            'seo_issues' => intval($seo_issues),
            'status_codes' => $status_codes,
            'avg_load_time' => round(floatval($avg_load_time), 2),
            'schema_stats' => $schema_stats,
            'social_stats' => $social_stats,
            'technical_stats' => $technical_stats
        );
    }
    
    public function get_detailed_results($session_id, $limit = null, $offset = 0) {
        global $wpdb;
        
        $table_results = $wpdb->prefix . 'seo_crawler_results';
        
        $sql = "SELECT * FROM $table_results WHERE session_id = %d ORDER BY created_at";
        
        if ($limit) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $session_id));
        
        // Process results to match Streamlit format
        $processed_results = array();
        foreach ($results as $result) {
            $processed_results[] = array(
                'url' => $result->url,
                'title' => $result->title,
                'title_length' => intval($result->title_length),
                'meta_description' => $result->meta_description,
                'meta_description_length' => intval($result->meta_description_length),
                'h1_content' => $result->h1_content,
                'h1_count' => intval($result->h1_count),
                'word_count' => intval($result->word_count),
                'flesch_reading_ease' => floatval($result->flesch_reading_ease),
                'internal_links' => intval($result->internal_links),
                'external_links' => intval($result->external_links),
                'images' => intval($result->images),
                'images_without_alt' => intval($result->images_without_alt),
                'load_time_ms' => floatval($result->load_time_ms),
                'status_code' => intval($result->status_code),
                'is_https' => boolval($result->is_https),
                'has_schema' => boolval($result->has_schema),
                'schema_count' => intval($result->schema_count),
                'has_og_tags' => boolval($result->has_og_tags),
                'has_twitter_cards' => boolval($result->has_twitter_cards),
                'social_tags_complete' => boolval($result->social_tags_complete),
                'has_viewport' => boolval($result->has_viewport),
                'has_favicon' => boolval($result->has_favicon),
                'canonical_url' => $result->canonical_url
            );
        }
        
        return $processed_results;
    }
    
    public function identify_critical_issues($session_id) {
        global $wpdb;
        
        $table_results = $wpdb->prefix . 'seo_crawler_results';
        
        $critical_issues = array();
        
        // Missing titles
        $missing_titles = $wpdb->get_results($wpdb->prepare(
            "SELECT url FROM $table_results 
             WHERE session_id = %d AND (title = '' OR title IS NULL) 
             LIMIT 10",
            $session_id
        ));
        
        if (!empty($missing_titles)) {
            $critical_issues[] = array(
                'type' => 'Missing Titles',
                'severity' => 'High',
                'count' => count($missing_titles),
                'urls' => array_column($missing_titles, 'url'),
                'description' => 'Pages without title tags hurt SEO significantly'
            );
        }
        
        // Missing meta descriptions
        $missing_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT url FROM $table_results 
             WHERE session_id = %d AND (meta_description = '' OR meta_description IS NULL) 
             LIMIT 10",
            $session_id
        ));
        
        if (!empty($missing_meta)) {
            $critical_issues[] = array(
                'type' => 'Missing Meta Descriptions',
                'severity' => 'Medium',
                'count' => count($missing_meta),
                'urls' => array_column($missing_meta, 'url'),
                'description' => 'Missing meta descriptions reduce click-through rates'
            );
        }
        
        // Multiple H1 tags
        $multiple_h1 = $wpdb->get_results($wpdb->prepare(
            "SELECT url, h1_count FROM $table_results 
             WHERE session_id = %d AND h1_count > 1 
             LIMIT 10",
            $session_id
        ));
        
        if (!empty($multiple_h1)) {
            $critical_issues[] = array(
                'type' => 'Multiple H1 Tags',
                'severity' => 'Medium',
                'count' => count($multiple_h1),
                'urls' => array_column($multiple_h1, 'url'),
                'description' => 'Multiple H1 tags can confuse search engines'
            );
        }
        
        // Missing schema markup
        $missing_schema = $wpdb->get_results($wpdb->prepare(
            "SELECT url FROM $table_results 
             WHERE session_id = %d AND has_schema = 0 
             LIMIT 10",
            $session_id
        ));
        
        if (!empty($missing_schema)) {
            $critical_issues[] = array(
                'type' => 'Missing Schema Markup',
                'severity' => 'Low',
                'count' => count($missing_schema),
                'urls' => array_column($missing_schema, 'url'),
                'description' => 'Schema markup helps search engines understand content'
            );
        }
        
        // Slow loading pages
        $slow_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT url, load_time_ms FROM $table_results 
             WHERE session_id = %d AND load_time_ms > 3000 
             ORDER BY load_time_ms DESC 
             LIMIT 10",
            $session_id
        ));
        
        if (!empty($slow_pages)) {
            $critical_issues[] = array(
                'type' => 'Slow Loading Pages',
                'severity' => 'High',
                'count' => count($slow_pages),
                'urls' => array_column($slow_pages, 'url'),
                'description' => 'Pages taking over 3 seconds to load hurt user experience'
            );
        }
        
        return $critical_issues;
    }
    
    public function generate_seo_recommendations($session_id) {
        $summary = $this->get_session_summary($session_id);
        $recommendations = array();
        
        // Title recommendations
        if ($summary['seo_issues'] > 0) {
            $recommendations[] = array(
                'category' => 'Content Optimization',
                'priority' => 'High',
                'title' => 'Fix Missing Titles and Meta Descriptions',
                'description' => 'Complete missing title tags and meta descriptions to improve search visibility.',
                'action' => 'Review pages with missing metadata and add compelling titles and descriptions.'
            );
        }
        
        // Schema recommendations
        if ($summary['schema_stats']->pages_with_schema < $summary['total_pages'] * 0.5) {
            $recommendations[] = array(
                'category' => 'Technical SEO',
                'priority' => 'Medium',
                'title' => 'Implement Schema Markup',
                'description' => 'Add structured data to help search engines understand your content better.',
                'action' => 'Implement relevant schema markup types for your content (Article, Organization, etc.)'
            );
        }
        
        // Social media recommendations
        if ($summary['social_stats']->pages_with_complete_social < $summary['total_pages'] * 0.7) {
            $recommendations[] = array(
                'category' => 'Social Media',
                'priority' => 'Medium',
                'title' => 'Complete Social Media Tags',
                'description' => 'Add Open Graph and Twitter Card tags to improve social sharing.',
                'action' => 'Implement og:title, og:description, og:image and Twitter Card tags.'
            );
        }
        
        // Performance recommendations
        if ($summary['avg_load_time'] > 2000) {
            $recommendations[] = array(
                'category' => 'Performance',
                'priority' => 'High',
                'title' => 'Optimize Page Load Times',
                'description' => 'Improve page speed to enhance user experience and SEO rankings.',
                'action' => 'Optimize images, enable caching, and minimize CSS/JavaScript files.'
            );
        }
        
        return $recommendations;
    }
    
    public function delete_session($session_id) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'seo_crawler_sessions';
        $table_results = $wpdb->prefix . 'seo_crawler_results';
        
        // Delete results first (foreign key constraint)
        $wpdb->delete($table_results, array('session_id' => $session_id));
        
        // Delete session
        $result = $wpdb->delete($table_sessions, array('id' => $session_id));
        
        return $result !== false;
    }
}