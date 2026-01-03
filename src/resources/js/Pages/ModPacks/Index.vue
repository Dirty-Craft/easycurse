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
                    <Link
                        v-for="modPack in modPacks"
                        :key="modPack.id"
                        :href="`/mod-packs/${modPack.id}`"
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
                    </Link>
                </div>
            </div>
        </div>

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
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
import Input from "../../Components/Input.vue";
import FormGroup from "../../Components/FormGroup.vue";
import Modal from "../../Components/Modal.vue";
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
</script>
