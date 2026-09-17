/* AVIC Portal — notification renderer, shared by all desks. */

AVIC.pages = AVIC.pages || {};

AVIC.pages.notifications = function (s) {
  const list = AVIC.notificationsFor(s).sort((a, b) => b.created_at.localeCompare(a.created_at));
  const host = document.getElementById('notif-list');
  const unread = list.filter(n => !n.is_read).length;
  document.getElementById('notif-count').textContent =
    unread ? unread + ' unread of ' + list.length : list.length + ' notifications, all read';

  host.innerHTML = list.length ? list.map(n =>
    '<div class="notif' + (n.is_read ? '' : ' is-unread') + '">' +
      '<div class="notif__bar"></div>' +
      '<div style="flex:1">' +
        '<div class="notif__t">' + UI.esc(n.title) + '</div>' +
        '<div class="notif__m">' + UI.esc(n.message) + '</div>' +
        '<div class="notif__d">' + UI.dateTime(n.created_at) + ' · ' + UI.ago(n.created_at) + '</div>' +
      '</div>' +
      (n.is_read ? '' : '<button class="btn btn--sm" data-read="' + n.id + '">Mark read</button>') +
    '</div>').join('')
    : '<div class="empty"><div class="empty__t">No notifications</div>' +
      '<div class="empty__d">Alerts about your claims will land here.</div></div>';

  host.querySelectorAll('[data-read]').forEach(b => b.onclick = () => {
    const n = AVIC.notifications.find(x => x.id === +b.dataset.read);
    n.is_read = 1;
    AVIC.pages.notifications(s);
    UI.toast('Marked as read.');
  });

  const all = document.getElementById('mark-all');
  if (all) all.onclick = () => {
    AVIC.notificationsFor(s).forEach(n => n.is_read = 1);
    AVIC.pages.notifications(s);
    UI.toast('All notifications marked read.');
  };
};
