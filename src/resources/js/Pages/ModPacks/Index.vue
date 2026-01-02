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
</script>

<style scoped>
/* Styles moved to modpacks.css */
</style>
