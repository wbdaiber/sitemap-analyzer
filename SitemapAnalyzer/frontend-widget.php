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
    var currentSessionId = null;
    
    $('#frontend-crawler-form').on('submit', function(e) {
        e.preventDefault();
        startFrontendCrawl();
    });
    
    $('#frontend-export').on('click', function() {
        exportResults();
    });
    
    function startFrontendCrawl() {
        var sitemapUrl = $('#frontend-sitemap-url').val();
        var delay = $('#frontend-delay').val();
        
        if (!sitemapUrl) {
            alert('Please enter a sitemap URL');
            return;
        }
        
        $('#frontend-progress').show();
        $('#frontend-results').hide();
        $('.crawl-button').prop('disabled', true);
        $('.progress-fill').css('width', '0%');
        $('.progress-text').text('Starting analysis...');
        
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
                currentSessionId = data.session_id;
                pollCrawlProgress(data.session_id);
            } else {
                alert(data.error || 'Failed to start crawl');
                resetForm();
            }
        })
        .fail(function() {
            alert('Failed to start analysis. Please try again.');
            resetForm();
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
                    alert('Analysis failed: ' + (data.error_message || 'Unknown error'));
                    resetForm();
                } else {
                    // Update progress
                    var progress = data.progress || 0;
                    $('.progress-fill').css('width', progress + '%');
                    $('.progress-text').text('Analyzed ' + data.completed_pages + ' of ' + data.total_pages + ' pages');
                }
            })
            .fail(function() {
                clearInterval(pollInterval);
                alert('Lost connection. Please refresh and try again.');
                resetForm();
            });
        }, 3000);
    }
    
    function loadCrawlResults(sessionId) {
        $.post(seoCrawlerAjax.ajax_url, {
            action: 'seo_get_results',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                displayResults(data.results);
                $('#frontend-progress').hide();
                $('#frontend-results').show();
            } else {
                alert('Failed to load results: ' + data.error);
                resetForm();
            }
        })
        .fail(function() {
            alert('Failed to load results. Please try again.');
            resetForm();
        });
    }
    
    function displayResults(results) {
        // Generate summary
        var totalPages = results.length;
        var issueCount = results.filter(function(page) {
            return !page.title || !page.meta_description || !page.has_schema || !page.has_og_tags;
        }).length;
        var avgLoadTime = results.reduce(function(sum, page) {
            return sum + (page.load_time_ms || 0);
        }, 0) / totalPages;
        var schemaCount = results.filter(function(page) {
            return page.has_schema;
        }).length;
        
        var summaryHtml = '<div class="summary-grid">';
        summaryHtml += '<div class="summary-item"><strong>' + totalPages + '</strong><br>Pages Analyzed</div>';
        summaryHtml += '<div class="summary-item"><strong>' + issueCount + '</strong><br>Pages with Issues</div>';
        summaryHtml += '<div class="summary-item"><strong>' + Math.round(avgLoadTime) + 'ms</strong><br>Avg Load Time</div>';
        summaryHtml += '<div class="summary-item"><strong>' + Math.round((schemaCount/totalPages)*100) + '%</strong><br>Schema Usage</div>';
        summaryHtml += '</div>';
        
        $('.results-summary').html(summaryHtml);
        
        // Generate table
        var tableHtml = '<table class="results-data-table">';
        tableHtml += '<thead><tr><th>URL</th><th>Title</th><th>Meta Description</th><th>Issues</th></tr></thead>';
        tableHtml += '<tbody>';
        
        results.forEach(function(page) {
            var issues = [];
            if (!page.title) issues.push('No title');
            if (!page.meta_description) issues.push('No meta description');
            if (!page.has_schema) issues.push('No schema');
            if (!page.has_og_tags) issues.push('No Open Graph');
            if (page.load_time_ms > 3000) issues.push('Slow loading');
            
            tableHtml += '<tr>';
            tableHtml += '<td><a href="' + page.url + '" target="_blank">' + 
                        (page.url.length > 60 ? page.url.substring(0, 60) + '...' : page.url) + '</a></td>';
            tableHtml += '<td>' + (page.title || '<em>Missing</em>') + '</td>';
            tableHtml += '<td>' + (page.meta_description || '<em>Missing</em>') + '</td>';
            tableHtml += '<td class="' + (issues.length > 0 ? 'has-issues' : 'no-issues') + '">';
            tableHtml += issues.length > 0 ? issues.join(', ') : 'None';
            tableHtml += '</td>';
            tableHtml += '</tr>';
        });
        
        tableHtml += '</tbody></table>';
        $('.results-table').html(tableHtml);
        $('.crawl-button').prop('disabled', false);
    }
    
    function exportResults() {
        if (!currentSessionId) {
            alert('No results to export');
            return;
        }
        
        $.post(seoCrawlerAjax.ajax_url, {
            action: 'seo_export_data',
            nonce: seoCrawlerAjax.nonce,
            session_id: currentSessionId
        })
        .done(function(response) {
            var blob = new Blob([response], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'seo-analysis-report.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .fail(function() {
            alert('Failed to export results');
        });
    }
    
    function resetForm() {
        $('#frontend-progress').hide();
        $('.crawl-button').prop('disabled', false);
        $('.progress-fill').css('width', '0%');
    }
});
</script>