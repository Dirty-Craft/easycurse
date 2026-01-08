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
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
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
