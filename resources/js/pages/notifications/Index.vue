<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Bell, Check, CheckCheck } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index, read, readAll } from '@/routes/notifications';

type NotificationData = {
    team_id: number;
    message: string;
    url?: string | null;
};

type NotificationRecord = {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_url: string | null;
    next_url: string | null;
};

type Props = {
    notifications: NotificationRecord[];
    pagination: Pagination;
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Notifications',
                href: props.currentTeam
                    ? index(props.currentTeam.slug).url
                    : '/',
            },
        ],
    }),
});

const formatDate = (value: string) =>
    new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

const readNotification = (notification: NotificationRecord) => {
    if (notification.read_at) {
        return;
    }

    router.patch(
        read([props.team.slug, notification.id]).url,
        {},
        { preserveScroll: true },
    );
};

const markAllRead = () => {
    router.post(readAll(props.team.slug).url, {}, { preserveScroll: true });
};

const hasUnread = (props: Props) => props.notifications.some((n) => !n.read_at);
</script>

<template>
    <div class="space-y-6">
        <Heading
            icon="Bell"
            title="Notifications"
            description="Approvals and operational alerts for this team"
        />

        <div class="flex justify-end">
            <Button
                v-if="hasUnread(props)"
                variant="outline"
                size="sm"
                class="gap-2"
                @click="markAllRead"
            >
                <CheckCheck class="h-4 w-4" />
                Mark all as read
            </Button>
        </div>

        <div v-if="notifications.length > 0" class="space-y-3">
            <Card
                v-for="notification in notifications"
                :key="notification.id"
                :accent-class="
                    notification.read_at
                        ? 'from-slate-300 via-slate-200 to-slate-100'
                        : 'from-blue-500 via-sky-400 to-cyan-400'
                "
            >
                <CardContent class="flex items-start justify-between gap-4 p-4">
                    <div class="flex items-start gap-3">
                        <Bell
                            class="mt-0.5 h-4 w-4 shrink-0"
                            :class="
                                notification.read_at
                                    ? 'text-slate-400'
                                    : 'text-blue-600'
                            "
                        />
                        <div>
                            <component
                                :is="notification.data.url ? Link : 'p'"
                                :href="notification.data.url ?? undefined"
                                class="text-sm font-medium text-slate-900"
                                @click="readNotification(notification)"
                            >
                                {{ notification.data.message }}
                            </component>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ formatDate(notification.created_at) }}
                            </p>
                        </div>
                    </div>

                    <Button
                        v-if="!notification.read_at"
                        variant="ghost"
                        size="sm"
                        class="gap-1.5 text-xs"
                        @click="readNotification(notification)"
                    >
                        <Check class="h-3.5 w-3.5" />
                        Mark read
                    </Button>
                </CardContent>
            </Card>

            <Pagination :pagination="pagination" label="notifications" />
        </div>

        <Card v-else class="border-dashed">
            <CardContent class="py-12 text-center">
                <Bell class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                <h3 class="mb-1 text-lg font-semibold text-gray-900">
                    Nothing to see here
                </h3>
                <p class="text-gray-600">
                    You'll see approvals and alerts for this team here as they
                    come in.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
