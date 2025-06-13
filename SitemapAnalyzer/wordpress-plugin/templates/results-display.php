<?php
/**
 * Results Display Template
 * Standalone template for displaying crawl results
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get session data
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if (!$session_id) {
    wp_die('Invalid session ID');
}

$admin = new SEO_Crawler_Admin();
$engine = new SEO_Crawler_Engine();

// Get session status
$status = $engine->get_crawl_status($session_id);
if ($status['status'] !== 'completed') {
    wp_die('Crawl not completed yet');
}

// Get results
$results = $admin->get_detailed_results($session_id);
$summary = $admin->get_session_summary($session_id);
$recommendations = $admin->generate_seo_recommendations($session_id);
$critical_issues = $admin->identify_critical_issues($session_id);

get_header();
?>

<div class="seo-crawler-results-page">
    <div class="streamlit-container">
        
        <div class="streamlit-header">
            <h1 class="streamlit-title">SEO Analysis Results</h1>
            <div class="streamlit-caption">
                Comprehensive SEO analysis results for your website crawl.
            </div>
        </div>
        
        <!-- Summary Metrics -->
        <div class="streamlit-section">
            <h2>Summary Report</h2>
            <div class="metric-grid">
                <div class="metric-card">
                    <span class="metric-value"><?php echo $summary['total_pages']; ?></span>
                    <div class="metric-label">Pages Analyzed</div>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo $summary['seo_issues']; ?></span>
                    <div class="metric-label">SEO Issues Found</div>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo $summary['avg_load_time']; ?>ms</span>
                    <div class="metric-label">Avg Load Time</div>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo $summary['schema_stats']->pages_with_schema ?? 0; ?></span>
                    <div class="metric-label">Pages with Schema</div>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo $summary['social_stats']->pages_with_complete_social ?? 0; ?></span>
                    <div class="metric-label">Complete Social Tags</div>
                </div>
                <div class="metric-card">
                    <span class="metric-value"><?php echo $summary['technical_stats']->https_pages ?? 0; ?></span>
                    <div class="metric-label">HTTPS Pages</div>
                </div>
            </div>
        </div>
        
        <!-- Critical Issues -->
        <?php if (!empty($critical_issues)): ?>
        <div class="streamlit-section">
            <h2>Critical Issues Requiring Attention</h2>
            <div class="critical-issues-grid">
                <?php foreach ($critical_issues as $issue): ?>
                <div class="issue-card <?php echo strtolower($issue['severity']); ?>">
                    <div class="issue-title"><?php echo esc_html($issue['type']); ?></div>
                    <span class="issue-severity <?php echo strtolower($issue['severity']); ?>"><?php echo $issue['severity']; ?></span>
                    <div class="issue-description"><?php echo esc_html($issue['description']); ?></div>
                    <div class="issue-count"><strong><?php echo $issue['count']; ?></strong> pages affected</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Detailed Results Table -->
        <div class="streamlit-section">
            <h2>Detailed Analysis Results</h2>
            <div class="table-controls">
                <input type="search" id="results-search" placeholder="Filter results..." class="streamlit-text-input">
                <a href="<?php echo admin_url('admin-ajax.php?action=seo_crawler_export&session_id=' . $session_id . '&nonce=' . wp_create_nonce('seo_crawler_nonce')); ?>" 
                   class="streamlit-button secondary">Download CSV</a>
            </div>
            <div class="table-container">
                <table class="streamlit-table" id="results-table">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Title</th>
                            <th>Meta Description</th>
                            <th>H1 Count</th>
                            <th>Status</th>
                            <th>Load Time</th>
                            <th>Issues</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $page): ?>
                            <?php
                            $issues = array();
                            if (!$page['title']) $issues[] = 'No title';
                            if (!$page['meta_description']) $issues[] = 'No meta description';
                            if ($page['h1_count'] === 0) $issues[] = 'No H1';
                            if ($page['h1_count'] > 1) $issues[] = 'Multiple H1s';
                            if (!$page['has_schema']) $issues[] = 'No schema';
                            if (!$page['has_og_tags']) $issues[] = 'No Open Graph';
                            if ($page['load_time_ms'] > 3000) $issues[] = 'Slow loading';
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($page['url']); ?>" target="_blank" title="<?php echo esc_attr($page['url']); ?>">
                                        <?php echo esc_html(strlen($page['url']) > 50 ? substr($page['url'], 0, 50) . '...' : $page['url']); ?>
                                    </a>
                                </td>
                                <td title="<?php echo esc_attr($page['title'] ?: 'Missing'); ?>">
                                    <?php echo esc_html(strlen($page['title'] ?: 'Missing') > 50 ? substr($page['title'] ?: 'Missing', 0, 50) . '...' : ($page['title'] ?: 'Missing')); ?>
                                </td>
                                <td title="<?php echo esc_attr($page['meta_description'] ?: 'Missing'); ?>">
                                    <?php echo esc_html(strlen($page['meta_description'] ?: 'Missing') > 60 ? substr($page['meta_description'] ?: 'Missing', 0, 60) . '...' : ($page['meta_description'] ?: 'Missing')); ?>
                                </td>
                                <td><?php echo $page['h1_count']; ?></td>
                                <td><span class="status-code status-<?php echo floor($page['status_code'] / 100); ?>xx"><?php echo $page['status_code']; ?></span></td>
                                <td><?php echo round($page['load_time_ms']); ?>ms</td>
                                <td class="<?php echo count($issues) > 0 ? 'has-issues' : 'no-issues'; ?>">
                                    <?php echo count($issues) > 0 ? esc_html(implode(', ', $issues)) : 'None'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recommendations -->
        <?php if (!empty($recommendations)): ?>
        <div class="streamlit-section">
            <h2>SEO Recommendations</h2>
            <div class="recommendations-grid">
                <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-card">
                    <div class="recommendation-header">
                        <h3 class="recommendation-title"><?php echo esc_html($rec['title']); ?></h3>
                        <span class="recommendation-priority <?php echo strtolower($rec['priority']); ?>"><?php echo $rec['priority']; ?></span>
                    </div>
                    <div class="recommendation-description"><?php echo esc_html($rec['description']); ?></div>
                    <div class="recommendation-action"><strong>Action:</strong> <?php echo esc_html($rec['action']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Search functionality
    $('#results-search').on('input', function() {
        var filter = $(this).val().toLowerCase();
        $('#results-table tbody tr').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(filter));
        });
    });
    
    // Table sorting
    $('.streamlit-table th').css('cursor', 'pointer').click(function() {
        var table = $(this).closest('table');
        var index = $(this).index();
        var rows = table.find('tbody tr').get();
        
        rows.sort(function(a, b) {
            var aText = $(a).children('td').eq(index).text();
            var bText = $(b).children('td').eq(index).text();
            
            if ($.isNumeric(aText) && $.isNumeric(bText)) {
                return aText - bText;
            }
            
            return aText.localeCompare(bText);
        });
        
        if ($(this).hasClass('sorted-asc')) {
            rows.reverse();
            $(this).removeClass('sorted-asc').addClass('sorted-desc');
        } else {
            $(this).removeClass('sorted-desc').addClass('sorted-asc');
        }
        
        $.each(rows, function(index, row) {
            table.children('tbody').append(row);
        });
    });
});
</script>

<?php get_footer(); ?>