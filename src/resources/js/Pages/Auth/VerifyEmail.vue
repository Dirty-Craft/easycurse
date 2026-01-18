<template>
    <Head :title="t('auth.verify.title')" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">{{ t("auth.verify.title") }}</h1>
                        <p class="auth-subtitle">
                            {{ t("auth.verify.subtitle") }}
                        </p>
                    </div>

                    <div
                        v-if="status"
                        :key="status"
                        class="alert alert-success"
                    >
                        {{ status }}
                    </div>

                    <div class="auth-content">
                        <p class="auth-text">
                            {{
                                t("auth.verify.message", {
                                    email: user?.email || "",
                                })
                            }}
                        </p>
                        <p class="auth-text">
                            {{ t("auth.verify.check_spam") }}
                        </p>
                    </div>

                    <form class="auth-form" @submit.prevent="submit">
                        <Button type="submit" full :disabled="form.processing">
                            {{
                                form.processing
                                    ? t("auth.verify.resending")
                                    : t("auth.verify.resend")
                            }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            {{ t("auth.verify.logout_prompt") }}
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                class="auth-link-text"
                                >{{ t("layout.logout") }}</Link
                            >
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
import { useTranslations } from "../../composables/useTranslations";

const { t } = useTranslations();

const page = usePage();
const status = computed(() => page.props.flash?.status);
const user = computed(() => page.props.user);

const form = useForm({});

const submit = () => {
    form.post("/email/verification-notification", {
        preserveScroll: true,
    });
};
</script>
