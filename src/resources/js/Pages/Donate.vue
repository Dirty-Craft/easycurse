<template>
    <Head :title="t('donate.title')" />
    <AppLayout>
        <div class="about-page">
            <section class="about-hero">
                <div class="container">
                    <div class="about-content">
                        <h1 class="about-title">
                            {{ t("donate.hero.title") }}
                        </h1>
                        <p class="about-subtitle">
                            {{ t("donate.hero.subtitle") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("donate.support.title") }}
                        </h2>
                        <p>
                            {{ t("donate.support.p1") }}
                        </p>
                        <p>
                            {{ t("donate.support.p2") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("donate.wallet.title") }}
                        </h2>
                        <p>
                            {{ t("donate.wallet.description") }}
                        </p>
                        <p>
                            <strong>{{
                                t("donate.wallet.address_label")
                            }}</strong>
                        </p>
                        <div
                            v-if="walletAddress"
                            class="wallet-address-container"
                        >
                            <p class="wallet-address">
                                {{ walletAddress }}
                            </p>
                            <Button
                                variant="secondary"
                                size="sm"
                                :disabled="isCopying"
                                class="copy-button"
                                @click="copyWalletAddress"
                            >
                                {{
                                    isCopying
                                        ? t("donate.wallet.copied")
                                        : t("donate.wallet.copy")
                                }}
                            </Button>
                        </div>
                        <p v-else class="wallet-address-missing">
                            {{ t("donate.wallet.address_missing") }}
                        </p>
                        <p>
                            {{ t("donate.wallet.note") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("donate.thank_you.title") }}
                        </h2>
                        <p>
                            {{ t("donate.thank_you.p1") }}
                        </p>
                        <p>
                            {{ t("donate.thank_you.p2") }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import AppLayout from "../Layouts/AppLayout.vue";
import Button from "../Components/Button.vue";
import { useTranslations } from "../composables/useTranslations";

const { t } = useTranslations();

const props = defineProps({
    walletAddress: {
        type: String,
        default: null,
    },
});

const isCopying = ref(false);

const copyWalletAddress = async () => {
    if (!props.walletAddress) {
        return;
    }

    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.walletAddress);
        } else {
            throw new Error("Clipboard API not available");
        }
        isCopying.value = true;
        setTimeout(() => {
            isCopying.value = false;
        }, 2000);
    } catch {
        // Fallback for older browsers
        const textArea = document.createElement("textarea");
        textArea.value = props.walletAddress;
        textArea.style.position = "fixed";
        textArea.style.opacity = "0";
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand("copy");
            isCopying.value = true;
            setTimeout(() => {
                isCopying.value = false;
            }, 2000);
        } catch {
            // Fallback failed
        }
        document.body.removeChild(textArea);
    }
};
</script>

<style scoped>
.wallet-address-container {
    display: flex;
    gap: var(--spacing-md);
    align-items: stretch;
    margin: var(--spacing-md) 0;
}

.wallet-address {
    font-family: monospace;
    font-size: 1rem;
    background: var(--color-background-light);
    padding: var(--spacing-md);
    border-radius: 8px;
    word-break: break-all;
    border: 1px solid var(--color-border);
    color: var(--color-text-primary);
    flex: 1;
    margin: 0;
    min-height: fit-content;
    display: flex;
    align-items: center;
}

.copy-button {
    flex-shrink: 0;
    white-space: nowrap;
    height: 100%;
    display: flex;
    align-items: center;
}

.wallet-address-missing {
    color: var(--color-text-secondary);
    font-style: italic;
    padding: var(--spacing-md);
}

@media (width <= 768px) {
    .wallet-address-container {
        flex-direction: column;
    }

    .copy-button {
        width: 100%;
    }
}
</style>
