(function () {
	'use strict';
	var cfg = window.KOZBRIDGEAdmin || {};
	document.addEventListener('DOMContentLoaded', function () {
		var input = document.querySelector('[data-kozbridge-select-key]');
		var copy = document.querySelector('[data-kozbridge-copy-key]');
		var status = document.querySelector('[data-kozbridge-key-status]');
		if (input) {
			input.addEventListener('click', function () { input.select(); });
		}
		if (copy && input) {
			copy.addEventListener('click', function () {
				var done = function () { if (status) { status.textContent = cfg.copied || 'API key copied.'; } };
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(input.value || '').then(done);
				} else {
					input.select();
					document.execCommand('copy');
					done();
				}
			});
		}
		Array.prototype.forEach.call(document.querySelectorAll('[data-kozbridge-revoke-form]'), function (form) {
			form.addEventListener('submit', function (event) {
				if (!window.confirm(cfg.revokeConfirm || 'Revoke the key?')) { event.preventDefault(); }
			});
		});

		var box = document.querySelector('[data-kozbridge-console]');
		if (!box) { return; }
		var endpoint = box.querySelector('[data-kozbridge-endpoint]');
		var params = box.querySelector('[data-kozbridge-params]');
		var run = box.querySelector('[data-kozbridge-run]');
		var state = box.querySelector('[data-kozbridge-console-status]');
		var output = box.querySelector('[data-kozbridge-output]');
		var hint = function () {
			var option = endpoint.options[endpoint.selectedIndex];
			params.value = option ? (option.getAttribute('data-hint') || '') : '';
			params.disabled = '' === params.value;
		};
		endpoint.addEventListener('change', hint);
		hint();
		run.addEventListener('click', function () {
			var body = new window.FormData();
			body.append('action', 'kozbridge_try');
			body.append('nonce', cfg.nonce || '');
			body.append('endpoint', endpoint.value);
			body.append('params', params.disabled ? '' : params.value);
			run.disabled = true;
			state.textContent = cfg.running || 'Running…';
			window.fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (response) { return response.json(); })
				.then(function (json) {
					state.textContent = 'HTTP ' + json.status + ' · ' + json.duration_ms + ' ms';
					output.textContent = JSON.stringify(json.data, null, 2);
					output.hidden = false;
				})
				.catch(function () { state.textContent = cfg.failed || 'Request failed.'; })
				.then(function () { run.disabled = false; });
		});
	});
}());
