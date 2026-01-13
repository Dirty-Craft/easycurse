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

                    <div class="account-info-card">
                        <div class="account-info-header">
                            <div class="account-type-wrapper">
                                <div class="account-type-left">
                                    <span class="account-type-label">
                                        {{ t("auth.profile.account_type") }}
                                    </span>
                                    <span
                                        v-if="isPremium && premiumUntil"
                                        class="account-type-expiry"
                                    >
                                        {{
                                            t(
                                                "auth.profile.premium_remaining_days",
                                                {
                                                    days: getRemainingDays(
                                                        premiumUntil,
                                                    ),
                                                },
                                            )
                                        }}
                                    </span>
                                </div>
                                <span
                                    :class="[
                                        'account-type-badge',
                                        isPremium
                                            ? 'badge-premium'
                                            : 'badge-free',
                                    ]"
                                >
                                    {{
                                        isPremium
                                            ? t("auth.profile.premium")
                                            : t("auth.profile.free")
                                    }}
                                </span>
                            </div>
                        </div>

                        <div v-if="!isPremium" class="account-usage-section">
                            <div class="usage-header">
                                <span class="usage-label">
                                    {{ t("auth.profile.monthly_runs") }}
                                </span>
                                <span class="usage-count">
                                    {{ monthlyRunCount }} / 10
                                </span>
                            </div>
                            <div class="usage-progress">
                                <div
                                    class="usage-progress-bar"
                                    :style="{
                                        width: `${Math.min(
                                            (monthlyRunCount / 10) * 100,
                                            100,
                                        )}%`,
                                    }"
                                ></div>
                            </div>
                            <p class="usage-hint">
                                {{ t("auth.profile.monthly_runs_hint") }}
                            </p>
                        </div>

                        <div v-if="!isPremium" class="premium-cta-section">
                            <Button
                                tag="Link"
                                href="/premium"
                                variant="primary"
                                full
                            >
                                {{ t("auth.profile.upgrade_to_premium") }}
                            </Button>
                        </div>
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
const isPremium = computed(() => page.props.isPremium ?? false);
const monthlyRunCount = computed(() => page.props.monthlyRunCount ?? 0);
const premiumUntil = computed(() => page.props.premiumUntil ?? null);

const form = useForm({
    name: user.name,
});

const getRemainingDays = (dateString) => {
    if (!dateString) return 0;
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = date - now;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays);
};

const submit = () => {
    form.put("/profile", {
        preserveScroll: true,
    });
};
</script>
