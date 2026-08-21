(() => {
	'use strict';

	const labels = window.KOZDONATEAdminI18n || { copied: 'Copied' };

	document.addEventListener('click', async event => {
		const button = event.target.closest('[data-kozdonate-copy-value]');
		if (!button) {
			return;
		}
		const target = document.querySelector(button.getAttribute('data-kozdonate-copy-value'));
		if (!target) {
			return;
		}
		const value = String(target.value || target.textContent || '');
		try {
			await navigator.clipboard.writeText(value);
			const original = button.textContent;
			button.textContent = labels.copied;
			window.setTimeout(() => { button.textContent = original; }, 1600);
		} catch (error) {
			target.type = 'text';
			target.select();
			document.execCommand('copy');
			target.type = target.id === 'kozdonate-confirm-secret' ? 'password' : 'text';
		}
	});
})();
