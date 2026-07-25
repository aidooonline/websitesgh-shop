<?php
/**
 * Soft + smart lead capture before WhatsApp.
 *
 * When a buyer taps any WhatsApp link (cart, product, order bar), a popup asks
 * for name, phone and area ONCE. After they fill it (or skip), the answer is
 * remembered in a cookie so returning buyers never see it again. The captured
 * details are:
 *   1. stamped into the WhatsApp message ("Name: ..., Phone: ..., Area: ...")
 *   2. sent to the wa-click beacon so the attribution row saves them, which
 *      also gives Enhanced Conversions the real phone to hash.
 *
 * Soft: a Skip link lets the impatient go straight through, so we never block a
 * sale. The WhatsApp message still carries the ref code, so even a skipper is
 * tracked; we just miss their name. Smart: filled once, remembered forever.
 *
 * This is theme-native now. LeadCapture (the client plugin) can be wired later
 * to also POST these leads to the websitesgh.com server plugin; the data shape
 * here (name, phone, area, product, gclid, utms, ref) is ready for that.
 *
 * @package WGHShop
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_footer', 'wghs_lead_capture_popup', 5 );
function wghs_lead_capture_popup() {
	// Only where ordering happens: product, shop, cart. Not admin, not blog.
	if ( is_admin() ) { return; }
	?>
	<div id="wghs-lead" class="wghs-lead" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="wghs-lead-title">
		<div class="wghs-lead__box">
			<button type="button" class="wghs-lead__x" aria-label="<?php esc_attr_e( 'Close', 'wghshop' ); ?>">&times;</button>
			<h3 id="wghs-lead-title" class="wghs-lead__title"><?php esc_html_e( 'Almost there', 'wghshop' ); ?></h3>
			<p class="wghs-lead__sub"><?php esc_html_e( 'Add your details so we can confirm your order and delivery. Pay on delivery.', 'wghshop' ); ?></p>
			<label class="wghs-lead__field">
				<span><?php esc_html_e( 'Your name', 'wghshop' ); ?></span>
				<input type="text" id="wghs-lead-name" autocomplete="name" placeholder="<?php esc_attr_e( 'e.g. Ama Owusu', 'wghshop' ); ?>">
			</label>
			<label class="wghs-lead__field">
				<span><?php esc_html_e( 'Phone number', 'wghshop' ); ?></span>
				<input type="tel" id="wghs-lead-phone" autocomplete="tel" inputmode="tel" placeholder="<?php esc_attr_e( 'e.g. 024 000 0000', 'wghshop' ); ?>">
			</label>
			<label class="wghs-lead__field">
				<span><?php esc_html_e( 'Your area', 'wghshop' ); ?></span>
				<input type="text" id="wghs-lead-area" autocomplete="address-level2" placeholder="<?php esc_attr_e( 'e.g. Madina, Accra', 'wghshop' ); ?>">
			</label>
			<button type="button" class="wghs-lead__go"><?php esc_html_e( 'Continue to WhatsApp', 'wghshop' ); ?></button>
			<button type="button" class="wghs-lead__skip"><?php esc_html_e( 'Skip for now', 'wghshop' ); ?></button>
		</div>
	</div>
	<?php
}

/**
 * Popup styles and behaviour. Kept inline and dependency-free so it works
 * regardless of theme build state, and never blocks the WhatsApp navigation.
 */
add_action( 'wp_footer', 'wghs_lead_capture_assets', 6 );
function wghs_lead_capture_assets() {
	if ( is_admin() ) { return; }
	?>
	<style>
	.wghs-lead{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(15,20,25,.55);padding:20px}
	.wghs-lead[hidden]{display:none}
	.wghs-lead__box{background:#fff;border-radius:16px;max-width:380px;width:100%;padding:26px 22px;box-shadow:0 24px 60px -20px rgba(0,0,0,.4);position:relative;animation:wghsLeadIn .18s ease-out}
	@keyframes wghsLeadIn{from{transform:translateY(10px);opacity:0}to{transform:translateY(0);opacity:1}}
	.wghs-lead__x{position:absolute;top:12px;right:14px;border:0;background:none;font-size:24px;line-height:1;color:#8a857b;cursor:pointer}
	.wghs-lead__title{font-size:20px;margin:0 0 4px;color:#20211C}
	.wghs-lead__sub{font-size:13.5px;color:#6b6a63;margin:0 0 16px;line-height:1.45}
	.wghs-lead__field{display:block;margin-bottom:12px}
	.wghs-lead__field span{display:block;font-size:12.5px;font-weight:600;color:#3a382f;margin-bottom:5px}
	.wghs-lead__field input{width:100%;padding:11px 13px;border:1px solid #d9d5cc;border-radius:10px;font-size:15px;font-family:inherit}
	.wghs-lead__field input:focus{outline:none;border-color:#0E8C5A;box-shadow:0 0 0 3px rgba(14,140,90,.14)}
	.wghs-lead__go{width:100%;padding:13px;border:0;border-radius:10px;background:#0E8C5A;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:2px}
	.wghs-lead__go:active{transform:translateY(1px)}
	.wghs-lead__skip{width:100%;padding:9px;border:0;background:none;color:#8a857b;font-size:13px;cursor:pointer;margin-top:6px;font-family:inherit}
	.wghs-lead__box.is-invalid .wghs-lead__field input:invalid{border-color:#c0392b}
	</style>
	<script>
	(function () {
		'use strict';
		var COOKIE = 'wghs_lead';
		function getLead() {
			var m = document.cookie.match(/(?:^|; )wghs_lead=([^;]*)/);
			if (!m) { return null; }
			try { return JSON.parse(decodeURIComponent(m[1])); } catch (e) { return null; }
		}
		function saveLead(d) {
			document.cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(d)) + ';path=/;max-age=15552000;SameSite=Lax';
		}
		var pop = document.getElementById('wghs-lead');
		if (!pop) { return; }
		var box = pop.querySelector('.wghs-lead__box');
		var elName = document.getElementById('wghs-lead-name');
		var elPhone = document.getElementById('wghs-lead-phone');
		var elArea = document.getElementById('wghs-lead-area');
		var pendingLink = null; // the WhatsApp anchor we intercepted

		function show() { pop.hidden = false; pop.setAttribute('aria-hidden', 'false'); setTimeout(function () { elName.focus(); }, 50); }
		function hide() { pop.hidden = true; pop.setAttribute('aria-hidden', 'true'); }

		/* Stamp the captured details into a WhatsApp link's prefilled text. */
		function stamp(link, lead) {
			try {
				var u = new URL(link.href);
				var t = u.searchParams.get('text') || '';
				if (lead && lead.name && t.indexOf('Name:') === -1) {
					var block = 'Name: ' + lead.name + '\nPhone: ' + lead.phone + (lead.area ? '\nArea: ' + lead.area : '');
					u.searchParams.set('text', block + (t ? '\n' + t : ''));
					link.href = u.toString();
				}
			} catch (e) { /* leave link untouched */ }
		}

		/* Proceed: stamp the link, let the beacon (in attribution.php) read the
		   lead cookie, then follow the link in a new tab. */
		function proceed(link) {
			var lead = getLead();
			stamp(link, lead);
			window.open(link.href, '_blank', 'noopener');
		}

		/* Intercept taps on any WhatsApp link. If we already have the lead,
		   don't interrupt, just stamp and go. If not, open the popup once. */
		document.addEventListener('click', function (e) {
			var a = e.target.closest('a[href*="wa.me"]');
			if (!a) { return; }
			var lead = getLead();
			if (lead && lead.phone) { stamp(a, lead); return; } // remembered: sail through
			// No lead yet: intercept and ask once.
			e.preventDefault();
			pendingLink = a;
			show();
		}, true); // capture phase, before the beacon's own handler

		pop.querySelector('.wghs-lead__go').addEventListener('click', function () {
			var name = (elName.value || '').trim();
			var phone = (elPhone.value || '').replace(/[^0-9+]/g, '');
			var area = (elArea.value || '').trim();
			if (!name || phone.length < 9) { box.classList.add('is-invalid'); elName.setAttribute('required',''); elPhone.setAttribute('required',''); if(!name){elName.focus();}else{elPhone.focus();} return; }
			var lead = { name: name, phone: phone, area: area };
			saveLead(lead);
			hide();
			if (pendingLink) { proceed(pendingLink); pendingLink = null; }
		});

		function skip() {
			hide();
			if (pendingLink) { window.open(pendingLink.href, '_blank', 'noopener'); pendingLink = null; }
		}
		pop.querySelector('.wghs-lead__skip').addEventListener('click', skip);
		pop.querySelector('.wghs-lead__x').addEventListener('click', function () { hide(); pendingLink = null; });
		pop.addEventListener('click', function (e) { if (e.target === pop) { hide(); pendingLink = null; } });
	}());
	</script>
	<?php
}
