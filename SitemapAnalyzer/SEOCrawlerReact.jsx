import React, { useState, useEffect } from 'react';
import './SEOCrawler.css';

const SEOCrawler = () => {
    const [sitemapUrl, setSitemapUrl] = useState('');
    const [delay, setDelay] = useState(3.0);
    const [crawlData, setCrawlData] = useState(null);
    const [crawlHistory, setCrawlHistory] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [progress, setProgress] = useState({ current: 0, total: 0 });
    const [userId, setUserId] = useState('');
    const [activeTab, setActiveTab] = useState('detailed');

    // Generate user ID on component mount
    useEffect(() => {
        const storedUserId = localStorage.getItem('seo_crawler_user_id');
        if (storedUserId) {
            setUserId(storedUserId);
            loadCrawlHistory(storedUserId);
        } else {
            const newUserId = Math.random().toString(36).substr(2, 8);
            setUserId(newUserId);
            localStorage.setItem('seo_crawler_user_id', newUserId);
        }
    }, []);

    const loadCrawlHistory = async (userIdToLoad) => {
        try {
            const formData = new FormData();
            formData.append('action', 'get_crawl_history');
            formData.append('user_id', userIdToLoad);
            formData.append('nonce', window.seo_crawler_ajax.nonce);

            const response = await fetch(window.seo_crawler_ajax.ajax_url, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            setCrawlHistory(data || []);
        } catch (error) {
            console.error('Failed to load crawl history:', error);
        }
    };

    const validateSitemapUrl = (url) => {
        try {
            new URL(url);
            return url.includes('sitemap') && (url.endsWith('.xml') || url.includes('sitemap'));
        } catch {
            return false;
        }
    };

    const startCrawl = async () => {
        if (!validateSitemapUrl(sitemapUrl)) {
            alert('Please enter a valid sitemap URL');
            return;
        }

        setIsLoading(true);
        setProgress({ current: 0, total: 0 });

        try {
            const formData = new FormData();
            formData.append('action', 'seo_crawl');
            formData.append('sitemap_url', sitemapUrl);
            formData.append('delay', delay);
            formData.append('user_id', userId);
            formData.append('nonce', window.seo_crawler_ajax.nonce);

            const response = await fetch(window.seo_crawler_ajax.ajax_url, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.error) {
                alert(data.error);
            } else {
                setCrawlData(data);
                loadCrawlHistory(userId);
            }
        } catch (error) {
            alert('Crawl failed: ' + error.message);
        } finally {
            setIsLoading(false);
        }
    };

    const exportToCSV = () => {
        if (!crawlData) return;

        const headers = ['URL', 'Title', 'Meta Description', 'H1 Count', 'Word Count', 'Load Time', 'Has Schema', 'Has Open Graph'];
        const csvContent = [
            headers.join(','),
            ...crawlData.map(page => [
                `"${page.url}"`,
                `"${page.title || ''}"`,
                `"${page.meta_description || ''}"`,
                page.h1_count || 0,
                page.word_count || 0,
                page.load_time_ms || 0,
                page.has_schema ? 'Yes' : 'No',
                page.has_og_tags ? 'Yes' : 'No'
            ].join(','))
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'seo-crawl-report.csv';
        a.click();
    };

    const renderDetailedData = () => {
        if (!crawlData) return <div>No data available</div>;

        return (
            <div className="detailed-data">
                <div className="table-controls">
                    <button onClick={exportToCSV} className="export-btn">
                        Export to CSV
                    </button>
                </div>
                <div className="table-container">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Title</th>
                                <th>Title Length</th>
                                <th>Meta Description</th>
                                <th>Meta Desc Length</th>
                                <th>H1 Count</th>
                                <th>Word Count</th>
                                <th>Load Time (ms)</th>
                                <th>Has Schema</th>
                                <th>Has Open Graph</th>
                                <th>Has Twitter Cards</th>
                                <th>HTTPS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {crawlData.map((page, index) => (
                                <tr key={index}>
                                    <td className="url-cell">
                                        <a href={page.url} target="_blank" rel="noopener noreferrer">
                                            {page.url}
                                        </a>
                                    </td>
                                    <td>{page.title || 'N/A'}</td>
                                    <td className={page.title_length > 60 ? 'warning' : page.title_length < 10 ? 'error' : ''}>
                                        {page.title_length || 0}
                                    </td>
                                    <td>{page.meta_description || 'N/A'}</td>
                                    <td className={page.meta_description_length > 160 ? 'warning' : !page.meta_description_length ? 'error' : ''}>
                                        {page.meta_description_length || 0}
                                    </td>
                                    <td className={page.h1_count > 1 ? 'warning' : page.h1_count === 0 ? 'error' : ''}>
                                        {page.h1_count || 0}
                                    </td>
                                    <td className={page.word_count < 300 ? 'warning' : ''}>
                                        {page.word_count || 0}
                                    </td>
                                    <td className={page.load_time_ms > 3000 ? 'warning' : ''}>
                                        {page.load_time_ms || 0}
                                    </td>
                                    <td className={page.has_schema ? 'success' : 'error'}>
                                        {page.has_schema ? 'Yes' : 'No'}
                                    </td>
                                    <td className={page.has_og_tags ? 'success' : 'error'}>
                                        {page.has_og_tags ? 'Yes' : 'No'}
                                    </td>
                                    <td className={page.has_twitter_cards ? 'success' : 'error'}>
                                        {page.has_twitter_cards ? 'Yes' : 'No'}
                                    </td>
                                    <td className={page.is_https ? 'success' : 'error'}>
                                        {page.is_https ? 'Yes' : 'No'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    };

    const renderKeyIssues = () => {
        if (!crawlData) return <div>No data available</div>;

        const issues = crawlData.reduce((acc, page) => {
            const pageIssues = [];
            
            if (!page.title) pageIssues.push('Missing title tag');
            if (!page.meta_description) pageIssues.push('Missing meta description');
            if (page.h1_count === 0) pageIssues.push('Missing H1 tag');
            if (page.h1_count > 1) pageIssues.push('Multiple H1 tags');
            if (!page.has_schema) pageIssues.push('Missing schema markup');
            if (!page.has_og_tags) pageIssues.push('Missing Open Graph tags');
            if (!page.has_twitter_cards) pageIssues.push('Missing Twitter Cards');
            if (!page.is_https) pageIssues.push('Not using HTTPS');
            if (page.load_time_ms > 3000) pageIssues.push('Slow loading page');

            if (pageIssues.length > 0) {
                acc.push({
                    url: page.url,
                    issues: pageIssues
                });
            }

            return acc;
        }, []);

        return (
            <div className="key-issues">
                {issues.length === 0 ? (
                    <div className="success-message">
                        No critical SEO issues found!
                    </div>
                ) : (
                    issues.map((item, index) => (
                        <div key={index} className="issue-item">
                            <h4>{item.url}</h4>
                            <ul>
                                {item.issues.map((issue, i) => (
                                    <li key={i} className="issue">{issue}</li>
                                ))}
                            </ul>
                        </div>
                    ))
                )}
            </div>
        );
    };

    return (
        <div className="seo-crawler-container">
            <div className="header">
                <h1>SEO Crawler</h1>
                <p>Comprehensive Site Analysis & SEO Report Generator</p>
            </div>

            <div className="crawl-settings">
                <h2>Crawl Settings</h2>
                <div className="input-group">
                    <div className="url-input">
                        <label>Sitemap URL</label>
                        <input
                            type="url"
                            value={sitemapUrl}
                            onChange={(e) => setSitemapUrl(e.target.value)}
                            placeholder="https://example.com/sitemap.xml"
                        />
                        {sitemapUrl && !validateSitemapUrl(sitemapUrl) && (
                            <div className="error">Invalid sitemap URL</div>
                        )}
                    </div>
                    <div className="delay-input">
                        <label>Delay (seconds)</label>
                        <input
                            type="number"
                            min="2"
                            max="5"
                            step="0.5"
                            value={delay}
                            onChange={(e) => setDelay(parseFloat(e.target.value))}
                        />
                    </div>
                </div>
                <button
                    onClick={startCrawl}
                    disabled={!sitemapUrl || !validateSitemapUrl(sitemapUrl) || isLoading}
                    className="crawl-button"
                >
                    {isLoading ? 'Crawling...' : 'Start SEO Crawl'}
                </button>
            </div>

            <div className="user-info">
                <div className="user-id">
                    <strong>Your Access Code: {userId}</strong>
                    <p>Save this code to access your crawl history later</p>
                </div>
            </div>

            {crawlData && (
                <div className="results-section">
                    <div className="tabs">
                        <button
                            className={activeTab === 'detailed' ? 'tab active' : 'tab'}
                            onClick={() => setActiveTab('detailed')}
                        >
                            Detailed Data
                        </button>
                        <button
                            className={activeTab === 'issues' ? 'tab active' : 'tab'}
                            onClick={() => setActiveTab('issues')}
                        >
                            Key Issues
                        </button>
                    </div>

                    <div className="tab-content">
                        {activeTab === 'detailed' && renderDetailedData()}
                        {activeTab === 'issues' && renderKeyIssues()}
                    </div>
                </div>
            )}

            {crawlHistory.length > 0 && (
                <div className="history-section">
                    <h3>Crawl History</h3>
                    <div className="history-list">
                        {crawlHistory.map((entry, index) => (
                            <div key={index} className="history-item">
                                <span>{entry.timestamp} - {entry.pages_crawled} pages</span>
                                <button onClick={() => setCrawlData(entry.results)}>
                                    Load Results
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default SEOCrawler;