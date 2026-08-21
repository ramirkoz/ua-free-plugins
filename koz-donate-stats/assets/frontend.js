(() => {
	'use strict';

	const config = window.KOZDONATEConfig || {};

	if (!config.endpoint || !config.contextKey || !config.contextToken) {
		return;
	}

	const RUNTIME_KEY = '__KOZDONATEConfigRuntimeV1';
	const runtime = window[RUNTIME_KEY] || {};

	if (runtime.initialized) {
		return;
	}

	runtime.initialized = true;
	runtime.started = false;
	runtime.lastDataLayerSignature = '';
	runtime.lastDataLayerAt = 0;
	window[RUNTIME_KEY] = runtime;

	const STORAGE_KEY = 'kozdonate_session_v2';
	const SESSION_MINUTES = Math.max(5, Math.min(120, Number(config.sessionMinutes || 30)));
	const COPY_PREFIX = 'copy-this-';

	let lastEventSignature = '';
	let lastEventAt = 0;
	let successReported = false;
	let lastConsentUpdateAt = 0;

	function randomSessionId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID().toLowerCase();
		}

		const bytes = new Uint8Array(24);

		if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
			window.crypto.getRandomValues(bytes);
			return Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
		}

		return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`.toLowerCase();
	}

	function sessionId() {
		const now = Date.now();
		let state = null;

		try {
			state = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY) || 'null');
		} catch (error) {
			state = null;
		}

		if (
			!state ||
			!/^[a-z0-9-]{20,64}$/i.test(String(state.id || '')) ||
			Number(state.expires || 0) < now
		) {
			state = { id: randomSessionId(), expires: now + SESSION_MINUTES * 60 * 1000 };
		} else {
			state.expires = now + SESSION_MINUTES * 60 * 1000;
		}

		try {
			window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
		} catch (error) {
			// Tracking must remain non-blocking even when storage is unavailable.
		}

		return String(state.id).toLowerCase();
	}

	function language() {
		const value = String(document.documentElement.lang || 'und')
			.trim()
			.toLowerCase()
			.replace('_', '-');

		return /^[a-z]{2,3}(?:-[a-z]{2})?$/.test(value) ? value : 'und';
	}


	function managerAllows(category) {
		try {
			if (
				window.KOZCONSENT &&
				typeof window.KOZCONSENT.allows === 'function'
			) {
				return Boolean(window.KOZCONSENT.allows(category));
			}
		} catch (error) {
			return false;
		}

		return false;
	}

	function localAllowed() {
		return config.consentGate !== 'all' || managerAllows(String(config.consentCategory || 'analytics'));
	}

	function dataLayerAllowed() {
		if (!config.dataLayerEnabled) {
			return false;
		}

		if (config.consentGate === 'none') {
			return true;
		}

		return managerAllows(String(config.consentCategory || 'analytics'));
	}

	function cleanTarget(value, fallback = '') {
		const cleaned = String(value || '')
			.toLowerCase()
			.trim()
			.replace(/[^a-z0-9._:-]+/g, '-')
			.replace(/^[-.]+|[-.]+$/g, '')
			.slice(0, 100);

		return cleaned || fallback;
	}

	function elementTarget(element, fallback) {
		if (!(element instanceof Element)) {
			return fallback;
		}

		const explicit = element.getAttribute('data-uafree-target');

		if (explicit) {
			return cleanTarget(explicit, fallback);
		}

		const id = element.getAttribute('id');

		if (id) {
			return cleanTarget(id, fallback);
		}

		return fallback;
	}

	function paymentHost(link) {
		if (!(link instanceof HTMLAnchorElement)) {
			return '';
		}

		try {
			const url = new URL(link.href, window.location.href);
			const hostname = url.hostname
				.toLowerCase()
				.replace(/^www\./, '')
				.replace(/[^a-z0-9.-]/g, '')
				.slice(0, 100);

			if (url.origin === window.location.origin) {
				return 'same-origin';
			}

			return hostname;
		} catch (error) {
			return '';
		}
	}

	function configuredPaymentHost(link) {
		const host = paymentHost(link);

		if (!host || host === 'same-origin') {
			return false;
		}

		const allowed = Array.isArray(config.paymentHosts) ? config.paymentHosts : [];

		return allowed.some(item => {
			const value = String(item || '').toLowerCase();
			return host === value || host.endsWith(`.${value}`);
		});
	}

	function safeClosest(target, selector) {
		if (!(target instanceof Element) || !selector) {
			return null;
		}

		try {
			return target.closest(selector);
		} catch (error) {
			return null;
		}
	}

	function copyTarget(target) {
		const element = safeClosest(target, String(config.copySelector || ''));

		if (!element) {
			return '';
		}

		const explicit = element.getAttribute('data-uafree-copy');

		if (explicit) {
			return cleanTarget(explicit, 'copy');
		}

		const className = Array.from(element.classList || []).find(
			name => name.startsWith(COPY_PREFIX)
		);

		if (className) {
			return cleanTarget(className.slice(COPY_PREFIX.length), 'copy');
		}

		return elementTarget(element, 'copy');
	}

	function pushExternalEvent(eventType, targetKey) {
		const detail = {
			event_type: eventType,
			target_key: targetKey,
			context_key: String(config.contextKey),
			language: language(),
			ad_account_mode: String(config.adAccountMode || 'none')
		};

		window.dispatchEvent(
			new CustomEvent('kozdonate:donation-event', { detail })
		);

		// Google Ads outbound-click conversion must represent one actual payment-provider opening.
		// Local journey events remain recorded through the REST endpoint and DOM event above.
		if (eventType !== 'payment_open' || !dataLayerAllowed()) {
			return;
		}

		const dataLayerSignature = `${eventType}|${targetKey}`;
		const dataLayerNow = Date.now();

		if (
			dataLayerSignature === runtime.lastDataLayerSignature
			&& dataLayerNow - runtime.lastDataLayerAt < 1500
		) {
			return;
		}

		runtime.lastDataLayerSignature = dataLayerSignature;
		runtime.lastDataLayerAt = dataLayerNow;

		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: String(config.dataLayerEvent || 'kozdonate_donation_event'),
			kozdonate_event_type: detail.event_type,
			kozdonate_target_key: detail.target_key,
			kozdonate_context_key: detail.context_key,
			kozdonate_language: detail.language,
			kozdonate_ad_account_mode: detail.ad_account_mode
		});
	}

	function send(eventType, targetKey = '') {
		if (document.visibilityState === 'prerender' || !localAllowed()) {
			return;
		}

		const target = cleanTarget(targetKey, '');
		const signature = `${eventType}|${target}`;
		const now = Date.now();

		if (signature === lastEventSignature && now - lastEventAt < 500) {
			return;
		}

		lastEventSignature = signature;
		lastEventAt = now;

		const payload = JSON.stringify({
			event_type: eventType,
			target_key: target,
			language: language(),
			session_id: sessionId(),
			context_key: String(config.contextKey),
			context_token: String(config.contextToken)
		});

		pushExternalEvent(eventType, target);

		if (navigator.sendBeacon) {
			const blob = new Blob([payload], { type: 'application/json' });

			if (navigator.sendBeacon(config.endpoint, blob)) {
				return;
			}
		}

		fetch(config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: true,
			headers: {
				'Content-Type': 'application/json'
			},
			body: payload
		}).catch(() => {
			// Statistics must never break the donation interface.
		});
	}

	function detectSuccess() {
		if (successReported || !config.allowClientSuccess || !config.successSelector) {
			return;
		}

		let marker = null;

		try {
			marker = document.querySelector(String(config.successSelector));
		} catch (error) {
			return;
		}

		if (!marker || !localAllowed()) {
			return;
		}

		successReported = true;
		send('donation_success', elementTarget(marker, 'reported'));
	}

	function startSuccessObserver() {
		detectSuccess();

		if (successReported || !config.allowClientSuccess || !config.successSelector) {
			return;
		}

		const observer = new MutationObserver(() => {
			detectSuccess();

			if (successReported) {
				observer.disconnect();
			}
		});

		observer.observe(document.documentElement, {
			childList: true,
			subtree: true,
			attributes: true
		});

		window.setTimeout(() => observer.disconnect(), 10 * 60 * 1000);
	}

	function handleInteraction(target) {
		const copy = copyTarget(target);

		if (copy) {
			// KOZ Copy Actions reports only a successful clipboard write.
			// When that integration is active, do not count the preliminary click.
			if (!config.copyIntegration) {
				send('copy_click', copy);
			}
			return;
		}

		const payment = safeClosest(target, String(config.paymentSelector || ''));
		const link = payment instanceof HTMLAnchorElement
			? payment
			: safeClosest(target, 'a[href]');

		if (
			payment ||
			(link instanceof HTMLAnchorElement && configuredPaymentHost(link))
		) {
			const paymentTarget = link instanceof HTMLAnchorElement
				? paymentHost(link)
				: elementTarget(payment, 'payment');

			const targetKey = elementTarget(payment || link, paymentTarget || 'payment');

			// A recognized payment action is also the user's support action.
			// Existing sites must not require new data attributes merely to count this click.
			send('donate_click', targetKey);
			send('payment_open', targetKey);
			return;
		}

		const donate = safeClosest(target, String(config.donateSelector || ''));

		if (donate) {
			send('donate_click', elementTarget(donate, 'donate'));
		}
	}

	function start() {
		if (runtime.started) {
			return;
		}

		runtime.started = true;
		send('page_view', 'page');

		document.addEventListener(
			'kozcoac:copy-success',
			event => {
				const detail = event && typeof event.detail === 'object'
					? event.detail
					: {};

				if (String(detail.plugin || '') !== 'koz-copy-actions') {
					return;
				}

				// The copied value is intentionally not present in this event.
				send('copy_click', cleanTarget(detail.key, 'copy'));
			}
		);

		document.addEventListener(
			'click',
			event => handleInteraction(event.target),
			true
		);

		document.addEventListener(
			'keydown',
			event => {
				if (event.key === 'Enter' || event.key === ' ') {
					handleInteraction(event.target);
				}
			},
			true
		);

		startSuccessObserver();

		const handleConsentUpdated = () => {
			const now = Date.now();

			if (now - lastConsentUpdateAt < 100) {
				return;
			}

			lastConsentUpdateAt = now;

			if (localAllowed()) {
				send('page_view', 'consent-granted');
				detectSuccess();
			}
		};

		window.addEventListener('kozconsent:updated', handleConsentUpdated);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
})();
