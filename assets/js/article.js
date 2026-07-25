/**
 * Article enhancements: reading progress, auto table of contents, share bar.
 * No dependencies. Runs only on single posts where the markup exists.
 */
(function () {
	'use strict';

	function progress() {
		var bar = document.getElementById('wghs-progress');
		var art = document.getElementById('wghs-article-content');
		if (!bar || !art) { return; }
		function upd() {
			var rect = art.getBoundingClientRect();
			var total = art.offsetHeight - window.innerHeight;
			var done = Math.min(Math.max(-rect.top, 0), total);
			bar.style.width = total > 0 ? (done / total * 100) + '%' : '0%';
		}
		window.addEventListener('scroll', upd, { passive: true });
		window.addEventListener('resize', upd);
		upd();
	}

	function toc() {
		var box = document.getElementById('wghs-toc');
		var art = document.getElementById('wghs-article-content');
		if (!box || !art) { return; }
		var heads = art.querySelectorAll('h2, h3');
		if (heads.length < 3) { return; } // not worth a TOC on short posts
		var ul = box.querySelector('ul');
		heads.forEach(function (h, i) {
			if (!h.id) { h.id = 'sec-' + i + '-' + h.textContent.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 40); }
			var li = document.createElement('li');
			li.className = h.tagName === 'H3' ? 'wghs-toc__sub' : '';
			var a = document.createElement('a');
			a.href = '#' + h.id;
			a.textContent = h.textContent;
			li.appendChild(a);
			ul.appendChild(li);
		});
		box.hidden = false;
	}

	function share() {
		var bar = document.querySelector('[data-wghs-share]');
		if (!bar) { return; }
		var url = encodeURIComponent(location.href);
		var title = encodeURIComponent(document.title);
		var map = {
			whatsapp: 'https://wa.me/?text=' + title + '%20' + url,
			facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url,
			x: 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title
		};
		bar.querySelectorAll('.wghs-share__btn').forEach(function (b) {
			var net = b.getAttribute('data-net');
			if (map[net]) {
				b.setAttribute('href', map[net]);
				b.setAttribute('target', '_blank');
			} else if (net === 'copy') {
				b.addEventListener('click', function () {
					navigator.clipboard && navigator.clipboard.writeText(location.href).then(function () {
						var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1500);
					});
				});
			}
		});
	}

	function init() { progress(); toc(); share(); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
