(function () {
  if (window.__sdWaWidget) return; window.__sdWaWidget = true;
  var c = {!! json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
  if (!c.mobile && window.innerWidth < 640) return;
  var side = c.position === 'left' ? 'left' : 'right';
  var link = 'https://wa.me/' + c.phone + (c.prefill ? '?text=' + encodeURIComponent(c.prefill) : '');
  var css = '.sdwa{position:fixed;bottom:20px;' + side + ':20px;z-index:2147483000;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}' +
    '.sdwa-btn{display:flex;align-items:center;gap:8px;border:0;border-radius:999px;padding:12px 18px;color:#fff;font-size:15px;font-weight:600;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.18)}' +
    '.sdwa-card{position:absolute;bottom:64px;' + side + ':0;width:300px;max-width:calc(100vw - 40px);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.2);overflow:hidden;display:none}' +
    '.sdwa-open .sdwa-card{display:block}.sdwa-head{padding:16px;color:#fff;font-weight:600}.sdwa-body{padding:16px;background:#ECE5DD}' +
    '.sdwa-bubble{background:#fff;border-radius:0 12px 12px 12px;padding:10px 12px;font-size:14px;color:#111;line-height:1.4;white-space:pre-line}' +
    '.sdwa-go{display:block;margin:12px 16px 16px;text-align:center;color:#fff;text-decoration:none;border-radius:999px;padding:10px;font-weight:600;font-size:14px}' +
    '.sdwa-x{float:right;background:none;border:0;color:#fff;font-size:18px;cursor:pointer;line-height:1}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
  var icon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.2z"/></svg>';
  var root = document.createElement('div'); root.className = 'sdwa';
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
  root.innerHTML = '<div class="sdwa-card" role="dialog"><div class="sdwa-head" style="background:' + c.color + '">' +
    '<button class="sdwa-x" aria-label="Close">&times;</button>' + esc(c.brand || 'WhatsApp') + '</div>' +
    '<div class="sdwa-body"><div class="sdwa-bubble">' + esc(c.greeting) + '</div></div>' +
    '<a class="sdwa-go" target="_blank" rel="noopener" style="background:' + c.color + '" href="' + link + '">Start chat</a></div>' +
    '<button class="sdwa-btn" style="background:' + c.color + '">' + icon + '<span>' + esc(c.button_text) + '</span></button>';
  document.body.appendChild(root);
  root.querySelector('.sdwa-btn').onclick = function () { root.classList.toggle('sdwa-open'); };
  root.querySelector('.sdwa-x').onclick = function () { root.classList.remove('sdwa-open'); };
  root.querySelector('.sdwa-go').onclick = function () { try { navigator.sendBeacon(c.click); } catch (e) {} };
})();
