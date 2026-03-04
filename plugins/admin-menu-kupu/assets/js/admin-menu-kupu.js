/* global AMK_VARS, ajaxurl */
(function(){
  if (typeof window === 'undefined' || typeof document === 'undefined') return;

  function applyTooltips(el) {
    if (!el) { return; }
    if (!AMK_VARS.tooltips_enabled) { return; }

    var original = el.getAttribute('data-amk-original');
    if (!original) { return; }

    // Progressive enhancement tooltip bubble.
    if (!el.classList.contains('amk-tooltip')) {
      el.classList.add('amk-tooltip');
      el.setAttribute('aria-label', original); // accessible name
      var bubble = document.createElement('span');
      bubble.setAttribute('data-amk-tip', '');
      bubble.textContent = original;
      el.appendChild(bubble);
    }
  }

  function translateMenuInDom(enable) {
    var nodes = document.querySelectorAll('#adminmenu .wp-has-submenu > a .wp-menu-name, #adminmenu a .wp-menu-name');
    nodes.forEach(function(node){
      var original = node.getAttribute('data-amk-original');
      var translated = node.getAttribute('data-amk-translated');

      if (!original) { return; }
      if (enable && translated) {
        node.textContent = translated;
      } else {
        node.textContent = original;
      }
      applyTooltips(node);
    });
    document.body.classList.toggle('amk-enabled', !!enable);
    // Update admin bar switch label if present
    var ab = document.getElementById('amk-adminbar-label');
    if (ab) {
      ab.textContent = enable ? 'Kupu: On' : 'Kupu: Off';
    }
  }

  // Initial enhancement
  document.addEventListener('DOMContentLoaded', function(){
    // Set title fallback
    document.querySelectorAll('#adminmenu .wp-menu-name').forEach(function(n){
      var orig = n.getAttribute('data-amk-original');
      if (orig && !n.getAttribute('title')) {
        n.setAttribute('title', orig);
      }
      applyTooltips(n);
    });

    translateMenuInDom(AMK_VARS.enabled);

    // Admin bar AJAX toggle
    var toggle = document.getElementById('amk-adminbar-toggle');
    if (toggle) {
      toggle.addEventListener('click', function(e){
        e.preventDefault();
        if (toggle.getAttribute('aria-disabled') === 'true') return;
        toggle.setAttribute('aria-disabled', 'true');
        var wanted = !document.body.classList.contains('amk-enabled');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxurl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function(){
          toggle.removeAttribute('aria-disabled');
          try {
            var res = JSON.parse(xhr.responseText);
            if (res && res.success && typeof res.data.enabled === 'boolean') {
              translateMenuInDom(res.data.enabled);
            }
          } catch (err){ /* no-op */ }
        };
        xhr.onerror = function(){ toggle.removeAttribute('aria-disabled'); };
        xhr.send('action=amk_toggle_user&nonce=' + encodeURIComponent(AMK_VARS.nonce) + '&enable=' + (wanted ? '1':'0'));
      });
    }
  });
})();
