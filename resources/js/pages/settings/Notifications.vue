<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bell, BellOff, BellRing } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    disablePushNotifications,
    enablePushNotifications,
    pushPermissionState,
} from '@/lib/pushNotifications';
import type { PushPermissionState } from '@/lib/pushNotifications';
import { edit } from '@/routes/notification-settings';

type Props = {
    vapidPublicKey: string | null;
    hasSubscriptions: boolean;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Notification settings',
                href: edit(),
            },
        ],
    },
});

const permission = ref<PushPermissionState>('unsupported');
const subscribed = ref(props.hasSubscriptions);
const processing = ref(false);
const error = ref<string | null>(null);

onMounted(() => {
    permission.value = pushPermissionState();
});

const enable = async () => {
    if (!props.vapidPublicKey) {
        error.value = 'Push notifications are not configured for this server.';

        return;
    }

    processing.value = true;
    error.value = null;

    try {
        permission.value = await enablePushNotifications(props.vapidPublicKey);
        subscribed.value = permission.value === 'granted';
    } catch {
        error.value = 'Could not enable notifications on this browser.';
    } finally {
        processing.value = false;
    }
};

const disable = async () => {
    processing.value = true;
    error.value = null;

    try {
        await disablePushNotifications();
        subscribed.value = false;
    } catch {
        error.value = 'Could not disable notifications on this browser.';
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Head title="Notification settings" />

    <h1 class="sr-only">Notification settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Notifications"
            description="Get an alert on this device when something needs your attention — an approval, an unsettled bill, or an operational issue."
        />

        <div class="rounded-lg border p-4">
            <div class="flex items-start gap-3">
                <BellRing
                    v-if="subscribed"
                    class="mt-0.5 size-5 text-emerald-600"
                />
                <BellOff
                    v-else-if="permission === 'denied'"
                    class="mt-0.5 size-5 text-red-600"
                />
                <Bell v-else class="mt-0.5 size-5 text-muted-foreground" />

                <div class="flex-1 space-y-1">
                    <p class="text-sm font-medium">
                        <template v-if="subscribed">
                            Notifications are enabled on this browser
                        </template>
                        <template v-else-if="permission === 'denied'">
                            Notifications are blocked in your browser settings
                        </template>
                        <template v-else-if="permission === 'unsupported'">
                            This browser doesn't support push notifications
                        </template>
                        <template v-else> Notifications are off </template>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="permission === 'denied'">
                            You'll need to allow notifications for this site in
                            your browser's settings, then reload this page.
                        </template>
                        <template v-else-if="permission === 'unsupported'">
                            Try a different browser to receive push
                            notifications here.
                        </template>
                        <template v-else>
                            This only affects the current browser — enable it on
                            every device you want alerts on.
                        </template>
                    </p>
                    <p v-if="error" class="text-sm text-red-600">
                        {{ error }}
                    </p>
                </div>

                <Button
                    v-if="
                        !subscribed &&
                        permission !== 'denied' &&
                        permission !== 'unsupported'
                    "
                    :disabled="processing"
                    @click="enable"
                >
                    {{ processing ? 'Enabling…' : 'Enable notifications' }}
                </Button>
                <Button
                    v-else-if="subscribed"
                    variant="outline"
                    :disabled="processing"
                    @click="disable"
                >
                    {{ processing ? 'Disabling…' : 'Disable' }}
                </Button>
            </div>
        </div>
    </div>
</template>
