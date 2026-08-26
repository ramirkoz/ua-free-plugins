(function(){
	'use strict';

	var CONFIG = window.KOZSTXLanguageLinkGuard || {};
	var ATTRIBUTE_NAMES = [
		'href',
		'action',
		'data-href',
		'data-url',
		'data-link',
		'data-target-url'
	];
	var SELECTOR = [
		'a[href]',
		'area[href]',
		'form[action]',
		'[data-href]',
		'[data-url]',
		'[data-link]',
		'[data-target-url]',
		'[onclick]'
	].join(',');

	function normalizePath(pathname){
		var path = String(pathname || '/');

		try {
			path = decodeURIComponent(path);
		} catch (error) {}

		path = '/' + path.replace(/^\/+|\/+$/g, '');

		if (path === '/') {
			return '/';
		}

		var parts = path.split('/').filter(Boolean);

		if (
			parts.length > 0
			&& Array.isArray(CONFIG.languageSlugs)
			&& CONFIG.languageSlugs.indexOf(parts[0].toLowerCase()) !== -1
		) {
			parts.shift();
		}

		return '/' + parts.join('/').toLowerCase() + '/';
	}

	function localizedUrl(value){
		if (
			!value
			|| /^(?:#|mailto:|tel:|javascript:|data:|blob:)/i.test(value)
		) {
			return '';
		}

		try {
			var url = new URL(value, window.location.href);

			if (url.origin !== window.location.origin) {
				return '';
			}

			var path = normalizePath(url.pathname);
			var targetValue = CONFIG.routeMap[path];

			if (!targetValue) {
				return '';
			}

			var target = new URL(targetValue, window.location.origin);
			target.search = url.search;
			target.hash = url.hash;

			return target.href;
		} catch (error) {
			return '';
		}
	}

	function rewriteAttribute(element, attribute){
		if (
			!(element instanceof Element)
			|| !element.hasAttribute(attribute)
		) {
			return false;
		}

		var current = String(
			element.getAttribute(attribute) || ''
		);
		var target = localizedUrl(current);

		if (!target || current === target) {
			return false;
		}

		element.setAttribute(attribute, target);
		element.setAttribute(
			'data-kozstx-localized-route',
			CONFIG.language
		);
		return true;
	}

	function onclickTarget(element){
		if (
			!(element instanceof Element)
			|| !element.hasAttribute('onclick')
		) {
			return '';
		}

		var source = String(
			element.getAttribute('onclick') || ''
		);
		var candidates = source.match(
			/(?:https?:\/\/[^'"\s)]+|\/[^'"\s)]+)/gi
		) || [];

		for (var i = 0; i < candidates.length; i++) {
			var target = localizedUrl(candidates[i]);

			if (target) {
				return target;
			}
		}

		return '';
	}

	function rewriteElement(element){
		if (!(element instanceof Element)) {
			return;
		}

		ATTRIBUTE_NAMES.forEach(function(attribute){
			rewriteAttribute(element, attribute);
		});

		var onclick = onclickTarget(element);

		if (onclick) {
			element.setAttribute(
				'data-kozstx-localized-onclick',
				onclick
			);
		}
	}

	function scan(root){
		if (!root) {
			return;
		}

		if (
			root instanceof Element
			&& root.matches(SELECTOR)
		) {
			rewriteElement(root);
		}

		if (root.querySelectorAll) {
			root.querySelectorAll(SELECTOR).forEach(
				rewriteElement
			);
		}
	}

	function closestActionTarget(node){
		return node instanceof Element
			? node.closest(SELECTOR)
			: null;
	}

	function modifiedClick(event){
		return Boolean(
			event.metaKey
			|| event.ctrlKey
			|| event.shiftKey
			|| event.altKey
			|| (
				typeof event.button === 'number'
				&& event.button !== 0
			)
		);
	}

	function forceCorrectNavigation(event){
		if (
			event.type === 'click'
			&& modifiedClick(event)
		) {
			return;
		}

		var element = closestActionTarget(event.target);

		if (!element) {
			return;
		}

		var target = '';

		for (var i = 0; i < ATTRIBUTE_NAMES.length; i++) {
			var attribute = ATTRIBUTE_NAMES[i];

			if (element.hasAttribute(attribute)) {
				target = localizedUrl(
					String(
						element.getAttribute(attribute) || ''
					)
				);

				if (target) {
					element.setAttribute(attribute, target);
					break;
				}
			}
		}

		if (!target) {
			target = String(
				element.getAttribute(
					'data-kozstx-localized-onclick'
				) || ''
			);
		}

		if (!target) {
			return;
		}

		/*
		 * Own ordinary navigation in capture phase because PageLayer can keep
		 * an older source-language URL in a later click handler.
		 */
		event.preventDefault();
		event.stopPropagation();

		if (
			typeof event.stopImmediatePropagation === 'function'
		) {
			event.stopImmediatePropagation();
		}

		window.location.assign(target);
	}

	function start(){
		scan(document.documentElement);

		[100, 400, 1000, 2500, 5000].forEach(
			function(delay){
				window.setTimeout(function(){
					scan(document.documentElement);
				}, delay);
			}
		);

		document.addEventListener(
			'click',
			forceCorrectNavigation,
			true
		);
		document.addEventListener(
			'submit',
			forceCorrectNavigation,
			true
		);

		if ('MutationObserver' in window) {
			new MutationObserver(function(mutations){
				mutations.forEach(function(mutation){
					if (
						mutation.type === 'attributes'
						&& mutation.target instanceof Element
					) {
						rewriteElement(mutation.target);
						return;
					}

					mutation.addedNodes.forEach(function(node){
						if (node instanceof Element) {
							scan(node);
						}
					});
				});
			}).observe(document.documentElement, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ATTRIBUTE_NAMES.concat(
					['onclick']
				)
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener(
			'DOMContentLoaded',
			start,
			{once:true}
		);
	} else {
		start();
	}
})();
