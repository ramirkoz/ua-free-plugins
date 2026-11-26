(function () {
	'use strict';
	var panel = document.getElementById('kozsuitecc-admin-support-panel');
	var container = document.getElementById('wpbody-content');
	var i18n = window.KOZSUITECCSupportI18n || {};
	var label = function (key, fallback) { return typeof i18n[key] === 'string' && i18n[key] ? i18n[key] : fallback; };
	if (!panel) { return; }
	if (container) { container.appendChild(panel); }
	panel.hidden = false;
	panel.addEventListener('click', function (event) {
		var button = event.target.closest('[data-koz-copy]');
		if (!button) { return; }
		var value = button.getAttribute('data-koz-copy') || '';
		var status = document.getElementById('kozsuitecc-support-copy-status');
		var done = function () {
			button.textContent = label('copied', 'Copied');
			button.classList.add('is-copied');
			if (status) { status.textContent = label('status', 'Address copied.'); }
			window.setTimeout(function () {
				button.textContent = label('copy', 'Copy');
				button.classList.remove('is-copied');
			}, 1600);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(value).then(done);
			return;
		}
		var input = document.createElement('textarea');
		input.value = value;
		input.setAttribute('readonly', 'readonly');
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild(input);
		input.select();
		document.execCommand('copy');
		document.body.removeChild(input);
		done();
	});
}());
