<script setup lang="ts">
import { http } from '@/lib/http';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type NotificationItem = {
    id: string;
    data: { type: string; title: string; message: string; action_url?: string };
    read_at: string | null;
    created_at: string;
};

const page = usePage();

// Seeded from the shared Inertia prop so the badge is correct on first
// paint (no round-trip needed just to show a number); refreshed for
// real from the server once the menu is actually opened.
const unreadCount = ref(
    Number((page.props.auth as { unread_notifications_count?: number }).unread_notifications_count ?? 0),
);
const notifications = ref<NotificationItem[]>([]);
const loading = ref(false);
const loaded = ref(false);

async function loadNotifications() {
    if (loaded.value) {
        return;
    }

    loading.value = true;

    try {
        const response = await http.get<{ notifications: NotificationItem[]; unread_count: number }>(
            route('admin.notifications.index'),
        );
        notifications.value = response.notifications;
        unreadCount.value = response.unread_count;
        loaded.value = true;
    } finally {
        loading.value = false;
    }
}

async function markAsRead(notification: NotificationItem) {
    if (notification.read_at) {
        return;
    }

    notification.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);

    await http.post<{ unread_count: number }>(route('admin.notifications.read', notification.id));
}

async function markAllAsRead() {
    notifications.value.forEach((notification) => {
        notification.read_at = notification.read_at ?? new Date().toISOString();
    });
    unreadCount.value = 0;

    await http.post<{ unread_count: number }>(route('admin.notifications.mark-all-read'));
}

const hasUnread = computed(() => unreadCount.value > 0);
</script>

<template>
    <v-menu @update:model-value="(open: boolean) => open && loadNotifications()">
        <template #activator="{ props: menuProps }">
            <v-btn v-bind="menuProps" icon variant="text">
                <v-badge :content="unreadCount" :model-value="hasUnread" color="error">
                    <v-icon icon="mdi-bell-outline" />
                </v-badge>
            </v-btn>
        </template>

        <v-card min-width="340" max-width="400">
            <v-card-title class="d-flex align-center justify-space-between">
                <span class="text-subtitle-1">Notifications</span>
                <v-btn
                    v-if="hasUnread"
                    variant="text"
                    size="small"
                    density="compact"
                    @click="markAllAsRead"
                >
                    Tout marquer comme lu
                </v-btn>
            </v-card-title>
            <v-divider />

            <v-list v-if="loading" density="compact">
                <v-list-item title="Chargement..." />
            </v-list>

            <v-list v-else-if="notifications.length === 0" density="compact">
                <v-list-item title="Aucune notification" />
            </v-list>

            <v-list v-else density="compact" class="notification-list">
                <v-list-item
                    v-for="notification in notifications"
                    :key="notification.id"
                    :title="notification.data.title"
                    :subtitle="notification.data.message"
                    :active="!notification.read_at"
                    lines="two"
                    @click="markAsRead(notification)"
                />
            </v-list>
        </v-card>
    </v-menu>
</template>

<style scoped>
.notification-list {
    max-height: 320px;
    overflow-y: auto;
}
</style>
