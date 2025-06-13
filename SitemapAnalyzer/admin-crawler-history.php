<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get crawl history from database
global $wpdb;
$sessions = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}seo_crawler_sessions 
     ORDER BY created_at DESC 
     LIMIT 50"
);
?>

<div class="wrap">
    <h1><?php _e('SEO Crawler History', 'seo-crawler-pro'); ?></h1>
    
    <div class="seo-crawler-history-container">
        
        <?php if (empty($sessions)): ?>
            <div class="no-sessions">
                <p><?php _e('No crawl sessions found.', 'seo-crawler-pro'); ?></p>
                <p><a href="<?php echo admin_url('admin.php?page=seo-crawler-pro'); ?>" class="button-primary">
                    <?php _e('Start Your First Crawl', 'seo-crawler-pro'); ?>
                </a></p>
            </div>
        <?php else: ?>
            
            <div class="history-filters">
                <input type="search" id="history-search" placeholder="<?php _e('Search by URL or domain...', 'seo-crawler-pro'); ?>" />
                <select id="status-filter">
                    <option value=""><?php _e('All Statuses', 'seo-crawler-pro'); ?></option>
                    <option value="completed"><?php _e('Completed', 'seo-crawler-pro'); ?></option>
                    <option value="running"><?php _e('Running', 'seo-crawler-pro'); ?></option>
                    <option value="error"><?php _e('Failed', 'seo-crawler-pro'); ?></option>
                </select>
                <button id="delete-selected" class="button" disabled>
                    <?php _e('Delete Selected', 'seo-crawler-pro'); ?>
                </button>
            </div>
            
            <form id="bulk-actions-form">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="select-all" />
                            </td>
                            <th class="manage-column"><?php _e('Sitemap URL', 'seo-crawler-pro'); ?></th>
                            <th class="manage-column"><?php _e('Status', 'seo-crawler-pro'); ?></th>
                            <th class="manage-column"><?php _e('Progress', 'seo-crawler-pro'); ?></th>
                            <th class="manage-column"><?php _e('Created', 'seo-crawler-pro'); ?></th>
                            <th class="manage-column"><?php _e('Duration', 'seo-crawler-pro'); ?></th>
                            <th class="manage-column"><?php _e('Actions', 'seo-crawler-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <?php
                            $domain = parse_url($session->sitemap_url, PHP_URL_HOST);
                            $progress_percent = $session->total_pages > 0 ? 
                                round(($session->completed_pages / $session->total_pages) * 100) : 0;
                            
                            $duration = '';
                            if ($session->completed_at) {
                                $start = new DateTime($session->created_at);
                                $end = new DateTime($session->completed_at);
                                $diff = $start->diff($end);
                                $duration = $diff->format('%H:%I:%S');
                            } elseif ($session->status === 'running') {
                                $start = new DateTime($session->created_at);
                                $now = new DateTime();
                                $diff = $start->diff($now);
                                $duration = $diff->format('%H:%I:%S') . ' (running)';
                            }
                            
                            $status_class = '';
                            switch ($session->status) {
                                case 'completed':
                                    $status_class = 'status-completed';
                                    break;
                                case 'running':
                                    $status_class = 'status-running';
                                    break;
                                case 'error':
                                    $status_class = 'status-error';
                                    break;
                                default:
                                    $status_class = 'status-pending';
                            }
                            ?>
                            <tr data-session-id="<?php echo $session->id; ?>" data-status="<?php echo $session->status; ?>">
                                <th class="check-column">
                                    <input type="checkbox" name="session_ids[]" value="<?php echo $session->id; ?>" />
                                </th>
                                <td class="sitemap-url">
                                    <strong><?php echo esc_html($domain); ?></strong><br>
                                    <small><?php echo esc_html($session->sitemap_url); ?></small>
                                </td>
                                <td class="status">
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($session->status); ?>
                                    </span>
                                    <?php if ($session->error_message): ?>
                                        <br><small class="error-message">
                                            <?php echo esc_html($session->error_message); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="progress">
                                    <div class="progress-bar-small">
                                        <div class="progress-fill-small" style="width: <?php echo $progress_percent; ?>%"></div>
                                    </div>
                                    <small><?php echo $session->completed_pages; ?> / <?php echo $session->total_pages; ?> pages</small>
                                </td>
                                <td class="created-date">
                                    <?php echo date('M j, Y g:i A', strtotime($session->created_at)); ?>
                                </td>
                                <td class="duration">
                                    <?php echo $duration; ?>
                                </td>
                                <td class="actions">
                                    <?php if ($session->status === 'completed'): ?>
                                        <a href="#" class="button button-small view-results" 
                                           data-session-id="<?php echo $session->id; ?>">
                                            <?php _e('View Results', 'seo-crawler-pro'); ?>
                                        </a>
                                        <a href="#" class="button button-small export-csv" 
                                           data-session-id="<?php echo $session->id; ?>">
                                            <?php _e('Export CSV', 'seo-crawler-pro'); ?>
                                        </a>
                                    <?php elseif ($session->status === 'running'): ?>
                                        <a href="#" class="button button-small stop-crawl" 
                                           data-session-id="<?php echo $session->id; ?>">
                                            <?php _e('Stop Crawl', 'seo-crawler-pro'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="#" class="button button-small delete-session" 
                                       data-session-id="<?php echo $session->id; ?>">
                                        <?php _e('Delete', 'seo-crawler-pro'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
        <?php endif; ?>
        
    </div>
</div>

<!-- Results Modal -->
<div id="results-modal" class="seo-crawler-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Crawl Results', 'seo-crawler-pro'); ?></h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div id="modal-results-content">
                <!-- Results will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
.seo-crawler-history-container {
    margin-top: 20px;
}

.no-sessions {
    text-align: center;
    padding: 40px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 5px;
}

.history-filters {
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
}

.history-filters input[type="search"] {
    width: 300px;
}

.history-filters select {
    width: 150px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-running {
    background: #d1ecf1;
    color: #0c5460;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.progress-bar-small {
    width: 100px;
    height: 10px;
    background: #eee;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill-small {
    height: 100%;
    background: #0073aa;
    transition: width 0.3s;
}

.error-message {
    color: #dc3232;
    font-style: italic;
}

.sitemap-url {
    max-width: 300px;
    word-break: break-all;
}

.actions .button {
    margin-right: 5px;
    margin-bottom: 5px;
}

/* Modal Styles */
.seo-crawler-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 5px;
    width: 90%;
    max-width: 1200px;
    max-height: 80vh;
    overflow: hidden;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close-modal {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
}

.close-modal:hover {
    color: #333;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: calc(80vh - 80px);
}

@media (max-width: 768px) {
    .history-filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .history-filters input,
    .history-filters select {
        width: 100%;
    }
    
    .sitemap-url {
        max-width: 200px;
    }
    
    .actions .button {
        font-size: 12px;
        padding: 4px 8px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Search and filter functionality
    $('#history-search, #status-filter').on('input change', function() {
        filterHistory();
    });
    
    function filterHistory() {
        var searchTerm = $('#history-search').val().toLowerCase();
        var statusFilter = $('#status-filter').val();
        
        $('tbody tr').each(function() {
            var $row = $(this);
            var sitemapText = $row.find('.sitemap-url').text().toLowerCase();
            var rowStatus = $row.data('status');
            
            var matchesSearch = !searchTerm || sitemapText.includes(searchTerm);
            var matchesStatus = !statusFilter || rowStatus === statusFilter;
            
            if (matchesSearch && matchesStatus) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }
    
    // Select all functionality
    $('#select-all').on('change', function() {
        $('input[name="session_ids[]"]:visible').prop('checked', this.checked);
        updateBulkActions();
    });
    
    $('input[name="session_ids[]"]').on('change', function() {
        updateBulkActions();
    });
    
    function updateBulkActions() {
        var checkedCount = $('input[name="session_ids[]"]:checked').length;
        $('#delete-selected').prop('disabled', checkedCount === 0);
    }
    
    // View results
    $('.view-results').on('click', function(e) {
        e.preventDefault();
        var sessionId = $(this).data('session-id');
        loadResults(sessionId);
    });
    
    function loadResults(sessionId) {
        $('#results-modal').show();
        $('#modal-results-content').html('<p>Loading results...</p>');
        
        $.post(ajaxurl, {
            action: 'seo_get_results',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                displayModalResults(data.results);
            } else {
                $('#modal-results-content').html('<p>Error loading results: ' + data.error + '</p>');
            }
        })
        .fail(function() {
            $('#modal-results-content').html('<p>Failed to load results.</p>');
        });
    }
    
    function displayModalResults(results) {
        var html = '<div class="results-summary">';
        html += '<h4>Summary</h4>';
        html += '<p>Total Pages: ' + results.length + '</p>';
        
        var issueCount = results.filter(function(page) {
            return !page.title || !page.meta_description || !page.has_schema;
        }).length;
        
        html += '<p>Pages with Issues: ' + issueCount + '</p>';
        html += '</div>';
        
        html += '<div class="results-table-container">';
        html += '<table class="wp-list-table widefat">';
        html += '<thead><tr>';
        html += '<th>URL</th><th>Title</th><th>Meta Description</th><th>Status</th><th>Issues</th>';
        html += '</tr></thead><tbody>';
        
        results.forEach(function(page) {
            var issues = [];
            if (!page.title) issues.push('No title');
            if (!page.meta_description) issues.push('No meta description');
            if (!page.has_schema) issues.push('No schema');
            if (!page.has_og_tags) issues.push('No Open Graph');
            
            html += '<tr>';
            html += '<td><a href="' + page.url + '" target="_blank">' + 
                   (page.url.length > 50 ? page.url.substring(0, 50) + '...' : page.url) + '</a></td>';
            html += '<td>' + (page.title || '<em>Missing</em>') + '</td>';
            html += '<td>' + (page.meta_description || '<em>Missing</em>') + '</td>';
            html += '<td>' + page.status_code + '</td>';
            html += '<td>' + (issues.length > 0 ? issues.join(', ') : 'None') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
        
        $('#modal-results-content').html(html);
    }
    
    // Export CSV
    $('.export-csv').on('click', function(e) {
        e.preventDefault();
        var sessionId = $(this).data('session-id');
        
        $.post(ajaxurl, {
            action: 'seo_export_data',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            // Create download
            var blob = new Blob([response], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'seo-crawl-' + sessionId + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        });
    });
    
    // Delete session
    $('.delete-session').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this crawl session?')) {
            var sessionId = $(this).data('session-id');
            deleteSession(sessionId);
        }
    });
    
    // Bulk delete
    $('#delete-selected').on('click', function() {
        var sessionIds = [];
        $('input[name="session_ids[]"]:checked').each(function() {
            sessionIds.push($(this).val());
        });
        
        if (sessionIds.length > 0 && confirm('Are you sure you want to delete ' + sessionIds.length + ' crawl sessions?')) {
            sessionIds.forEach(function(sessionId) {
                deleteSession(sessionId);
            });
        }
    });
    
    function deleteSession(sessionId) {
        $.post(ajaxurl, {
            action: 'seo_delete_session',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                $('tr[data-session-id="' + sessionId + '"]').fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert('Failed to delete session: ' + data.error);
            }
        });
    }
    
    // Close modal
    $('.close-modal').on('click', function() {
        $('#results-modal').hide();
    });
    
    $(window).on('click', function(e) {
        if (e.target.id === 'results-modal') {
            $('#results-modal').hide();
        }
    });
});
</script>