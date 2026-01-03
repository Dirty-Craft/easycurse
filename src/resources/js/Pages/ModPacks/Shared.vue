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
        alert(
            t("modpacks.show.download_pack_failed", { error: errorMessage }),
        );
    } finally {
        isDownloadingAll.value = false;
    }
};

const getCurseForgeUrl = (slug) => {
    return `https://www.curseforge.com/minecraft/mc-mods/${slug}`;
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

.sharer-name {
    color: var(--color-text-secondary);
    font-size: 0.9375rem;
    margin: var(--spacing-xs) 0 0 0;
    font-style: italic;
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
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
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
</style>
