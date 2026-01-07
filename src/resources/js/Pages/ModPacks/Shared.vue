<template>
    <Head :title="modPack.name" />
    <AppLayout>
        <div class="modpacks-content">
            <div class="modpacks-header">
                <div class="header-top">
                    <Link href="/" class="back-link">
                        {{ t("modpacks.shared.back") }}
                    </Link>
                </div>
                <div class="header-main">
                    <div class="header-left">
                        <h1 class="modpacks-title">{{ modPack.name }}</h1>
                        <p v-if="sharerName" class="sharer-name">
                            {{ t("modpacks.shared.by", { name: sharerName }) }}
                        </p>
                        <div class="version-info">
                            <p class="modpacks-subtitle">
                                {{ modPack.minecraft_version }} •
                                {{ modPack.software }}
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
                        <Button
                            v-if="isOwner"
                            tag="Link"
                            :href="`/mod-packs/${modPack.id}`"
                            variant="secondary"
                        >
                            {{ t("modpacks.shared.view_collection") }}
                        </Button>
                        <Button
                            v-else-if="isAuthenticated"
                            variant="primary"
                            :disabled="isAddingToCollection"
                            @click="addToCollection"
                        >
                            {{
                                isAddingToCollection
                                    ? t("modpacks.shared.adding")
                                    : t("modpacks.shared.add_collection")
                            }}
                        </Button>
                        <Button
                            v-else
                            tag="Link"
                            href="/login"
                            variant="primary"
                        >
                            {{ t("modpacks.shared.login_to_add") }}
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
                        </div>
                    </div>

                    <div v-if="modPack.items.length === 0" class="empty-state">
                        <p>{{ t("modpacks.shared.empty") }}</p>
                    </div>

                    <div v-else>
                        <div
                            v-if="selectedItems.size > 0"
                            class="bulk-actions-bar"
                        >
                            <div class="bulk-actions-info">
                                {{
                                    t("modpacks.show.selected_count", {
                                        count: selectedItems.size,
                                    })
                                }}
                            </div>
                            <div class="bulk-actions-buttons">
                                <Button
                                    variant="success"
                                    size="sm"
                                    :disabled="isDownloadingBulk"
                                    :class="{
                                        'btn-loading': isDownloadingBulk,
                                    }"
                                    @click="downloadBulkSelected"
                                >
                                    <span v-if="!isDownloadingBulk">{{
                                        t("modpacks.show.download_selected")
                                    }}</span>
                                    <span v-else class="loading-text">{{
                                        t("modpacks.show.downloading")
                                    }}</span>
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    @click="clearSelection"
                                >
                                    {{ t("modpacks.show.clear_selection") }}
                                </Button>
                            </div>
                        </div>

                        <div class="mods-list">
                            <div
                                v-for="(item, index) in modPack.items"
                                :key="item.id"
                                class="mod-item"
                                :class="{
                                    'mod-item-selected': selectedItems.has(
                                        item.id,
                                    ),
                                }"
                            >
                                <div class="mod-item-content">
                                    <div class="mod-item-checkbox">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedItems.has(item.id)
                                            "
                                            @change="
                                                toggleItemSelection(item.id)
                                            "
                                        />
                                    </div>
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
                                        :disabled="
                                            downloadingItems.has(item.id)
                                        "
                                        @click="downloadModItem(item)"
                                    >
                                        {{
                                            downloadingItems.has(item.id)
                                                ? "..."
                                                : t("modpacks.show.download")
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
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
    isOwner: {
        type: Boolean,
        default: false,
    },
    sharerName: {
        type: String,
        default: null,
    },
});

const page = usePage();
const isAuthenticated = computed(() => {
    return !!page.props.auth?.user;
});

const isAddingToCollection = ref(false);
const isDownloadingAll = ref(false);
const downloadingItems = ref(new Set());
const selectedItems = ref(new Set());
const isDownloadingBulk = ref(false);

const addToCollection = () => {
    if (!isAuthenticated.value) {
        router.visit("/login");
        return;
    }

    if (!confirm(t("modpacks.shared.add_confirm"))) {
        return;
    }

    isAddingToCollection.value = true;
    router.post(
        `/shared/${props.modPack.share_token}/add-to-collection`,
        {},
        {
            onSuccess: () => {
                // Success message is shown via redirect
            },
            onError: (errors) => {
                // eslint-disable-next-line no-console
                console.error("Error adding to collection:", errors);
                alert(t("modpacks.shared.add_failed"));
            },
            onFinish: () => {
                isAddingToCollection.value = false;
            },
        },
    );
};

const downloadModItem = async (item) => {
    if (!item.curseforge_mod_id || !item.curseforge_file_id) {
        alert(t("modpacks.show.download_info_missing"));
        return;
    }

    downloadingItems.value.add(item.id);

    try {
        // For shared modpacks, we need to get the download link differently
        // Since we don't have the mod pack ID in the route, we'll use the share token
        const response = await axios.get(
            `/shared/${props.modPack.share_token}/items/${item.id}/download-link`,
        );

        const downloadInfo = response.data.data;
        if (!downloadInfo || !downloadInfo.download_url) {
            throw new Error("Download URL not available");
        }

        // Use a simple anchor click to download
        const a = document.createElement("a");
        a.href = downloadInfo.download_url;
        a.target = "_blank";
        a.rel = "noopener noreferrer";
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            document.body.removeChild(a);
        }, 100);
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

// Helper function to download a file via proxy endpoint
const downloadFileViaProxy = async (url, shareToken) => {
    const proxyUrl = `/shared/${shareToken}/proxy-download?url=${encodeURIComponent(url)}`;

    const response = await fetch(proxyUrl, {
        method: "GET",
        credentials: "include",
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

        // Get all download links for shared modpack
        const response = await axios.get(
            `/shared/${props.modPack.share_token}/download-links`,
        );

        if (!response.data || !response.data.data) {
            throw new Error("Invalid response from server");
        }

        const downloadLinks = response.data.data;
        if (downloadLinks.length === 0) {
            alert(t("modpacks.show.no_download_info"));
            isDownloadingAll.value = false;
            return;
        }

        // Download all files in parallel
        const files = await Promise.all(
            downloadLinks.map(async (link) => {
                try {
                    if (!link.download_url || !link.mod_name) {
                        throw new Error("Missing download URL or mod name");
                    }

                    // Use proxy endpoint to bypass CORS restrictions
                    const blob = await downloadFileViaProxy(
                        link.download_url,
                        props.modPack.share_token,
                    );

                    if (blob.size === 0) {
                        throw new Error(`Downloaded file is empty`);
                    }

                    // Check if the blob is actually an error page
                    const slice = blob.slice(0, 1024);
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

                    return {
                        name: `${link.mod_name}_ERROR.txt`,
                        input: new Blob(
                            [
                                `Error: Failed to download ${link.mod_name}\n${error.message}\n\nURL: ${link.download_url || "N/A"}`,
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

        // Create ZIP file
        const zipBlob = await downloadZip(successfulFiles).blob();

        if (!zipBlob || zipBlob.size === 0) {
            throw new Error(t("modpacks.show.zip_failed"));
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

const toggleItemSelection = (itemId) => {
    if (selectedItems.value.has(itemId)) {
        selectedItems.value.delete(itemId);
    } else {
        selectedItems.value.add(itemId);
    }
};

const clearSelection = () => {
    selectedItems.value.clear();
};

const downloadBulkSelected = async () => {
    if (selectedItems.value.size === 0) {
        return;
    }

    const itemIds = Array.from(selectedItems.value);
    const selectedMods = props.modPack.items.filter((item) =>
        itemIds.includes(item.id),
    );

    if (selectedMods.length === 0) {
        return;
    }

    try {
        isDownloadingBulk.value = true;

        // Get download links for selected items
        const response = await axios.post(
            `/shared/${props.modPack.share_token}/bulk-download-links`,
            {
                item_ids: itemIds,
            },
        );

        if (!response.data || !response.data.data) {
            throw new Error("Invalid response from server");
        }

        const downloadLinks = response.data.data;
        if (downloadLinks.length === 0) {
            alert(t("modpacks.show.no_download_info"));
            isDownloadingBulk.value = false;
            return;
        }

        // Download all files in parallel
        const files = await Promise.all(
            downloadLinks.map(async (link) => {
                try {
                    if (!link.download_url || !link.mod_name) {
                        throw new Error("Missing download URL or mod name");
                    }

                    const blob = await downloadFileViaProxy(
                        link.download_url,
                        props.modPack.share_token,
                    );

                    if (blob.size === 0) {
                        throw new Error(`Downloaded file is empty`);
                    }

                    // Check if the blob is actually an error page
                    const slice = blob.slice(0, 1024);
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
                    // Return error file
                    return {
                        name: `${link.mod_name}_ERROR.txt`,
                        input: new Blob(
                            [
                                `Error: Failed to download ${link.mod_name}\n${error.message}\n\nURL: ${link.download_url || "N/A"}`,
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
            isDownloadingBulk.value = false;
            return;
        }

        // Warn if some files failed
        if (errorFiles.length > 0) {
            const successCount = files.length - errorFiles.length;
            const failCount = errorFiles.length;
            if (
                !confirm(
                    t("modpacks.show.download_partial_confirm", {
                        failCount,
                        successCount,
                    }),
                )
            ) {
                isDownloadingBulk.value = false;
                return;
            }
        }

        // Filter out error files if user chose to continue
        const successfulFiles = files.filter(
            (f) => !f.name.endsWith("_ERROR.txt"),
        );

        if (successfulFiles.length === 0) {
            alert(t("modpacks.show.no_successful_downloads"));
            isDownloadingBulk.value = false;
            return;
        }

        // Create ZIP file
        const zipBlob = await downloadZip(successfulFiles).blob();

        if (!zipBlob || zipBlob.size === 0) {
            throw new Error("Failed to create ZIP file");
        }

        // Trigger download
        const url = URL.createObjectURL(zipBlob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${props.modPack.name.replace(/[^a-z0-9]/gi, "_")}_selected_mods.zip`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        // Clear selection after successful download
        clearSelection();
    } catch (error) {
        console.error("Error downloading selected mods:", error);
        const errorMessage =
            error?.message || error?.toString() || "Unknown error";
        alert(t("modpacks.show.download_pack_failed", { error: errorMessage }));
    } finally {
        isDownloadingBulk.value = false;
    }
};

const getCurseForgeUrl = (slug) => {
    return `https://www.curseforge.com/minecraft/mc-mods/${slug}`;
};
</script>
