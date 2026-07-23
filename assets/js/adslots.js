/**
 * Ad slot loader for WebsitesGH Shop.
 *
 * Fills every .wgh-adslot container from the wgh-ad-slots plugin, matching the
 * contract already running on websitesgh.com:
 *   GET  /wp-json/wgh-ad-slots/v1/serve?slot={slot}  ->  { id, html } or { empty: true }
 *   POST /wp-json/wgh-ad-slots/v1/click/{id}
 *
 * If the plugin is absent every request fails quietly and the containers stay
 * empty, which CSS collapses. Nothing on the page breaks.
 */
(function () {
	'use strict';

	var cfg  = window.WGHS_ADS || {};
	var base = cfg.root || (window.wpApiSettings && window.wpApiSettings.root) || '/wp-json/';
	if (base.slice(-1) !== '/') { base += '/'; }
	var api = base + 'wgh-ad-slots/v1/';

	function track(id) {
		if (!id) { return; }
		try {
			var url = api + 'click/' + encodeURIComponent(id);
			if (navigator.sendBeacon) { navigator.sendBeacon(url); return; }
			fetch(url, { method: 'POST', keepalive: true, credentials: 'same-origin' });
		} catch (e) { /* tracking must never block the click */ }
	}

	function bind(el) {
		el.addEventListener('click', function (ev) {
			var card = ev.target.closest('[data-ad]');
			if (card) { track(card.getAttribute('data-ad')); }
		});
	}

	function gallery(el) {
		var imgs = el.querySelectorAll('.wgh-ad-gimg');
		if (imgs.length < 2) { return; }
		var i = 0;
		setInterval(function () {
			imgs[i].classList.remove('is-active');
			i = (i + 1) % imgs.length;
			imgs[i].classList.add('is-active');
		}, 4000);
	}

	function fill(el) {
		var slot = el.getAttribute('data-slot') || 'sidebar';
		fetch(api + 'serve?slot=' + encodeURIComponent(slot), { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (d) {
				if (!d || d.empty || !d.html) { return; }
				el.innerHTML = d.html;
				bind(el);
				gallery(el);
			})
			.catch(function () { /* plugin not installed, leave empty */ });
	}

	function rotate(el) {
		var ms = parseInt(el.getAttribute('data-rotate'), 10);
		if (!ms || ms < 3000) { return; }
		setInterval(function () { fill(el); }, ms);
	}

	function init() {
		var slots = document.querySelectorAll('.wgh-adslot');
		if (!slots.length) { return; }
		// Only fetch when the slot is near the viewport. Keeps the blog fast.
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) {
					if (!e.isIntersecting) { return; }
					io.unobserve(e.target);
					fill(e.target);
					rotate(e.target);
				});
			}, { rootMargin: '300px' });
			slots.forEach(function (s) { io.observe(s); });
		} else {
			slots.forEach(function (s) { fill(s); rotate(s); });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
