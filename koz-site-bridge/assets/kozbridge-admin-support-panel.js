(function () {
	'use strict';
	var i18n = window.KOZBRIDGEI18n || {};
	var panel = document.getElementById('kozbridge-support-panel');
	var container = document.getElementById('wpbody-content');
	if (!panel) { return; }
	if (container) { container.appendChild(panel); }
	panel.hidden = false;
	panel.addEventListener('click', function (event) {
		var button = event.target.closest('[data-kozbridge-copy]');
		if (!button) { return; }
		var value = button.getAttribute('data-kozbridge-copy') || '';
		var status = document.getElementById('kozbridge-support-copy-status');
		var done = function () {
			button.textContent = i18n.copied || 'Copied';
			button.classList.add('is-copied');
			if (status) { status.textContent = i18n.status || 'Address copied.'; }
			window.setTimeout(function () {
				button.textContent = i18n.copy || 'Copy';
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
