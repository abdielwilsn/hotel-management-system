import { router } from '@inertiajs/vue3';
import {
    destroy as destroySubscription,
    store as storeSubscription,
} from '@/routes/push-subscriptions';

export type PushPermissionState =
    | 'unsupported'
    | 'default'
    | 'granted'
    | 'denied';

export function pushPermissionState(): PushPermissionState {
    if (
        !('Notification' in window) ||
        !('serviceWorker' in navigator) ||
        !('PushManager' in window)
    ) {
        return 'unsupported';
    }

    return Notification.permission as PushPermissionState;
}

// Browsers hand the VAPID public key back as a URL-safe base64 string; the
// Push API wants it as the raw bytes.
function urlBase64ToUint8Array(base64: string): Uint8Array {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = window.atob(
        (base64 + padding).replace(/-/g, '+').replace(/_/g, '/'),
    );

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

/**
 * Register the service worker, ask the browser for permission, and send the
 * resulting subscription to the server. Only ever called from an explicit
 * user action (a button click) — browsers penalize sites that prompt for
 * notification permission unprompted.
 */
export async function enablePushNotifications(
    vapidPublicKey: string,
): Promise<PushPermissionState> {
    const state = pushPermissionState();

    if (state === 'unsupported') {
        return state;
    }

    const registration =
        await navigator.serviceWorker.register('/service-worker.js');
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return permission as PushPermissionState;
    }

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey) as BufferSource,
    });

    const json = subscription.toJSON();

    await new Promise<void>((resolve, reject) => {
        router.post(
            storeSubscription().url,
            {
                endpoint: json.endpoint,
                public_key: json.keys?.p256dh,
                auth_token: json.keys?.auth,
                content_encoding: 'aesgcm',
            },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('Could not save the push subscription.')),
            },
        );
    });

    return 'granted';
}

/**
 * Unsubscribe this browser and remove its subscription server-side.
 */
export async function disablePushNotifications(): Promise<void> {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const registration =
        await navigator.serviceWorker.getRegistration('/service-worker.js');
    const subscription = await registration?.pushManager.getSubscription();

    if (!subscription) {
        return;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    await new Promise<void>((resolve, reject) => {
        router.delete(destroySubscription().url, {
            data: { endpoint },
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () =>
                reject(new Error('Could not remove the push subscription.')),
        });
    });
}
