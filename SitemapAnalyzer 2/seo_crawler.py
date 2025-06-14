import requests
from bs4 import BeautifulSoup
import csv
import time
from urllib.parse import urljoin, urlparse
import json
import re
from datetime import datetime
import hashlib
from collections import Counter

class ComprehensiveSEOCrawler:
    def __init__(self, sitemap_url, delay=1.0):
        self.sitemap_url = sitemap_url
        self.delay = delay
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        })
        self.all_titles = []  # For duplicate detection
        self.all_meta_descriptions = []
        self.all_h1s = []
        self.redirect_chains = {}
    
    def fetch_sitemap_urls(self):
        """Extract URLs from XML sitemap with last modified dates"""
        try:
            response = self.session.get(self.sitemap_url)
            response.raise_for_status()
            
            soup = BeautifulSoup(response.content, 'xml')
            urls_data = []
            
            # Handle regular sitemap
            for url_tag in soup.find_all('url'):
                loc = url_tag.find('loc')
                if loc:
                    url_data = {'url': loc.text.strip()}
                    
                    # Get last modified from sitemap
                    lastmod = url_tag.find('lastmod')
                    if lastmod:
                        url_data['sitemap_lastmod'] = lastmod.text.strip()
                    
                    # Get priority and changefreq
                    priority = url_tag.find('priority')
                    if priority:
                        url_data['sitemap_priority'] = priority.text.strip()
                    
                    changefreq = url_tag.find('changefreq')
                    if changefreq:
                        url_data['sitemap_changefreq'] = changefreq.text.strip()
                    
                    urls_data.append(url_data)
            
            print(f"Found {len(urls_data)} URLs in sitemap")
            return urls_data
            
        except Exception as e:
            print(f"Error fetching sitemap: {e}")
            return []
    
    def check_redirect_chain(self, url):
        """Follow redirect chain and return chain info"""
        try:
            redirect_chain = []
            current_url = url
            
            # Don't follow redirects automatically
            response = self.session.get(current_url, allow_redirects=False, timeout=10)
            
            while response.status_code in [301, 302, 303, 307, 308]:
                redirect_chain.append({
                    'url': current_url,
                    'status': response.status_code,
                    'redirect_type': 'permanent' if response.status_code == 301 else 'temporary'
                })
                
                # Get redirect location
                location = response.headers.get('Location')
                if not location:
                    break
                
                # Handle relative redirects
                if location.startswith('/'):
                    parsed_url = urlparse(current_url)
                    current_url = f"{parsed_url.scheme}://{parsed_url.netloc}{location}"
                else:
                    current_url = location
                
                # Prevent infinite loops
                if len(redirect_chain) > 10:
                    break
                
                response = self.session.get(current_url, allow_redirects=False, timeout=10)
            
            # Final destination
            if redirect_chain:
                redirect_chain.append({
                    'url': current_url,
                    'status': response.status_code,
                    'redirect_type': 'final'
                })
            
            return {
                'has_redirects': len(redirect_chain) > 0,
                'redirect_count': len(redirect_chain) - 1 if redirect_chain else 0,
                'redirect_chain': redirect_chain,
                'final_url': current_url,
                'final_status': response.status_code
            }
            
        except Exception as e:
            return {
                'has_redirects': False,
                'redirect_count': 0,
                'redirect_chain': [],
                'final_url': url,
                'final_status': 'ERROR',
                'redirect_error': str(e)
            }
    
    def extract_dates(self, soup, url):
        """Extract published and modified dates from various sources"""
        dates = {
            'date_published': '',
            'date_modified': '',
            'date_source': ''
        }
        
        # Try Schema.org structured data first
        schema_scripts = soup.find_all('script', type='application/ld+json')
        for script in schema_scripts:
            try:
                schema_data = json.loads(script.string)
                if isinstance(schema_data, dict):
                    if 'datePublished' in schema_data:
                        dates['date_published'] = schema_data['datePublished']
                        dates['date_source'] = 'schema'
                    if 'dateModified' in schema_data:
                        dates['date_modified'] = schema_data['dateModified']
                        if not dates['date_source']:
                            dates['date_source'] = 'schema'
            except:
                pass
        
        # Try Open Graph dates
        if not dates['date_published']:
            og_published = soup.find('meta', attrs={'property': 'article:published_time'})
            if og_published:
                dates['date_published'] = og_published.get('content', '')
                dates['date_source'] = 'opengraph'
        
        if not dates['date_modified']:
            og_modified = soup.find('meta', attrs={'property': 'article:modified_time'})
            if og_modified:
                dates['date_modified'] = og_modified.get('content', '')
                if not dates['date_source']:
                    dates['date_source'] = 'opengraph'
        
        # Try WordPress/CMS specific meta tags
        if not dates['date_published']:
            wp_published = soup.find('meta', attrs={'name': 'article:published_time'})
            if wp_published:
                dates['date_published'] = wp_published.get('content', '')
                dates['date_source'] = 'meta'
        
        # Try to find dates in text content (less reliable)
        if not dates['date_published']:
            time_tags = soup.find_all('time')
            for time_tag in time_tags:
                datetime_attr = time_tag.get('datetime')
                if datetime_attr and ('published' in time_tag.get('class', []) or 'pubdate' in time_tag.get('class', [])):
                    dates['date_published'] = datetime_attr
                    dates['date_source'] = 'html_time'
                    break
        
        return dates
    
    def analyze_content_quality(self, soup):
        """Analyze content quality metrics"""
        body = soup.find('body')
        if not body:
            return {}
        
        text = body.get_text()
        
        # Reading level approximation (Flesch Reading Ease)
        sentences = len(re.findall(r'[.!?]+', text))
        words = len(text.split())
        syllables = sum([self.count_syllables(word) for word in text.split()])
        
        if sentences > 0 and words > 0:
            flesch_score = 206.835 - (1.015 * (words / sentences)) - (84.6 * (syllables / words))
        else:
            flesch_score = 0
        
        # Content structure analysis
        paragraphs = len(soup.find_all('p'))
        lists = len(soup.find_all(['ul', 'ol']))
        tables = len(soup.find_all('table'))
        
        return {
            'word_count': words,
            'sentence_count': sentences,
            'paragraph_count': paragraphs,
            'list_count': lists,
            'table_count': tables,
            'flesch_reading_ease': round(flesch_score, 2) if flesch_score else 0,
            'avg_words_per_sentence': round(words / sentences, 2) if sentences > 0 else 0
        }
    
    def count_syllables(self, word):
        """Simple syllable counter"""
        word = word.lower()
        count = 0
        vowels = "aeiouy"
        if word[0] in vowels:
            count += 1
        for index in range(1, len(word)):
            if word[index] in vowels and word[index - 1] not in vowels:
                count += 1
        if word.endswith("e"):
            count -= 1
        if count == 0:
            count += 1
        return count
    
    def check_technical_seo(self, soup, response):
        """Check technical SEO factors"""
        tech_data = {}
        
        # SSL check
        tech_data['is_https'] = response.url.startswith('https://')
        
        # Check for AMP
        amp_link = soup.find('link', rel='amphtml')
        tech_data['has_amp'] = bool(amp_link)
        if amp_link:
            tech_data['amp_url'] = amp_link.get('href', '')
        
        # Mobile viewport
        viewport = soup.find('meta', attrs={'name': 'viewport'})
        tech_data['has_viewport'] = bool(viewport)
        if viewport:
            tech_data['viewport_content'] = viewport.get('content', '')
        
        # Favicon check
        favicon = soup.find('link', rel=['icon', 'shortcut icon'])
        tech_data['has_favicon'] = bool(favicon)
        
        # Check for social media meta tags completeness
        social_tags = {
            'og:title': soup.find('meta', attrs={'property': 'og:title'}),
            'og:description': soup.find('meta', attrs={'property': 'og:description'}),
            'og:image': soup.find('meta', attrs={'property': 'og:image'}),
            'og:url': soup.find('meta', attrs={'property': 'og:url'}),
            'og:type': soup.find('meta', attrs={'property': 'og:type'}),
            'twitter:card': soup.find('meta', attrs={'name': 'twitter:card'}),
            'twitter:title': soup.find('meta', attrs={'name': 'twitter:title'}),
            'twitter:description': soup.find('meta', attrs={'name': 'twitter:description'}),
            'twitter:image': soup.find('meta', attrs={'name': 'twitter:image'})
        }
        
        # Count present social tags
        present_social_tags = sum(1 for tag in social_tags.values() if tag)
        tech_data['social_tags_count'] = present_social_tags
        tech_data['social_tags_complete'] = present_social_tags >= 6  # Basic requirement
        tech_data['missing_social_tags'] = [key for key, tag in social_tags.items() if not tag]
        
        # Detailed social media analysis
        tech_data['has_og_tags'] = any(soup.find('meta', attrs={'property': prop}) for prop in ['og:title', 'og:description', 'og:image'])
        tech_data['has_twitter_cards'] = any(soup.find('meta', attrs={'name': name}) for name in ['twitter:card', 'twitter:title', 'twitter:description'])
        
        # Check for hreflang
        hreflang_links = soup.find_all('link', rel='alternate', hreflang=True)
        tech_data['has_hreflang'] = len(hreflang_links) > 0
        tech_data['hreflang_count'] = len(hreflang_links)
        
        # Schema markup detection
        schema_scripts = soup.find_all('script', type='application/ld+json')
        tech_data['has_schema'] = len(schema_scripts) > 0
        tech_data['schema_count'] = len(schema_scripts)
        
        # Parse schema types if present
        schema_types = []
        for script in schema_scripts:
            try:
                schema_data = json.loads(script.string)
                if isinstance(schema_data, dict) and '@type' in schema_data:
                    schema_types.append(schema_data['@type'])
                elif isinstance(schema_data, list):
                    for item in schema_data:
                        if isinstance(item, dict) and '@type' in item:
                            schema_types.append(item['@type'])
            except:
                pass
        
        tech_data['schema_types'] = list(set(schema_types))  # Remove duplicates
        tech_data['schema_types_count'] = len(tech_data['schema_types'])
        
        return tech_data
    
    def extract_page_data(self, url_data):
        """Extract comprehensive SEO data from a single page"""
        url = url_data.get('url') if isinstance(url_data, dict) else url_data
        
        start_time = time.time()
        
        # Check redirect chain first
        redirect_info = self.check_redirect_chain(url)
        final_url = redirect_info['final_url']
        
        try:
            # Use final URL for content analysis
            response = self.session.get(final_url, timeout=15)
            load_time = round((time.time() - start_time) * 1000, 2)  # in milliseconds
            
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Initialize data structure
            data = {
                'original_url': url,
                'final_url': final_url,
                'status_code': response.status_code,
                'load_time_ms': load_time,
                'response_size_kb': round(len(response.content) / 1024, 2),
                'content_type': response.headers.get('content-type', ''),
                
                # Redirect information
                'has_redirects': redirect_info['has_redirects'],
                'redirect_count': redirect_info['redirect_count'],
                'redirect_type': redirect_info['redirect_chain'][0]['redirect_type'] if redirect_info['redirect_chain'] else '',
                
                # Sitemap data
                'sitemap_lastmod': url_data.get('sitemap_lastmod', '') if isinstance(url_data, dict) else '',
                'sitemap_priority': url_data.get('sitemap_priority', '') if isinstance(url_data, dict) else '',
                'sitemap_changefreq': url_data.get('sitemap_changefreq', '') if isinstance(url_data, dict) else '',
                
                # Basic SEO elements
                'title': '',
                'title_length': 0,
                'meta_description': '',
                'meta_description_length': 0,
                'meta_keywords': '',
                'h1': '',
                'h1_length': 0,
                'h1_count': 0,
                'h2_count': 0,
                'h3_count': 0,
                'h4_count': 0,
                'h5_count': 0,
                'h6_count': 0,
                
                # Content analysis
                'internal_links': 0,
                'external_links': 0,
                'images': 0,
                'images_without_alt': 0,
                'canonical_url': '',
                'robots_content': '',
                
                # Duplicate content detection
                'title_duplicate': False,
                'meta_description_duplicate': False,
                'h1_duplicate': False,
                
                # Dates
                'date_published': '',
                'date_modified': '',
                'date_source': '',
                
                # Content quality
                'word_count': 0,
                'sentence_count': 0,
                'paragraph_count': 0,
                'list_count': 0,
                'table_count': 0,
                'flesch_reading_ease': 0,
                'avg_words_per_sentence': 0,
                
                # Technical SEO
                'is_https': False,
                'has_amp': False,
                'amp_url': '',
                'has_viewport': False,
                'viewport_content': '',
                'has_favicon': False,
                
                # Social Media & Open Graph
                'social_tags_count': 0,
                'social_tags_complete': False,
                'missing_social_tags': [],
                'has_og_tags': False,
                'has_twitter_cards': False,
                
                # Schema Markup
                'has_schema': False,
                'schema_count': 0,
                'schema_types': [],
                'schema_types_count': 0,
                
                # Internationalization
                'has_hreflang': False,
                'hreflang_count': 0,
                
                # Additional metadata
                'crawl_timestamp': datetime.now().isoformat(),
                'url_hash': hashlib.md5(url.encode()).hexdigest()
            }
            
            # Extract title
            title_tag = soup.find('title')
            if title_tag:
                data['title'] = title_tag.get_text().strip()
                data['title_length'] = len(data['title'])
            
            # Check for duplicate title
            if data['title'] in self.all_titles:
                data['title_duplicate'] = True
            else:
                self.all_titles.append(data['title'])
            
            # Extract meta description
            meta_desc = soup.find('meta', attrs={'name': 'description'})
            if meta_desc:
                data['meta_description'] = meta_desc.get('content', '').strip()
                data['meta_description_length'] = len(data['meta_description'])
            
            # Check for duplicate meta description
            if data['meta_description'] in self.all_meta_descriptions:
                data['meta_description_duplicate'] = True
            else:
                self.all_meta_descriptions.append(data['meta_description'])
            
            # Extract meta keywords
            meta_keywords = soup.find('meta', attrs={'name': 'keywords'})
            if meta_keywords:
                data['meta_keywords'] = meta_keywords.get('content', '').strip()
            
            # Extract H1 and count all headings
            h1_tags = soup.find_all('h1')
            data['h1_count'] = len(h1_tags)
            if h1_tags:
                data['h1'] = h1_tags[0].get_text().strip()
                data['h1_length'] = len(data['h1'])
                
                # Check for duplicate H1
                if data['h1'] in self.all_h1s:
                    data['h1_duplicate'] = True
                else:
                    self.all_h1s.append(data['h1'])
            
            # Count other headings
            for i in range(2, 7):
                data[f'h{i}_count'] = len(soup.find_all(f'h{i}'))
            
            # Extract canonical URL
            canonical = soup.find('link', rel='canonical')
            if canonical:
                data['canonical_url'] = canonical.get('href', '')
            
            # Extract robots meta
            robots = soup.find('meta', attrs={'name': 'robots'})
            if robots:
                data['robots_content'] = robots.get('content', '')
            
            # Count links
            base_domain = urlparse(final_url).netloc
            internal_links = 0
            external_links = 0
            
            for link in soup.find_all('a', href=True):
                href = link['href']
                if href.startswith('http'):
                    if urlparse(href).netloc == base_domain:
                        internal_links += 1
                    else:
                        external_links += 1
                elif href.startswith('/') or not href.startswith('#'):
                    internal_links += 1
            
            data['internal_links'] = internal_links
            data['external_links'] = external_links
            
            # Count images and alt text issues
            images = soup.find_all('img')
            data['images'] = len(images)
            data['images_without_alt'] = len([img for img in images if not img.get('alt')])
            
            # Extract dates
            date_info = self.extract_dates(soup, final_url)
            data.update(date_info)
            
            # Analyze content quality
            content_quality = self.analyze_content_quality(soup)
            data.update(content_quality)
            
            # Check technical SEO
            tech_seo = self.check_technical_seo(soup, response)
            data.update(tech_seo)
            
            return data
            
        except Exception as e:
            # Return error data
            return {
                'original_url': url,
                'final_url': final_url,
                'status_code': 'ERROR',
                'error': str(e),
                'crawl_timestamp': datetime.now().isoformat(),
                'url_hash': hashlib.md5(url.encode()).hexdigest()
            }
    
    def crawl_all_pages(self, progress_callback=None):
        """Crawl all pages from sitemap with progress tracking"""
        urls_data = self.fetch_sitemap_urls()
        if not urls_data:
            return []
        
        all_data = []
        total_urls = len(urls_data)
        
        for i, url_data in enumerate(urls_data):
            if progress_callback:
                progress_callback(i + 1, total_urls, url_data.get('url', ''))
            
            page_data = self.extract_page_data(url_data)
            all_data.append(page_data)
            
            # Respect delay
            if self.delay > 0:
                time.sleep(self.delay)
        
        return all_data
    
    def generate_summary_report(self, all_data):
        """Generate summary statistics from crawled data"""
        if not all_data:
            return {}
        
        # Filter out error pages for accurate stats
        valid_pages = [page for page in all_data if page.get('status_code') != 'ERROR']
        
        summary = {
            'total_pages_crawled': len(all_data),
            'successful_pages': len(valid_pages),
            'error_pages': len(all_data) - len(valid_pages),
            'pages_with_redirects': len([p for p in valid_pages if p.get('has_redirects')]),
            'https_pages': len([p for p in valid_pages if p.get('is_https')]),
            'pages_without_title': len([p for p in valid_pages if not p.get('title')]),
            'pages_without_meta_description': len([p for p in valid_pages if not p.get('meta_description')]),
            'pages_without_h1': len([p for p in valid_pages if not p.get('h1')]),
            'duplicate_titles': len([p for p in valid_pages if p.get('title_duplicate')]),
            'duplicate_meta_descriptions': len([p for p in valid_pages if p.get('meta_description_duplicate')]),
            'duplicate_h1s': len([p for p in valid_pages if p.get('h1_duplicate')]),
            'pages_without_viewport': len([p for p in valid_pages if not p.get('has_viewport')]),
            'pages_without_favicon': len([p for p in valid_pages if not p.get('has_favicon')]),
            'pages_incomplete_social_tags': len([p for p in valid_pages if not p.get('social_tags_complete')]),
            'images_without_alt': sum([p.get('images_without_alt', 0) for p in valid_pages]),
            'avg_load_time': round(sum([p.get('load_time_ms', 0) for p in valid_pages]) / len(valid_pages), 2) if valid_pages else 0,
            'avg_page_size': round(sum([p.get('response_size_kb', 0) for p in valid_pages]) / len(valid_pages), 2) if valid_pages else 0,
            'avg_word_count': round(sum([p.get('word_count', 0) for p in valid_pages]) / len(valid_pages), 2) if valid_pages else 0
        }
        
        return summary
