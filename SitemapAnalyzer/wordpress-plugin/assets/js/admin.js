/**
 * Admin-specific JavaScript for SEO Crawler WordPress Plugin
 * Enhances admin functionality and WordPress integration
 */

(function($) {
    'use strict';

    // Admin-specific initialization
    $(document).ready(function() {
        initializeAdminFeatures();
        setupAdminAjaxHandlers();
        enhanceAdminInterface();
    });

    function initializeAdminFeatures() {
        // Auto-refresh running crawls
        if ($('.status-running').length > 0) {
            startCrawlMonitoring();
        }

        // Setup admin notifications
        setupAdminNotifications();

        // Initialize tooltips
        initializeTooltips();

        // Setup keyboard shortcuts
        setupKeyboardShortcuts();
    }

    function setupAdminAjaxHandlers() {
        // Add missing AJAX handlers for admin-specific actions
        $(document).on('click', '.stop-crawl', function(e) {
            e.preventDefault();
            var sessionId = $(this).data('session-id');
            stopCrawlSession(sessionId);
        });

        // Delete session handler
        $(document).on('click', '.delete-session', function(e) {
            e.preventDefault();
            var sessionId = $(this).data('session-id');
            var $row = $(this).closest('tr');
            
            if (confirm('Are you sure you want to delete this crawl session? This action cannot be undone.')) {
                deleteSessionWithConfirmation(sessionId, $row);
            }
        });
    }

    function enhanceAdminInterface() {
        // Add admin-specific UI enhancements
        addProgressIndicators();
        setupDataTables();
        enhanceFormValidation();
        addBulkActions();
    }

    function startCrawlMonitoring() {
        var runningCrawls = $('.status-running').closest('tr').map(function() {
            return $(this).data('session-id');
        }).get();

        if (runningCrawls.length > 0) {
            var monitorInterval = setInterval(function() {
                updateRunningCrawls(runningCrawls, function(stillRunning) {
                    if (stillRunning.length === 0) {
                        clearInterval(monitorInterval);
                    } else {
                        runningCrawls = stillRunning;
                    }
                });
            }, 10000); // Check every 10 seconds
        }
    }

    function updateRunningCrawls(sessionIds, callback) {
        var stillRunning = [];

        sessionIds.forEach(function(sessionId) {
            $.post(ajaxurl, {
                action: 'seo_crawler_status',
                nonce: seoCrawlerAjax.nonce,
                session_id: sessionId
            })
            .done(function(response) {
                var data = JSON.parse(response);
                var $row = $('tr[data-session-id="' + sessionId + '"]');

                if (data.status === 'completed') {
                    updateRowStatus($row, 'completed', data);
                    showAdminNotice('Crawl completed for session ' + sessionId, 'success');
                } else if (data.status === 'error') {
                    updateRowStatus($row, 'error', data);
                    showAdminNotice('Crawl failed for session ' + sessionId + ': ' + data.error_message, 'error');
                } else if (data.status === 'running') {
                    updateProgressDisplay($row, data);
                    stillRunning.push(sessionId);
                }
            });
        });

        setTimeout(function() {
            callback(stillRunning);
        }, 1000);
    }

    function updateRowStatus($row, status, data) {
        var $statusCell = $row.find('.status-column');
        var $progressCell = $row.find('.progress-column');
        var $actionsCell = $row.find('.actions-column');

        // Update status badge
        var statusClass = 'status-' + status;
        var badgeHtml = '<span class="status-badge ' + statusClass + '">' + 
                       status.charAt(0).toUpperCase() + status.slice(1) + '</span>';

        if (data.error_message) {
            badgeHtml += '<br><small class="error-message">' + data.error_message + '</small>';
        }

        $statusCell.html(badgeHtml);

        // Update progress
        if (data.total_pages && data.completed_pages) {
            var progressPercent = Math.round((data.completed_pages / data.total_pages) * 100);
            $progressCell.find('.progress-fill-mini').css('width', progressPercent + '%');
            $progressCell.find('small').text(data.completed_pages + ' / ' + data.total_pages + ' pages');
        }

        // Update actions
        if (status === 'completed') {
            $actionsCell.html(
                '<button class="button button-small view-results" data-session-id="' + data.session_id + '">View Results</button>' +
                '<button class="button button-small export-csv" data-session-id="' + data.session_id + '">Export CSV</button>' +
                '<button class="button button-small button-link-delete delete-session" data-session-id="' + data.session_id + '">Delete</button>'
            );
        }

        // Update row data attribute
        $row.attr('data-status', status);
    }

    function updateProgressDisplay($row, data) {
        var $progressCell = $row.find('.progress-column');
        
        if (data.total_pages && data.completed_pages) {
            var progressPercent = Math.round((data.completed_pages / data.total_pages) * 100);
            $progressCell.find('.progress-fill-mini').css('width', progressPercent + '%');
            $progressCell.find('small').text(data.completed_pages + ' / ' + data.total_pages + ' pages');
        }
    }

    function stopCrawlSession(sessionId) {
        if (!confirm('Are you sure you want to stop this crawl?')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'seo_stop_crawl',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                showAdminNotice('Crawl stopped successfully', 'success');
                location.reload(); // Refresh to show updated status
            } else {
                showAdminNotice('Failed to stop crawl: ' + data.error, 'error');
            }
        })
        .fail(function() {
            showAdminNotice('Network error while stopping crawl', 'error');
        });
    }

    function deleteSessionWithConfirmation(sessionId, $row) {
        $.post(ajaxurl, {
            action: 'seo_delete_session',
            nonce: seoCrawlerAjax.nonce,
            session_id: sessionId
        })
        .done(function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    checkEmptyTable();
                });
                showAdminNotice('Crawl session deleted successfully', 'success');
            } else {
                showAdminNotice('Failed to delete session: ' + data.error, 'error');
            }
        })
        .fail(function() {
            showAdminNotice('Network error while deleting session', 'error');
        });
    }

    function checkEmptyTable() {
        if ($('.crawl-sessions-table tbody tr').length === 0) {
            $('.crawl-history-admin').html(
                '<div class="no-sessions-message">' +
                '<h2>No crawl sessions found</h2>' +
                '<p>Start your first SEO crawl to see results here.</p>' +
                '<a href="' + adminUrl + 'admin.php?page=seo-crawler" class="button button-primary">Start New Crawl</a>' +
                '</div>'
            );
        }
    }

    function setupAdminNotifications() {
        // Enhanced admin notification system
        window.showAdminNotice = function(message, type = 'info', dismissible = true) {
            var noticeClass = 'notice-' + type;
            var dismissClass = dismissible ? 'is-dismissible' : '';
            
            var $notice = $('<div class="notice ' + noticeClass + ' ' + dismissClass + '">' +
                          '<p>' + message + '</p>' +
                          (dismissible ? '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' : '') +
                          '</div>');
            
            $('.wrap').prepend($notice);
            
            // Auto-dismiss informational notices
            if (type === 'info' && dismissible) {
                setTimeout(function() {
                    $notice.fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            // Handle manual dismiss
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(500, function() {
                    $(this).remove();
                });
            });
        };

        // Override global functions for admin context
        window.showError = function(message) {
            showAdminNotice(message, 'error');
        };

        window.showSuccess = function(message) {
            showAdminNotice(message, 'success');
        };
    }

    function initializeTooltips() {
        // Add tooltips to various elements
        $('[title]').each(function() {
            var $element = $(this);
            var title = $element.attr('title');
            
            if (title && title.length > 50) {
                $element.attr('data-tooltip', title);
                $element.removeAttr('title');
                
                $element.hover(
                    function() {
                        var tooltip = $('<div class="admin-tooltip">' + $(this).attr('data-tooltip') + '</div>');
                        $('body').append(tooltip);
                        
                        var pos = $(this).offset();
                        tooltip.css({
                            top: pos.top - tooltip.outerHeight() - 5,
                            left: pos.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                        });
                    },
                    function() {
                        $('.admin-tooltip').remove();
                    }
                );
            }
        });
    }

    function setupKeyboardShortcuts() {
        $(document).keydown(function(e) {
            // Ctrl/Cmd + Enter to start crawl
            if ((e.ctrlKey || e.metaKey) && e.which === 13) {
                if ($('#start-crawl-btn').is(':visible') && !$('#start-crawl-btn').is(':disabled')) {
                    e.preventDefault();
                    $('#start-crawl-btn').click();
                }
            }
            
            // Escape to close modals
            if (e.which === 27) {
                $('.seo-results-modal:visible').hide();
            }
        });
    }

    function addProgressIndicators() {
        // Enhance progress indicators with better animations
        $('.progress-fill-mini').each(function() {
            var width = $(this).css('width');
            $(this).css('width', '0%').animate({ width: width }, 1000);
        });
    }

    function setupDataTables() {
        // Add sorting capabilities to tables
        $('.crawl-sessions-table th').css('cursor', 'pointer').click(function() {
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
    }

    function enhanceFormValidation() {
        // Enhanced form validation with real-time feedback
        $('#sitemap-url-input').on('input', function() {
            var url = $(this).val();
            var $feedback = $(this).siblings('.url-feedback');
            
            if ($feedback.length === 0) {
                $feedback = $('<div class="url-feedback"></div>');
                $(this).after($feedback);
            }
            
            if (url && !isValidUrl(url)) {
                $feedback.text('Please enter a valid URL').addClass('error');
                $('#start-crawl-btn, #validate-sitemap-btn').prop('disabled', true);
            } else if (url && !url.includes('sitemap')) {
                $feedback.text('URL should point to a sitemap file').addClass('warning');
                $('#start-crawl-btn, #validate-sitemap-btn').prop('disabled', false);
            } else {
                $feedback.text('').removeClass('error warning');
                $('#start-crawl-btn, #validate-sitemap-btn').prop('disabled', false);
            }
        });
    }

    function addBulkActions() {
        // Enhanced bulk actions functionality
        $('#delete-selected').click(function() {
            var selected = $('input[name="session_ids[]"]:checked');
            var count = selected.length;
            
            if (count === 0) return;
            
            var message = count === 1 ? 
                'Are you sure you want to delete this crawl session?' :
                'Are you sure you want to delete these ' + count + ' crawl sessions?';
            
            if (confirm(message)) {
                var sessionIds = selected.map(function() {
                    return $(this).val();
                }).get();
                
                bulkDeleteSessions(sessionIds);
            }
        });
    }

    function bulkDeleteSessions(sessionIds) {
        var completed = 0;
        var errors = [];
        
        sessionIds.forEach(function(sessionId) {
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
                    errors.push('Session ' + sessionId + ': ' + data.error);
                }
            })
            .fail(function() {
                errors.push('Session ' + sessionId + ': Network error');
            })
            .always(function() {
                completed++;
                if (completed === sessionIds.length) {
                    if (errors.length === 0) {
                        showAdminNotice('All selected sessions deleted successfully', 'success');
                    } else {
                        showAdminNotice('Some deletions failed: ' + errors.join(', '), 'error');
                    }
                    checkEmptyTable();
                }
            });
        });
    }

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

})(jQuery);

// CSS for admin tooltips and enhancements
jQuery(document).ready(function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .admin-tooltip {
                position: absolute;
                background: #1d2327;
                color: #fff;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                max-width: 250px;
                z-index: 999999;
                word-wrap: break-word;
            }
            
            .admin-tooltip::after {
                content: '';
                position: absolute;
                top: 100%;
                left: 50%;
                margin-left: -5px;
                border-width: 5px;
                border-style: solid;
                border-color: #1d2327 transparent transparent transparent;
            }
            
            .url-feedback {
                font-size: 12px;
                margin-top: 4px;
            }
            
            .url-feedback.error {
                color: #d63638;
            }
            
            .url-feedback.warning {
                color: #dba617;
            }
            
            .crawl-sessions-table th.sorted-asc::after {
                content: ' ↑';
                color: #2271b1;
            }
            
            .crawl-sessions-table th.sorted-desc::after {
                content: ' ↓';
                color: #2271b1;
            }
        `)
        .appendTo('head');
});