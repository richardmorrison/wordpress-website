/* global AMK_TITLES */
(function(){
  if (typeof document === 'undefined' || !AMK_TITLES || !AMK_TITLES.headers) return;

  function replace(node) {
    if (!node) return;
    var text = node.textContent.trim();
    var t = AMK_TITLES.headers[text];
    if (!t) return;
    node.setAttribute('data-amk-original', text);
    node.setAttribute('title', text);
    node.textContent = t;
  }

  document.addEventListener('DOMContentLoaded', function(){
    var h1 = document.querySelector('h1.wp-heading-inline') || document.querySelector('#wpbody-content h1');
    replace(h1);
    // Also try screen-reader-text header in some screens
    var sr = document.querySelector('.wrap .wp-heading-inline ~ .screen-reader-text');
    replace(sr);
  });
})();
