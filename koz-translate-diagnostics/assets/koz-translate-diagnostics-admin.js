(function () {
	'use strict';

	var button = document.getElementById('koztdiag-copy-summary');
	var summary = document.getElementById('koztdiag-summary');
	var status = document.getElementById('koztdiag-copy-status');
	var labels = window.koztdiagAdmin || { copied: 'Copied.', failed: 'Copy failed.' };

	if (!button || !summary) {
		return;
	}

	function report(ok) {
		if (status) {
			status.textContent = ok ? labels.copied : labels.failed;
		}
	}

	function fallback() {
		summary.focus();
		summary.select();
		try {
			report(document.execCommand('copy'));
		} catch (error) {
			report(false);
		}
	}

	button.addEventListener('click', function () {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(summary.value).then(function () {
				report(true);
			}, fallback);
			return;
		}
		fallback();
	});
}());
