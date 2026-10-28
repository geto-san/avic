// Progressive enhancement only: every page works without this file.
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) {
    e.preventDefault();
  }
});
// Thousands separators while typing in money fields.
document.addEventListener('input', function (e) {
  if (!e.target.classList || !e.target.classList.contains('money-input')) { return; }
  var digits = e.target.value.replace(/[^0-9]/g, '');
  e.target.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
});
