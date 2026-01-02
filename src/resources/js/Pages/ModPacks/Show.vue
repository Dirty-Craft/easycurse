<template>
    <Head :title="modPack.name" />
    <AppLayout>
        <div class="dashboard-content">
            <div class="dashboard-header">
                <div class="header-top">
                    <Link href="/mod-packs" class="back-link">
                        ← Back to Mod Packs
                    </Link>
                </div>
                <div class="header-main">
                    <div class="header-left">
                        <h1 class="dashboard-title">{{ modPack.name }}</h1>
                        <div class="version-info">
                            <p class="dashboard-subtitle">
                                {{ modPack.minecraft_version }} •
                                {{ modPack.software }}
                                <button
                                    class="btn-change-version"
                                    @click="showChangeVersionModal = true"
                                >
                                    Change
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
                        <button
                            class="btn btn-secondary"
                            @click="openEditModal"
                        >
                            Edit
                        </button>
                        <button class="btn btn-danger" @click="deleteModPack">
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-main">
                <div class="dashboard-card">
                    <div class="section-header">
                        <h3 class="section-title">Mods</h3>
                        <div class="section-actions">
                            <button
                                v-if="modPack.items.length > 0"
                                class="btn btn-success"
                                :class="{ 'btn-loading': isDownloadingAll }"
                                :disabled="isDownloadingAll"
                                @click="downloadAllAsZip"
                            >
                                <span v-if="!isDownloadingAll"
                                    >📦 Download All as ZIP</span
                                >
                                <span v-else class="loading-text"
                                    >Downloading...</span
                                >
                            </button>
                            <button
                                class="btn btn-primary"
                                @click="showAddModModal = true"
                            >
                                + Add Mod
                            </button>
                        </div>
                    </div>

                    <div v-if="modPack.items.length === 0" class="empty-state">
                        <p>
                            No mods added yet. Add your first mod to get
                            started!
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
                                    </div>
                                    <div class="mod-item-version">
                                        {{ item.mod_version }}
                                    </div>
                                </div>
                            </div>
                            <div class="mod-item-actions">
                                <button
                                    v-if="
                                        item.curseforge_mod_id &&
                                        item.curseforge_file_id
                                    "
                                    class="btn btn-sm btn-success"
                                    :disabled="downloadingItems.has(item.id)"
                                    @click="downloadModItem(item)"
                                >
                                    {{
                                        downloadingItems.has(item.id)
                                            ? "..."
                                            : "⬇️ Download"
                                    }}
                                </button>
                                <button
                                    class="btn btn-sm btn-danger"
                                    @click="deleteModItem(item.id)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
            <div class="modal-content" @click.stop>
                <div class="modal-header">
                    <h2>Edit Mod Pack</h2>
                    <button class="modal-close" @click="closeEditModal">
                        ×
                    </button>
                </div>
                <form class="modal-body" @submit.prevent="handleUpdateModPack">
                    <div class="form-group">
                        <label for="edit-name">Name</label>
                        <input
                            id="edit-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            class="form-input"
                            :class="{
                                'form-input-error': editForm.errors.name,
                            }"
                        />
                        <div v-if="editForm.errors.name" class="form-error">
                            {{ editForm.errors.name }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-description"
                            >Description (optional)</label
                        >
                        <textarea
                            id="edit-description"
                            v-model="editForm.description"
                            class="form-input"
                            rows="3"
                        ></textarea>
                        <div
                            v-if="editForm.errors.description"
                            class="form-error"
                        >
                            {{ editForm.errors.description }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            :disabled="editForm.processing"
                            @click="closeEditModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="editForm.processing"
                        >
                            {{ editForm.processing ? "Updating..." : "Update" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Version Modal -->
        <div
            v-if="showChangeVersionModal"
            class="modal-overlay"
            @click="showChangeVersionModal = false"
        >
            <div class="modal-content" @click.stop>
                <div class="modal-header">
                    <h2>Change Version</h2>
                    <button
                        class="modal-close"
                        @click="showChangeVersionModal = false"
                    >
                        ×
                    </button>
                </div>
                <form class="modal-body" @submit.prevent="updateModPackVersion">
                    <div class="version-change-notice">
                        <p>
                            <strong>Note:</strong> Changing the version will
                            create a <strong>new mod pack</strong> with the same
                            name plus "(Updated to X Y)". All mods will be
                            updated to versions compatible with the selected
                            Minecraft version and mod loader.
                        </p>
                    </div>
                    <div
                        v-if="versionForm.errors.version_change"
                        class="error-message"
                    >
                        <p>{{ versionForm.errors.version_change }}</p>
                    </div>
                    <div class="form-group">
                        <label for="change-minecraft_version"
                            >Minecraft Version</label
                        >
                        <select
                            id="change-minecraft_version"
                            v-model="versionForm.minecraft_version"
                            required
                            class="form-input"
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
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="change-software">Software</label>
                        <select
                            id="change-software"
                            v-model="versionForm.software"
                            required
                            class="form-input"
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
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            @click="showChangeVersionModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="versionForm.processing"
                        >
                            {{
                                versionForm.processing
                                    ? "Creating..."
                                    : "Create New Mod Pack"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Mod Modal -->
        <div
            v-if="showAddModModal"
            class="modal-overlay"
            @click="showAddModModal = false"
        >
            <div class="modal-content modal-content-large" @click.stop>
                <div class="modal-header">
                    <h2>Add Mod from CurseForge</h2>
                    <button class="modal-close" @click="closeAddModModal">
                        ×
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Search for Mod -->
                    <div v-if="addModStep === 'search'" class="add-mod-step">
                        <div class="form-group">
                            <label for="mod-search">Search for Mod</label>
                            <div class="search-input-wrapper">
                                <input
                                    id="mod-search"
                                    v-model="modSearchQuery"
                                    type="text"
                                    class="form-input"
                                    placeholder="Search by mod name or slug..."
                                    @input="debouncedSearch"
                                />
                                <div v-if="isSearching" class="search-loading">
                                    Searching...
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="modSearchResults.length > 0"
                            class="search-results"
                        >
                            <div class="search-results-header">
                                <p class="search-results-count">
                                    Found {{ modSearchResults.length }} mod(s)
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
                                        </div>
                                        <div class="mod-result-meta">
                                            <span class="mod-result-slug">
                                                {{ mod.slug }}
                                            </span>
                                            <span
                                                v-if="mod.downloadCount"
                                                class="mod-result-downloads"
                                            >
                                                {{
                                                    formatDownloads(
                                                        mod.downloadCount,
                                                    )
                                                }}
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
                            <p>No mods found. Try a different search term.</p>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                @click="closeAddModModal"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Select Version -->
                    <div
                        v-if="addModStep === 'selectVersion'"
                        class="add-mod-step"
                    >
                        <div class="selected-mod-info">
                            <h3>{{ selectedMod.name }}</h3>
                            <p class="selected-mod-slug">
                                {{ selectedMod.slug }}
                            </p>
                        </div>

                        <div v-if="isLoadingFiles" class="loading-files">
                            <p>Loading available versions...</p>
                        </div>

                        <div v-else-if="modFiles.length > 0" class="files-list">
                            <div class="files-list-header">
                                <p>
                                    Select a version for
                                    <strong>{{
                                        modPack.minecraft_version
                                    }}</strong>
                                    ({{ modPack.software }})
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
                                            {{
                                                file.displayName ||
                                                file.fileName
                                            }}
                                        </div>
                                        <div class="file-item-meta">
                                            <span class="file-item-date">
                                                {{ formatDate(file.fileDate) }}
                                            </span>
                                            <span
                                                v-if="file.fileLength"
                                                class="file-item-size"
                                            >
                                                {{
                                                    formatFileSize(
                                                        file.fileLength,
                                                    )
                                                }}
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
                                No compatible versions found for
                                <strong>{{ modPack.minecraft_version }}</strong>
                                ({{ modPack.software }}).
                            </p>
                        </div>

                        <div v-if="addModError" class="error-message">
                            <p>{{ addModError }}</p>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                @click="addModStep = 'search'"
                            >
                                ← Back
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="!selectedFile"
                                @click="addMod"
                            >
                                Add Mod
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import AppLayout from "../../Layouts/AppLayout.vue";
import axios from "axios";
import { downloadZip } from "client-zip";

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
const addModStep = ref("search"); // 'search' or 'selectVersion'

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
        addModError.value = `This mod (${selectedMod.value.name}) is already added to the mod pack.`;
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
                addModError.value = "Failed to add mod. Please try again.";
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
            console.error("Error changing version:", errors);
        },
    });
};

const deleteModPack = () => {
    if (confirm("Are you sure you want to delete this mod pack?")) {
        router.delete(`/mod-packs/${props.modPack.id}`);
    }
};

const deleteModItem = (itemId) => {
    if (confirm("Are you sure you want to remove this mod?")) {
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

const downloadModItem = async (item) => {
    if (!item.curseforge_mod_id || !item.curseforge_file_id) {
        alert("This mod does not have download information available.");
        return;
    }

    downloadingItems.value.add(item.id);

    try {
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/items/${item.id}/download-link`,
        );

        console.log("Download link response:", response.data);

        const downloadInfo = response.data.data;
        if (!downloadInfo || !downloadInfo.download_url) {
            console.error("Download info missing:", response.data);
            throw new Error("Download URL not available");
        }

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

        console.log("Download link opened for", item.mod_name);
    } catch (error) {
        console.error("Error downloading mod:", error);
        alert(
            `Failed to download ${item.mod_name}: ${error.message || "Unknown error"}. Please check the console for details.`,
        );
    } finally {
        downloadingItems.value.delete(item.id);
    }
};

// Helper function to download a file via our proxy endpoint (bypasses CORS)
const downloadFileViaProxy = async (url) => {
    try {
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
    } catch (error) {
        throw error;
    }
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

        console.log("Fetching download links for mod pack:", props.modPack.id);

        // Get all download links
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/download-links`,
        );

        console.log("Download links response:", response.data);

        if (!response.data || !response.data.data) {
            throw new Error("Invalid response from server");
        }

        const downloadLinks = response.data.data;
        if (downloadLinks.length === 0) {
            alert("No mods with download information available.");
            isDownloadingAll.value = false;
            return;
        }

        console.log(`Downloading ${downloadLinks.length} mods...`);

        // Download all files in parallel using XMLHttpRequest
        // This works better with mediafilez.forgecdn.net than fetch()
        const files = await Promise.all(
            downloadLinks.map(async (link) => {
                try {
                    if (!link.download_url || !link.mod_name) {
                        throw new Error("Missing download URL or mod name");
                    }

                    console.log(
                        `Downloading ${link.mod_name} via proxy from ${link.download_url}`,
                    );

                    // Use our proxy endpoint to bypass CORS restrictions
                    const blob = await downloadFileViaProxy(link.download_url);

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
            alert(
                "Unable to download any mods. This may be due to:\n" +
                    "1. Network connectivity issues\n" +
                    "2. CurseForge CDN restrictions\n" +
                    "3. Browser security settings\n\n" +
                    "Please try using the individual download buttons instead.",
            );
            isDownloadingAll.value = false;
            return;
        }

        // Warn if some files failed
        if (errorFiles.length > 0) {
            const successCount = files.length - errorFiles.length;
            const failCount = errorFiles.length;
            console.warn(
                `${failCount} out of ${files.length} files failed to download`,
            );
            if (
                !confirm(
                    `${failCount} mod(s) failed to download. Continue creating ZIP with ${successCount} successfully downloaded mod(s)?`,
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
            alert("No mods were successfully downloaded.");
            isDownloadingAll.value = false;
            return;
        }

        console.log(`Creating ZIP with ${successfulFiles.length} files...`);

        // Create ZIP file
        const zipBlob = await downloadZip(successfulFiles).blob();
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

        console.log("ZIP download triggered");
    } catch (error) {
        console.error("Error downloading mod pack:", error);
        const errorMessage =
            error?.message || error?.toString() || "Unknown error";
        alert(
            `Failed to download mod pack: ${errorMessage}. Please check the console for details.`,
        );
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
}

.header-actions {
    display: flex;
    gap: var(--spacing-md);
}

.dashboard-subtitle {
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
}

.mod-item-name {
    font-size: 1.125rem;
    font-weight: 500;
    color: var(--color-text-primary);
    margin: 0 0 var(--spacing-xs) 0;
}

.mod-item-version {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
}

.mod-item-actions {
    display: flex;
    gap: var(--spacing-sm);
    align-items: center;
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-success:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgb(16 185 129 / 30%);
}

.btn-success:disabled,
.btn-success.btn-loading {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
    position: relative;
}

.btn-success.btn-loading {
    min-width: 180px;
}

.btn-success.btn-loading .loading-text {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.btn-success.btn-loading .loading-text::before {
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

/* Modal Styles */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgb(0 0 0 / 60%);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: var(--spacing-lg);
}

.modal-content {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-xl);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-xl);
    border-bottom: 1px solid var(--color-border);
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--color-text-primary);
}

.modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    color: var(--color-text-secondary);
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    transition: all var(--transition-base);
}

.modal-close:hover {
    background: var(--color-background-light);
    color: var(--color-text-primary);
}

.modal-body {
    padding: var(--spacing-xl);
}

.form-group {
    margin-bottom: var(--spacing-lg);
}

.form-group label {
    display: block;
    margin-bottom: var(--spacing-sm);
    color: var(--color-text-primary);
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: var(--spacing-md);
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    color: var(--color-text-primary);
    font-size: 1rem;
    transition: all var(--transition-base);
}

.form-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgb(0 217 255 / 10%);
}

.form-input textarea {
    resize: vertical;
    min-height: 80px;
}

.form-input-error {
    border-color: var(--color-error);
}

.form-error {
    margin-top: var(--spacing-xs);
    font-size: 0.875rem;
    color: var(--color-error);
}

.form-help-text {
    margin-top: var(--spacing-xs);
    font-size: 0.875rem;
    color: var(--color-text-secondary);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-xl);
    border-top: 1px solid var(--color-border);
}

/* Button Styles */
.btn {
    padding: var(--spacing-sm) var(--spacing-lg);
    border-radius: var(--radius-md);
    font-weight: 500;
    font-size: 0.9375rem;
    cursor: pointer;
    transition: all var(--transition-base);
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary {
    background: linear-gradient(
        135deg,
        var(--color-primary),
        var(--color-secondary)
    );
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

.btn-secondary {
    background: var(--color-background-light);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border);
}

.btn-secondary:hover {
    background: var(--color-surface-light);
    border-color: var(--color-primary);
}

.btn-danger {
    background: var(--color-error);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-sm {
    padding: var(--spacing-xs) var(--spacing-md);
    font-size: 0.875rem;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.modal-content-large {
    max-width: 700px;
}

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
</style>
