(function(){
	'use strict';

	var SOURCE_BASE = String((window.KOZSTXGalleryCompat || {}).sourceBase || window.location.href);
	var ROOT_SELECTOR = '[data-kozstx-gallery-compat="1"],.pgc-sgb-cb,[class*="wp-block-pgcsimplygalleryblock"],.simply-gallery-amp,.sgb-gallery';
	var URL_ATTRS = [
		'data-src',
		'data-lazy-src',
		'data-original',
		'data-image',
		'data-full',
		'data-large',
		'data-thumb',
		'data-thumbnail'
	];
	var SRCSET_ATTRS = ['data-srcset','data-lazy-srcset'];

	function absolute(value){
		value = String(value || '').trim();

		if (!value || /^(?:data:|blob:|mailto:|tel:|javascript:|#)/i.test(value)) {
			return value;
		}

		try {
			return new URL(value, SOURCE_BASE).href;
		} catch (error) {
			return value;
		}
	}

	function looksLikePlaceholder(value){
		value = String(value || '');

		return !value ||
			/^data:image\//i.test(value) ||
			/about:blank/i.test(value) ||
			/placeholder|preloader|transparent/i.test(value);
	}

	function candidate(img){
		for (var i = 0; i < URL_ATTRS.length; i++) {
			var value = img.getAttribute(URL_ATTRS[i]);

			if (value && !looksLikePlaceholder(value)) {
				return absolute(value);
			}
		}

		return '';
	}

	function repairImage(img, index){
		if (!(img instanceof HTMLImageElement)) {
			return;
		}

		for (var i = 0; i < SRCSET_ATTRS.length; i++) {
			var srcset = img.getAttribute(SRCSET_ATTRS[i]);

			if (srcset && !img.getAttribute('srcset')) {
				img.setAttribute('srcset', srcset);
			}
		}

		var fallback = candidate(img);
		var current = img.getAttribute('src');

		if (fallback && (looksLikePlaceholder(current) || (img.complete && img.naturalWidth === 0))) {
			img.setAttribute('src', fallback);
		}

		if (index < 12) {
			img.loading = 'eager';
		} else if (!img.loading) {
			img.loading = 'lazy';
		}

		img.decoding = 'async';

		if (!img.dataset.uafreeGalleryRepair) {
			img.dataset.uafreeGalleryRepair = '1';
			img.addEventListener('error', function(){
				var retry = candidate(img);

				if (retry && img.getAttribute('src') !== retry) {
					img.setAttribute('src', retry);
				}
			}, {passive:true});
		}
	}

	function repair(root){
		var scope = root && root.querySelectorAll ? root : document;
		var galleries = [];

		if (root instanceof Element && root.matches(ROOT_SELECTOR)) {
			galleries.push(root);
		}

		scope.querySelectorAll(ROOT_SELECTOR).forEach(function(gallery){
			galleries.push(gallery);
		});

		galleries.forEach(function(gallery){
			gallery.querySelectorAll('img').forEach(repairImage);

			gallery.querySelectorAll('source').forEach(function(source){
				SRCSET_ATTRS.forEach(function(attribute){
					var value = source.getAttribute(attribute);

					if (value && !source.getAttribute('srcset')) {
						source.setAttribute('srcset', value);
					}
				});
			});
		});
	}

	function start(){
		repair(document);

		setTimeout(function(){
			repair(document);
			window.dispatchEvent(new Event('resize'));
			window.dispatchEvent(new Event('scroll'));
		}, 800);

		setTimeout(function(){
			repair(document);
			window.dispatchEvent(new Event('resize'));
		}, 2500);

		if ('MutationObserver' in window) {
			new MutationObserver(function(mutations){
				mutations.forEach(function(mutation){
					mutation.addedNodes.forEach(function(node){
						if (node instanceof Element) {
							repair(node);
						}
					});
				});
			}).observe(document.documentElement, {
				childList: true,
				subtree: true
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, {once:true});
	} else {
		start();
	}
})();
