<template>
    <Head :title="t('auth.change.title')" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">{{ t("auth.change.title") }}</h1>
                        <p class="auth-subtitle">
                            {{ t("auth.change.subtitle") }}
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
                            :label="t('auth.change.current')"
                            input-id="current_password"
                            :error="form.errors.current_password"
                        >
                            <Input
                                id="current_password"
                                v-model="form.current_password"
                                type="password"
                                required
                                autofocus
                                autocomplete="current-password"
                            />
                        </FormGroup>

                        <FormGroup
                            :label="t('auth.change.new')"
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
                            :label="t('auth.change.confirm_new')"
                            input-id="password_confirmation"
                            :error="form.errors.password_confirmation"
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
                                    ? t("auth.change.changing")
                                    : t("auth.change.change")
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

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post("/change-password", {
        preserveScroll: true,
        onFinish: () =>
            form.reset("current_password", "password", "password_confirmation"),
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
</style>
