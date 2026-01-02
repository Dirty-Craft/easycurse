<template>
    <Head :title="modSet.name" />
    <DashboardLayout>
        <div class="dashboard-content">
            <div class="dashboard-header">
                <div class="header-top">
                    <Link href="/mod-sets" class="back-link">
                        ← Back to Mod Sets
                    </Link>
                </div>
                <div class="header-main">
                    <div class="header-left">
                        <h1 class="dashboard-title">{{ modSet.name }}</h1>
                        <p class="dashboard-subtitle">
                            {{ modSet.minecraft_version }} •
                            {{ modSet.software_label }}
                        </p>
                    </div>
                    <div class="header-actions">
                        <button
                            class="btn btn-secondary"
                            @click="showEditModal = true"
                        >
                            Edit
                        </button>
                        <button class="btn btn-danger" @click="deleteModSet">
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-main">
                <div v-if="modSet.description" class="dashboard-card">
                    <h3 class="section-title">Description</h3>
                    <p class="description-text">{{ modSet.description }}</p>
                </div>

                <div class="dashboard-card">
                    <div class="section-header">
                        <h3 class="section-title">Mods</h3>
                        <div class="section-actions">
                            <button
                                v-if="modSet.items.length > 0"
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

                    <div v-if="modSet.items.length === 0" class="empty-state">
                        <p>
                            No mods added yet. Add your first mod to get
                            started!
                        </p>
                    </div>

                    <div v-else class="mods-list">
                        <div
                            v-for="(item, index) in modSet.items"
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
        <div
            v-if="showEditModal"
            class="modal-overlay"
            @click="showEditModal = false"
        >
            <div class="modal-content" @click.stop>
                <div class="modal-header">
                    <h2>Edit Mod Set</h2>
                    <button class="modal-close" @click="showEditModal = false">
                        ×
                    </button>
                </div>
                <form class="modal-body" @submit.prevent="updateModSet">
                    <div class="form-group">
                        <label for="edit-name">Name</label>
                        <input
                            id="edit-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            class="form-input"
                        />
                    </div>
                    <div class="form-group">
                        <label for="edit-minecraft_version"
                            >Minecraft Version</label
                        >
                        <input
                            id="edit-minecraft_version"
                            v-model="editForm.minecraft_version"
                            type="text"
                            required
                            class="form-input"
                        />
                    </div>
                    <div class="form-group">
                        <label for="edit-software">Software</label>
                        <select
                            id="edit-software"
                            v-model="editForm.software"
                            required
                            class="form-input"
                        >
                            <option value="forge">Forge</option>
                            <option value="fabric">Fabric</option>
                        </select>
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
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            @click="showEditModal = false"
                        >
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Update
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
                                        modSet.minecraft_version
                                    }}</strong>
                                    ({{ modSet.software_label }})
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
                                <strong>{{ modSet.minecraft_version }}</strong>
                                ({{ modSet.software_label }}).
                            </p>
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
    </DashboardLayout>
</template>

<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import DashboardLayout from "../../Layouts/DashboardLayout.vue";
import axios from "axios";
import { downloadZip } from "client-zip";

const props = defineProps({
    modSet: Object,
});

const showEditModal = ref(false);
const showAddModModal = ref(false);
const addModStep = ref("search"); // 'search' or 'selectVersion'

const editForm = useForm({
    name: props.modSet.name,
    minecraft_version: props.modSet.minecraft_version,
    software: props.modSet.software.value,
    description: props.modSet.description || "",
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
            `/mod-sets/${props.modSet.id}/search-mods`,
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

    try {
        const response = await axios.get(
            `/mod-sets/${props.modSet.id}/mod-files`,
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
};

const addMod = () => {
    if (!selectedMod.value || !selectedFile.value) {
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

    form.post(`/mod-sets/${props.modSet.id}/items`, {
        onSuccess: () => {
            closeAddModModal();
        },
    });
};

const updateModSet = () => {
    editForm.put(`/mod-sets/${props.modSet.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const deleteModSet = () => {
    if (confirm("Are you sure you want to delete this mod set?")) {
        router.delete(`/mod-sets/${props.modSet.id}`);
    }
};

const deleteModItem = (itemId) => {
    if (confirm("Are you sure you want to remove this mod?")) {
        router.delete(`/mod-sets/${props.modSet.id}/items/${itemId}`);
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
            `/mod-sets/${props.modSet.id}/items/${item.id}/download-link`,
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

const downloadAllAsZip = async () => {
    try {
        if (
            !props.modSet ||
            !props.modSet.items ||
            props.modSet.items.length === 0
        ) {
            return;
        }

        isDownloadingAll.value = true;

        console.log("Fetching download links for mod set:", props.modSet.id);

        // Get all download links
        const response = await axios.get(
            `/mod-sets/${props.modSet.id}/download-links`,
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

        // Check for CORS issues - CurseForge CDN doesn't allow CORS from browsers
        // We need to inform the user about this limitation
        let corsWarningShown = false;

        // Fetch all files in parallel
        const files = await Promise.all(
            downloadLinks.map(async (link) => {
                try {
                    if (!link.download_url || !link.mod_name) {
                        throw new Error("Missing download URL or mod name");
                    }

                    console.log(
                        `Fetching ${link.mod_name} from ${link.download_url}`,
                    );

                    // Try to fetch with CORS
                    let fileResponse;
                    try {
                        fileResponse = await fetch(link.download_url, {
                            method: "GET",
                            mode: "cors",
                            credentials: "omit",
                        });
                    } catch (fetchError) {
                        // CORS error or network error
                        if (
                            fetchError.name === "TypeError" &&
                            fetchError.message.includes("Failed to fetch")
                        ) {
                            if (!corsWarningShown) {
                                corsWarningShown = true;
                                console.warn(
                                    "CORS error detected. CurseForge CDN blocks cross-origin requests.",
                                );
                            }
                            throw new Error(
                                `CORS blocked: Browser security prevents downloading this file directly. Use individual download buttons instead.`,
                            );
                        }
                        throw fetchError;
                    }

                    if (!fileResponse.ok) {
                        const errorText = await fileResponse
                            .text()
                            .catch(() => "Unable to read error");
                        console.error(
                            `Failed to fetch ${link.mod_name}:`,
                            fileResponse.status,
                            errorText,
                        );
                        throw new Error(
                            `Failed to fetch ${link.mod_name}: ${fileResponse.status} ${fileResponse.statusText}`,
                        );
                    }

                    const blob = await fileResponse.blob();
                    console.log(
                        `Successfully fetched ${link.mod_name}, size: ${blob.size} bytes`,
                    );

                    if (blob.size === 0) {
                        throw new Error(`Downloaded file is empty`);
                    }

                    return {
                        name: link.filename || `${link.mod_name}.jar`,
                        input: blob,
                    };
                } catch (error) {
                    console.error(`Error fetching ${link.mod_name}:`, error);
                    // Return a placeholder file with error message
                    return {
                        name: `${link.mod_name}_ERROR.txt`,
                        input: new Blob(
                            [
                                `Error: Failed to download ${link.mod_name}\n${error.message}\n\nURL: ${link.download_url || "N/A"}\n\nNote: CurseForge CDN blocks cross-origin requests. Individual downloads work, but ZIP creation requires server-side proxy.`,
                            ],
                            { type: "text/plain" },
                        ),
                    };
                }
            }),
        );

        // Check if all files failed due to CORS
        const errorFiles = files.filter((f) => f.name.endsWith("_ERROR.txt"));
        if (errorFiles.length === files.length && errorFiles.length > 0) {
            try {
                const firstError = errorFiles[0];
                const errorText = await new Response(firstError.input).text();
                if (errorText.includes("CORS blocked")) {
                    alert(
                        "Unable to create ZIP file: CurseForge CDN blocks cross-origin requests from browsers.\n\n" +
                            "This is a browser security restriction (CORS). To download all mods:\n" +
                            "1. Use the individual download buttons (they work fine)\n" +
                            "2. Or install a CORS browser extension\n\n" +
                            "Note: Server-side ZIP creation would work but is not implemented per your requirements.",
                    );
                    isDownloadingAll.value = false;
                    return;
                }
            } catch (e) {
                console.error("Error reading error file:", e);
            }
        }

        // Warn if some files failed
        if (errorFiles.length > 0 && errorFiles.length < files.length) {
            console.warn(
                `${errorFiles.length} out of ${files.length} files failed to download`,
            );
        }

        console.log("All files fetched, creating ZIP...");

        // Create ZIP file
        const zipBlob = await downloadZip(files).blob();
        console.log("ZIP created, size:", zipBlob.size);

        if (!zipBlob || zipBlob.size === 0) {
            throw new Error("Failed to create ZIP file");
        }

        // Trigger download
        const url = URL.createObjectURL(zipBlob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${props.modSet.name.replace(/[^a-z0-9]/gi, "_")}_mods.zip`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        console.log("ZIP download triggered");
    } catch (error) {
        console.error("Error downloading mod set:", error);
        const errorMessage =
            error?.message || error?.toString() || "Unknown error";
        alert(
            `Failed to download mod set: ${errorMessage}. Please check the console for details.`,
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
</style>
