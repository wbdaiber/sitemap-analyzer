/**
 * Streamlit-Style JavaScript for SEO Crawler WordPress Plugin
 * Replicates the interactive functionality of the original Streamlit application
 */

(function($) {
    'use strict';

    // Global variables
    let currentSessionId = null;
    let pollInterval = null;
    let currentResults = null;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeApp();
    });

    function initializeApp() {
        // Set user ID display
        if (window.seoCrawlerUserId) {
            $('#display-user-id').text(window.seoCrawlerUserId);
        }

        // Event listeners
        $('#start-crawl-btn').on('click', startCrawl);
        $('#validate-sitemap-btn').on('click', validateSitemap);
        $('#export-csv-btn').on('click', exportResults);
        $('#table-search').on('input', filterTable);

        // Load initial crawl history
        loadCrawlHistory();

        // Auto-save form data
        saveFormData();
        loadFormData();
    }

    function startCrawl() {
        const sitemapUrl = $('#sitemap-url-input').val().trim();
        const delay = parseFloat($('#crawl-delay-select').val());
        const maxPages = parseInt($('#max-pages-input').val());

        // Validation
        if (!sitemapUrl) {
            showError('Please enter a sitemap URL');
            return;
        }

        if (!isValidUrl(sitemapUrl)) {
            showError('Please enter a valid URL');
            return;
        }

        // Save form data
        saveFormData();

        // Show progress section
        showProgressSection();

        // Disable start button
        $('#start-crawl-btn').prop('disabled', true).text('🔄 Crawling...');

        // Start the crawl
        $.ajax({
            url: seoCrawlerAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_crawler_start',
                nonce: seoCrawlerAjax.nonce,
                sitemap_url: sitemapUrl,
                delay: delay,
                max_pages: maxPages,
                user_id: window.seoCrawlerUserId || 'anonymous'
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    currentSessionId = data.session_id;
                    pollCrawlProgress();
                } else {
                    showError(data.error || 'Failed to start crawl');
                    resetCrawlForm();
                }
            },
            error: function() {
                showError('Network error. Please try again.');
                resetCrawlForm();
            }
        });
    }

    function validateSitemap() {
        const sitemapUrl = $('#sitemap-url-input').val().trim();

        if (!sitemapUrl) {
            showError('Please enter a sitemap URL');
            return;
        }

        $('#validate-sitemap-btn').prop('disabled', true).text('🔄 Validating...');

        $.ajax({
            url: seoCrawlerAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_crawler_validate',
                nonce: seoCrawlerAjax.nonce,
                sitemap_url: sitemapUrl
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    showSuccess(`Valid sitemap! Found ${data.urls_found || 'multiple'} URLs.`);
                } else {
                    showError(data.message || 'Invalid sitemap');
                }
            },
            error: function() {
                showError('Failed to validate sitemap');
            },
            complete: function() {
                $('#validate-sitemap-btn').prop('disabled', false).text('✅ Validate Sitemap');
            }
        });
    }

    function pollCrawlProgress() {
        if (!currentSessionId) return;

        pollInterval = setInterval(function() {
            $.ajax({
                url: seoCrawlerAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'seo_crawler_status',
                    nonce: seoCrawlerAjax.nonce,
                    session_id: currentSessionId
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    updateProgress(data);

                    if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        loadResults();
                    } else if (data.status === 'error') {
                        clearInterval(pollInterval);
                        showError(data.error_message || 'Crawl failed');
                        resetCrawlForm();
                    }
                },
                error: function() {
                    clearInterval(pollInterval);
                    showError('Lost connection. Please refresh and try again.');
                    resetCrawlForm();
                }
            });
        }, 3000);
    }

    function updateProgress(data) {
        const progress = data.progress || 0;
        const currentPages = data.completed_pages || 0;
        const totalPages = data.total_pages || 0;

        $('#progress-fill').css('width', progress + '%');
        $('#progress-text').text(`Analyzing your website... ${progress}% complete`);
        $('#progress-details').text(`Processed ${currentPages} of ${totalPages} pages`);
    }

    function loadResults() {
        if (!currentSessionId) return;

        $.ajax({
            url: seoCrawlerAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_crawler_results',
                nonce: seoCrawlerAjax.nonce,
                session_id: currentSessionId
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    currentResults = data;
                    displayResults(data);
                    hideProgressSection();
                    showResultsSection();
                    resetCrawlForm();
                    loadCrawlHistory(); // Refresh history
                } else {
                    showError(data.error || 'Failed to load results');
                    resetCrawlForm();
                }
            },
            error: function() {
                showError('Failed to load results');
                resetCrawlForm();
            }
        });
    }

    function displayResults(data) {
        // Display summary metrics
        displaySummaryMetrics(data.summary);

        // Display critical issues
        displayCriticalIssues(data.results);

        // Display detailed results table
        displayDetailedTable(data.results);

        // Display recommendations
        displayRecommendations(data.recommendations);

        // Create charts
        createCharts(data.summary, data.results);
    }

    function displaySummaryMetrics(summary) {
        const metricsHtml = `
            <div class="metric-card">
                <span class="metric-value">${summary.total_pages}</span>
                <div class="metric-label">Pages Analyzed</div>
            </div>
            <div class="metric-card">
                <span class="metric-value">${summary.seo_issues}</span>
                <div class="metric-label">SEO Issues Found</div>
            </div>
            <div class="metric-card">
                <span class="metric-value">${summary.avg_load_time}ms</span>
                <div class="metric-label">Avg Load Time</div>
            </div>
            <div class="metric-card">
                <span class="metric-value">${summary.schema_stats?.pages_with_schema || 0}</span>
                <div class="metric-label">Pages with Schema</div>
            </div>
            <div class="metric-card">
                <span class="metric-value">${summary.social_stats?.pages_with_complete_social || 0}</span>
                <div class="metric-label">Complete Social Tags</div>
            </div>
            <div class="metric-card">
                <span class="metric-value">${summary.technical_stats?.https_pages || 0}</span>
                <div class="metric-label">HTTPS Pages</div>
            </div>
        `;

        $('#summary-metrics').html(metricsHtml);
    }

    function displayCriticalIssues(results) {
        const issues = identifyCriticalIssues(results);
        let issuesHtml = '';

        if (issues.length === 0) {
            issuesHtml = '<div class="issue-card"><p>🎉 No critical issues found! Your SEO looks good.</p></div>';
        } else {
            issues.forEach(issue => {
                issuesHtml += `
                    <div class="issue-card ${issue.severity.toLowerCase()}">
                        <div class="issue-title">${issue.type}</div>
                        <span class="issue-severity ${issue.severity.toLowerCase()}">${issue.severity}</span>
                        <div class="issue-description">${issue.description}</div>
                        <div class="issue-count"><strong>${issue.count}</strong> pages affected</div>
                    </div>
                `;
            });
        }

        $('#critical-issues-container').html(`<div class="critical-issues-grid">${issuesHtml}</div>`);
    }

    function displayDetailedTable(results) {
        if (!results || results.length === 0) {
            $('#detailed-results-table').html('<p>No results to display.</p>');
            return;
        }

        let tableHtml = `
            <table class="streamlit-table">
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
        `;

        results.forEach(page => {
            const issues = [];
            if (!page.title) issues.push('No title');
            if (!page.meta_description) issues.push('No meta description');
            if (page.h1_count === 0) issues.push('No H1');
            if (page.h1_count > 1) issues.push('Multiple H1s');
            if (!page.has_schema) issues.push('No schema');
            if (!page.has_og_tags) issues.push('No Open Graph');
            if (page.load_time_ms > 3000) issues.push('Slow loading');

            tableHtml += `
                <tr>
                    <td><a href="${page.url}" target="_blank" title="${page.url}">${truncateUrl(page.url)}</a></td>
                    <td title="${page.title || 'Missing'}">${truncateText(page.title || 'Missing', 50)}</td>
                    <td title="${page.meta_description || 'Missing'}">${truncateText(page.meta_description || 'Missing', 60)}</td>
                    <td>${page.h1_count}</td>
                    <td><span class="status-code status-${Math.floor(page.status_code / 100)}xx">${page.status_code}</span></td>
                    <td>${Math.round(page.load_time_ms)}ms</td>
                    <td class="${issues.length > 0 ? 'has-issues' : 'no-issues'}">${issues.length > 0 ? issues.join(', ') : 'None'}</td>
                </tr>
            `;
        });

        tableHtml += '</tbody></table>';
        $('#detailed-results-table').html(tableHtml);
    }

    function displayRecommendations(recommendations) {
        if (!recommendations || recommendations.length === 0) {
            $('#recommendations-container').html('<p>No specific recommendations at this time.</p>');
            return;
        }

        let recHtml = '';
        recommendations.forEach(rec => {
            recHtml += `
                <div class="recommendation-card">
                    <div class="recommendation-header">
                        <h4 class="recommendation-title">${rec.title}</h4>
                        <span class="recommendation-priority ${rec.priority.toLowerCase()}">${rec.priority}</span>
                    </div>
                    <div class="recommendation-description">${rec.description}</div>
                    <div class="recommendation-action"><strong>Action:</strong> ${rec.action}</div>
                </div>
            `;
        });

        $('#recommendations-container').html(`<div class="recommendations-grid">${recHtml}</div>`);
    }

    function createCharts(summary, results) {
        // Create simple chart representations (could be enhanced with Chart.js)
        createSEOIssuesChart(results);
        createStatusCodeChart(summary.status_codes);
        createLoadTimeChart(results);
    }

    function createSEOIssuesChart(results) {
        const issues = {
            'Missing Titles': results.filter(p => !p.title).length,
            'Missing Meta Descriptions': results.filter(p => !p.meta_description).length,
            'Missing Schema': results.filter(p => !p.has_schema).length,
            'Missing Open Graph': results.filter(p => !p.has_og_tags).length,
            'Multiple H1s': results.filter(p => p.h1_count > 1).length,
            'Slow Loading': results.filter(p => p.load_time_ms > 3000).length
        };

        let chartHtml = '<div class="simple-bar-chart">';
        Object.entries(issues).forEach(([issue, count]) => {
            if (count > 0) {
                const percentage = (count / results.length) * 100;
                chartHtml += `
                    <div class="chart-bar">
                        <div class="chart-label">${issue}</div>
                        <div class="chart-bar-container">
                            <div class="chart-bar-fill" style="width: ${percentage}%"></div>
                            <span class="chart-value">${count}</span>
                        </div>
                    </div>
                `;
            }
        });
        chartHtml += '</div>';

        $('#seo-issues-chart').html(chartHtml);
    }

    function createStatusCodeChart(statusCodes) {
        if (!statusCodes || statusCodes.length === 0) {
            $('#status-code-chart').html('<p>No status code data available.</p>');
            return;
        }

        let chartHtml = '<div class="simple-pie-chart">';
        statusCodes.forEach(status => {
            chartHtml += `
                <div class="pie-item">
                    <span class="status-code status-${Math.floor(status.status_code / 100)}xx">${status.status_code}</span>
                    <span class="count">${status.count} pages</span>
                </div>
            `;
        });
        chartHtml += '</div>';

        $('#status-code-chart').html(chartHtml);
    }

    function createLoadTimeChart(results) {
        const loadTimes = results.map(p => p.load_time_ms);
        const bins = {
            'Fast (< 1s)': loadTimes.filter(t => t < 1000).length,
            'Good (1-2s)': loadTimes.filter(t => t >= 1000 && t < 2000).length,
            'Fair (2-3s)': loadTimes.filter(t => t >= 2000 && t < 3000).length,
            'Slow (> 3s)': loadTimes.filter(t => t >= 3000).length
        };

        let chartHtml = '<div class="simple-bar-chart">';
        Object.entries(bins).forEach(([range, count]) => {
            const percentage = (count / results.length) * 100;
            chartHtml += `
                <div class="chart-bar">
                    <div class="chart-label">${range}</div>
                    <div class="chart-bar-container">
                        <div class="chart-bar-fill" style="width: ${percentage}%"></div>
                        <span class="chart-value">${count}</span>
                    </div>
                </div>
            `;
        });
        chartHtml += '</div>';

        $('#load-time-chart').html(chartHtml);
    }

    function loadCrawlHistory() {
        if (!window.seoCrawlerUserId) return;

        $.ajax({
            url: seoCrawlerAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_crawler_history',
                nonce: seoCrawlerAjax.nonce,
                user_id: window.seoCrawlerUserId
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    displayCrawlHistory(data.history);
                }
            }
        });
    }

    function displayCrawlHistory(history) {
        if (!history || history.length === 0) {
            $('#crawl-history-container').html('<p>No previous crawls found.</p>');
            return;
        }

        let historyHtml = '<div class="history-grid">';
        history.slice(0, 5).forEach(crawl => {
            const domain = extractDomain(crawl.sitemap_url);
            const date = new Date(crawl.created_at).toLocaleDateString();
            
            historyHtml += `
                <div class="history-card">
                    <div class="history-info">
                        <h4>${domain}</h4>
                        <p>${date} - ${crawl.completed_pages}/${crawl.total_pages} pages</p>
                    </div>
                    <span class="history-status ${crawl.status}">${crawl.status}</span>
                </div>
            `;
        });
        historyHtml += '</div>';

        $('#crawl-history-container').html(historyHtml);
    }

    function exportResults() {
        if (!currentSessionId) {
            showError('No results to export');
            return;
        }

        $('#export-csv-btn').prop('disabled', true).text('📥 Exporting...');

        $.ajax({
            url: seoCrawlerAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_crawler_export',
                nonce: seoCrawlerAjax.nonce,
                session_id: currentSessionId
            },
            success: function(response) {
                // Create download
                const blob = new Blob([response], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `seo-crawl-results-${currentSessionId}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showSuccess('Results exported successfully!');
            },
            error: function() {
                showError('Failed to export results');
            },
            complete: function() {
                $('#export-csv-btn').prop('disabled', false).text('📥 Download CSV');
            }
        });
    }

    function filterTable() {
        const filter = $('#table-search').val().toLowerCase();
        const rows = $('#detailed-results-table tbody tr');

        rows.each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(filter));
        });
    }

    // Utility functions
    function identifyCriticalIssues(results) {
        const issues = [];

        const missingTitles = results.filter(p => !p.title).length;
        if (missingTitles > 0) {
            issues.push({
                type: 'Missing Page Titles',
                severity: 'High',
                count: missingTitles,
                description: 'Pages without title tags significantly hurt SEO performance.'
            });
        }

        const missingMeta = results.filter(p => !p.meta_description).length;
        if (missingMeta > 0) {
            issues.push({
                type: 'Missing Meta Descriptions',
                severity: 'Medium',
                count: missingMeta,
                description: 'Missing meta descriptions reduce click-through rates from search results.'
            });
        }

        const multipleH1 = results.filter(p => p.h1_count > 1).length;
        if (multipleH1 > 0) {
            issues.push({
                type: 'Multiple H1 Tags',
                severity: 'Medium',
                count: multipleH1,
                description: 'Multiple H1 tags can confuse search engines about page hierarchy.'
            });
        }

        const slowPages = results.filter(p => p.load_time_ms > 3000).length;
        if (slowPages > 0) {
            issues.push({
                type: 'Slow Loading Pages',
                severity: 'High',
                count: slowPages,
                description: 'Pages taking over 3 seconds to load hurt user experience and rankings.'
            });
        }

        return issues;
    }

    function showProgressSection() {
        $('#crawl-progress-section').show();
        $('#results-section').hide();
    }

    function hideProgressSection() {
        $('#crawl-progress-section').hide();
    }

    function showResultsSection() {
        $('#results-section').show();
    }

    function resetCrawlForm() {
        $('#start-crawl-btn').prop('disabled', false).text('🚀 Start SEO Analysis');
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    function saveFormData() {
        const formData = {
            sitemapUrl: $('#sitemap-url-input').val(),
            delay: $('#crawl-delay-select').val(),
            maxPages: $('#max-pages-input').val()
        };
        localStorage.setItem('seo_crawler_form_data', JSON.stringify(formData));
    }

    function loadFormData() {
        const savedData = localStorage.getItem('seo_crawler_form_data');
        if (savedData) {
            const formData = JSON.parse(savedData);
            $('#sitemap-url-input').val(formData.sitemapUrl || '');
            $('#crawl-delay-select').val(formData.delay || '3.0');
            $('#max-pages-input').val(formData.maxPages || '100');
        }
    }

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    function truncateUrl(url) {
        if (url.length <= 50) return url;
        try {
            const urlObj = new URL(url);
            const path = urlObj.pathname + urlObj.search;
            if (path.length > 30) {
                return urlObj.hostname + path.substring(0, 30) + '...';
            }
            return urlObj.hostname + path;
        } catch (_) {
            return url.substring(0, 50) + '...';
        }
    }

    function truncateText(text, maxLength) {
        if (!text || text.length <= maxLength) return text || '';
        return text.substring(0, maxLength) + '...';
    }

    function extractDomain(url) {
        try {
            return new URL(url).hostname;
        } catch (_) {
            return url;
        }
    }

    function showError(message) {
        // Simple error display - could be enhanced with a toast system
        alert('Error: ' + message);
    }

    function showSuccess(message) {
        // Simple success display - could be enhanced with a toast system
        alert('Success: ' + message);
    }

})(jQuery);