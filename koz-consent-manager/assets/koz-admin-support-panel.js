(function () {
	'use strict';
	var labels = window.KOZCONSENTI18n || { copy: 'Copy', copied: 'Copied', status: 'Address copied.' };
	var panel = document.getElementById('kozconsent-support-panel');
	var container = document.getElementById('wpbody-content');
	if (!panel) { return; }
	if (container) { container.appendChild(panel); }
	panel.hidden = false;
	panel.addEventListener('click', function (event) {
		var button = event.target.closest('[data-koz-copy]');
		if (!button) { return; }
		var value = button.getAttribute('data-koz-copy') || '';
		var status = document.getElementById('kozconsent-support-copy-status');
		var done = function () {
			button.textContent = labels.copied;
			button.classList.add('is-copied');
			if (status) { status.textContent = labels.status; }
			window.setTimeout(function () {
				button.textContent = labels.copy;
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
