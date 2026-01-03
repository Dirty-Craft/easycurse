<template>
    <Head :title="t('modpacks.index.title')" />
    <AppLayout>
        <div class="modpacks-content">
            <div class="modpacks-header">
                <h1 class="modpacks-title">{{ t("modpacks.index.title") }}</h1>
                <p class="modpacks-subtitle">
                    {{ t("modpacks.index.subtitle") }}
                </p>
            </div>

            <div class="modpacks-main">
                <div class="mod-packs-actions">
                    <Button @click="showCreateModal = true">
                        {{ t("modpacks.index.create") }}
                    </Button>
                </div>

                <div v-if="modPacks.length === 0" class="modpacks-card">
                    <p class="modpacks-placeholder">
                        {{ t("modpacks.index.empty") }}
                    </p>
                </div>

                <div v-else class="mod-packs-grid">
                    <div
                        v-for="modPack in modPacks"
                        :key="modPack.id"
                        class="mod-pack-card"
                    >
                        <div class="mod-pack-header">
                            <h3 class="mod-pack-name">{{ modPack.name }}</h3>
                        </div>
                        <div class="mod-pack-info">
                            <div class="info-item">
                                <span class="info-label">{{
                                    t("modpacks.index.minecraft_version")
                                }}</span>
                                <span class="info-value">{{
                                    modPack.minecraft_version
                                }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">{{
                                    t("modpacks.index.software")
                                }}</span>
                                <span class="info-value">{{
                                    modPack.software
                                }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">{{
                                    t("modpacks.index.mods")
                                }}</span>
                                <span class="info-value">{{
                                    modPack.items.length
                                }}</span>
                            </div>
                        </div>
                        <p
                            v-if="modPack.description"
                            class="mod-pack-description"
                        >
                            {{ modPack.description }}
                        </p>
                        <div class="mod-pack-actions">
                            <Button
                                size="sm"
                                variant="primary"
                                @click="openShareModal(modPack)"
                            >
                                {{ t("modpacks.index.share") }}
                            </Button>
                            <Button
                                tag="Link"
                                :href="`/mod-packs/${modPack.id}`"
                                size="sm"
                                variant="secondary"
                            >
                                {{ t("modpacks.index.view") }}
                            </Button>
                            <Button
                                size="sm"
                                variant="danger"
                                @click="deleteModPack(modPack.id)"
                            >
                                {{ t("modpacks.index.delete") }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share Modal -->
        <Modal
            v-model:show="showShareModal"
            :title="t('modpacks.index.share_modal.title')"
            @close="closeShareModal"
        >
            <div class="share-modal-content">
                <p class="share-description">
                    {{ t("modpacks.index.share_modal.description") }}
                </p>
                <div class="share-link-container">
                    <Input
                        id="share-link"
                        :model-value="shareUrl"
                        type="text"
                        readonly
                        class="share-link-input"
                    />
                    <Button
                        variant="secondary"
                        :disabled="isCopying"
                        @click="copyShareLink"
                    >
                        {{
                            isCopying
                                ? t("modpacks.index.share_modal.copied")
                                : t("modpacks.index.share_modal.copy")
                        }}
                    </Button>
                </div>
                <div class="share-actions">
                    <Button
                        variant="danger"
                        size="sm"
                        :disabled="isRegenerating"
                        @click="regenerateShareToken"
                    >
                        {{
                            isRegenerating
                                ? t("modpacks.index.share_modal.regenerating")
                                : t("modpacks.index.share_modal.regenerate")
                        }}
                    </Button>
                    <p class="regenerate-warning">
                        {{ t("modpacks.index.share_modal.warning") }}
                    </p>
                </div>
            </div>
        </Modal>

        <!-- Create Modal -->
        <Modal
            v-model:show="showCreateModal"
            :title="t('modpacks.index.create_modal.title')"
        >
            <form @submit.prevent="createModPack">
                <FormGroup
                    :label="t('modpacks.index.create_modal.name')"
                    input-id="name"
                >
                    <Input id="name" v-model="form.name" type="text" required />
                </FormGroup>
                <FormGroup
                    :label="t('modpacks.index.create_modal.minecraft_version')"
                    input-id="minecraft_version"
                >
                    <Input
                        id="minecraft_version"
                        v-model="form.minecraft_version"
                        type="select"
                        required
                    >
                        <option value="" disabled>
                            {{
                                gameVersions.length === 0
                                    ? t(
                                          "modpacks.index.create_modal.no_versions",
                                      )
                                    : t(
                                          "modpacks.index.create_modal.select_version",
                                      )
                            }}
                        </option>
                        <option
                            v-for="version in gameVersions"
                            :key="version.id || version.name"
                            :value="version.name"
                        >
                            {{ version.name }}
                        </option>
                    </Input>
                </FormGroup>
                <FormGroup
                    :label="t('modpacks.index.create_modal.software')"
                    input-id="software"
                >
                    <Input
                        id="software"
                        v-model="form.software"
                        type="select"
                        required
                    >
                        <option value="" disabled>
                            {{
                                modLoaders.length === 0
                                    ? t(
                                          "modpacks.index.create_modal.no_loaders",
                                      )
                                    : t(
                                          "modpacks.index.create_modal.select_loader",
                                      )
                            }}
                        </option>
                        <option
                            v-for="loader in modLoaders"
                            :key="loader.id"
                            :value="loader.slug"
                        >
                            {{ loader.name }}
                        </option>
                    </Input>
                </FormGroup>
                <FormGroup
                    :label="t('modpacks.index.create_modal.description')"
                    input-id="description"
                >
                    <Input
                        id="description"
                        v-model="form.description"
                        type="textarea"
                        :rows="3"
                    />
                </FormGroup>
            </form>
            <template #footer>
                <Button variant="secondary" @click="showCreateModal = false">
                    {{ t("modpacks.index.create_modal.cancel") }}
                </Button>
                <Button @click="createModPack">
                    {{ t("modpacks.index.create_modal.create") }}
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { Head, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
import Input from "../../Components/Input.vue";
import FormGroup from "../../Components/FormGroup.vue";
import Modal from "../../Components/Modal.vue";
import axios from "axios";
import { useTranslations } from "../../composables/useTranslations";

const { t } = useTranslations();

defineProps({
    modPacks: Array,
    gameVersions: {
        type: Array,
        default: () => [],
    },
    modLoaders: {
        type: Array,
        default: () => [],
    },
});

const showCreateModal = ref(false);
const showShareModal = ref(false);
const selectedModPack = ref(null);
const shareUrl = ref("");
const isCopying = ref(false);
const isRegenerating = ref(false);
const form = ref({
    name: "",
    minecraft_version: "",
    software: "",
    description: "",
});

watch(showCreateModal, (isOpen) => {
    if (isOpen) {
        // Reset form when modal opens
        form.value = {
            name: "",
            minecraft_version: "",
            software: "",
            description: "",
        };
    }
});

const createModPack = () => {
    router.post("/mod-packs", form.value, {
        onSuccess: () => {
            showCreateModal.value = false;
            form.value = {
                name: "",
                minecraft_version: "",
                software: "",
                description: "",
            };
        },
    });
};

const deleteModPack = (id) => {
    if (confirm(t("modpacks.show.delete_confirm"))) {
        router.delete(`/mod-packs/${id}`);
    }
};

const openShareModal = async (modPack) => {
    selectedModPack.value = modPack;
    showShareModal.value = true;
    shareUrl.value = "";
    // Generate or get share token
    await generateShareToken();
};

const closeShareModal = () => {
    showShareModal.value = false;
    selectedModPack.value = null;
    shareUrl.value = "";
};

const generateShareToken = async () => {
    if (!selectedModPack.value) return;

    try {
        const response = await axios.post(
            `/mod-packs/${selectedModPack.value.id}/share`,
        );
        shareUrl.value = response.data.share_url;
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error generating share token:", error);
        alert(t("modpacks.show.generate_failed"));
    }
};

const regenerateShareToken = async () => {
    if (!selectedModPack.value) return;

    if (!confirm(t("modpacks.show.regenerate_confirm"))) {
        return;
    }

    isRegenerating.value = true;
    try {
        const response = await axios.post(
            `/mod-packs/${selectedModPack.value.id}/share`,
            { regenerate: true },
        );
        shareUrl.value = response.data.share_url;
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error regenerating share token:", error);
        alert(t("modpacks.show.regenerate_failed"));
    } finally {
        isRegenerating.value = false;
    }
};

const copyShareLink = async () => {
    if (!shareUrl.value) {
        await generateShareToken();
    }

    try {
        await navigator.clipboard.writeText(shareUrl.value);
        isCopying.value = true;
        setTimeout(() => {
            isCopying.value = false;
        }, 2000);
    } catch (error) {
        // Fallback for older browsers
        const input = document.getElementById("share-link");
        if (input) {
            input.select();
            document.execCommand("copy");
            isCopying.value = true;
            setTimeout(() => {
                isCopying.value = false;
            }, 2000);
        } else {
            alert(t("modpacks.show.copy_failed"));
        }
    }
};
</script>

<style scoped>
/* Styles moved to modpacks.css */

.share-modal-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.share-description {
    color: var(--color-text-secondary);
    font-size: 0.9375rem;
    margin: 0;
}

.share-link-container {
    display: flex;
    gap: var(--spacing-md);
    align-items: stretch;
}

.share-link-input {
    flex: 1;
}

.share-link-input :deep(input) {
    font-family: monospace;
    font-size: 0.875rem;
}

.share-actions {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
    margin-top: var(--spacing-md);
}

.regenerate-warning {
    color: var(--color-text-secondary);
    font-size: 0.8125rem;
    margin: 0;
}
</style>
