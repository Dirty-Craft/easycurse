<template>
    <Head title="My Mods" />
    <AppLayout>
        <div class="modpacks-content">
            <div class="modpacks-header">
                <h1 class="modpacks-title">My Mods</h1>
                <p class="modpacks-subtitle">Manage your mod packs</p>
            </div>

            <div class="modpacks-main">
                <div class="mod-packs-actions">
                    <Button @click="showCreateModal = true">
                        + Create Mod Pack
                    </Button>
                </div>

                <div v-if="modPacks.length === 0" class="modpacks-card">
                    <p class="modpacks-placeholder">
                        No mod packs yet. Create your first mod pack to get
                        started!
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
                            <div class="mod-pack-actions">
                                <Button
                                    size="sm"
                                    variant="primary"
                                    @click="openShareModal(modPack)"
                                >
                                    Share
                                </Button>
                                <Button
                                    tag="Link"
                                    :href="`/mod-packs/${modPack.id}`"
                                    size="sm"
                                    variant="secondary"
                                >
                                    View
                                </Button>
                                <Button
                                    size="sm"
                                    variant="danger"
                                    @click="deleteModPack(modPack.id)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </div>
                        <div class="mod-pack-info">
                            <div class="info-item">
                                <span class="info-label"
                                    >Minecraft Version:</span
                                >
                                <span class="info-value">{{
                                    modPack.minecraft_version
                                }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Software:</span>
                                <span class="info-value">{{
                                    modPack.software
                                }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Mods:</span>
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Share Modal -->
        <Modal
            v-model:show="showShareModal"
            title="Share Mod Pack"
            @close="closeShareModal"
        >
            <div class="share-modal-content">
                <p class="share-description">
                    Share this mod pack with others by sending them the link
                    below.
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
                        {{ isCopying ? "Copied!" : "Copy" }}
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
                                ? "Regenerating..."
                                : "Regenerate Link"
                        }}
                    </Button>
                    <p class="regenerate-warning">
                        Regenerating will expire the previous link.
                    </p>
                </div>
            </div>
        </Modal>

        <!-- Create Modal -->
        <Modal v-model:show="showCreateModal" title="Create Mod Pack">
            <form @submit.prevent="createModPack">
                <FormGroup label="Name" input-id="name">
                    <Input id="name" v-model="form.name" type="text" required />
                </FormGroup>
                <FormGroup
                    label="Minecraft Version"
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
                                    ? "No versions available"
                                    : "Select a Minecraft version"
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
                <FormGroup label="Software" input-id="software">
                    <Input
                        id="software"
                        v-model="form.software"
                        type="select"
                        required
                    >
                        <option value="" disabled>
                            {{
                                modLoaders.length === 0
                                    ? "No loaders available"
                                    : "Select a mod loader"
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
                    label="Description (optional)"
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
                    Cancel
                </Button>
                <Button @click="createModPack"> Create </Button>
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
    if (confirm("Are you sure you want to delete this mod pack?")) {
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
        alert("Failed to generate share link. Please try again.");
    }
};

const regenerateShareToken = async () => {
    if (!selectedModPack.value) return;

    if (
        !confirm(
            "Are you sure you want to regenerate the share link? The previous link will no longer work.",
        )
    ) {
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
        alert("Failed to regenerate share link. Please try again.");
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
            alert("Failed to copy link. Please copy it manually.");
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
