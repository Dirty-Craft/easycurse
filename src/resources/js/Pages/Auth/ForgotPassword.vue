<template>
    <Head :title="t('auth.forgot.title')" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">{{ t("auth.forgot.title") }}</h1>
                        <p class="auth-subtitle">
                            {{ t("auth.forgot.subtitle") }}
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
                            :label="t('auth.login.email')"
                            input-id="email"
                            :error="form.errors.email"
                        >
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                            />
                        </FormGroup>

                        <Button type="submit" full :disabled="form.processing">
                            {{
                                form.processing
                                    ? t("auth.forgot.sending")
                                    : t("auth.forgot.send_link")
                            }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            {{ t("auth.forgot.remember") }}
                            <Link href="/login" class="auth-link">{{
                                t("auth.login.sign_in")
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

const form = useForm({
    email: "",
});

const submit = () => {
    form.post("/forgot-password", {
        preserveScroll: true,
    });
};
</script>
