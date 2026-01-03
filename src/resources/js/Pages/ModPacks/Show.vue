<template>
    <Head :title="modPack.name" />
    <AppLayout>
        <div class="modpacks-content">
            <div class="modpacks-header">
                <div class="header-top">
                    <Link href="/mod-packs" class="back-link">
                        {{ t("modpacks.show.back") }}
                    </Link>
                </div>
                <div class="header-main">
                    <div class="header-left">
                        <h1 class="modpacks-title">{{ modPack.name }}</h1>
                        <div class="version-info">
                            <p class="modpacks-subtitle">
                                {{ modPack.minecraft_version }} •
                                {{ modPack.software }}
                                <button
                                    class="btn-change-version"
                                    @click="showChangeVersionModal = true"
                                >
                                    {{ t("modpacks.show.change") }}
                                </button>
                            </p>
                            <p
                                v-if="modPack.description"
                                class="description-text"
                            >
                                {{ modPack.description }}
                            </p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <Button variant="primary" @click="openShareModal">
                            {{ t("modpacks.show.share") }}
                        </Button>
                        <Button variant="secondary" @click="openEditModal">
                            {{ t("modpacks.show.edit") }}
                        </Button>
                        <Button variant="danger" @click="deleteModPack">
                            {{ t("modpacks.show.delete") }}
                        </Button>
                    </div>
                </div>
            </div>

            <div class="modpacks-main">
                <div class="modpacks-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            {{ t("modpacks.show.mods") }}
                        </h3>
                        <div class="section-actions">
                            <Button
                                v-if="modPack.items.length > 0"
                                variant="success"
                                :class="{ 'btn-loading': isDownloadingAll }"
                                :disabled="isDownloadingAll"
                                @click="downloadAllAsZip"
                            >
                                <span v-if="!isDownloadingAll">{{
                                    t("modpacks.show.download_all")
                                }}</span>
                                <span v-else class="loading-text">{{
                                    t("modpacks.show.downloading")
                                }}</span>
                            </Button>
                            <Button @click="showAddModModal = true">
                                {{ t("modpacks.show.add_mod") }}
                            </Button>
                        </div>
                    </div>

                    <div v-if="modPack.items.length === 0" class="empty-state">
                        <p>
                            {{ t("modpacks.show.empty") }}
                        </p>
                    </div>

                    <div v-else class="mods-list">
                        <div
                            v-for="(item, index) in modPack.items"
                            :key="item.id"
                            class="mod-item"
                        >
                            <div class="mod-item-content">
                                <div class="mod-item-number">
                                    {{ index + 1 }}
                                </div>
                                <div class="mod-item-info">
                                    <div class="mod-item-name">
                                        {{ item.mod_name }}
                                        <a
                                            v-if="item.curseforge_slug"
                                            :href="
                                                getCurseForgeUrl(
                                                    item.curseforge_slug,
                                                )
                                            "
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="curseforge-link"
                                            @click.stop
                                        >
                                            <svg
                                                class="curseforge-icon"
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="16"
                                                height="16"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path
                                                    d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"
                                                ></path>
                                                <polyline
                                                    points="15 3 21 3 21 9"
                                                ></polyline>
                                                <line
                                                    x1="10"
                                                    y1="14"
                                                    x2="21"
                                                    y2="3"
                                                ></line>
                                            </svg>
                                        </a>
                                    </div>
                                    <div class="mod-item-version">
                                        {{ item.mod_version }}
                                    </div>
                                </div>
                            </div>
                            <div class="mod-item-actions">
                                <Button
                                    v-if="
                                        item.curseforge_mod_id &&
                                        item.curseforge_file_id
                                    "
                                    size="sm"
                                    variant="success"
                                    :disabled="downloadingItems.has(item.id)"
                                    @click="downloadModItem(item)"
                                >
                                    {{
                                        downloadingItems.has(item.id)
                                            ? "..."
                                            : t("modpacks.show.download")
                                    }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="danger"
                                    @click="deleteModItem(item.id)"
                                >
                                    {{ t("modpacks.show.remove") }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Modal
            v-model:show="showEditModal"
            :title="t('modpacks.show.edit_modal.title')"
            @close="closeEditModal"
        >
            <form @submit.prevent="handleUpdateModPack">
                <FormGroup
                    :label="t('modpacks.index.create_modal.name')"
                    input-id="edit-name"
                    :error="editForm.errors.name"
                >
                    <Input
                        id="edit-name"
                        v-model="editForm.name"
                        type="text"
                        required
                        :input-class="{
                            'form-input-error': editForm.errors.name,
                        }"
                    />
                </FormGroup>
                <FormGroup
                    :label="t('modpacks.index.create_modal.description')"
                    input-id="edit-description"
                    :error="editForm.errors.description"
                >
                    <Input
                        id="edit-description"
                        v-model="editForm.description"
                        type="textarea"
                        :rows="3"
                    />
                </FormGroup>
            </form>
            <template #footer>
                <Button
                    variant="secondary"
                    :disabled="editForm.processing"
                    @click="closeEditModal"
                >
                    {{ t("modpacks.show.edit_modal.cancel") }}
                </Button>
                <Button
                    :disabled="editForm.processing"
                    @click="handleUpdateModPack"
                >
                    {{
                        editForm.processing
                            ? t("modpacks.show.edit_modal.updating")
                            : t("modpacks.show.edit_modal.update")
                    }}
                </Button>
            </template>
        </Modal>

        <!-- Change Version Modal -->
        <Modal
            v-model:show="showChangeVersionModal"
            :title="t('modpacks.show.version_modal.title')"
        >
            <form @submit.prevent="updateModPackVersion">
                <div class="version-change-notice">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <p v-html="t('modpacks.show.version_modal.note')"></p>
                </div>
                <div
                    v-if="versionForm.errors.version_change"
                    class="error-message"
                >
                    <p>{{ versionForm.errors.version_change }}</p>
                </div>
                <FormGroup
                    :label="t('modpacks.index.create_modal.minecraft_version')"
                    input-id="change-minecraft_version"
                >
                    <Input
                        id="change-minecraft_version"
                        v-model="versionForm.minecraft_version"
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
                    input-id="change-software"
                >
                    <Input
                        id="change-software"
                        v-model="versionForm.software"
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
            </form>
            <template #footer>
                <Button
                    variant="secondary"
                    @click="showChangeVersionModal = false"
                >
                    {{ t("modpacks.show.edit_modal.cancel") }}
                </Button>
                <Button
                    :disabled="versionForm.processing"
                    @click="updateModPackVersion"
                >
                    {{
                        versionForm.processing
                            ? t("modpacks.show.version_modal.creating")
                            : t("modpacks.show.edit_modal.update")
                    }}
                </Button>
            </template>
        </Modal>

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

        <!-- Add Mod Modal -->
        <Modal
            v-model:show="showAddModModal"
            :title="t('modpacks.show.add_modal.title')"
            size="large"
            @close="closeAddModModal"
        >
            <!-- Step 1: Search for Mod -->
            <div v-if="addModStep === 'search'" class="add-mod-step">
                <FormGroup
                    :label="t('modpacks.show.add_modal.search')"
                    input-id="mod-search"
                >
                    <div class="search-input-wrapper">
                        <Input
                            id="mod-search"
                            v-model="modSearchQuery"
                            type="text"
                            :placeholder="
                                t('modpacks.show.add_modal.search_placeholder')
                            "
                            @input="debouncedSearch"
                            @paste="handlePaste"
                        />
                        <div v-if="isSearching" class="search-loading">
                            {{ t("modpacks.show.add_modal.searching") }}
                        </div>
                    </div>
                </FormGroup>

                <div v-if="modSearchResults.length > 0" class="search-results">
                    <div class="search-results-header">
                        <p class="search-results-count">
                            {{
                                t("modpacks.show.add_modal.found", {
                                    count: modSearchResults.length,
                                })
                            }}
                        </p>
                    </div>
                    <div class="mod-results-list">
                        <div
                            v-for="mod in modSearchResults"
                            :key="mod.id"
                            class="mod-result-item"
                            @click="selectMod(mod)"
                        >
                            <div class="mod-result-content">
                                <div class="mod-result-name">
                                    {{ mod.name }}
                                    <a
                                        v-if="mod.slug"
                                        :href="getCurseForgeUrl(mod.slug)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="curseforge-link"
                                        @click.stop
                                    >
                                        <svg
                                            class="curseforge-icon"
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="16"
                                            height="16"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path
                                                d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"
                                            ></path>
                                            <polyline
                                                points="15 3 21 3 21 9"
                                            ></polyline>
                                            <line
                                                x1="10"
                                                y1="14"
                                                x2="21"
                                                y2="3"
                                            ></line>
                                        </svg>
                                    </a>
                                </div>
                                <div class="mod-result-meta">
                                    <span class="mod-result-slug">
                                        {{ mod.slug }}
                                    </span>
                                    <span
                                        v-if="mod.downloadCount"
                                        class="mod-result-downloads"
                                    >
                                        {{ formatDownloads(mod.downloadCount) }}
                                        downloads
                                    </span>
                                </div>
                            </div>
                            <div class="mod-result-arrow">→</div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="
                        modSearchQuery &&
                        !isSearching &&
                        modSearchResults.length === 0 &&
                        searchPerformed
                    "
                    class="search-no-results"
                >
                    <p>{{ t("modpacks.show.add_modal.no_results") }}</p>
                </div>

                <div class="modal-footer">
                    <Button variant="secondary" @click="closeAddModModal">
                        {{ t("modpacks.show.edit_modal.cancel") }}
                    </Button>
                </div>
            </div>

            <!-- Step 2: Select Version -->
            <div v-if="addModStep === 'selectVersion'" class="add-mod-step">
                <div class="selected-mod-info">
                    <h3 class="selected-mod-name">
                        {{ selectedMod.name }}
                        <a
                            v-if="selectedMod.slug"
                            :href="getCurseForgeUrl(selectedMod.slug)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="curseforge-link"
                            @click.stop
                        >
                            <svg
                                class="curseforge-icon"
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"
                                ></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    </h3>
                    <p class="selected-mod-slug">
                        {{ selectedMod.slug }}
                    </p>
                </div>

                <div v-if="isLoadingFiles" class="loading-files">
                    <p>{{ t("modpacks.show.add_modal.loading") }}</p>
                </div>

                <div v-else-if="modFiles.length > 0" class="files-list">
                    <div class="files-list-header">
                        <p>
                            {{
                                t("modpacks.show.add_modal.select_version", {
                                    version: modPack.minecraft_version,
                                    software: modPack.software,
                                })
                            }}
                        </p>
                    </div>
                    <div class="files-list-items">
                        <div
                            v-for="file in modFiles"
                            :key="file.id"
                            class="file-item"
                            :class="{
                                'file-item-selected':
                                    selectedFile?.id === file.id,
                            }"
                            @click="selectFile(file)"
                        >
                            <div class="file-item-content">
                                <div class="file-item-name">
                                    {{ file.displayName || file.fileName }}
                                </div>
                                <div class="file-item-meta">
                                    <span class="file-item-date">
                                        {{ formatDate(file.fileDate) }}
                                    </span>
                                    <span
                                        v-if="file.fileLength"
                                        class="file-item-size"
                                    >
                                        {{ formatFileSize(file.fileLength) }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-if="selectedFile?.id === file.id"
                                class="file-item-check"
                            >
                                ✓
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="no-files">
                    <p>
                        {{
                            t("modpacks.show.add_modal.no_compatible", {
                                version: modPack.minecraft_version,
                                software: modPack.software,
                            })
                        }}
                    </p>
                </div>

                <div v-if="addModError" class="error-message">
                    <p>{{ addModError }}</p>
                </div>

                <div class="modal-footer">
                    <Button variant="secondary" @click="addModStep = 'search'">
                        {{ t("modpacks.show.add_modal.back") }}
                    </Button>
                    <Button :disabled="!selectedFile" @click="addMod">
                        {{ t("modpacks.show.add_modal.add") }}
                    </Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
import Input from "../../Components/Input.vue";
import FormGroup from "../../Components/FormGroup.vue";
import Modal from "../../Components/Modal.vue";
import axios from "axios";
import { downloadZip } from "client-zip";
import { useTranslations } from "../../composables/useTranslations";

const { t } = useTranslations();

const props = defineProps({
    modPack: Object,
    gameVersions: {
        type: Array,
        default: () => [],
    },
    modLoaders: {
        type: Array,
        default: () => [],
    },
});

const showEditModal = ref(false);
const showAddModModal = ref(false);
const showChangeVersionModal = ref(false);
const showShareModal = ref(false);
const addModStep = ref("search"); // 'search' or 'selectVersion'
const shareUrl = ref("");
const isCopying = ref(false);
const isRegenerating = ref(false);

const editForm = useForm({
    name: props.modPack.name,
    description: props.modPack.description || "",
});

const versionForm = useForm({
    minecraft_version: props.modPack.minecraft_version,
    software: props.modPack.software,
});

const openEditModal = () => {
    editForm.name = props.modPack.name;
    editForm.description = props.modPack.description || "";
    editForm.clearErrors();
    showEditModal.value = true;
};

const openShareModal = async () => {
    showShareModal.value = true;
    // Generate or get share token
    if (!shareUrl.value) {
        await generateShareToken();
    }
};

const closeShareModal = () => {
    showShareModal.value = false;
};

const generateShareToken = async () => {
    try {
        const response = await axios.post(
            `/mod-packs/${props.modPack.id}/share`,
        );
        shareUrl.value = response.data.share_url;
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error generating share token:", error);
        alert(t("modpacks.show.generate_failed"));
    }
};

const regenerateShareToken = async () => {
    if (!confirm(t("modpacks.show.regenerate_confirm"))) {
        return;
    }

    isRegenerating.value = true;
    try {
        const response = await axios.post(
            `/mod-packs/${props.modPack.id}/share`,
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
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(shareUrl.value);
        } else {
            throw new Error("Clipboard API not available");
        }
        isCopying.value = true;
        setTimeout(() => {
            isCopying.value = false;
        }, 2000);
    } catch {
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

const closeEditModal = () => {
    showEditModal.value = false;
    editForm.clearErrors();
};

watch(showChangeVersionModal, (isOpen) => {
    if (isOpen) {
        // Update form with current values when modal opens
        versionForm.minecraft_version = props.modPack.minecraft_version;
        versionForm.software = props.modPack.software;
        // Clear any previous errors
        versionForm.clearErrors();
    }
});

const modSearchQuery = ref("");
const modSearchResults = ref([]);
const isSearching = ref(false);
const searchPerformed = ref(false);
const selectedMod = ref(null);
const modFiles = ref([]);
const isLoadingFiles = ref(false);
const selectedFile = ref(null);
const isDownloadingAll = ref(false);
const downloadingItems = ref(new Set());
const addModError = ref("");

let searchTimeout = null;

const debouncedSearch = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    if (modSearchQuery.value.length < 2) {
        modSearchResults.value = [];
        searchPerformed.value = false;
        return;
    }

    searchTimeout = setTimeout(() => {
        searchMods();
    }, 500);
};

// Watch for changes to modSearchQuery to handle paste events
// Must be defined after modSearchQuery and debouncedSearch
watch(modSearchQuery, () => {
    debouncedSearch();
});

const handlePaste = () => {
    // The watcher on modSearchQuery will handle triggering the search
    // after the v-model updates from the paste event
};

const searchMods = async () => {
    if (modSearchQuery.value.length < 2) {
        return;
    }

    isSearching.value = true;
    searchPerformed.value = true;

    try {
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/search-mods`,
            {
                params: {
                    query: modSearchQuery.value,
                },
            },
        );

        modSearchResults.value = response.data.data || [];
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error searching mods:", error);
        modSearchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

const selectMod = async (mod) => {
    selectedMod.value = mod;
    addModStep.value = "selectVersion";
    isLoadingFiles.value = true;
    modFiles.value = [];
    selectedFile.value = null;
    addModError.value = "";

    try {
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/mod-files`,
            {
                params: {
                    mod_id: mod.id,
                },
            },
        );

        modFiles.value = response.data.data || [];

        // Auto-select the latest file if available
        if (modFiles.value.length > 0) {
            selectedFile.value = modFiles.value[0];
        }
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error loading mod files:", error);
        modFiles.value = [];
    } finally {
        isLoadingFiles.value = false;
    }
};

const selectFile = (file) => {
    selectedFile.value = file;
};

const closeAddModModal = () => {
    showAddModModal.value = false;
    addModStep.value = "search";
    modSearchQuery.value = "";
    modSearchResults.value = [];
    searchPerformed.value = false;
    selectedMod.value = null;
    modFiles.value = [];
    selectedFile.value = null;
    addModError.value = "";
};

const addMod = () => {
    if (!selectedMod.value || !selectedFile.value) {
        return;
    }

    // Clear any previous errors
    addModError.value = "";

    // Check if mod is already in the mod pack
    const existingMod = props.modPack.items.find(
        (item) => item.curseforge_mod_id === selectedMod.value.id,
    );

    if (existingMod) {
        // Show error message
        addModError.value = t("modpacks.show.mod_already_added", {
            name: selectedMod.value.name,
        });
        return;
    }

    const form = useForm({
        mod_name: selectedMod.value.name,
        mod_version:
            selectedFile.value.displayName || selectedFile.value.fileName,
        curseforge_mod_id: selectedMod.value.id,
        curseforge_file_id: selectedFile.value.id,
        curseforge_slug: selectedMod.value.slug,
    });

    form.post(`/mod-packs/${props.modPack.id}/items`, {
        onSuccess: () => {
            closeAddModModal();
        },
        onError: (errors) => {
            // Handle backend validation errors
            if (errors.curseforge_mod_id) {
                addModError.value = errors.curseforge_mod_id;
            } else {
                addModError.value = t("modpacks.show.add_failed");
            }
        },
    });
};

const handleUpdateModPack = () => {
    editForm.put(`/mod-packs/${props.modPack.id}`, {
        onSuccess: () => {
            closeEditModal();
        },
        onError: () => {
            // Errors are displayed in the form
        },
    });
};

const updateModPackVersion = () => {
    versionForm.post(`/mod-packs/${props.modPack.id}/change-version`, {
        onSuccess: () => {
            showChangeVersionModal.value = false;
            // The backend will redirect to the new mod pack
        },
        onError: (errors) => {
            // Errors are already displayed in the form
            // eslint-disable-next-line no-console
            console.error("Error changing version:", errors);
        },
    });
};

const deleteModPack = () => {
    if (confirm(t("modpacks.show.delete_confirm"))) {
        router.delete(`/mod-packs/${props.modPack.id}`);
    }
};

const deleteModItem = (itemId) => {
    if (confirm(t("modpacks.show.remove_confirm"))) {
        router.delete(`/mod-packs/${props.modPack.id}/items/${itemId}`);
    }
};

const formatDownloads = (count) => {
    if (count >= 1000000) {
        return (count / 1000000).toFixed(1) + "M";
    }
    if (count >= 1000) {
        return (count / 1000).toFixed(1) + "K";
    }
    return count.toString();
};

const formatDate = (dateString) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const formatFileSize = (bytes) => {
    if (!bytes) return "";
    if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + " MB";
    }
    if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + " KB";
    }
    return bytes + " B";
};

const getCurseForgeUrl = (slug) => {
    return `https://www.curseforge.com/minecraft/mc-mods/${slug}`;
};

const downloadModItem = async (item) => {
    if (!item.curseforge_mod_id || !item.curseforge_file_id) {
        alert(t("modpacks.show.download_info_missing"));
        return;
    }

    downloadingItems.value.add(item.id);

    try {
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/items/${item.id}/download-link`,
        );

        // eslint-disable-next-line no-console
        console.log("Download link response:", response.data);

        const downloadInfo = response.data.data;
        if (!downloadInfo || !downloadInfo.download_url) {
            // eslint-disable-next-line no-console
            console.error("Download info missing:", response.data);
            throw new Error("Download URL not available");
        }

        // eslint-disable-next-line no-console
        console.log("Downloading from URL:", downloadInfo.download_url);

        // CurseForge CDN blocks CORS, so we can't fetch the file via JavaScript
        // Use a simple anchor click - this will navigate to the file
        // CurseForge CDN should send Content-Disposition header to trigger download
        // If not, the file will open in a new tab and user can save it manually
        const a = document.createElement("a");
        a.href = downloadInfo.download_url;
        a.target = "_blank";
        a.rel = "noopener noreferrer";
        document.body.appendChild(a);
        a.click();
        // Small delay before removing to ensure click is processed
        setTimeout(() => {
            document.body.removeChild(a);
        }, 100);

        // eslint-disable-next-line no-console
        console.log("Download link opened for", item.mod_name);
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error downloading mod:", error);
        alert(
            t("modpacks.show.download_failed", {
                name: item.mod_name,
                error: error.message || "Unknown error",
            }),
        );
    } finally {
        downloadingItems.value.delete(item.id);
    }
};

// Helper function to download a file via our proxy endpoint (bypasses CORS)
const downloadFileViaProxy = async (url) => {
    // Use our server-side proxy to bypass CORS restrictions
    const proxyUrl = `/mod-packs/${props.modPack.id}/proxy-download?url=${encodeURIComponent(url)}`;

    const response = await fetch(proxyUrl, {
        method: "GET",
        credentials: "include", // Include cookies for auth
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(
            errorData.error ||
                `HTTP ${response.status}: ${response.statusText}`,
        );
    }

    const blob = await response.blob();
    return blob;
};

const downloadAllAsZip = async () => {
    try {
        if (
            !props.modPack ||
            !props.modPack.items ||
            props.modPack.items.length === 0
        ) {
            return;
        }

        isDownloadingAll.value = true;

        // eslint-disable-next-line no-console
        console.log("Fetching download links for mod pack:", props.modPack.id);

        // Get all download links
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/download-links`,
        );

        // eslint-disable-next-line no-console
        console.log("Download links response:", response.data);

        if (!response.data || !response.data.data) {
            throw new Error("Invalid response from server");
        }

        const downloadLinks = response.data.data;
        if (downloadLinks.length === 0) {
            alert(t("modpacks.show.no_download_info"));
            isDownloadingAll.value = false;
            return;
        }

        // eslint-disable-next-line no-console
        console.log(`Downloading ${downloadLinks.length} mods...`);

        // Download all files in parallel using XMLHttpRequest
        // This works better with mediafilez.forgecdn.net than fetch()
        const files = await Promise.all(
            downloadLinks.map(async (link) => {
                try {
                    if (!link.download_url || !link.mod_name) {
                        throw new Error("Missing download URL or mod name");
                    }

                    // eslint-disable-next-line no-console
                    console.log(
                        `Downloading ${link.mod_name} via proxy from ${link.download_url}`,
                    );

                    // Use our proxy endpoint to bypass CORS restrictions
                    const blob = await downloadFileViaProxy(link.download_url);

                    // eslint-disable-next-line no-console
                    console.log(
                        `Successfully downloaded ${link.mod_name}, size: ${blob.size} bytes`,
                    );

                    if (blob.size === 0) {
                        throw new Error(`Downloaded file is empty`);
                    }

                    // Check if the blob is actually an error page (HTML response)
                    // Read a small portion to check content type without corrupting the file
                    const slice = blob.slice(0, 1024); // First 1KB
                    const sliceText = await slice.text();
                    if (
                        sliceText.trim().startsWith("<") ||
                        sliceText.includes("<!DOCTYPE") ||
                        sliceText.includes("<html")
                    ) {
                        throw new Error(
                            `Received HTML instead of file (likely an error page)`,
                        );
                    }

                    return {
                        name: link.filename || `${link.mod_name}.jar`,
                        input: blob,
                    };
                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error(`Error downloading ${link.mod_name}:`, error);

                    // Return error file
                    return {
                        name: `${link.mod_name}_ERROR.txt`,
                        input: new Blob(
                            [
                                `Error: Failed to download ${link.mod_name}\n${error.message}\n\nURL: ${link.download_url || "N/A"}\n\nIf this persists, try using the individual download buttons instead.`,
                            ],
                            { type: "text/plain" },
                        ),
                    };
                }
            }),
        );

        // Check if all files failed
        const errorFiles = files.filter((f) => f.name.endsWith("_ERROR.txt"));
        if (errorFiles.length === files.length && errorFiles.length > 0) {
            alert(t("modpacks.show.download_error"));
            isDownloadingAll.value = false;
            return;
        }

        // Warn if some files failed
        if (errorFiles.length > 0) {
            const successCount = files.length - errorFiles.length;
            const failCount = errorFiles.length;
            // eslint-disable-next-line no-console
            console.warn(
                `${failCount} out of ${files.length} files failed to download`,
            );
            if (
                !confirm(
                    t("modpacks.show.download_partial_confirm", {
                        failCount,
                        successCount,
                    }),
                )
            ) {
                isDownloadingAll.value = false;
                return;
            }
        }

        // Filter out error files if user chose to continue
        const successfulFiles = files.filter(
            (f) => !f.name.endsWith("_ERROR.txt"),
        );

        if (successfulFiles.length === 0) {
            alert(t("modpacks.show.no_successful_downloads"));
            isDownloadingAll.value = false;
            return;
        }

        // eslint-disable-next-line no-console
        console.log(`Creating ZIP with ${successfulFiles.length} files...`);

        // Create ZIP file
        const zipBlob = await downloadZip(successfulFiles).blob();
        // eslint-disable-next-line no-console
        console.log("ZIP created, size:", zipBlob.size);

        if (!zipBlob || zipBlob.size === 0) {
            throw new Error("Failed to create ZIP file");
        }

        // Trigger download
        const url = URL.createObjectURL(zipBlob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${props.modPack.name.replace(/[^a-z0-9]/gi, "_")}_mods.zip`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        // eslint-disable-next-line no-console
        console.log("ZIP download triggered");
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error downloading mod pack:", error);
        const errorMessage =
            error?.message || error?.toString() || "Unknown error";
        alert(t("modpacks.show.download_pack_failed", { error: errorMessage }));
    } finally {
        isDownloadingAll.value = false;
    }
};
</script>

<style scoped>
.header-top {
    margin-bottom: var(--spacing-md);
}

.back-link {
    color: var(--color-text-secondary);
    text-decoration: none;
    font-size: 0.9375rem;
    transition: color var(--transition-base);
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.back-link:hover {
    color: var(--color-primary);
}

.header-main {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--spacing-lg);
    flex-wrap: wrap;
}

.header-left {
    flex: 1;
    min-width: 0;
}

.header-actions {
    display: flex;
    gap: var(--spacing-md);
}

.modpacks-subtitle {
    color: var(--color-text-secondary);
    font-size: 0.9375rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.version-info {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.btn-change-version {
    padding: var(--spacing-xs) var(--spacing-sm);
    background: var(--color-background-light);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--transition-base);
    margin-left: var(--spacing-xs);
}

.btn-change-version:hover {
    background: var(--color-surface-light);
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.section-actions {
    display: flex;
    gap: var(--spacing-md);
    align-items: center;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0 0 var(--spacing-md) 0;
}

.description-text {
    color: var(--color-text-secondary);
    line-height: 1.6;
    margin: 0;
}

.empty-state {
    text-align: center;
    padding: var(--spacing-4xl) 0;
    color: var(--color-text-secondary);
}

.mods-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.mod-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-lg);
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    transition: all var(--transition-base);
    gap: var(--spacing-md);
}

.mod-item:hover {
    border-color: var(--color-primary);
    background: var(--color-background-light);
}

.mod-item-content {
    display: flex;
    align-items: center;
    gap: var(--spacing-lg);
    flex: 1;
    min-width: 0;
}

.mod-item-number {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(
        135deg,
        var(--color-primary),
        var(--color-secondary)
    );
    color: white;
    border-radius: var(--radius-full);
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.mod-item-info {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.mod-item-name {
    font-size: 1.125rem;
    font-weight: 500;
    color: var(--color-text-primary);
    margin: 0 0 var(--spacing-xs) 0;
    word-break: break-word;
    overflow-wrap: break-word;
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.mod-item-version {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    word-break: break-word;
    overflow-wrap: break-word;
}

.mod-item-actions {
    display: flex;
    gap: var(--spacing-sm);
    align-items: center;
}

/* Button loading state - specific to this page */
.btn-loading {
    min-width: 180px;
}

.btn-loading .loading-text {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.btn-loading .loading-text::before {
    content: "";
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Page-specific styles */

.add-mod-step {
    min-height: 300px;
}

.search-input-wrapper {
    position: relative;
}

.search-loading {
    position: absolute;
    right: var(--spacing-md);
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-secondary);
    font-size: 0.875rem;
}

.search-results {
    margin-top: var(--spacing-lg);
    max-height: 400px;
    overflow-y: auto;
}

.search-results-header {
    margin-bottom: var(--spacing-md);
}

.search-results-count {
    color: var(--color-text-secondary);
    font-size: 0.875rem;
    margin: 0;
}

.mod-results-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.mod-result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-base);
}

.mod-result-item:hover {
    border-color: var(--color-primary);
    background: var(--color-background-light);
    transform: translateX(4px);
}

.mod-result-content {
    flex: 1;
}

.mod-result-name {
    font-weight: 500;
    color: var(--color-text-primary);
    margin-bottom: var(--spacing-xs);
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.mod-result-meta {
    display: flex;
    gap: var(--spacing-md);
    font-size: 0.875rem;
    color: var(--color-text-secondary);
}

.mod-result-slug {
    font-family: monospace;
}

.mod-result-downloads {
    color: var(--color-text-secondary);
}

.mod-result-arrow {
    color: var(--color-text-secondary);
    font-size: 1.25rem;
    transition: transform var(--transition-base);
}

.mod-result-item:hover .mod-result-arrow {
    transform: translateX(4px);
    color: var(--color-primary);
}

.curseforge-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-secondary);
    text-decoration: none;
    transition: all var(--transition-base);
    opacity: 0.6;
    flex-shrink: 0;
}

.curseforge-link:hover {
    color: var(--color-primary);
    opacity: 1;
    transform: translateY(-1px);
}

.curseforge-icon {
    width: 16px;
    height: 16px;
    display: block;
}

.search-no-results {
    text-align: center;
    padding: var(--spacing-2xl) 0;
    color: var(--color-text-secondary);
}

.selected-mod-info {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--color-border);
}

.selected-mod-info h3 {
    margin: 0 0 var(--spacing-xs) 0;
    color: var(--color-text-primary);
}

.selected-mod-name {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.selected-mod-slug {
    margin: 0;
    color: var(--color-text-secondary);
    font-family: monospace;
    font-size: 0.875rem;
}

.loading-files,
.no-files {
    text-align: center;
    padding: var(--spacing-2xl) 0;
    color: var(--color-text-secondary);
}

.files-list-header {
    margin-bottom: var(--spacing-md);
}

.files-list-header p {
    margin: 0;
    color: var(--color-text-secondary);
    font-size: 0.9375rem;
}

.files-list-items {
    max-height: 400px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-base);
}

.file-item:hover {
    border-color: var(--color-primary);
    background: var(--color-background-light);
}

.file-item-selected {
    border-color: var(--color-primary);
    background: var(--color-background-light);
    box-shadow: 0 0 0 2px rgb(0 217 255 / 20%);
}

.file-item-content {
    flex: 1;
}

.file-item-name {
    font-weight: 500;
    color: var(--color-text-primary);
    margin-bottom: var(--spacing-xs);
}

.file-item-meta {
    display: flex;
    gap: var(--spacing-md);
    font-size: 0.875rem;
    color: var(--color-text-secondary);
}

.file-item-check {
    color: var(--color-primary);
    font-size: 1.25rem;
    font-weight: bold;
}

.error-message {
    margin: var(--spacing-md) 0;
    padding: var(--spacing-md);
    background: rgb(239 68 68 / 10%);
    border: 1px solid var(--color-error);
    border-radius: var(--radius-md);
    color: var(--color-error);
    font-size: 0.875rem;
}

.error-message p {
    margin: 0;
}

.version-change-notice {
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-md);
    background: rgb(0 217 255 / 10%);
    border: 1px solid var(--color-primary);
    border-radius: var(--radius-md);
    color: var(--color-text-primary);
    font-size: 0.875rem;
    line-height: 1.6;
}

.version-change-notice p {
    margin: 0;
}

.version-change-notice strong {
    color: var(--color-primary);
}

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

/* Responsive styles for mobile */
@media (width <= 768px) {
    .header-main {
        flex-direction: column;
        align-items: stretch;
    }

    .header-actions {
        flex-wrap: wrap;
        width: 100%;
    }

    .header-actions :deep(button) {
        flex: 1;
        min-width: 0;
    }

    .modpacks-subtitle {
        flex-wrap: wrap;
    }

    .section-header {
        flex-direction: column;
        align-items: stretch;
        gap: var(--spacing-md);
    }

    .section-actions {
        flex-wrap: wrap;
        width: 100%;
    }

    .section-actions :deep(button) {
        flex: 1;
        min-width: 0;
    }

    .mod-item {
        flex-direction: column;
        align-items: stretch;
        gap: var(--spacing-md);
    }

    .mod-item-content {
        flex-direction: row;
        align-items: flex-start;
        min-width: 0;
    }

    .mod-item-info {
        min-width: 0;
        flex: 1;
    }

    .mod-item-name {
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .mod-item-version {
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .mod-item-actions {
        width: 100%;
        justify-content: stretch;
    }

    .mod-item-actions :deep(button) {
        flex: 1;
        min-width: 0;
    }

    .share-link-container {
        flex-direction: column;
    }

    .share-link-container :deep(button) {
        width: 100%;
    }
}

@media (width <= 640px) {
    .mod-item-content {
        gap: var(--spacing-md);
    }

    .mod-item-number {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }

    .mod-item-name {
        font-size: 1rem;
    }

    .mod-item-version {
        font-size: 0.8125rem;
    }

    .header-actions {
        flex-direction: column;
    }

    .section-actions {
        flex-direction: column;
    }

    .mod-item-actions {
        flex-direction: column;
    }
}
</style>
