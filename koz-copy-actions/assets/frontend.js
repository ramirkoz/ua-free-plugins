(() => {
	'use strict';

	const config = window.KOZCOACConfig || {};
	const selectors = Array.isArray(config.selectors) ? config.selectors.filter((item) => typeof item === 'string' && item) : [];
	if (!selectors.length) return;

	const messages = config.messages || { copied: 'Copied', error: 'Could not copy', hint: 'Copy to clipboard' };
	let toastTimer = null;
	let scanTimer = null;

	function safeMatches(element, selector) {
		try { return element.matches(selector); } catch (error) { return false; }
	}

	function closestTarget(start) {
		if (!(start instanceof Element)) return null;
		let node = start;
		while (node && node !== document.documentElement) {
			if (node.hasAttribute('data-uafree-copy-ignore')) return null;
			for (const selector of selectors) {
				if (safeMatches(node, selector)) return node;
			}
			node = node.parentElement;
		}
		return null;
	}

	function normalizeValue(value) {
		const text = String(value == null ? '' : value);
		return config.collapseWhitespace ? text.replace(/\s+/g, ' ').trim() : text.trim();
	}

	function valueFromTarget(element) {
		if (element.hasAttribute('data-copy-value')) return normalizeValue(element.getAttribute('data-copy-value'));
		const targetId = element.getAttribute('data-copy-target') || '';
		if (/^#[A-Za-z][A-Za-z0-9_\-:.]{0,99}$/.test(targetId)) {
			const referenced = document.querySelector(targetId);
			if (referenced) {
				if (referenced instanceof HTMLInputElement || referenced instanceof HTMLTextAreaElement || referenced instanceof HTMLSelectElement) {
					return normalizeValue(referenced.value);
				}
				return normalizeValue(referenced.textContent || '');
			}
		}
		if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement) {
			return normalizeValue(element.value);
		}
		return normalizeValue(element.textContent || '');
	}

	async function writeClipboard(value) {
		if (!value) throw new Error('Empty copy value');
		if (navigator.clipboard && window.isSecureContext) {
			await navigator.clipboard.writeText(value);
			return;
		}
		const textarea = document.createElement('textarea');
		textarea.value = value;
		textarea.setAttribute('readonly', '');
		textarea.setAttribute('aria-hidden', 'true');
		textarea.style.position = 'fixed';
		textarea.style.inset = '0 auto auto -9999px';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.select();
		textarea.setSelectionRange(0, textarea.value.length);
		const copied = document.execCommand('copy');
		textarea.remove();
		if (!copied) throw new Error('Legacy clipboard API failed');
	}

	function targetKey(element) {
		const explicit = element.getAttribute('data-copy-key') || element.getAttribute('data-uafree-copy') || '';
		if (/^[A-Za-z0-9_-]{1,80}$/.test(explicit)) return explicit;
		if (/^[A-Za-z][A-Za-z0-9_-]{0,79}$/.test(element.id || '')) return element.id;
		return 'copy';
	}

	function dispatchSuccess(element) {
		document.dispatchEvent(new CustomEvent('kozcoac:copy-success', {
			detail: { key: targetKey(element), plugin: 'koz-copy-actions' }
		}));
		document.dispatchEvent(new CustomEvent('uafree:copy-success', {
			detail: { key: targetKey(element), plugin: 'koz-copy-actions' }
		}));
	}

	function showToast(message, isError) {
		if (!config.showToast) return;
		let toast = document.getElementById('kozcoac-copy-toast');
		if (!toast) {
			toast = document.createElement('div');
			toast.id = 'kozcoac-copy-toast';
			toast.className = `kozcoac-copy-toast-${config.toastPosition || 'bottom-center'}`;
			toast.setAttribute('role', 'status');
			toast.setAttribute('aria-live', 'polite');
			document.body.appendChild(toast);
		}
		toast.textContent = message;
		toast.classList.toggle('is-error', Boolean(isError));
		toast.classList.add('is-visible');
		window.clearTimeout(toastTimer);
		toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), Number(config.toastDuration) || 1800);
	}

	function isNativeInteractive(element) {
		return element.matches('button, a[href], input, textarea, select, summary, [contenteditable="true"]');
	}

	function decorate(element) {
		if (!(element instanceof HTMLElement) || element.dataset.kozcoacDecorated === '1') return;
		element.dataset.kozcoacDecorated = '1';
		element.classList.add('kozcoac-copy-target');
		if (config.showIcon) element.classList.add('kozcoac-copy-icon');
		if (!element.hasAttribute('title')) element.setAttribute('title', messages.hint);
		if (config.decorateTargets && !isNativeInteractive(element)) {
			if (!element.hasAttribute('tabindex')) element.setAttribute('tabindex', '0');
			if (!element.hasAttribute('role')) element.setAttribute('role', 'button');
			if (!element.hasAttribute('aria-label')) element.setAttribute('aria-label', messages.hint);
		}
	}

	function scan(root) {
		const context = root && root.querySelectorAll ? root : document;
		for (const selector of selectors) {
			try {
				context.querySelectorAll(selector).forEach(decorate);
				if (root instanceof HTMLElement && safeMatches(root, selector)) decorate(root);
			} catch (error) {}
		}
	}

	async function activate(element, event) {
		if (element instanceof HTMLAnchorElement && (config.preventLinkNavigation || element.getAttribute('data-uafree-copy-prevent-default') === '1')) {
			event.preventDefault();
		}
		try {
			await writeClipboard(valueFromTarget(element));
			element.classList.add('kozcoac-copy-success');
			showToast(messages.copied, false);
			dispatchSuccess(element);
			window.setTimeout(() => element.classList.remove('kozcoac-copy-success'), 700);
		} catch (error) {
			showToast(messages.error, true);
		}
	}

	document.addEventListener('click', (event) => {
		const target = closestTarget(event.target);
		if (target) activate(target, event);
	});

	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' && event.key !== ' ') return;
		const target = closestTarget(event.target);
		if (!target || isNativeInteractive(target)) return;
		event.preventDefault();
		activate(target, event);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => scan(document), { once: true });
	} else {
		scan(document);
	}

	if (config.decorateTargets || config.showIcon) {
		const observer = new MutationObserver((mutations) => {
			let candidate = null;
			for (const mutation of mutations) {
				for (const node of mutation.addedNodes) {
					if (node instanceof HTMLElement) { candidate = node; break; }
				}
				if (candidate) break;
			}
			if (!candidate) return;
			window.clearTimeout(scanTimer);
			scanTimer = window.setTimeout(() => scan(document), 60);
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}
})();
