<?php
/**
 * Crawl History Page Template
 * Displays historical crawl data in admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$admin = new SEO_Crawler_Admin();
$sessions = $admin->get_user_sessions();
?>

<div class="wrap">
    <h1><?php _e('SEO Crawler History', 'seo-crawler'); ?></h1>
    
    <div class="crawl-history-admin">
        
        <?php if (empty($sessions)): ?>
            <div class="no-sessions-message">
                <h2><?php _e('No crawl sessions found', 'seo-crawler'); ?></h2>
                <p><?php _e('Start your first SEO crawl to see results here.', 'seo-crawler'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=seo-crawler'); ?>" class="button button-primary">
                    <?php _e('Start New Crawl', 'seo-crawler'); ?>
                </a>
            </div>
        <?php else: ?>
            
            <div class="history-controls">
                <div class="search-filter">
                    <input type="search" id="history-search" placeholder="<?php _e('Search by URL or domain...', 'seo-crawler'); ?>" />
                    <select id="status-filter">
                        <option value=""><?php _e('All Statuses', 'seo-crawler'); ?></option>
                        <option value="completed"><?php _e('Completed', 'seo-crawler'); ?></option>
                        <option value="running"><?php _e('Running', 'seo-crawler'); ?></option>
                        <option value="error"><?php _e('Failed', 'seo-crawler'); ?></option>
                    </select>
                </div>
                <div class="bulk-actions">
                    <button id="delete-selected" class="button" disabled>
                        <?php _e('Delete Selected', 'seo-crawler'); ?>
                    </button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped crawl-sessions-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all" />
                        </td>
                        <th class="manage-column"><?php _e('Domain', 'seo-crawler'); ?></th>
                        <th class="manage-column"><?php _e('Status', 'seo-crawler'); ?></th>
                        <th class="manage-column"><?php _e('Progress', 'seo-crawler'); ?></th>
                        <th class="manage-column"><?php _e('Created', 'seo-crawler'); ?></th>
                        <th class="manage-column"><?php _e('Duration', 'seo-crawler'); ?></th>
                        <th class="manage-column"><?php _e('Actions', 'seo-crawler'); ?></th>
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
                            $duration = $diff->format('%H:%I:%S') . ' (ongoing)';
                        }
                        
                        $status_class = 'status-' . $session->status;
                        ?>
                        <tr data-session-id="<?php echo $session->id; ?>" data-status="<?php echo $session->status; ?>">
                            <th class="check-column">
                                <input type="checkbox" name="session_ids[]" value="<?php echo $session->id; ?>" />
                            </th>
                            <td class="domain-column">
                                <strong><?php echo esc_html($domain); ?></strong><br>
                                <small title="<?php echo esc_attr($session->sitemap_url); ?>">
                                    <?php echo esc_html(strlen($session->sitemap_url) > 60 ? 
                                        substr($session->sitemap_url, 0, 60) . '...' : 
                                        $session->sitemap_url); ?>
                                </small>
                            </td>
                            <td class="status-column">
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($session->status); ?>
                                </span>
                                <?php if ($session->error_message): ?>
                                    <br><small class="error-message" title="<?php echo esc_attr($session->error_message); ?>">
                                        <?php echo esc_html(strlen($session->error_message) > 50 ? 
                                            substr($session->error_message, 0, 50) . '...' : 
                                            $session->error_message); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="progress-column">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: <?php echo $progress_percent; ?>%"></div>
                                </div>
                                <small><?php echo $session->completed_pages; ?> / <?php echo $session->total_pages; ?> pages</small>
                            </td>
                            <td class="created-column">
                                <?php echo date('M j, Y', strtotime($session->created_at)); ?><br>
                                <small><?php echo date('g:i A', strtotime($session->created_at)); ?></small>
                            </td>
                            <td class="duration-column">
                                <?php echo $duration; ?>
                            </td>
                            <td class="actions-column">
                                <?php if ($session->status === 'completed'): ?>
                                    <button class="button button-small view-results" 
                                           data-session-id="<?php echo $session->id; ?>">
                                        <?php _e('View Results', 'seo-crawler'); ?>
                                    </button>
                                    <button class="button button-small export-csv" 
                                           data-session-id="<?php echo $session->id; ?>">
                                        <?php _e('Export CSV', 'seo-crawler'); ?>
                                    </button>
                                <?php elseif ($session->status === 'running'): ?>
                                    <button class="button button-small stop-crawl" 
                                           data-session-id="<?php echo $session->id; ?>">
                                        <?php _e('Stop Crawl', 'seo-crawler'); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="button button-small button-link-delete delete-session" 
                                       data-session-id="<?php echo $session->id; ?>">
                                    <?php _e('Delete', 'seo-crawler'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php endif; ?>
        
    </div>
</div>

<!-- Results Modal -->
<div id="results-modal" class="seo-results-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Crawl Results', 'seo-crawler'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modal-results-content">
                <!-- Results will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
.crawl-history-admin {
    margin-top: 20px;
}

.no-sessions-message {
    text-align: center;
    padding: 3rem;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.history-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.search-filter {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-filter input {
    width: 250px;
}

.search-filter select {
    width: 120px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
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

.progress-bar-mini {
    width: 80px;
    height: 6px;
    background: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 4px;
}

.progress-fill-mini {
    height: 100%;
    background: #00a32a;
    transition: width 0.3s;
}

.error-message {
    color: #d63638;
    font-style: italic;
}

.actions-column .button {
    margin: 2px;
    font-size: 11px;
    padding: 4px 8px;
    height: auto;
    line-height: 1.4;
}

/* Modal styles */
.seo-results-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.seo-results-modal .modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 4px;
    width: 90%;
    max-width: 1000px;
    max-height: 80vh;
    overflow: hidden;
}

.seo-results-modal .modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f9f9f9;
}

.seo-results-modal .modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: #000;
}

.seo-results-modal .modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: calc(80vh - 80px);
}

/* Responsive */
@media screen and (max-width: 782px) {
    .history-controls {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .search-filter {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-filter input,
    .search-filter select {
        width: 100%;
    }
    
    .crawl-sessions-table .actions-column .button {
        display: block;
        width: 100%;
        margin-bottom: 4px;
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
            var domainText = $row.find('.domain-column').text().toLowerCase();
            var rowStatus = $row.data('status');
            
            var matchesSearch = !searchTerm || domainText.includes(searchTerm);
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
    
    // Action handlers
    $('.view-results').on('click', function(e) {
        e.preventDefault();
        var sessionId = $(this).data('session-id');
        loadAndDisplayResults(sessionId);
    });
    
    $('.export-csv').on('click', function(e) {
        e.preventDefault();
        var sessionId = $(this).data('session-id');
        exportSessionData(sessionId);
    });
    
    $('.delete-session').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this crawl session?')) {
            var sessionId = $(this).data('session-id');
            deleteSession(sessionId);
        }
    });
    
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
    
    // Modal functionality
    $('.modal-close').on('click', function() {
        $('#results-modal').hide();
    });
    
    $(window).on('click', function(e) {
        if (e.target.id === 'results-modal') {
            $('#results-modal').hide();
        }
    });
    
    function loadAndDisplayResults(sessionId) {
        $('#results-modal').show();
        $('#modal-results-content').html('<p>Loading results...</p>');
        
        $.post(ajaxurl, {
            action: 'seo_crawler_results',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            var data = JSON.parse(response);
            if (data.success && data.results) {
                displayModalResults(data.results, data.summary);
            } else {
                $('#modal-results-content').html('<p>Error loading results: ' + (data.error || 'Unknown error') + '</p>');
            }
        })
        .fail(function() {
            $('#modal-results-content').html('<p>Failed to load results.</p>');
        });
    }
    
    function displayModalResults(results, summary) {
        var html = '<div class="modal-results-summary">';
        html += '<h4>Summary</h4>';
        html += '<p><strong>Total Pages:</strong> ' + results.length + '</p>';
        
        if (summary) {
            html += '<p><strong>SEO Issues:</strong> ' + (summary.seo_issues || 0) + '</p>';
            html += '<p><strong>Average Load Time:</strong> ' + (summary.avg_load_time || 0) + 'ms</p>';
        }
        
        html += '</div>';
        
        html += '<div class="modal-results-table">';
        html += '<h4>Detailed Results</h4>';
        html += '<table class="wp-list-table widefat">';
        html += '<thead><tr>';
        html += '<th>URL</th><th>Title</th><th>Status</th><th>Issues</th>';
        html += '</tr></thead><tbody>';
        
        results.slice(0, 50).forEach(function(page) {
            var issues = [];
            if (!page.title) issues.push('No title');
            if (!page.meta_description) issues.push('No meta description');
            if (!page.has_schema) issues.push('No schema');
            if (!page.has_og_tags) issues.push('No Open Graph');
            
            var url = page.url.length > 60 ? page.url.substring(0, 60) + '...' : page.url;
            var title = page.title ? (page.title.length > 40 ? page.title.substring(0, 40) + '...' : page.title) : 'Missing';
            
            html += '<tr>';
            html += '<td><a href="' + page.url + '" target="_blank">' + url + '</a></td>';
            html += '<td>' + title + '</td>';
            html += '<td>' + page.status_code + '</td>';
            html += '<td>' + (issues.length > 0 ? issues.join(', ') : 'None') + '</td>';
            html += '</tr>';
        });
        
        if (results.length > 50) {
            html += '<tr><td colspan="4"><em>Showing first 50 results. Export CSV for complete data.</em></td></tr>';
        }
        
        html += '</tbody></table></div>';
        
        $('#modal-results-content').html(html);
    }
    
    function exportSessionData(sessionId) {
        $.post(ajaxurl, {
            action: 'seo_crawler_export',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            var blob = new Blob([response], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'seo-crawl-' + sessionId + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .fail(function() {
            alert('Failed to export data.');
        });
    }
    
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
                    updateBulkActions();
                });
            } else {
                alert('Failed to delete session: ' + (data.error || 'Unknown error'));
            }
        })
        .fail(function() {
            alert('Failed to delete session.');
        });
    }
});
</script>