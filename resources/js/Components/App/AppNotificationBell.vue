<script setup lang="ts">
import { http } from '@/lib/http';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type NotificationItem = {
    id: string;
    data: { type: string; title: string; message: string; action_url?: string };
    read_at: string | null;
    created_at: string;
};

// One icon/color per notification `type` — extend as new notification
// types are introduced server-side, defaults to a neutral bell so an
// unknown future type never renders blank.
const TYPE_STYLE: Record<string, { icon: string; color: string }> = {
    manager_assigned: { icon: 'mdi-office-building-marker-outline', color: 'primary' },
};

function typeStyle(type: string) {
    return TYPE_STYLE[type] ?? { icon: 'mdi-bell-outline', color: 'secondary' };
}

function relativeTime(iso: string): string {
    const diffMs = Date.now() - new Date(iso).getTime();
    const minutes = Math.floor(diffMs / 60000);

    if (minutes < 1) {
        return "À l'instant";
    }
    if (minutes < 60) {
        return `Il y a ${minutes} min`;
    }
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return `Il y a ${hours} h`;
    }
    const days = Math.floor(hours / 24);
    if (days < 7) {
        return `Il y a ${days} j`;
    }

    return new Date(iso).toLocaleDateString();
}

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
const menuOpen = ref(false);

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
    if (!notification.read_at) {
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        await http.post<{ unread_count: number }>(route('admin.notifications.read', notification.id));
    }

    menuOpen.value = false;

    if (notification.data.action_url) {
        router.visit(notification.data.action_url);
    }
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
    <v-menu
        v-model="menuOpen"
        location="bottom end"
        offset="10"
        @update:model-value="(open: boolean) => open && loadNotifications()"
    >
        <template #activator="{ props: menuProps }">
            <v-btn v-bind="menuProps" icon variant="text">
                <v-badge :content="unreadCount" :model-value="hasUnread" color="error" location="top end">
                    <v-icon icon="mdi-bell-outline" />
                </v-badge>
            </v-btn>
        </template>

        <v-card min-width="360" max-width="400" elevation="8" rounded="lg" class="notif-card">
            <div class="d-flex align-center justify-space-between px-4 py-3">
                <span class="text-subtitle-1 font-weight-medium">Notifications</span>
                <v-btn
                    v-if="hasUnread"
                    variant="text"
                    size="small"
                    density="compact"
                    color="primary"
                    class="text-none"
                    @click="markAllAsRead"
                >
                    Tout marquer comme lu
                </v-btn>
            </div>
            <v-divider />

            <div v-if="loading" class="d-flex justify-center align-center py-8">
                <v-progress-circular indeterminate color="primary" size="28" />
            </div>

            <div v-else-if="notifications.length === 0" class="d-flex flex-column align-center text-center py-8 px-4">
                <v-icon icon="mdi-bell-sleep-outline" size="40" color="secondary" class="mb-2" />
                <span class="text-body-2 text-medium-emphasis">Aucune notification pour le moment</span>
            </div>

            <v-list v-else density="compact" class="notif-list py-0">
                <v-list-item
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="notif-item py-3"
                    :class="{ 'notif-item--unread': !notification.read_at }"
                    @click="markAsRead(notification)"
                >
                    <template #prepend>
                        <v-avatar :color="typeStyle(notification.data.type).color" variant="tonal" size="38">
                            <v-icon :icon="typeStyle(notification.data.type).icon" size="20" />
                        </v-avatar>
                    </template>

                    <v-list-item-title class="text-body-2 font-weight-medium d-flex align-center ga-2">
                        {{ notification.data.title }}
                        <span v-if="!notification.read_at" class="notif-dot" />
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-wrap text-body-2">
                        {{ notification.data.message }}
                    </v-list-item-subtitle>
                    <div class="text-caption text-medium-emphasis mt-1">
                        {{ relativeTime(notification.created_at) }}
                    </div>
                </v-list-item>
            </v-list>
        </v-card>
    </v-menu>
</template>

<style scoped>
.notif-card {
    overflow: hidden;
}

.notif-list {
    max-height: 360px;
    overflow-y: auto;
}

.notif-item {
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background-color 0.15s ease;
}

.notif-item:hover {
    background-color: rgba(var(--v-theme-primary), 0.06);
}

.notif-item--unread {
    border-left-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.04);
}

.notif-dot {
    width: 6px;
    height: 6px;
    min-width: 6px;
    border-radius: 50%;
    background-color: rgb(var(--v-theme-primary));
    display: inline-block;
}
</style>
