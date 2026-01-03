<template>
    <Head :title="t('auth.profile.title')" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">
                            {{ t("auth.profile.title") }}
                        </h1>
                        <p class="auth-subtitle">
                            {{ t("auth.profile.subtitle") }}
                        </p>
                    </div>

                    <div
                        v-if="status"
                        :key="status"
                        class="alert alert-success"
                    >
                        {{ status }}
                    </div>

                    <form class="auth-form" @submit.prevent="submit">
                        <FormGroup
                            :label="t('auth.register.name')"
                            input-id="name"
                            :error="form.errors.name"
                        >
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                autocomplete="name"
                            />
                        </FormGroup>

                        <FormGroup
                            :label="t('auth.login.email')"
                            input-id="email"
                        >
                            <Input
                                id="email"
                                :value="user.email"
                                type="email"
                                disabled
                            />
                            <p class="field-hint">
                                {{ t("auth.profile.email_hint") }}
                            </p>
                        </FormGroup>

                        <Button type="submit" full :disabled="form.processing">
                            {{
                                form.processing
                                    ? t("auth.profile.updating")
                                    : t("auth.profile.update")
                            }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            <Link href="/mod-packs" class="auth-link">{{
                                t("auth.change.back")
                            }}</Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
import Input from "../../Components/Input.vue";
import FormGroup from "../../Components/FormGroup.vue";
import { useTranslations } from "../../composables/useTranslations";

const { t } = useTranslations();

const page = usePage();
const status = computed(() => page.props.flash?.status);
const user = page.props.user;

const form = useForm({
    name: user.name,
});

const submit = () => {
    form.put("/profile", {
        preserveScroll: true,
    });
};
</script>

<style scoped>
.alert {
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-radius: 0.375rem;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.field-hint {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--color-text-secondary);
}
</style>
