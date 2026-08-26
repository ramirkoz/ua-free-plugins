(function(){
	'use strict';
	var countdown = document.getElementById('kozstx-countdown');
	var language = document.getElementById('kozstx-wait-language');
	var remaining = 10;

	if (language) {
		language.addEventListener('change', function(){
			if (language.value) {
				window.location.href = language.value;
			}
		});
	}

	window.setInterval(function(){
		remaining = Math.max(0, remaining - 1);
		if (countdown) {
			countdown.textContent = String(remaining);
		}
		if (remaining === 0) {
			window.location.reload();
		}
	}, 1000);
}());
