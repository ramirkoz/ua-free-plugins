(function () {
	'use strict';

	function text(value) {
		return String(value == null ? '' : value);
	}

	function safePath(value) {
		try {
			var url = new URL(value, window.location.origin);
			return url.pathname || '/';
		} catch (error) {
			return '/';
		}
	}

	function emptyLinkCount(doc) {
		var count = 0;
		doc.querySelectorAll('a').forEach(function (link) {
			var visible = text(link.textContent).replace(/\s+/g, ' ').trim();
			var aria = text(link.getAttribute('aria-label')).trim();
			var title = text(link.getAttribute('title')).trim();
			var imageAlt = '';
			var image = link.querySelector('img');
			if (image) {
				imageAlt = text(image.getAttribute('alt')).trim();
			}
			if (!visible && !aria && !title && !imageAlt) {
				count += 1;
			}
		});
		return count;
	}

	function inspectHtml(html) {
		var parser = new DOMParser();
		var doc = parser.parseFromString(html, 'text/html');
		var images = Array.prototype.slice.call(doc.querySelectorAll('img'));
		var withoutAlt = images.filter(function (image) {
			return image.getAttribute('alt') === null || text(image.getAttribute('alt')).trim() === '';
		}).length;
		var description = doc.querySelector('meta[name="description"]');
		var robots = doc.querySelector('meta[name="robots"]');
		var robotsValue = robots ? text(robots.getAttribute('content')).toLowerCase() : '';
		var xRobots = '';
		return {
			h1_count: doc.querySelectorAll('h1').length,
			image_count: images.length,
			images_without_alt: withoutAlt,
			empty_links: emptyLinkCount(doc),
			meta_description_present: Boolean(description && text(description.getAttribute('content')).trim()),
			noindex_meta: /(^|[,\s])noindex([,\s]|$)/.test(robotsValue),
			x_robots_tag: xRobots
		};
	}

	function td(value) {
		var cell = document.createElement('td');
		cell.textContent = text(value);
		return cell;
	}

	function renderRow(body, item, i18n) {
		var row = document.createElement('tr');
		row.appendChild(td(item.path));
		row.appendChild(td(item.status));
		row.appendChild(td(item.h1_count));
		row.appendChild(td(item.images_without_alt + '/' + item.image_count));
		row.appendChild(td(item.empty_links));
		row.appendChild(td(item.meta_description_present ? i18n.present : i18n.missing));
		row.appendChild(td(item.noindex ? i18n.yes : i18n.no));
		body.appendChild(row);
	}

	function summaryHtml(result) {
		return '<p><strong>' + result.checked + '</strong> checked; '
			+ '<strong>' + result.summary.http_errors + '</strong> HTTP/request errors; '
			+ '<strong>' + result.summary.missing_h1_pages + '</strong> pages without H1; '
			+ '<strong>' + result.summary.multiple_h1_pages + '</strong> pages with multiple H1; '
			+ '<strong>' + result.summary.images_without_alt + '</strong> images without useful ALT; '
			+ '<strong>' + result.summary.empty_links + '</strong> potential empty links; '
			+ '<strong>' + result.summary.missing_meta_description_pages + '</strong> pages without meta description.</p>';
	}

	async function fetchOne(route) {
		try {
			var response = await fetch(route.url, {
				method: 'GET',
				credentials: 'omit',
				cache: 'no-store',
				redirect: 'follow',
				headers: {'X-KOZSEO-Rendered-Audit': '1'}
			});
			var html = await response.text();
			var inspected = inspectHtml(html);
			var xRobots = text(response.headers.get('x-robots-tag')).toLowerCase();
			return Object.assign({
				path: safePath(route.url),
				status: response.status,
				ok: response.ok,
				noindex: inspected.noindex_meta || /(^|[,\s])noindex([,\s]|$)/.test(xRobots),
				error: ''
			}, inspected);
		} catch (error) {
			return {
				path: safePath(route.url),
				status: 0,
				ok: false,
				h1_count: 0,
				image_count: 0,
				images_without_alt: 0,
				empty_links: 0,
				meta_description_present: false,
				noindex: false,
				error: error && error.message ? error.message : 'request_failed'
			};
		}
	}

	function buildResult(version, items) {
		var summary = {
			http_errors: 0,
			missing_h1_pages: 0,
			multiple_h1_pages: 0,
			images_without_alt: 0,
			empty_links: 0,
			missing_meta_description_pages: 0,
			noindex_pages: 0
		};
		items.forEach(function (item) {
			if (!item.ok) { summary.http_errors += 1; }
			if (item.ok && item.h1_count === 0) { summary.missing_h1_pages += 1; }
			if (item.ok && item.h1_count > 1) { summary.multiple_h1_pages += 1; }
			summary.images_without_alt += item.images_without_alt;
			summary.empty_links += item.empty_links;
			if (item.ok && !item.meta_description_present) { summary.missing_meta_description_pages += 1; }
			if (item.noindex) { summary.noindex_pages += 1; }
		});
		return {
			generated_at: new Date().toISOString(),
			plugin: {name: 'KOZ SEO Core', version: version},
			mode: 'browser_rendered_public_page_audit',
			read_only: true,
			database_writes: false,
			external_requests: false,
			checked: items.length,
			summary: summary,
			items: items,
			privacy: {
				page_html_exported: false,
				visitor_identifiers_exported: false,
				credentials_exported: false,
				public_paths_only: true
			}
		};
	}

	function downloadJson(result, filename) {
		var blob = new Blob([JSON.stringify(result, null, 2)], {type: 'application/json;charset=utf-8'});
		var url = URL.createObjectURL(blob);
		var anchor = document.createElement('a');
		anchor.href = url;
		anchor.download = filename;
		document.body.appendChild(anchor);
		anchor.click();
		anchor.remove();
		window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
	}

	function init() {
		var root = document.getElementById('kozseo-rendered-audit');
		if (!root) { return; }
		var run = document.getElementById('kozseo-rendered-audit-run');
		var download = document.getElementById('kozseo-rendered-audit-download');
		var status = document.getElementById('kozseo-rendered-audit-status');
		var summary = document.getElementById('kozseo-rendered-audit-summary');
		var table = document.getElementById('kozseo-rendered-audit-table');
		var body = table ? table.querySelector('tbody') : null;
		var i18n = window.kozseoRenderedAuditI18n || {};
		var routes = [];
		try {
			routes = JSON.parse(root.getAttribute('data-routes') || '[]');
		} catch (error) {
			routes = [];
		}
		var version = root.getAttribute('data-version') || '';
		var lastResult = null;

		if (!run || !download || !status || !summary || !table || !body) { return; }

		run.addEventListener('click', async function () {
			run.disabled = true;
			download.disabled = true;
			status.textContent = i18n.running || 'Scanning rendered pages…';
			summary.innerHTML = '';
			body.innerHTML = '';
			table.style.display = '';
			var items = [];
			for (var index = 0; index < routes.length; index += 1) {
				status.textContent = (i18n.running || 'Scanning rendered pages…') + ' ' + (index + 1) + '/' + routes.length;
				var item = await fetchOne(routes[index]);
				items.push(item);
				renderRow(body, item, i18n);
			}
			lastResult = buildResult(version, items);
			summary.innerHTML = summaryHtml(lastResult);
			status.textContent = i18n.completed || 'Rendered-page scan completed.';
			run.disabled = false;
			download.disabled = false;
		});

		download.addEventListener('click', function () {
			if (!lastResult) { return; }
			downloadJson(lastResult, i18n.downloadFilename || 'koz-seo-rendered-audit.json');
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, {once: true});
	} else {
		init();
	}
})();
