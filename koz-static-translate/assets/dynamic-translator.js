(function(){
	'use strict';

	var CONFIG = window.KOZSTXDynamicTranslator || {};
	var pending = new Map();
	var seen = new Set();
	var attempts = new Map();
	var chartsToRefresh = new Set();
	var chartSources = new WeakMap();
	var busy = false;
	var timer = 0;

	var EXCLUDED_SELECTOR = [
		'script',
		'style',
		'noscript',
		'template',
		'canvas',
		'code',
		'pre',
		'textarea',
		'input',
		'select',
		'[contenteditable="true"]',
		'.kozstx-language-switcher',
		'[data-kozstx-gallery-compat]',
		'.pgc-sgb-cb',
		'[class*="wp-block-pgcsimplygalleryblock"]',
		'.simply-gallery-amp',
		'.sgb-gallery'
	].join(',');

	function normalize(value){
		return String(value || '')
			.replace(/[\u00A0\u200B\u200C\u200D\u2060\uFEFF]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function containsSourceScript(value){
		if (CONFIG.sourceLanguage === 'uk') {
			return /[\u0400-\u04FF]/.test(value);
		}

		return /[A-Za-z\u00C0-\u024F\u0370-\u03FF\u0400-\u04FF\u0600-\u06FF\u0900-\u097F\u3040-\u30FF\u3400-\u9FFF]/.test(value);
	}

	function eligible(value){
		value = normalize(value);

		if (value.length < 2 || value.length > 1000) {
			return false;
		}

		if (!containsSourceScript(value)) {
			return false;
		}

		if (/^(?:https?:\/\/|www\.|mailto:|tel:)/i.test(value)) {
			return false;
		}

		if (/^[\d\s.,:+\-–—\/()%$€₴₿]+$/.test(value)) {
			return false;
		}

		return true;
	}

	function excluded(element){
		return element instanceof Element && Boolean(element.closest(EXCLUDED_SELECTOR));
	}

	function addTarget(source, apply){
		source = normalize(source);

		if (!eligible(source)) {
			return;
		}

		if (
			CONFIG.dictionary
			&& typeof CONFIG.dictionary[source] === 'string'
			&& normalize(CONFIG.dictionary[source])
		) {
			try {
				apply(normalize(CONFIG.dictionary[source]));
			} catch (error) {}
			return;
		}

		if (!pending.has(source)) {
			pending.set(source, []);
		}

		pending.get(source).push(apply);

		if (!seen.has(source)) {
			seen.add(source);
		}

		schedule();
	}

	function scanText(root){
		if (!root) {
			return;
		}

		var walker = document.createTreeWalker(
			root,
			NodeFilter.SHOW_TEXT,
			{
				acceptNode: function(node){
					var parent = node.parentElement;

					if (!parent || excluded(parent)) {
						return NodeFilter.FILTER_REJECT;
					}

					return eligible(node.nodeValue)
						? NodeFilter.FILTER_ACCEPT
						: NodeFilter.FILTER_REJECT;
				}
			}
		);

		var nodes = [];
		var node;

		while ((node = walker.nextNode())) {
			nodes.push(node);
		}

		nodes.forEach(function(textNode){
			var raw = String(textNode.nodeValue || '');
			var source = normalize(raw);
			var prefix = (raw.match(/^\s*/) || [''])[0];
			var suffix = (raw.match(/\s*$/) || [''])[0];

			addTarget(source, function(translated){
				if (textNode.isConnected && normalize(textNode.nodeValue) === source) {
					textNode.nodeValue = prefix + translated + suffix;
				}
			});
		});
	}

	function scanAttributes(root){
		if (!(root instanceof Element) && root !== document) {
			return;
		}

		var elements = [];

		if (root instanceof Element) {
			elements.push(root);
		}

		if (root.querySelectorAll) {
			root.querySelectorAll('[title],[aria-label],[placeholder]').forEach(
				function(element){
					elements.push(element);
				}
			);
		}

		elements.forEach(function(element){
			if (excluded(element)) {
				return;
			}

			['title','aria-label','placeholder'].forEach(function(attribute){
				if (!element.hasAttribute(attribute)) {
					return;
				}

				var source = normalize(element.getAttribute(attribute));

				addTarget(source, function(translated){
					if (
						element.isConnected &&
						normalize(element.getAttribute(attribute)) === source
					) {
						element.setAttribute(attribute, translated);
					}
				});
			});
		});
	}

	function chartInstances(){
		var charts = [];

		if (!window.Chart) {
			return charts;
		}

		try {
			if (window.Chart.instances) {
				Object.keys(window.Chart.instances).forEach(function(key){
					var chart = window.Chart.instances[key];

					if (chart && charts.indexOf(chart) === -1) {
						charts.push(chart);
					}
				});
			}
		} catch (error) {}

		if (typeof window.Chart.getChart === 'function') {
			document.querySelectorAll('canvas').forEach(function(canvas){
				try {
					var chart = window.Chart.getChart(canvas);

					if (chart && charts.indexOf(chart) === -1) {
						charts.push(chart);
					}
				} catch (error) {}
			});
		}

		return charts;
	}

	function rememberChartSource(chart, key, value){
		if (!chartSources.has(chart)) {
			chartSources.set(chart, new Map());
		}

		var map = chartSources.get(chart);

		if (!map.has(key)) {
			map.set(key, value);
		}

		return map.get(key);
	}

	function addChartTarget(chart, key, source, setter){
		source = rememberChartSource(chart, key, normalize(source));

		addTarget(source, function(translated){
			try {
				setter(translated);
				chartsToRefresh.add(chart);
			} catch (error) {}
		});
	}

	function scanCharts(){
		chartInstances().forEach(function(chart){
			if (!chart || !chart.data) {
				return;
			}

			if (Array.isArray(chart.data.labels)) {
				chart.data.labels.forEach(function(label, index){
					if (typeof label !== 'string') {
						return;
					}

					addChartTarget(
						chart,
						'label:' + index,
						label,
						function(translated){
							chart.data.labels[index] = translated;
						}
					);
				});
			}

			if (Array.isArray(chart.data.datasets)) {
				chart.data.datasets.forEach(function(dataset, datasetIndex){
					if (!dataset || typeof dataset.label !== 'string') {
						return;
					}

					addChartTarget(
						chart,
						'dataset:' + datasetIndex,
						dataset.label,
						function(translated){
							dataset.label = translated;
						}
					);
				});
			}

			var options = chart.options || {};
			var plugins = options.plugins || {};

			['title', 'subtitle'].forEach(function(name){
				var block = plugins[name];

				if (!block || typeof block.text !== 'string') {
					return;
				}

				addChartTarget(
					chart,
					'plugin:' + name,
					block.text,
					function(translated){
						block.text = translated;
					}
				);
			});

			var scales = options.scales || {};

			Object.keys(scales).forEach(function(scaleName){
				var scale = scales[scaleName];

				if (
					!scale
					|| !scale.title
					|| typeof scale.title.text !== 'string'
				) {
					return;
				}

				addChartTarget(
					chart,
					'scale:' + scaleName,
					scale.title.text,
					function(translated){
						scale.title.text = translated;
					}
				);
			});
		});
	}

	function refreshCharts(){
		if (chartsToRefresh.size === 0) {
			return;
		}

		var charts = Array.from(chartsToRefresh);
		chartsToRefresh.clear();

		window.requestAnimationFrame(function(){
			charts.forEach(function(chart){
				if (!chart || chart._destroyed) {
					return;
				}

				try {
					chart.update('none');
					return;
				} catch (error) {}

				try {
					chart.update(0);
					return;
				} catch (error) {}

				try {
					chart.render();
				} catch (error) {}
			});
		});
	}

	function scan(root){
		scanText(root);
		scanAttributes(root);
	}

	function schedule(){
		clearTimeout(timer);
		timer = window.setTimeout(flush, 180);
	}

	function applyTranslations(items){
		var completed = new Set();

		if (!Array.isArray(items)) {
			return completed;
		}

		items.forEach(function(item){
			var source = normalize(item && item.source);
			var translated = normalize(item && item.translated);

			if (!source || !translated || !pending.has(source)) {
				return;
			}

			var targets = pending.get(source) || [];
			pending.delete(source);
			attempts.delete(source);
			completed.add(source);

			targets.forEach(function(apply){
				try {
					apply(translated);
				} catch (error) {}
			});
		});

		refreshCharts();

		try {
			window.dispatchEvent(
				new CustomEvent('kozstx:dynamic-translated')
			);
		} catch (error) {}

		return completed;
	}

	function flush(){
		if (busy || pending.size === 0) {
			return;
		}

		var texts = Array.from(pending.keys()).slice(0, Number(CONFIG.maxBatch || 50));

		if (texts.length === 0) {
			return;
		}

		busy = true;

		fetch(CONFIG.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': String(CONFIG.restNonce || '')
			},
			body: JSON.stringify({
				source_id: CONFIG.sourceId,
				language: CONFIG.language,
				texts: texts
			})
		})
		.then(function(response){
			return response.json();
		})
		.then(function(payload){
			var completed = applyTranslations(
				payload && payload.translations
			);
			var deferred = new Set(
				Array.isArray(payload && payload.deferred)
					? payload.deferred.map(normalize)
					: []
			);

			deferred.forEach(function(text){
				if (pending.has(text)) {
					pending.delete(text);
					attempts.delete(text);
				}
			});

			texts.forEach(function(text){
				if (completed.has(text) || !pending.has(text)) {
					return;
				}

				var count = Number(attempts.get(text) || 0) + 1;
				attempts.set(text, count);

				if (count >= 10) {
					pending.delete(text);
					attempts.delete(text);
				}
			});
		})
		.catch(function(){
			texts.forEach(function(text){
				if (!pending.has(text)) {
					return;
				}

				var count = Number(attempts.get(text) || 0) + 1;
				attempts.set(text, count);

				if (count >= 10) {
					pending.delete(text);
					attempts.delete(text);
				}
			});
		})
		.finally(function(){
			busy = false;

			if (pending.size > 0) {
				window.setTimeout(schedule, 700);
			}
		});
	}

	function start(){
		scan(document.body || document.documentElement);

		[
			100,
			300,
			700,
			1200,
			2500,
			5000,
			8000,
			12000,
			20000,
			30000
		].forEach(function(delay){
			window.setTimeout(function(){
				scan(document.body || document.documentElement);
				scanCharts();
			}, delay);
		});

		window.addEventListener('load', function(){
			window.setTimeout(function(){
				scan(document.body || document.documentElement);
				scanCharts();
			}, 250);
		}, {once:true});

		if ('MutationObserver' in window) {
			new MutationObserver(function(mutations){
				mutations.forEach(function(mutation){
					if (mutation.type === 'characterData') {
						scanText(mutation.target.parentNode);
						return;
					}

					mutation.addedNodes.forEach(function(node){
						if (node.nodeType === Node.TEXT_NODE) {
							scanText(node.parentNode);
						} else if (node instanceof Element) {
							scan(node);
						}
					});
				});
			}).observe(document.documentElement, {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, {once:true});
	} else {
		start();
	}
})();
