(function () {
	'use strict';

	function init() {
		var config = window.UAFreeConsentManager || null;
		if (!config) return;

		var runtimeKey = '__UAFreeConsentManagerRuntimeV1';
		var runtime = window[runtimeKey] || {};

		function statusAllows(status, category) {
			var key = String(category || '').trim().toLowerCase();
			if (key === 'necessary') return true;
			if (['analytics', 'advertising', 'external_media'].indexOf(key) === -1) return false;
			return Boolean(status && status[key] === true);
		}

		config.getStatus = function () { return readCookie(); };
		config.allows = function (category) { return statusAllows(readCookie(), category); };
		window.UAFreeConsent = config;
		window.uafreeConsentAllows = config.allows;

		if (runtime.initialized) return;
		runtime.initialized = true;
		window[runtimeKey] = runtime;

		var root = document.getElementById('uafree-consent');
		var reopen = document.getElementById('uafree-consent-reopen');
		if (!root || !reopen) return;

		var preferences = document.getElementById('uafree-consent-preferences');
		var customize = root.querySelector('[data-uafree-consent="customize"]');
		var save = root.querySelector('[data-uafree-consent="save"]');
		var accept = root.querySelector('[data-uafree-consent="accept"]');
		var reject = root.querySelector('[data-uafree-consent="reject"]');
		var analytics = document.getElementById('uafree-consent-analytics');
		var advertising = document.getElementById('uafree-consent-advertising');
		var externalMedia = document.getElementById('uafree-consent-external-media');
		var lastFocus = null;
		var loadedIntegrations = Object.create(null);

		function bool(value) { return value === true; }

		function defaultStatus() {
			return {
				schema_version: 1,
				necessary: true,
				analytics: false,
				advertising: false,
				external_media: false,
				policy_version: String(config.policyVersion || '1'),
				updated_at: null
			};
		}

		function readCookie() {
			var name = String(config.cookieName || 'uafree_consent') + '=';
			var parts = document.cookie ? document.cookie.split(';') : [];
			for (var index = 0; index < parts.length; index += 1) {
				var item = parts[index].trim();
				if (item.indexOf(name) !== 0) continue;
				try {
					var parsed = JSON.parse(decodeURIComponent(item.slice(name.length)));
					if (!parsed || parsed.schema_version !== 1 || String(parsed.policy_version || '') !== String(config.policyVersion || '1')) return defaultStatus();
					if (typeof parsed.updated_at !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/.test(parsed.updated_at)) return defaultStatus();
					return {
						schema_version: 1,
						necessary: true,
						analytics: bool(parsed.analytics),
						advertising: bool(parsed.advertising),
						external_media: bool(parsed.external_media),
						policy_version: String(config.policyVersion || '1'),
						updated_at: parsed.updated_at
					};
				} catch (error) {
					return defaultStatus();
				}
			}
			return defaultStatus();
		}

		function currentSelection() {
			return {
				schema_version: 1,
				necessary: true,
				analytics: bool(analytics && analytics.checked),
				advertising: bool(advertising && advertising.checked),
				external_media: bool(externalMedia && externalMedia.checked),
				policy_version: String(config.policyVersion || '1'),
				updated_at: new Date().toISOString()
			};
		}

		function setCheckboxes(status) {
			if (analytics) analytics.checked = bool(status.analytics);
			if (advertising) advertising.checked = bool(status.advertising);
			if (externalMedia) externalMedia.checked = bool(status.external_media);
		}

		function saveCookie(status) {
			var maxAge = Math.max(1, Number(config.cookieLifetime || 180)) * 86400;
			var cookie = String(config.cookieName || 'uafree_consent') + '=' + encodeURIComponent(JSON.stringify(status));
			cookie += '; Max-Age=' + String(maxAge) + '; Path=/; SameSite=Lax';
			if (config.secureCookie) cookie += '; Secure';
			document.cookie = cookie;
		}

		function executeInline(code, handle, position) {
			if (typeof code !== 'string' || code === '') return;
			var script = document.createElement('script');
			script.type = 'text/javascript';
			script.setAttribute('data-uafree-consent-handle', String(handle || ''));
			script.setAttribute('data-uafree-consent-inline', position);
			script.text = code;
			document.head.appendChild(script);
			document.head.removeChild(script);
		}

		function loadScript(record) {
			return new Promise(function (resolve) {
				var before = [].concat(record.inline_before || []);
				var after = [].concat(record.inline_after || []);
				before.forEach(function (code) { executeInline(code, record.handle, 'before'); });

				if (!record.src) {
					after.forEach(function (code) { executeInline(code, record.handle, 'after'); });
					resolve();
					return;
				}

				var script = document.createElement('script');
				script.src = String(record.src);
				script.async = false;
				script.type = record.type === 'module' ? 'module' : 'text/javascript';
				script.setAttribute('data-uafree-consent-handle', String(record.handle || ''));
				script.onload = function () {
					after.forEach(function (code) { executeInline(code, record.handle, 'after'); });
					resolve();
				};
				script.onerror = function () {
					if (config.debug && window.console && console.warn) console.warn('[UA FREE Consent Manager] script failed', record.handle);
					resolve();
				};
				document.head.appendChild(script);
			});
		}

		function loadIntegration(integration) {
			var id = String(integration.id || '');
			if (!id || loadedIntegrations[id]) return Promise.resolve();
			loadedIntegrations[id] = true;
			var chain = Promise.resolve();
			[].concat(integration.scripts || []).forEach(function (record) {
				chain = chain.then(function () { return loadScript(record || {}); });
			});
			return chain;
		}

		function loadAllowedIntegrations(status) {
			[].concat(config.integrations || []).forEach(function (integration) {
				var category = String(integration.category || '');
				if (category && status[category] === true) loadIntegration(integration);
			});
		}

		function emit(status) {
			window.dispatchEvent(new CustomEvent('uafree:consent-updated', { detail: status }));
			window.dispatchEvent(new CustomEvent('uafree_consent_update', { detail: status }));
			if (config.debug && window.console && console.info) console.info('[UA FREE Consent Manager] consent updated', status);
		}

		function persist(status) {
			saveCookie(status);
			emit(status);
			loadAllowedIntegrations(status);
			root.hidden = true;
			reopen.hidden = false;
		}

		function showPreferences() {
			lastFocus = document.activeElement;
			preferences.hidden = false;
			save.hidden = false;
			customize.setAttribute('aria-expanded', 'true');
			customize.hidden = true;
			if (analytics) analytics.focus();
		}

		function closePreferences() {
			preferences.hidden = true;
			save.hidden = true;
			customize.hidden = false;
			customize.setAttribute('aria-expanded', 'false');
			if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
		}

		accept.addEventListener('click', function () {
			setCheckboxes({ analytics: true, advertising: true, external_media: true });
			persist(currentSelection());
		});

		reject.addEventListener('click', function () {
			setCheckboxes({ analytics: false, advertising: false, external_media: false });
			persist(currentSelection());
		});

		customize.addEventListener('click', showPreferences);
		save.addEventListener('click', function () { persist(currentSelection()); });
		reopen.addEventListener('click', function () {
			lastFocus = reopen;
			reopen.hidden = true;
			root.hidden = false;
			showPreferences();
		});

		root.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !preferences.hidden) closePreferences();
		});

		var status = readCookie();
		setCheckboxes(status);
		loadAllowedIntegrations(status);
		root.hidden = status.updated_at !== null;
		reopen.hidden = status.updated_at === null;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
}());
