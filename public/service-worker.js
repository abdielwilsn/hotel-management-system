// Plain static file served as-is by Laravel — not built by Vite. Handles
// browser push notifications for the hotel app: showing them (even when no
// tab is open) and focusing/opening the relevant page on click.

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();
    const title = payload.title || 'Notification';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge || undefined,
        data: payload.data || {},
        tag: payload.tag || undefined,
        renotify: Boolean(payload.tag),
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url;

    if (!url) {
        return;
    }

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                for (const client of clients) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }

                if (self.clients.openWindow) {
                    return self.clients.openWindow(url);
                }
            }),
    );
});
