<template>
    <Head :title="t('auth.register.title')" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">
                            {{ t("auth.register.create") }}
                        </h1>
                        <p class="auth-subtitle">
                            {{ t("auth.register.subtitle") }}
                        </p>
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
                            :error="form.errors.email"
                        >
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                            />
                        </FormGroup>

                        <FormGroup
                            :label="t('auth.login.password')"
                            input-id="password"
                            :error="form.errors.password"
                        >
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                        </FormGroup>

                        <FormGroup
                            :label="t('auth.register.confirm_password')"
                            input-id="password_confirmation"
                        >
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                        </FormGroup>

                        <Button type="submit" full :disabled="form.processing">
                            {{
                                form.processing
                                    ? t("auth.register.creating")
                                    : t("auth.register.create")
                            }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            {{ t("auth.register.have_account") }}
                            <Link href="/login" class="auth-link">{{
                                t("auth.register.sign_in")
                            }}</Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
import Input from "../../Components/Input.vue";
import FormGroup from "../../Components/FormGroup.vue";
import { useTranslations } from "../../composables/useTranslations";

const { t } = useTranslations();

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post("/register", {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>
