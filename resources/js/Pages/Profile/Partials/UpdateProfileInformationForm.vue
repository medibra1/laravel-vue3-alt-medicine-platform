<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-h6 font-weight-bold">
                {{ $t('auth.profile.info') }}
            </h2>

            <p class="text-body-2 text-medium-emphasis mt-1">
                {{ $t('auth.profile.infoHint') }}
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="d-flex flex-column ga-4 mt-6"
        >
            <AppInputText
                id="name"
                v-model="form.name"
                :label="$t('common.name')"
                autofocus
                autocomplete="name"
                :error="form.errors.name"
            />

            <AppInputText
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('common.email')"
                autocomplete="username"
                :error="form.errors.email"
            />

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-body-2">
                    {{ $t('auth.profile.unverified') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-body-2 text-decoration-underline"
                    >
                        {{ $t('auth.profile.resend') }}
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="text-body-2 text-success font-weight-medium mt-2"
                >
                    {{ $t('auth.profile.verificationSent') }}
                </div>
            </div>

            <div class="d-flex align-center ga-4">
                <AppButton type="submit" :label="$t('common.save')" :loading="form.processing" />

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-body-2 text-medium-emphasis"
                    >
                        {{ $t('common.saved') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
