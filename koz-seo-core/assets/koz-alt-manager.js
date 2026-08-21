(function () {
	'use strict';

	var cfg = window.KOZSEOAltManager || {};
	var analyzeButton = document.getElementById('kozseo-ai-analyze');
	var progress = document.getElementById('kozseo-ai-progress');
	var sizeSelect = document.getElementById('kozseo-ai-batch-size');
	var scopeSelect = document.getElementById('kozseo-ai-scope');
	var applyButton = document.getElementById('kozseo-ai-apply-selected');
	var selectAll = document.getElementById('kozseo-ai-select-all');

	function post(data) {
		var body = new URLSearchParams();
		Object.keys(data).forEach(function (key) {
			var value = data[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) { body.append(key + '[]', item); });
			} else {
				body.append(key, value);
			}
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: body.toString()
		}).then(function (response) { return response.json(); });
	}

	function restPost(url, payload) {
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.restNonce
			},
			body: JSON.stringify(payload || {})
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok) {
					var err = new Error(json && json.message ? json.message : 'REST request failed');
					err.payload = json;
					throw err;
				}
				return json;
			});
		});
	}

	function postAnalyze() {
		var onlyEmpty = !scopeSelect || scopeSelect.value !== 'all';
		return restPost(cfg.restAnalyzeUrl, {only_empty: onlyEmpty});
	}

	function updateStats(stats) {
		if (!stats) { return; }
		['total', 'analyzed', 'high', 'decorative', 'uncertain', 'applied', 'errors', 'remaining', 'pending_empty'].forEach(function (key) {
			var node = document.getElementById('kozseo-ai-stat-' + key);
			if (node && Object.prototype.hasOwnProperty.call(stats, key)) {
				node.textContent = stats[key];
			}
		});
	}

	function selectedIds() {
		var ids = [];
		document.querySelectorAll('.kozseo-ai-row:checked').forEach(function (box) { ids.push(box.value); });
		return ids;
	}

	function candidateInput(id) {
		return document.querySelector('.kozseo-ai-alt-edit[data-id="' + String(id).replace(/"/g, '') + '"]');
	}

	if (analyzeButton) {
		analyzeButton.addEventListener('click', function () {
			var requested = parseInt(sizeSelect ? sizeSelect.value : '10', 10) || 10;
			requested = Math.max(1, Math.min(parseInt(cfg.maxBatch || 50, 10), requested));
			var completed = 0;
			var failures = 0;
			analyzeButton.disabled = true;
			if (progress) { progress.textContent = (cfg.labels && cfg.labels.analyzing) || 'Analyzing…'; }

			function next() {
				if (completed >= requested) {
					if (progress) { progress.textContent = 'Processed ' + completed + ' image(s). Errors: ' + failures + '. Reloading preview…'; }
					window.setTimeout(function () { window.location.reload(); }, 700);
					return;
				}
				postAnalyze().then(function (json) {
					if (json && json.done) {
						if (progress) { progress.textContent = (cfg.labels && cfg.labels.noMore) || 'No unanalyzed images remain in this queue.'; }
						analyzeButton.disabled = false;
						return;
					}
					completed += 1;
					updateStats(json.stats);
					if (progress) { progress.textContent = 'Analyzed ' + completed + ' / ' + requested + '…'; }
					next();
				}).catch(function (error) {
					failures += 1;
					var msg = error && error.message ? error.message : ((cfg.labels && cfg.labels.error) || 'Error');
					if (progress) { progress.textContent = 'Stopped after ' + completed + ' image(s): ' + msg; }
					analyzeButton.disabled = false;
				});
			}
			next();
		});
	}

	if (selectAll) {
		selectAll.addEventListener('change', function () {
			document.querySelectorAll('.kozseo-ai-row').forEach(function (box) { box.checked = selectAll.checked; });
		});
	}

	document.querySelectorAll('.kozseo-ai-alt-edit').forEach(function (input) {
		input.addEventListener('input', function () {
			var id = input.getAttribute('data-id');
			var rowBox = document.querySelector('.kozseo-ai-row[value="' + id + '"]');
			if (rowBox) { rowBox.checked = true; }
		});
	});

	if (applyButton) {
		applyButton.addEventListener('click', function () {
			var ids = selectedIds();
			if (!ids.length) {
				window.alert('Select at least one analyzed image.');
				return;
			}
			if (!window.confirm((cfg.labels && cfg.labels.applyConfirm) || 'Approve and apply selected ALT text?')) {
				return;
			}
			var replace = document.getElementById('kozseo-ai-replace');
			var payload = {
				action: cfg.applyAction,
				nonce: cfg.nonce,
				ids: ids,
				replace: replace && replace.checked ? '1' : ''
			};
			ids.forEach(function (id) {
				var input = candidateInput(id);
				payload['alts[' + id + ']'] = input ? input.value : '';
			});
			applyButton.disabled = true;
			post(payload).then(function (json) {
				if (!json || !json.success) {
					var msg = json && json.data && json.data.message ? json.data.message : ((cfg.labels && cfg.labels.error) || 'Error');
					window.alert(msg);
					applyButton.disabled = false;
					return;
				}
				updateStats(json.data.stats);
				window.alert('Updated: ' + json.data.updated + '. Skipped: ' + json.data.skipped + '.');
				ids.forEach(function (id) {
					var row = document.querySelector('tr[data-kozseo-ai-id="' + id + '"]');
					if (row) { row.remove(); }
				});
				if (selectAll) { selectAll.checked = false; }
				applyButton.disabled = false;
				if (analyzeButton) { analyzeButton.disabled = false; }
				if (progress) { progress.textContent = 'ALT values applied. Ready for the next batch.'; }
			}).catch(function () {
				window.alert((cfg.labels && cfg.labels.error) || 'Error');
				applyButton.disabled = false;
			});
		});
	}

	document.querySelectorAll('.kozseo-ai-reanalyze').forEach(function (button) {
		button.addEventListener('click', function () {
			var id = button.getAttribute('data-id');
			if (!id) { return; }
			button.disabled = true;
			if (progress) { progress.textContent = (cfg.labels && cfg.labels.reanalyzing) || 'Re-analyzing…'; }
			restPost(String(cfg.restReanalyzeUrl || '').replace(/\/$/, '') + '/' + encodeURIComponent(id), {}).then(function (json) {
				updateStats(json.stats);
				if (progress) { progress.textContent = 'Re-analysis completed. Reloading preview…'; }
				window.setTimeout(function () { window.location.reload(); }, 500);
			}).catch(function (error) {
				button.disabled = false;
				var msg = error && error.message ? error.message : ((cfg.labels && cfg.labels.error) || 'Error');
				if (progress) { progress.textContent = msg; }
			});
		});
	});

	document.querySelectorAll('.kozseo-ai-skip').forEach(function (button) {
		button.addEventListener('click', function () {
			var id = button.getAttribute('data-id');
			if (!id) { return; }
			if (!window.confirm((cfg.labels && cfg.labels.skipConfirm) || 'Skip this analyzed image?')) { return; }
			button.disabled = true;
			post({
				action: cfg.skipAction,
				nonce: cfg.nonce,
				ids: [id]
			}).then(function (json) {
				if (!json || !json.success) {
					var msg = json && json.data && json.data.message ? json.data.message : ((cfg.labels && cfg.labels.error) || 'Error');
					window.alert(msg);
					button.disabled = false;
					return;
				}
				updateStats(json.data.stats);
				var row = document.querySelector('tr[data-kozseo-ai-id="' + id + '"]');
				if (row) { row.remove(); }
			}).catch(function () {
				window.alert((cfg.labels && cfg.labels.error) || 'Error');
				button.disabled = false;
			});
		});
	});
}());
