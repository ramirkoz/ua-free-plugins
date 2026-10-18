(function () {
	'use strict';

	var CATEGORIES = ['analytics', 'advertising', 'external_media'];

	function init() {
		var config = window.KOZCONSENTConfig || null;
		if (!config) return;

		var runtimeKey = '__KOZCONSENTRuntimeV1';
		var runtime = window[runtimeKey] || {};

		function bool(value) { return value === true; }

		function defaultStatus() {
			return {
				schema_version: 1,
				necessary: true,
				analytics: false,
				advertising: false,
				external_media: false,
				policy_version: String(config.policyVersion || '1'),
				updated_at: null,
				id: ''
			};
		}

		function readCookie() {
			var names = [String(config.cookieName || 'kozconsent')];
			if (config.legacyCookieName) names.push(String(config.legacyCookieName));
			var parts = document.cookie ? document.cookie.split(';') : [];
			for (var n = 0; n < names.length; n += 1) {
				var prefix = names[n] + '=';
				for (var i = 0; i < parts.length; i += 1) {
					var item = parts[i].trim();
					if (item.indexOf(prefix) !== 0) continue;
					try {
						var parsed = JSON.parse(decodeURIComponent(item.slice(prefix.length)));
						if (!parsed || parsed.schema_version !== 1 || String(parsed.policy_version || '') !== String(config.policyVersion || '1')) return defaultStatus();
						if (typeof parsed.updated_at !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/.test(parsed.updated_at)) return defaultStatus();
						return {
							schema_version: 1,
							necessary: true,
							analytics: bool(parsed.analytics),
							advertising: bool(parsed.advertising),
							external_media: bool(parsed.external_media),
							policy_version: String(config.policyVersion || '1'),
							updated_at: parsed.updated_at,
							id: /^[a-f0-9]{32}$/.test(String(parsed.id || '')) ? String(parsed.id) : ''
						};
					} catch (error) {
						return defaultStatus();
					}
				}
			}
			return defaultStatus();
		}

		function statusAllows(status, category) {
			var key = String(category || '').trim().toLowerCase();
			if (key === 'necessary') return true;
			if (CATEGORIES.indexOf(key) === -1) return false;
			return Boolean(status && status[key] === true);
		}

		config.getStatus = function () { return readCookie(); };
		config.allows = function (category) { return statusAllows(readCookie(), category); };
		config.open = function () { openSettings(null); };
		window.KOZCONSENT = config;
		window[['UA', 'Free', 'Consent', 'Manager'].join('')] = config;
		window[['UA', 'Free', 'Consent'].join('')] = config;
		window[['ua', 'free', 'Consent', 'Allows'].join('')] = config.allows;

		if (runtime.initialized) return;
		runtime.initialized = true;
		window[runtimeKey] = runtime;

		var root = document.getElementById('kozconsent');
		if (!root) return;
		var panel = root.querySelector('.kozconsent__panel');
		var reopen = document.getElementById('kozconsent-reopen');
		var preferences = document.getElementById('kozconsent-preferences');
		var gpcNote = document.getElementById('kozconsent-gpc');
		var customize = root.querySelector('[data-kozconsent="customize"]');
		var save = root.querySelector('[data-kozconsent="save"]');
		var accept = root.querySelector('[data-kozconsent="accept"]');
		var reject = root.querySelector('[data-kozconsent="reject"]');
		var boxes = {
			analytics: document.getElementById('kozconsent-analytics'),
			advertising: document.getElementById('kozconsent-advertising'),
			external_media: document.getElementById('kozconsent-external-media')
		};
		var modal = config.layout === 'modal';
		var gpc = Boolean(config.honorGpc && navigator.globalPrivacyControl === true);
		var lastFocus = null;
		var loaded = Object.create(null);

		function log(message, data) {
			if (config.debug && window.console && console.info) console.info('[KOZ Consent Manager] ' + message, data || '');
		}

		function newId() {
			var bytes = new Uint8Array(16);
			if (window.crypto && window.crypto.getRandomValues) {
				window.crypto.getRandomValues(bytes);
			} else {
				for (var i = 0; i < 16; i += 1) bytes[i] = Math.floor(Math.random() * 256);
			}
			return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
		}

		function selection(previous) {
			return {
				schema_version: 1,
				necessary: true,
				analytics: bool(boxes.analytics && boxes.analytics.checked),
				advertising: bool(boxes.advertising && boxes.advertising.checked),
				external_media: bool(boxes.external_media && boxes.external_media.checked),
				policy_version: String(config.policyVersion || '1'),
				updated_at: new Date().toISOString(),
				id: previous && previous.id ? previous.id : newId()
			};
		}

		function setBoxes(status) {
			CATEGORIES.forEach(function (key) { if (boxes[key]) boxes[key].checked = bool(status[key]); });
		}

		function saveCookie(status) {
			var maxAge = Math.max(1, Number(config.cookieLifetime || 180)) * 86400;
			var cookie = String(config.cookieName || 'kozconsent') + '=' + encodeURIComponent(JSON.stringify(status));
			cookie += '; Max-Age=' + String(maxAge) + '; Path=/; SameSite=Lax';
			if (config.secureCookie) cookie += '; Secure';
			document.cookie = cookie;
		}

		/* ------------------------------------------------------------ signals */

		function updateGoogle(status) {
			if (!config.googleConsentMode) return;
			window.dataLayer = window.dataLayer || [];
			window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
			var a = status.analytics ? 'granted' : 'denied';
			var d = status.advertising ? 'granted' : 'denied';
			window.gtag('consent', 'update', {
				ad_storage: d,
				ad_user_data: d,
				ad_personalization: d,
				analytics_storage: a,
				personalization_storage: a
			});
		}

		function updateMicrosoft(status) {
			if (!config.microsoft) return;
			window.uetq = window.uetq || [];
			window.uetq.push('consent', 'update', { ad_storage: status.advertising ? 'granted' : 'denied' });
			window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
			window.clarity('consentv2', {
				ad_Storage: status.advertising ? 'granted' : 'denied',
				analytics_Storage: status.analytics ? 'granted' : 'denied'
			});
		}

		function syncWpConsentApi(status, force) {
			if (!config.wpConsentApi || typeof window.wp_set_consent !== 'function') return;
			window.wp_consent_type = window.wp_consent_type || 'optin';
			var map = config.wpConsentMap || {};
			Object.keys(map).forEach(function (apiCategory) {
				var ours = map[apiCategory];
				var value = ours === 'necessary' || status[ours] === true ? 'allow' : 'deny';
				var current = typeof window.wp_has_consent === 'function' ? (window.wp_has_consent(apiCategory) ? 'allow' : 'deny') : '';
				if (force || current !== value) window.wp_set_consent(apiCategory, value);
			});
		}

		function pushEvent(name, status) {
			if (!config.events) return;
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				event: name,
				kozconsent: {
					analytics: status.analytics === true,
					advertising: status.advertising === true,
					external_media: status.external_media === true,
					choice: status.updated_at !== null
				}
			});
		}

		function emit(status) {
			window.dispatchEvent(new CustomEvent('kozconsent:updated', { detail: status }));
			window.dispatchEvent(new CustomEvent(['ua', 'free', ':consent-updated'].join(''), { detail: status }));
			window.dispatchEvent(new CustomEvent(['ua', 'free', '_consent_update'].join(''), { detail: status }));
			log('consent updated', status);
		}

		function record(status, choice) {
			if (!config.log || !config.log.enabled || !config.log.endpoint || !window.fetch) return;
			try {
				window.fetch(config.log.endpoint, {
					method: 'POST',
					credentials: 'omit',
					keepalive: true,
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						id: status.id,
						choice: choice,
						analytics: status.analytics,
						advertising: status.advertising,
						external_media: status.external_media,
						policy: status.policy_version,
						gpc: gpc,
						language: root.getAttribute('lang') || ''
					})
				}).catch(function () {});
			} catch (error) {}
		}

		/* ------------------------------------------------------------ scripts and media */

		function executeInline(code, handle, position) {
			if (typeof code !== 'string' || code === '') return;
			var script = document.createElement('script');
			script.setAttribute('data-kozconsent-handle', String(handle || ''));
			script.setAttribute('data-kozconsent-inline', position);
			script.text = code;
			document.head.appendChild(script);
			document.head.removeChild(script);
		}

		function loadScript(record) {
			return new Promise(function (resolve) {
				[].concat(record.inline_before || []).forEach(function (code) { executeInline(code, record.handle, 'before'); });
				var after = [].concat(record.inline_after || []);
				if (!record.src) {
					after.forEach(function (code) { executeInline(code, record.handle, 'after'); });
					resolve();
					return;
				}
				var script = document.createElement('script');
				script.src = String(record.src);
				script.async = false;
				if (record.type === 'module') script.type = 'module';
				script.setAttribute('data-kozconsent-handle', String(record.handle || ''));
				script.onload = function () {
					after.forEach(function (code) { executeInline(code, record.handle, 'after'); });
					resolve();
				};
				script.onerror = function () { log('script failed', record.handle); resolve(); };
				document.head.appendChild(script);
			});
		}

		function loadIntegrations(status) {
			[].concat(config.integrations || []).forEach(function (integration) {
				var id = String(integration.id || '');
				if (!id || loaded[id] || status[String(integration.category || '')] !== true) return;
				loaded[id] = true;
				var chain = Promise.resolve();
				[].concat(integration.scripts || []).forEach(function (record) {
					chain = chain.then(function () { return loadScript(record || {}); });
				});
			});
		}

		function embedSource(src) {
			if (config.youtubeNocookie) {
				src = src.replace(/^(https?:)?\/\/(www\.)?youtube\.com\/embed\//i, 'https://www.youtube-nocookie.com/embed/');
			}
			return src;
		}

		function restoreEmbed(frame) {
			var src = frame.getAttribute('data-kozconsent-src');
			if (!src) return;
			frame.removeAttribute('srcdoc');
			frame.removeAttribute('data-kozconsent-src');
			frame.setAttribute('src', embedSource(src));
		}

		function restoreAllEmbeds() {
			Array.prototype.forEach.call(document.querySelectorAll('iframe[data-kozconsent-src]'), restoreEmbed);
		}

		/* ------------------------------------------------------------ banner */

		function focusables() {
			return Array.prototype.filter.call(panel.querySelectorAll('button, [href], input:not([disabled])'), function (el) {
				return !el.hidden && el.offsetParent !== null;
			});
		}

		function showBanner() {
			root.hidden = false;
			if (reopen) reopen.hidden = true;
			if (gpcNote) gpcNote.hidden = !gpc;
			if (modal) {
				document.documentElement.classList.add('kozconsent-locked');
				panel.focus();
			}
		}

		function hideBanner() {
			root.hidden = true;
			document.documentElement.classList.remove('kozconsent-locked');
			if (reopen) reopen.hidden = false;
		}

		function showPreferences() {
			preferences.hidden = false;
			save.hidden = false;
			customize.setAttribute('aria-expanded', 'true');
			customize.hidden = true;
			if (boxes.analytics) boxes.analytics.focus();
		}

		function closePreferences() {
			preferences.hidden = true;
			save.hidden = true;
			customize.hidden = false;
			customize.setAttribute('aria-expanded', 'false');
			customize.focus();
		}

		function openSettings(trigger) {
			lastFocus = trigger || document.activeElement;
			setBoxes(readCookie());
			showBanner();
			showPreferences();
		}

		function persist(status, choice) {
			saveCookie(status);
			updateGoogle(status);
			updateMicrosoft(status);
			syncWpConsentApi(status, true);
			pushEvent('kozconsent_update', status);
			emit(status);
			loadIntegrations(status);
			if (status.external_media) restoreAllEmbeds();
			record(status, choice);
			hideBanner();
			if (lastFocus && typeof lastFocus.focus === 'function' && document.contains(lastFocus)) lastFocus.focus();
		}

		accept.addEventListener('click', function () {
			setBoxes({ analytics: true, advertising: !gpc, external_media: true });
			persist(selection(readCookie()), 'accept');
		});
		reject.addEventListener('click', function () {
			setBoxes({ analytics: false, advertising: false, external_media: false });
			persist(selection(readCookie()), 'reject');
		});
		customize.addEventListener('click', showPreferences);
		save.addEventListener('click', function () { persist(selection(readCookie()), 'custom'); });
		if (reopen) reopen.addEventListener('click', function () { openSettings(reopen); });

		document.addEventListener('click', function (event) {
			var target = event.target && event.target.closest ? event.target.closest('.kozconsent-open, a[href="#kozconsent"], a[href$="#kozconsent"]') : null;
			if (!target) return;
			event.preventDefault();
			openSettings(target);
		});

		document.addEventListener('kozconsent:load', function (event) {
			var frame = event.target;
			if (event.detail && event.detail.always) {
				var current = readCookie();
				setBoxes(current);
				if (boxes.external_media) boxes.external_media.checked = true;
				persist(selection(current), 'custom');
				return;
			}
			if (frame && frame.tagName === 'IFRAME') restoreEmbed(frame);
		});

		root.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !preferences.hidden) {
				closePreferences();
				return;
			}
			if (event.key === 'Tab' && modal) {
				var items = focusables();
				if (!items.length) return;
				var first = items[0];
				var last = items[items.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			}
		});

		var status = readCookie();
		setBoxes(status);
		if (status.updated_at !== null) {
			syncWpConsentApi(status, false);
			loadIntegrations(status);
			if (status.external_media) restoreAllEmbeds();
			hideBanner();
		} else {
			if (gpc && boxes.advertising) boxes.advertising.checked = false;
			showBanner();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
}());
