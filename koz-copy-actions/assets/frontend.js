(() => {
	'use strict';

	const config = window.KOZCOACConfig || {};
	const toStrings = (list) => (Array.isArray(list) ? list.filter((item) => typeof item === 'string' && item) : []);
	const messages = Object.assign({ copied: 'Copied', error: 'Could not copy', hint: 'Copy to clipboard', copy: 'Copy' }, config.messages || {});
	const INJECT_BUTTON_CLASS = 'kozcoac-inject-button';
	const BUILTIN_BUTTON_CLASS = 'kozcoac-copy-button';
	let toastTimer = null;
	let scanTimer = null;
	let pendingSpaceTarget = null;
	const pendingRoots = new Set();
	const injectHosts = new WeakMap();

	function isValidSelector(selector) {
		try { document.createDocumentFragment().querySelector(selector); return true; } catch (error) { return false; }
	}

	// One combined selector lets the browser do matching natively; invalid entries are dropped so one bad line cannot disable the rest.
	const joinedSelector = toStrings(config.selectors).filter(isValidSelector).join(',');
	const injectSelector = toStrings(config.injectSelectors).filter(isValidSelector).join(',');
	if (!joinedSelector && !injectSelector) return;

	function closestTarget(start) {
		if (!(start instanceof Element) || !joinedSelector) return null;
		const target = start.closest(joinedSelector);
		if (!target || target === document.documentElement) return null;
		const ignored = start.closest('[data-uafree-copy-ignore]');
		return ignored && target.contains(ignored) ? null : target;
	}

	function normalizeValue(value, preserve) {
		const text = String(value == null ? '' : value);
		return config.collapseWhitespace && !preserve ? text.replace(/\s+/g, ' ').trim() : text.trim();
	}

	function isFormControl(element) {
		return element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement;
	}

	// Returns either a literal value or the element whose content should be copied.
	function resolveSource(element) {
		if (injectHosts.has(element)) return { node: injectHosts.get(element), preserve: true };
		if (element.hasAttribute('data-copy-value')) return { value: element.getAttribute('data-copy-value') };

		const targetId = element.getAttribute('data-copy-target') || '';
		if (/^#[A-Za-z][A-Za-z0-9_\-:.]{0,99}$/.test(targetId)) {
			const referenced = document.querySelector(targetId);
			if (referenced) return isFormControl(referenced) ? { value: referenced.value } : { node: referenced };
		}

		if (isFormControl(element)) return { value: element.value };
		return { node: element };
	}

	function payloadFor(element) {
		const source = resolveSource(element);
		if (!source.node) return { text: normalizeValue(source.value), html: '' };

		const clone = source.node.cloneNode(true);
		clone.querySelectorAll(`.${INJECT_BUTTON_CLASS}`).forEach((button) => button.remove());
		const format = element.getAttribute('data-copy-format') || config.copyFormat;
		return {
			text: normalizeValue(clone.textContent || '', source.preserve),
			html: format === 'html' ? clone.innerHTML.trim() : ''
		};
	}

	async function writeRich(text, html) {
		const item = new window.ClipboardItem({
			'text/html': new Blob([html], { type: 'text/html' }),
			'text/plain': new Blob([text], { type: 'text/plain' })
		});
		await navigator.clipboard.write([item]);
	}

	async function writeClipboard(text, html) {
		if (!text) throw new Error('Empty copy value');
		if (navigator.clipboard && window.isSecureContext) {
			if (html && window.ClipboardItem && navigator.clipboard.write) {
				try { await writeRich(text, html); return; } catch (error) { /* Fall back to plain text below. */ }
			}
			await navigator.clipboard.writeText(text);
			return;
		}

		const textarea = document.createElement('textarea');
		textarea.value = text;
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
		const keySource = injectHosts.get(element) || element;
		const explicit = keySource.getAttribute('data-copy-key') || keySource.getAttribute('data-uafree-copy') || '';
		if (/^[A-Za-z0-9_-]{1,80}$/.test(explicit)) return explicit;
		if (/^[A-Za-z][A-Za-z0-9_-]{0,79}$/.test(keySource.id || '')) return keySource.id;
		return 'copy';
	}

	function dispatchSuccess(element) {
		document.dispatchEvent(new CustomEvent('kozcoac:copy-success', {
			detail: { key: targetKey(element), plugin: 'koz-copy-actions' }
		}));
		// Compatibility for existing foundation integrations.
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
		// Shortcode and block buttons already carry a visible "Copy" label.
		if (config.showIcon && !element.classList.contains(BUILTIN_BUTTON_CLASS)) element.classList.add('kozcoac-copy-icon');
		if (!element.hasAttribute('title')) element.setAttribute('title', messages.hint);
		if (config.decorateTargets && !isNativeInteractive(element)) {
			if (!element.hasAttribute('tabindex')) element.setAttribute('tabindex', '0');
			if (!element.hasAttribute('role')) element.setAttribute('role', 'button');
			if (!element.hasAttribute('aria-label')) element.setAttribute('aria-label', messages.hint);
		}
	}

	function inject(host) {
		if (!(host instanceof HTMLElement) || host.dataset.kozcoacInjected === '1') return;
		// pre > code: one button on the outer element is enough.
		if (host.parentElement && host.parentElement.closest(injectSelector)) return;
		host.dataset.kozcoacInjected = '1';

		const button = document.createElement('button');
		button.type = 'button';
		button.className = INJECT_BUTTON_CLASS;
		button.textContent = messages.copy;
		button.setAttribute('aria-label', messages.hint);
		injectHosts.set(button, host);

		if (window.getComputedStyle(host).display.startsWith('inline')) {
			button.classList.add(`${INJECT_BUTTON_CLASS}--inline`);
			host.insertAdjacentElement('afterend', button);
		} else {
			host.classList.add('kozcoac-inject-host');
			host.appendChild(button);
		}
	}

	function scan(root) {
		const context = root && root.querySelectorAll ? root : document;
		if (joinedSelector) {
			if (root instanceof HTMLElement && root.matches(joinedSelector)) decorate(root);
			context.querySelectorAll(joinedSelector).forEach(decorate);
		}
		if (injectSelector) {
			if (root instanceof HTMLElement && root.matches(injectSelector)) inject(root);
			context.querySelectorAll(injectSelector).forEach(inject);
		}
	}

	function flushPendingRoots() {
		const roots = [...pendingRoots];
		pendingRoots.clear();
		for (const root of roots) {
			// Skip detached nodes and nodes already covered by another pending ancestor.
			if (!root.isConnected || roots.some((other) => other !== root && other.contains(root))) continue;
			scan(root);
		}
	}

	async function activate(element, event) {
		if (element instanceof HTMLAnchorElement && (config.preventLinkNavigation || element.getAttribute('data-uafree-copy-prevent-default') === '1')) {
			event.preventDefault();
		}
		try {
			const payload = payloadFor(element);
			await writeClipboard(payload.text, payload.html);
			element.classList.add('kozcoac-copy-success');
			showToast(messages.copied, false);
			dispatchSuccess(element);
			window.setTimeout(() => element.classList.remove('kozcoac-copy-success'), 700);
		} catch (error) {
			showToast(messages.error, true);
		}
	}

	document.addEventListener('click', (event) => {
		const injected = event.target instanceof Element ? event.target.closest(`.${INJECT_BUTTON_CLASS}`) : null;
		const target = injected && injectHosts.has(injected) ? injected : closestTarget(event.target);
		if (target) activate(target, event);
	});

	// Native button semantics: Enter activates on keydown, Space on keyup (and can be cancelled by moving focus).
	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' && event.key !== ' ') return;
		// Injected buttons are native buttons and fire their own click.
		if (event.target instanceof Element && event.target.closest(`.${INJECT_BUTTON_CLASS}`)) return;
		const target = closestTarget(event.target);
		if (!target || isNativeInteractive(target)) return;
		event.preventDefault();
		if (event.key === 'Enter') {
			activate(target, event);
		} else {
			pendingSpaceTarget = target;
		}
	});

	document.addEventListener('keyup', (event) => {
		if (event.key !== ' ' || !pendingSpaceTarget) return;
		const target = closestTarget(event.target);
		const pending = pendingSpaceTarget;
		pendingSpaceTarget = null;
		if (target !== pending) return;
		event.preventDefault();
		activate(target, event);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => scan(document), { once: true });
	} else {
		scan(document);
	}

	if (config.decorateTargets || config.showIcon || injectSelector) {
		const observer = new MutationObserver((mutations) => {
			for (const mutation of mutations) {
				for (const node of mutation.addedNodes) {
					if (node instanceof HTMLElement && !node.classList.contains(INJECT_BUTTON_CLASS)) pendingRoots.add(node);
				}
			}
			if (!pendingRoots.size) return;
			window.clearTimeout(scanTimer);
			scanTimer = window.setTimeout(flushPendingRoots, 60);
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}
})();
