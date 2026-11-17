<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppModulePicker from '@/Components/App/AppModulePicker.vue';
import type { AppModule } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * A manager narrowing which modules one practitioner can use on the
 * center being worked on (null = every module of that center). The
 * practitioner's other centers are not affected.
 */
const props = defineProps<{
    account: { id: number; name: string; modules: AppModule[] | null } | null;
    /** Modules that can be granted at all: the ones the center has. */
    available: AppModule[];
}>();

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const form = useForm({ modules: null as AppModule[] | null });

watch(
    () => props.account,
    (account) => {
        form.clearErrors();
        form.modules = account?.modules ? [...account.modules] : null;
    },
    { immediate: true },
);

function submit() {
    if (!props.account) {
        return;
    }

    form.put(route('admin.users.center-modules.update', props.account.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <AppDialog
        :visible="account !== null"
        :header="account ? t('users.modulesFor', { name: account.name }) : ''"
        max-width="640px"
        @update:visible="emit('close')"
    >
        <form class="d-flex flex-column ga-4" @submit.prevent="submit">
            <div>
                <AppCheckbox
                    :model-value="form.modules !== null"
                    :label="t('users.restrict')"
                    @update:model-value="form.modules = $event ? [...available] : null"
                />
                <p class="text-caption text-medium-emphasis mb-3">{{ t('users.restrictCenterHint') }}</p>
                <AppModulePicker v-if="form.modules !== null" v-model="form.modules" :available="available" />
            </div>

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" :label="t('common.cancel')" severity="secondary" @click="emit('close')" />
                <AppButton type="submit" :label="t('common.save')" :loading="form.processing" />
            </div>
        </form>
    </AppDialog>
</template>
