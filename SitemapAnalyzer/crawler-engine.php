<?php
/**
 * SEO Crawler Engine - Core crawling functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function seo_crawler_process_session($session_id) {
    global $wpdb;
    
    // Get session details
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}seo_crawler_sessions WHERE id = %d",
        $session_id
    ));
    
    if (!$session || $session->status !== 'running') {
        return;
    }
    
    $settings = json_decode($session->settings, true);
    $delay = isset($settings['delay']) ? floatval($settings['delay']) : 3.0;
    $max_pages = isset($settings['max_pages']) ? intval($settings['max_pages']) : 1000;
    
    try {
        // Parse sitemap to get URLs
        $urls = seo_crawler_parse_sitemap($session->sitemap_url);
        
        if (empty($urls)) {
            seo_crawler_update_session_status($session_id, 'error', 'No URLs found in sitemap');
            return;
        }
        
        // Limit URLs if max_pages is set
        if ($max_pages > 0 && count($urls) > $max_pages) {
            $urls = array_slice($urls, 0, $max_pages);
        }
        
        // Update total pages count
        $wpdb->update(
            $wpdb->prefix . 'seo_crawler_sessions',
            array('total_pages' => count($urls)),
            array('id' => $session_id)
        );
        
        // Process each URL
        $completed = 0;
        foreach ($urls as $url_data) {
            $url = is_array($url_data) ? $url_data['url'] : $url_data;
            
            // Crawl the page
            $page_data = seo_crawler_analyze_page($url);
            
            // Save results to database
            seo_crawler_save_page_results($session_id, $page_data);
            
            // Update progress
            $completed++;
            $wpdb->update(
                $wpdb->prefix . 'seo_crawler_sessions',
                array('completed_pages' => $completed),
                array('id' => $session_id)
            );
            
            // Respect delay setting
            if ($delay > 0) {
                sleep($delay);
            }
            
            // Check if we should stop (session might be cancelled)
            $current_session = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}seo_crawler_sessions WHERE id = %d",
                $session_id
            ));
            
            if ($current_session !== 'running') {
                break;
            }
        }
        
        // Mark session as completed
        seo_crawler_update_session_status($session_id, 'completed');
        
    } catch (Exception $e) {
        seo_crawler_update_session_status($session_id, 'error', $e->getMessage());
    }
}

function seo_crawler_parse_sitemap($sitemap_url) {
    $urls = array();
    
    // Fetch sitemap content
    $response = wp_remote_get($sitemap_url, array(
        'timeout' => 30,
        'user-agent' => 'SEO Crawler Pro WordPress Plugin'
    ));
    
    if (is_wp_error($response)) {
        throw new Exception('Failed to fetch sitemap: ' . $response->get_error_message());
    }
    
    $content = wp_remote_retrieve_body($response);
    
    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    
    if ($xml === false) {
        throw new Exception('Invalid XML sitemap format');
    }
    
    // Handle different sitemap formats
    if (isset($xml->url)) {
        // Standard sitemap
        foreach ($xml->url as $url_element) {
            $url_data = array(
                'url' => (string)$url_element->loc
            );
            
            if (isset($url_element->lastmod)) {
                $url_data['lastmod'] = (string)$url_element->lastmod;
            }
            
            $urls[] = $url_data;
        }
    } elseif (isset($xml->sitemap)) {
        // Sitemap index - recursively fetch child sitemaps
        foreach ($xml->sitemap as $sitemap_element) {
            $child_sitemap_url = (string)$sitemap_element->loc;
            try {
                $child_urls = seo_crawler_parse_sitemap($child_sitemap_url);
                $urls = array_merge($urls, $child_urls);
            } catch (Exception $e) {
                // Continue with other sitemaps if one fails
                continue;
            }
        }
    }
    
    return $urls;
}

function seo_crawler_analyze_page($url) {
    $start_time = microtime(true);
    
    // Initialize result array
    $result = array(
        'url' => $url,
        'status_code' => 0,
        'load_time_ms' => 0,
        'title' => '',
        'title_length' => 0,
        'meta_description' => '',
        'meta_description_length' => 0,
        'h1_content' => '',
        'h1_count' => 0,
        'word_count' => 0,
        'flesch_reading_ease' => 0,
        'internal_links' => 0,
        'external_links' => 0,
        'images' => 0,
        'images_without_alt' => 0,
        'is_https' => false,
        'has_schema' => false,
        'schema_count' => 0,
        'has_og_tags' => false,
        'has_twitter_cards' => false,
        'social_tags_complete' => false,
        'has_viewport' => false,
        'has_favicon' => false,
        'canonical_url' => '',
        'error' => null
    );
    
    try {
        // Fetch the page
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'user-agent' => 'SEO Crawler Pro WordPress Plugin',
            'redirection' => 5
        ));
        
        $load_time = (microtime(true) - $start_time) * 1000;
        $result['load_time_ms'] = round($load_time, 2);
        
        if (is_wp_error($response)) {
            $result['error'] = $response->get_error_message();
            return $result;
        }
        
        $result['status_code'] = wp_remote_retrieve_response_code($response);
        $result['is_https'] = strpos($url, 'https://') === 0;
        
        // Only analyze content for successful responses
        if ($result['status_code'] === 200) {
            $html = wp_remote_retrieve_body($response);
            $result = array_merge($result, seo_crawler_analyze_html($html, $url));
        }
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

function seo_crawler_analyze_html($html, $url) {
    $analysis = array();
    
    // Load HTML into DOMDocument
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    // Extract title
    $title_elements = $dom->getElementsByTagName('title');
    if ($title_elements->length > 0) {
        $analysis['title'] = trim($title_elements->item(0)->textContent);
        $analysis['title_length'] = strlen($analysis['title']);
    }
    
    // Extract meta description
    $meta_elements = $dom->getElementsByTagName('meta');
    foreach ($meta_elements as $meta) {
        if (strtolower($meta->getAttribute('name')) === 'description') {
            $analysis['meta_description'] = trim($meta->getAttribute('content'));
            $analysis['meta_description_length'] = strlen($analysis['meta_description']);
            break;
        }
    }
    
    // Extract H1 tags
    $h1_elements = $dom->getElementsByTagName('h1');
    $analysis['h1_count'] = $h1_elements->length;
    if ($h1_elements->length > 0) {
        $analysis['h1_content'] = trim($h1_elements->item(0)->textContent);
    }
    
    // Count words in main content
    $body_elements = $dom->getElementsByTagName('body');
    if ($body_elements->length > 0) {
        $body_text = $body_elements->item(0)->textContent;
        $word_count = str_word_count(strip_tags($body_text));
        $analysis['word_count'] = $word_count;
        
        // Calculate reading ease (simplified Flesch formula)
        $sentence_count = preg_split('/[.!?]+/', $body_text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentence_count);
        
        if ($sentence_count > 0 && $word_count > 0) {
            $avg_sentence_length = $word_count / $sentence_count;
            $analysis['flesch_reading_ease'] = max(0, min(100, 206.835 - (1.015 * $avg_sentence_length)));
        }
    }
    
    // Count links
    $base_domain = parse_url($url, PHP_URL_HOST);
    $links = $dom->getElementsByTagName('a');
    $internal_links = 0;
    $external_links = 0;
    
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (empty($href) || strpos($href, '#') === 0) {
            continue;
        }
        
        if (strpos($href, 'http') === 0) {
            $link_domain = parse_url($href, PHP_URL_HOST);
            if ($link_domain === $base_domain) {
                $internal_links++;
            } else {
                $external_links++;
            }
        } else {
            $internal_links++;
        }
    }
    
    $analysis['internal_links'] = $internal_links;
    $analysis['external_links'] = $external_links;
    
    // Count images and alt text issues
    $images = $dom->getElementsByTagName('img');
    $analysis['images'] = $images->length;
    $images_without_alt = 0;
    
    foreach ($images as $img) {
        $alt = trim($img->getAttribute('alt'));
        if (empty($alt)) {
            $images_without_alt++;
        }
    }
    
    $analysis['images_without_alt'] = $images_without_alt;
    
    // Check for schema markup
    $script_elements = $dom->getElementsByTagName('script');
    $schema_count = 0;
    
    foreach ($script_elements as $script) {
        if ($script->getAttribute('type') === 'application/ld+json') {
            $schema_count++;
        }
    }
    
    $analysis['has_schema'] = $schema_count > 0;
    $analysis['schema_count'] = $schema_count;
    
    // Check for Open Graph tags
    $has_og_tags = false;
    foreach ($meta_elements as $meta) {
        if (strpos($meta->getAttribute('property'), 'og:') === 0) {
            $has_og_tags = true;
            break;
        }
    }
    $analysis['has_og_tags'] = $has_og_tags;
    
    // Check for Twitter Cards
    $has_twitter_cards = false;
    foreach ($meta_elements as $meta) {
        if (strpos($meta->getAttribute('name'), 'twitter:') === 0) {
            $has_twitter_cards = true;
            break;
        }
    }
    $analysis['has_twitter_cards'] = $has_twitter_cards;
    
    // Check for viewport meta tag
    $has_viewport = false;
    foreach ($meta_elements as $meta) {
        if (strtolower($meta->getAttribute('name')) === 'viewport') {
            $has_viewport = true;
            break;
        }
    }
    $analysis['has_viewport'] = $has_viewport;
    
    // Check for favicon
    $link_elements = $dom->getElementsByTagName('link');
    $has_favicon = false;
    foreach ($link_elements as $link) {
        $rel = strtolower($link->getAttribute('rel'));
        if (in_array($rel, array('icon', 'shortcut icon'))) {
            $has_favicon = true;
            break;
        }
    }
    $analysis['has_favicon'] = $has_favicon;
    
    // Extract canonical URL
    foreach ($link_elements as $link) {
        if (strtolower($link->getAttribute('rel')) === 'canonical') {
            $analysis['canonical_url'] = $link->getAttribute('href');
            break;
        }
    }
    
    // Determine social tags completeness
    $analysis['social_tags_complete'] = $has_og_tags && $has_twitter_cards;
    
    return $analysis;
}

function seo_crawler_save_page_results($session_id, $page_data) {
    global $wpdb;
    
    $table_results = $wpdb->prefix . 'seo_crawler_results';
    
    $wpdb->insert(
        $table_results,
        array(
            'session_id' => $session_id,
            'url' => $page_data['url'],
            'title' => $page_data['title'] ?? '',
            'title_length' => $page_data['title_length'] ?? 0,
            'meta_description' => $page_data['meta_description'] ?? '',
            'meta_description_length' => $page_data['meta_description_length'] ?? 0,
            'h1_content' => $page_data['h1_content'] ?? '',
            'h1_count' => $page_data['h1_count'] ?? 0,
            'word_count' => $page_data['word_count'] ?? 0,
            'flesch_reading_ease' => $page_data['flesch_reading_ease'] ?? 0,
            'internal_links' => $page_data['internal_links'] ?? 0,
            'external_links' => $page_data['external_links'] ?? 0,
            'images' => $page_data['images'] ?? 0,
            'images_without_alt' => $page_data['images_without_alt'] ?? 0,
            'load_time_ms' => $page_data['load_time_ms'] ?? 0,
            'status_code' => $page_data['status_code'] ?? 0,
            'is_https' => $page_data['is_https'] ?? false,
            'has_schema' => $page_data['has_schema'] ?? false,
            'schema_count' => $page_data['schema_count'] ?? 0,
            'has_og_tags' => $page_data['has_og_tags'] ?? false,
            'has_twitter_cards' => $page_data['has_twitter_cards'] ?? false,
            'social_tags_complete' => $page_data['social_tags_complete'] ?? false,
            'has_viewport' => $page_data['has_viewport'] ?? false,
            'has_favicon' => $page_data['has_favicon'] ?? false,
            'canonical_url' => $page_data['canonical_url'] ?? ''
        )
    );
}

function seo_crawler_update_session_status($session_id, $status, $error_message = null) {
    global $wpdb;
    
    $update_data = array('status' => $status);
    
    if ($status === 'completed') {
        $update_data['completed_at'] = current_time('mysql');
    }
    
    if ($error_message) {
        $update_data['error_message'] = $error_message;
    }
    
    $wpdb->update(
        $wpdb->prefix . 'seo_crawler_sessions',
        $update_data,
        array('id' => $session_id)
    );
}
?>