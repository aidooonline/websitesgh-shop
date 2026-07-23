/* WebsitesGH Shop theme scripts */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    /* Mobile menu */
    var menu = document.getElementById('wghs-mobile-menu');
    var panel = document.getElementById('wghs-mobile-panel');
    var toggle = document.getElementById('wghs-menu-toggle');

    function openMenu() {
      if (!menu) return;
      menu.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      requestAnimationFrame(function () { if (panel) panel.style.transform = 'translateX(0)'; });
      if (toggle) toggle.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
      if (!menu) return;
      if (panel) panel.style.transform = 'translateX(-100%)';
      document.body.style.overflow = '';
      setTimeout(function () { menu.classList.add('hidden'); }, 300);
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }
    if (toggle) toggle.addEventListener('click', openMenu);
    if (menu) menu.querySelectorAll('[data-close]').forEach(function (el) { el.addEventListener('click', closeMenu); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenu(); closeChat(); } });

    /* Mobile search drawer */
    var sToggle = document.getElementById('wghs-search-toggle');
    var sDrawer = document.getElementById('wghs-search-drawer');
    if (sToggle && sDrawer) {
      sToggle.addEventListener('click', function () {
        sDrawer.classList.toggle('hidden');
        var input = sDrawer.querySelector('input[type="search"]');
        if (input && !sDrawer.classList.contains('hidden')) input.focus();
      });
    }

    /* Header elevation */
    var header = document.getElementById('site-header');
    if (header) {
      var onScroll = function () {
        if (window.scrollY > 8) header.classList.add('shadow-card');
        else header.classList.remove('shadow-card');
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    /* WhatsApp chat box: on-page panel, sending opens WhatsApp with the typed message */
    var chat = document.getElementById('wghs-chat');
    var chatPanel = document.getElementById('wghs-chat-panel');
    var chatToggle = document.getElementById('wghs-chat-toggle');
    var chatForm = document.getElementById('wghs-chat-form');
    var chatInput = document.getElementById('wghs-chat-input');

    function openChat() {
      if (!chatPanel) return;
      chatPanel.classList.remove('hidden');
      if (chatInput) chatInput.focus();
    }
    function closeChat() {
      if (chatPanel) chatPanel.classList.add('hidden');
    }
    if (chatToggle) {
      chatToggle.addEventListener('click', function () {
        if (chatPanel && chatPanel.classList.contains('hidden')) openChat();
        else closeChat();
      });
    }
    if (chat) chat.querySelectorAll('[data-close-chat]').forEach(function (el) { el.addEventListener('click', closeChat); });
    /* Any "Chat with us" button elsewhere on the page opens the same panel */
    document.querySelectorAll('[data-open-chat]').forEach(function (el) {
      el.addEventListener('click', function (e) { e.preventDefault(); openChat(); });
    });
    if (chatForm && chatToggle) {
      chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var num = chatToggle.getAttribute('data-wa') || '';
        var msg = (chatInput && chatInput.value.trim()) || 'Hi WebsitesGH Shop, I have a question.';
        if (!num) return;
        window.open('https://wa.me/' + num + '?text=' + encodeURIComponent(msg), '_blank', 'noopener');
      });
    }
  });
})();

/* Mini-cart drawer + cart bubble sync + block-cart WhatsApp checkout */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var drawer = document.getElementById('wghs-cart-drawer');
    var panel = document.getElementById('wghs-cart-panel');

    function openCart() {
      if (!drawer) return;
      drawer.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      requestAnimationFrame(function () { if (panel) panel.style.transform = 'translateX(0)'; });
    }
    function closeCart() {
      if (!drawer) return;
      if (panel) panel.style.transform = 'translateX(100%)';
      document.body.style.overflow = '';
      setTimeout(function () { drawer.classList.add('hidden'); }, 300);
    }
    if (drawer) drawer.querySelectorAll('[data-close-cart]').forEach(function (el) { el.addEventListener('click', closeCart); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCart(); });

    /* Open the drawer when something is added, sync bubble visibility */
    function syncBubble() {
      document.querySelectorAll('.wghs-cart-bubble').forEach(function (b) {
        var inner = b.querySelector('.wghs-cart-count');
        var n = inner ? parseInt(inner.getAttribute('data-count') || inner.textContent || '0', 10) : 0;
        b.classList.toggle('opacity-0', !(n > 0));
      });
    }
    if (window.jQuery) {
      jQuery(document.body).on('added_to_cart', function () { syncBubble(); });
      jQuery(document.body).on('wc_fragments_refreshed wc_fragments_loaded removed_from_cart', syncBubble);
    }
    syncBubble();

    /* Block-based cart page: point its checkout button at WhatsApp checkout.
       Guards prevent self-triggering mutations (observer loop = frozen page). */
    if (window.TPG && TPG.waCartUrl) {
      var waLabel = TPG.waCartLabel || 'Checkout on WhatsApp';
      var observer = null;
      var fixBlockCheckout = function () {
        var changed = false;
        document.querySelectorAll('.wc-block-cart__submit-container a, a.wc-block-cart__submit-button').forEach(function (a) {
          if (a.getAttribute('href') !== TPG.waCartUrl) { a.setAttribute('href', TPG.waCartUrl); changed = true; }
          if (a.textContent !== waLabel) { a.textContent = waLabel; changed = true; }
        });
        return changed;
      };
      var safeFix = function () {
        if (observer) observer.disconnect();
        fixBlockCheckout();
        if (observer) observer.observe(document.body, { childList: true, subtree: true });
      };
      observer = new MutationObserver(function () { safeFix(); });
      safeFix();
    }
  });
})();
