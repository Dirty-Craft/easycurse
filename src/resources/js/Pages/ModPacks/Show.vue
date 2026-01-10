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
                                <Button
                                    variant="primary"
                                    @click="showChangeVersionModal = true"
                                >
                                    {{ t("modpacks.show.change") }}
                                </Button>
                            </p>
                            <div
                                v-if="
                                    modPack.minecraft_update_reminder_version &&
                                    modPack.minecraft_update_reminder_software
                                "
                                class="reminder-info"
                            >
                                <div class="reminder-content">
                                    <svg
                                        class="reminder-icon"
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
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline
                                            points="12 6 12 12 16 14"
                                        ></polyline>
                                    </svg>
                                    <span class="reminder-text">
                                        {{
                                            t("modpack.reminder_active", {
                                                version:
                                                    modPack.minecraft_update_reminder_version,
                                                software:
                                                    modPack.minecraft_update_reminder_software,
                                            })
                                        }}
                                    </span>
                                </div>
                                <Button
                                    variant="danger"
                                    size="sm"
                                    :disabled="isCancellingReminder"
                                    :class="{
                                        'btn-loading': isCancellingReminder,
                                    }"
                                    class="reminder-cancel-btn"
                                    @click="cancelReminder"
                                >
                                    {{
                                        isCancellingReminder
                                            ? t("modpacks.show.updating")
                                            : t("modpack.cancel_reminder")
                                    }}
                                </Button>
                            </div>
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
                        <Button variant="secondary" @click="duplicateModPack">
                            {{ t("modpacks.show.duplicate") }}
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
                                variant="primary"
                                :class="{ 'btn-loading': isUpdatingAll }"
                                :disabled="isUpdatingAll"
                                @click="updateAllToLatest"
                            >
                                <span v-if="!isUpdatingAll">{{
                                    t("modpacks.show.update_all_to_latest")
                                }}</span>
                                <span v-else class="loading-text">{{
                                    t("modpacks.show.updating")
                                }}</span>
                            </Button>
                            <div
                                v-if="modPack.items.length > 0"
                                class="export-dropdown"
                            >
                                <Button
                                    variant="success"
                                    :class="{ 'btn-loading': isExporting }"
                                    :disabled="isExporting || showExportMenu"
                                    @click="showExportMenu = !showExportMenu"
                                >
                                    <span v-if="!isExporting"
                                        >📦
                                        {{ t("modpacks.show.download") }}</span
                                    >
                                    <span v-else class="loading-text">{{
                                        t("modpacks.show.downloading")
                                    }}</span>
                                    <span class="dropdown-arrow">▼</span>
                                </Button>
                                <div
                                    v-if="showExportMenu"
                                    class="export-menu"
                                    @click.stop
                                >
                                    <button
                                        class="export-menu-item"
                                        @click="
                                            () => {
                                                downloadAllAsZip();
                                                showExportMenu = false;
                                            }
                                        "
                                    >
                                        {{ t("modpacks.show.export_zip") }}
                                    </button>
                                    <button
                                        class="export-menu-item"
                                        @click="exportModpack('curseforge')"
                                    >
                                        {{
                                            t("modpacks.show.export_curseforge")
                                        }}
                                    </button>
                                    <button
                                        class="export-menu-item"
                                        @click="exportModpack('multimc')"
                                    >
                                        {{ t("modpacks.show.export_multimc") }}
                                    </button>
                                    <button
                                        class="export-menu-item"
                                        @click="exportModpack('modrinth')"
                                    >
                                        {{ t("modpacks.show.export_modrinth") }}
                                    </button>
                                    <button
                                        class="export-menu-item"
                                        @click="exportModpack('text')"
                                    >
                                        {{ t("modpacks.show.export_text") }}
                                    </button>
                                    <button
                                        class="export-menu-item"
                                        @click="exportModpack('csv')"
                                    >
                                        {{ t("modpacks.show.export_csv") }}
                                    </button>
                                </div>
                            </div>
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

                    <div v-else>
                        <div class="mods-search-wrapper">
                            <div class="mods-search-input-container">
                                <svg
                                    class="mods-search-icon"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                                <Input
                                    id="mods-search"
                                    v-model="modsSearchQuery"
                                    type="text"
                                    :placeholder="
                                        t(
                                            'modpacks.show.search_mods_placeholder',
                                        )
                                    "
                                    class="mods-search-input"
                                />
                                <button
                                    v-if="modsSearchQuery"
                                    type="button"
                                    class="mods-search-clear"
                                    aria-label="Clear search"
                                    @click="clearSearch"
                                >
                                    <svg
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
                                        <line
                                            x1="18"
                                            y1="6"
                                            x2="6"
                                            y2="18"
                                        ></line>
                                        <line
                                            x1="6"
                                            y1="6"
                                            x2="18"
                                            y2="18"
                                        ></line>
                                    </svg>
                                </button>
                            </div>
                        </div>

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
                                    variant="primary"
                                    size="sm"
                                    :disabled="isUpdatingBulk"
                                    :class="{
                                        'btn-loading': isUpdatingBulk,
                                    }"
                                    @click="updateBulkToLatest"
                                >
                                    <span v-if="!isUpdatingBulk">{{
                                        t(
                                            "modpacks.show.update_selected_to_latest",
                                        )
                                    }}</span>
                                    <span v-else class="loading-text">{{
                                        t("modpacks.show.updating")
                                    }}</span>
                                </Button>
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
                                    variant="danger"
                                    size="sm"
                                    :disabled="isDeletingBulk"
                                    @click="deleteBulkSelected"
                                >
                                    {{ t("modpacks.show.delete_selected") }}
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

                        <div
                            v-if="filteredMods.length === 0"
                            class="empty-state"
                        >
                            <p>
                                {{ t("modpacks.show.no_search_results") }}
                            </p>
                        </div>

                        <div v-else class="mods-list">
                            <div
                                v-for="(item, index) in filteredMods"
                                :key="item.id"
                                class="mod-item"
                                :class="{
                                    'mod-item-selected': selectedItems.has(
                                        item.id,
                                    ),
                                    'mod-item-dragging':
                                        draggedItemId === item.id,
                                    'mod-item-drag-over':
                                        dragOverItemId === item.id,
                                }"
                                draggable="true"
                                @dragstart="
                                    handleDragStart($event, item.id, index)
                                "
                                @dragend="handleDragEnd"
                                @dragover.prevent="
                                    handleDragOver($event, item.id)
                                "
                                @dragleave="handleDragLeave"
                                @drop="handleDrop($event, item.id)"
                            >
                                <div class="mod-item-content">
                                    <div class="mod-item-drag-handle">
                                        <svg
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
                                            <circle
                                                cx="9"
                                                cy="12"
                                                r="1"
                                            ></circle>
                                            <circle
                                                cx="9"
                                                cy="5"
                                                r="1"
                                            ></circle>
                                            <circle
                                                cx="9"
                                                cy="19"
                                                r="1"
                                            ></circle>
                                            <circle
                                                cx="15"
                                                cy="12"
                                                r="1"
                                            ></circle>
                                            <circle
                                                cx="15"
                                                cy="5"
                                                r="1"
                                            ></circle>
                                            <circle
                                                cx="15"
                                                cy="19"
                                                r="1"
                                            ></circle>
                                        </svg>
                                    </div>
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
                                                v-if="
                                                    item.curseforge_slug ||
                                                    item.modrinth_slug
                                                "
                                                :href="getItemModUrl(item)"
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
                                            (item.curseforge_mod_id &&
                                                item.curseforge_file_id) ||
                                            (item.modrinth_project_id &&
                                                item.modrinth_version_id)
                                        "
                                        size="sm"
                                        variant="primary"
                                        @click="openUpdateModModal(item)"
                                    >
                                        {{ t("modpacks.show.update") }}
                                    </Button>
                                    <Button
                                        v-if="
                                            (item.curseforge_mod_id &&
                                                item.curseforge_file_id) ||
                                            (item.modrinth_project_id &&
                                                item.modrinth_version_id)
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
                    <Button
                        v-if="versionForm.errors.mods_without_version"
                        variant="secondary"
                        size="sm"
                        :disabled="isSettingReminder"
                        :class="{ 'btn-loading': isSettingReminder }"
                        class="reminder-button"
                        @click="setReminder"
                    >
                        {{
                            isSettingReminder
                                ? t("modpacks.show.updating")
                                : t("modpack.remind_me_once_available")
                        }}
                    </Button>
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
                                    <div class="mod-result-platform-logo">
                                        <img
                                            v-if="mod._source === 'curseforge'"
                                            class="platform-logo platform-logo-curseforge"
                                            src="https://static-beta.curseforge.com/images/favicon.ico"
                                            alt="CurseForge"
                                            title="CurseForge"
                                            @error="handleLogoError"
                                        />
                                        <img
                                            v-if="mod._source === 'modrinth'"
                                            class="platform-logo platform-logo-modrinth"
                                            src="https://cdn.modrinth.com/logo.svg"
                                            alt="Modrinth"
                                            title="Modrinth"
                                            @error="handleLogoError"
                                        />
                                    </div>
                                    <div class="mod-result-name-text">
                                        {{ mod.name || mod.title }}
                                        <a
                                            v-if="mod.slug"
                                            :href="getModUrl(mod)"
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
                        {{ selectedMod.name || selectedMod.title }}
                        <a
                            v-if="selectedMod.slug"
                            :href="getModUrl(selectedMod)"
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
                                    {{
                                        file.displayName ||
                                        file.fileName ||
                                        file.version_number ||
                                        file.name
                                    }}
                                </div>
                                <div class="file-item-meta">
                                    <span class="file-item-date">
                                        {{
                                            formatDate(
                                                file.fileDate ||
                                                    file.date_published,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            file.fileLength ||
                                            (file.files &&
                                                file.files[0] &&
                                                file.files[0].size)
                                        "
                                        class="file-item-size"
                                    >
                                        {{
                                            formatFileSize(
                                                file.fileLength ||
                                                    (file.files &&
                                                        file.files[0] &&
                                                        file.files[0].size),
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

        <!-- Update Mod Modal -->
        <Modal
            v-model:show="showUpdateModModal"
            :title="t('modpacks.show.update_modal.title')"
            size="large"
            @close="closeUpdateModModal"
        >
            <!-- Step 1: Search for Mod (pre-filled with current mod) -->
            <div v-if="updateModStep === 'search'" class="add-mod-step">
                <FormGroup
                    :label="t('modpacks.show.add_modal.search')"
                    input-id="update-mod-search"
                >
                    <div class="search-input-wrapper">
                        <Input
                            id="update-mod-search"
                            v-model="updateModSearchQuery"
                            type="text"
                            :placeholder="
                                t('modpacks.show.add_modal.search_placeholder')
                            "
                            @input="debouncedUpdateSearch"
                            @paste="handleUpdatePaste"
                        />
                        <div v-if="isUpdateSearching" class="search-loading">
                            {{ t("modpacks.show.add_modal.searching") }}
                        </div>
                    </div>
                </FormGroup>

                <div
                    v-if="updateModSearchResults.length > 0"
                    class="search-results"
                >
                    <div class="search-results-header">
                        <p class="search-results-count">
                            {{
                                t("modpacks.show.add_modal.found", {
                                    count: updateModSearchResults.length,
                                })
                            }}
                        </p>
                    </div>
                    <div class="mod-results-list">
                        <div
                            v-for="mod in updateModSearchResults"
                            :key="mod.id"
                            class="mod-result-item"
                            @click="selectUpdateMod(mod)"
                        >
                            <div class="mod-result-content">
                                <div class="mod-result-name">
                                    <div class="mod-result-platform-logo">
                                        <img
                                            v-if="mod._source === 'curseforge'"
                                            class="platform-logo platform-logo-curseforge"
                                            src="https://static-beta.curseforge.com/images/favicon.ico"
                                            alt="CurseForge"
                                            title="CurseForge"
                                            @error="handleLogoError"
                                        />
                                        <img
                                            v-if="mod._source === 'modrinth'"
                                            class="platform-logo platform-logo-modrinth"
                                            src="https://cdn.modrinth.com/logo.svg"
                                            alt="Modrinth"
                                            title="Modrinth"
                                            @error="handleLogoError"
                                        />
                                    </div>
                                    <div class="mod-result-name-text">
                                        {{ mod.name || mod.title }}
                                        <a
                                            v-if="mod.slug"
                                            :href="getModUrl(mod)"
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
                        updateModSearchQuery &&
                        !isUpdateSearching &&
                        updateModSearchResults.length === 0 &&
                        updateSearchPerformed
                    "
                    class="search-no-results"
                >
                    <p>{{ t("modpacks.show.add_modal.no_results") }}</p>
                </div>

                <div class="modal-footer">
                    <Button variant="secondary" @click="closeUpdateModModal">
                        {{ t("modpacks.show.edit_modal.cancel") }}
                    </Button>
                </div>
            </div>

            <!-- Step 2: Select Version -->
            <div v-if="updateModStep === 'selectVersion'" class="add-mod-step">
                <div class="selected-mod-info">
                    <h3 class="selected-mod-name">
                        {{ updateSelectedMod.name || updateSelectedMod.title }}
                        <a
                            v-if="updateSelectedMod.slug"
                            :href="getModUrl(updateSelectedMod)"
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
                        {{ updateSelectedMod.slug }}
                    </p>
                </div>

                <div v-if="isLoadingUpdateFiles" class="loading-files">
                    <p>{{ t("modpacks.show.add_modal.loading") }}</p>
                </div>

                <div v-else-if="updateModFiles.length > 0" class="files-list">
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
                            v-for="file in updateModFiles"
                            :key="file.id"
                            class="file-item"
                            :class="{
                                'file-item-selected':
                                    updateSelectedFile?.id === file.id,
                            }"
                            @click="selectUpdateFile(file)"
                        >
                            <div class="file-item-content">
                                <div class="file-item-name">
                                    {{
                                        file.displayName ||
                                        file.fileName ||
                                        file.version_number ||
                                        file.name
                                    }}
                                </div>
                                <div class="file-item-meta">
                                    <span class="file-item-date">
                                        {{
                                            formatDate(
                                                file.fileDate ||
                                                    file.date_published,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            file.fileLength ||
                                            (file.files &&
                                                file.files[0] &&
                                                file.files[0].size)
                                        "
                                        class="file-item-size"
                                    >
                                        {{
                                            formatFileSize(
                                                file.fileLength ||
                                                    (file.files &&
                                                        file.files[0] &&
                                                        file.files[0].size),
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-if="updateSelectedFile?.id === file.id"
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

                <div v-if="updateModError" class="error-message">
                    <p>{{ updateModError }}</p>
                </div>

                <div class="modal-footer">
                    <Button
                        variant="secondary"
                        @click="updateModStep = 'search'"
                    >
                        {{ t("modpacks.show.add_modal.back") }}
                    </Button>
                    <Button :disabled="!updateSelectedFile" @click="updateMod">
                        {{ t("modpacks.show.update_modal.update") }}
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- Update Preview Modal -->
        <Modal
            v-model:show="showUpdatePreviewModal"
            :title="t('modpacks.show.update_preview_modal.title')"
            size="large"
            @close="closeUpdatePreviewModal"
        >
            <div v-if="isPreviewLoading" class="loading-preview">
                <p>{{ t("modpacks.show.update_preview_modal.loading") }}</p>
            </div>
            <div v-else>
                <p class="preview-description">
                    {{
                        t("modpacks.show.update_preview_modal.description", {
                            count: updatePreview.length,
                        })
                    }}
                </p>
                <div class="preview-list">
                    <div
                        v-for="update in updatePreview"
                        :key="update.item_id"
                        class="preview-item"
                    >
                        <div class="preview-item-content">
                            <div class="preview-item-name">
                                {{ update.mod_name }}
                            </div>
                            <div class="preview-item-versions">
                                <span class="preview-current-version">
                                    {{
                                        t(
                                            "modpacks.show.update_preview_modal.current",
                                        )
                                    }}:
                                    {{ update.current_version }}
                                </span>
                                <span class="preview-arrow">→</span>
                                <span class="preview-latest-version">
                                    {{
                                        t(
                                            "modpacks.show.update_preview_modal.latest",
                                        )
                                    }}:
                                    {{ update.latest_version }}
                                </span>
                            </div>
                            <div
                                v-if="update.file_date"
                                class="preview-item-date"
                            >
                                {{ formatDate(update.file_date) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button
                    variant="secondary"
                    :disabled="isUpdatingAll || isUpdatingBulk"
                    @click="closeUpdatePreviewModal"
                >
                    {{ t("modpacks.show.edit_modal.cancel") }}
                </Button>
                <Button
                    variant="primary"
                    :disabled="isUpdatingAll || isUpdatingBulk"
                    :class="{ 'btn-loading': isUpdatingAll || isUpdatingBulk }"
                    @click="confirmUpdatePreview"
                >
                    <span v-if="!isUpdatingAll && !isUpdatingBulk">{{
                        t("modpacks.show.update_preview_modal.confirm")
                    }}</span>
                    <span v-else class="loading-text">{{
                        t("modpacks.show.updating")
                    }}</span>
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref, watch, computed, onMounted, onBeforeUnmount } from "vue";
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
const showUpdateModModal = ref(false);
const showChangeVersionModal = ref(false);
const showShareModal = ref(false);
const showUpdatePreviewModal = ref(false);
const addModStep = ref("search"); // 'search' or 'selectVersion'
const updateModStep = ref("search"); // 'search' or 'selectVersion'
const shareUrl = ref("");
const isCopying = ref(false);
const isRegenerating = ref(false);
const isUpdatingAll = ref(false);
const isUpdatingBulk = ref(false);
const currentUpdateItem = ref(null);
const updatePreview = ref([]);
const isPreviewLoading = ref(false);
const pendingUpdateType = ref(null); // 'all' or 'bulk'
const pendingItemIds = ref([]);
const isSettingReminder = ref(false);
const isCancellingReminder = ref(false);

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
const modsSearchQuery = ref("");
const selectedItems = ref(new Set());
const isDownloadingBulk = ref(false);
const isExporting = ref(false);
const showExportMenu = ref(false);
const isDeletingBulk = ref(false);

// Drag & drop state
const draggedItemId = ref(null);
const draggedItemIndex = ref(null);
const dragOverItemId = ref(null);
const isReordering = ref(false);

// Update mod modal state
const updateModSearchQuery = ref("");
const updateModSearchResults = ref([]);
const isUpdateSearching = ref(false);
const updateSearchPerformed = ref(false);
const updateSelectedMod = ref(null);
const updateModFiles = ref([]);
const isLoadingUpdateFiles = ref(false);
const updateSelectedFile = ref(null);
const updateModError = ref("");

let searchTimeout = null;
let updateSearchTimeout = null;

// Filter mods based on search query
const filteredMods = computed(() => {
    if (!modsSearchQuery.value.trim()) {
        return props.modPack.items;
    }

    const query = modsSearchQuery.value.toLowerCase().trim();
    return props.modPack.items.filter((item) => {
        const modName = (item.mod_name || "").toLowerCase();
        const modVersion = (item.mod_version || "").toLowerCase();
        const curseforgeSlug = (item.curseforge_slug || "").toLowerCase();

        return (
            modName.includes(query) ||
            modVersion.includes(query) ||
            curseforgeSlug.includes(query)
        );
    });
});

const clearSearch = () => {
    modsSearchQuery.value = "";
};

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
        // Determine source: check _source property or infer from mod structure
        // Modrinth mods have string IDs, CurseForge mods have numeric IDs
        const source =
            mod._source ||
            (typeof mod.id === "string" ? "modrinth" : "curseforge");

        // Get the correct mod ID (Modrinth might use project_id)
        const modId = mod.id || mod.project_id;

        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/mod-files`,
            {
                params: {
                    mod_id: modId,
                    source: source,
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

    // Determine source: check _source property or infer from mod structure
    const source =
        selectedMod.value._source ||
        (typeof selectedMod.value.id === "string" ? "modrinth" : "curseforge");

    // Get the correct mod ID (Modrinth might use project_id)
    const modId = selectedMod.value.id || selectedMod.value.project_id;

    // Check if mod is already in the mod pack
    let existingMod = null;
    if (source === "curseforge") {
        existingMod = props.modPack.items.find(
            (item) => item.curseforge_mod_id === modId,
        );
    } else if (source === "modrinth") {
        existingMod = props.modPack.items.find(
            (item) => item.modrinth_project_id === modId,
        );
    }

    if (existingMod) {
        // Show error message
        addModError.value = t("modpacks.show.mod_already_added", {
            name: selectedMod.value.name || selectedMod.value.title,
        });
        return;
    }

    // Build form data based on source
    // Modrinth search results use 'title', while CurseForge uses 'name'
    const modName = selectedMod.value.name || selectedMod.value.title || "";

    if (!modName) {
        addModError.value =
            t("modpacks.show.add_failed") + ": Missing mod name";
        return;
    }

    const formData = {
        mod_name: modName,
        source: source,
    };

    if (source === "curseforge") {
        formData.mod_version =
            selectedFile.value.displayName || selectedFile.value.fileName;
        formData.curseforge_mod_id = modId;
        formData.curseforge_file_id = selectedFile.value.id;
        if (selectedMod.value.slug) {
            formData.curseforge_slug = selectedMod.value.slug;
        }
    } else if (source === "modrinth") {
        // Modrinth versions use version_number or name
        formData.mod_version =
            selectedFile.value.version_number ||
            selectedFile.value.name ||
            selectedFile.value.id;
        formData.modrinth_project_id = modId;
        formData.modrinth_version_id = selectedFile.value.id;
        if (selectedMod.value.slug) {
            formData.modrinth_slug = selectedMod.value.slug;
        }
    }

    const form = useForm(formData);

    form.post(`/mod-packs/${props.modPack.id}/items`, {
        onSuccess: () => {
            closeAddModModal();
        },
        onError: (errors) => {
            // Handle backend validation errors
            // eslint-disable-next-line no-console
            console.error("Add mod error:", errors);

            // Check for specific field errors
            if (errors.curseforge_mod_id) {
                addModError.value = errors.curseforge_mod_id;
            } else if (errors.modrinth_project_id) {
                addModError.value = errors.modrinth_project_id;
            } else if (errors.mod_version) {
                addModError.value = errors.mod_version;
            } else if (errors.mod_name) {
                addModError.value = errors.mod_name;
            } else if (Object.keys(errors).length > 0) {
                // Show first error found
                const firstError = Object.values(errors)[0];
                addModError.value = Array.isArray(firstError)
                    ? firstError[0]
                    : firstError;
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

const setReminder = async () => {
    if (!versionForm.minecraft_version || !versionForm.software) {
        return;
    }

    isSettingReminder.value = true;
    try {
        const response = await axios.post(
            `/mod-packs/${props.modPack.id}/set-reminder`,
            {
                minecraft_version: versionForm.minecraft_version,
                software: versionForm.software,
            },
        );

        if (response.data && response.data.message) {
            alert(t("modpack.reminder_set"));
            showChangeVersionModal.value = false;
            versionForm.clearErrors();
            // Reload the page to show the reminder info
            router.reload();
        }
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error setting reminder:", error);

        // Check if it's a validation error
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            const errors = error.response.data.errors;
            const errorMessage = Object.values(errors).flat().join(", ");
            alert(`${t("modpack.reminder_set_failed")}: ${errorMessage}`);
        } else if (
            error.response &&
            error.response.data &&
            error.response.data.message
        ) {
            alert(
                `${t("modpack.reminder_set_failed")}: ${error.response.data.message}`,
            );
        } else {
            alert(t("modpack.reminder_set_failed"));
        }
    } finally {
        isSettingReminder.value = false;
    }
};

const cancelReminder = async () => {
    if (!confirm(t("modpack.cancel_reminder") + "?")) {
        return;
    }

    isCancellingReminder.value = true;
    try {
        const response = await axios.post(
            `/mod-packs/${props.modPack.id}/cancel-reminder`,
        );

        // eslint-disable-next-line no-console
        console.log("Cancel reminder response:", response);

        // If we get here, the request was successful
        alert(t("modpack.reminder_cancelled"));

        // Reload the page to update the UI
        router.reload({ only: ["modPack"] });
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error cancelling reminder:", error);
        // eslint-disable-next-line no-console
        console.error("Error response:", error.response);
        // eslint-disable-next-line no-console
        console.error("Error status:", error.response?.status);
        // eslint-disable-next-line no-console
        console.error("Error data:", error.response?.data);

        // Check if it's a validation error
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            const errors = error.response.data.errors;
            const errorMessage = Object.values(errors).flat().join(", ");
            alert(`${t("modpack.reminder_cancel_failed")}: ${errorMessage}`);
        } else if (
            error.response &&
            error.response.data &&
            error.response.data.message
        ) {
            alert(
                `${t("modpack.reminder_cancel_failed")}: ${error.response.data.message}`,
            );
        } else if (error.response) {
            alert(
                `${t("modpack.reminder_cancel_failed")}: ${error.response.status} ${error.response.statusText}`,
            );
        } else if (error.message) {
            alert(`${t("modpack.reminder_cancel_failed")}: ${error.message}`);
        } else {
            alert(t("modpack.reminder_cancel_failed"));
        }
    } finally {
        isCancellingReminder.value = false;
    }
};

const duplicateModPack = () => {
    if (confirm(t("modpacks.show.duplicate_confirm"))) {
        router.post(`/mod-packs/${props.modPack.id}/duplicate`);
    }
};

const deleteModPack = () => {
    if (confirm(t("modpacks.show.delete_confirm"))) {
        router.delete(`/mod-packs/${props.modPack.id}`);
    }
};

const deleteModItem = (itemId) => {
    if (confirm(t("modpacks.show.remove_confirm"))) {
        router.delete(`/mod-packs/${props.modPack.id}/items/${itemId}`, {
            onSuccess: () => {
                // Remove from selected items if it was selected
                selectedItems.value.delete(itemId);
            },
        });
    }
};

const openUpdateModModal = async (item) => {
    currentUpdateItem.value = item;
    showUpdateModModal.value = true;
    updateModStep.value = "selectVersion";
    updateModError.value = "";

    // Pre-fill the mod info
    if (item.curseforge_mod_id) {
        updateSelectedMod.value = {
            id: item.curseforge_mod_id,
            name: item.mod_name,
            slug: item.curseforge_slug,
            _source: "curseforge",
        };
        updateModSearchQuery.value = item.mod_name;

        // Load files for this mod
        isLoadingUpdateFiles.value = true;
        updateModFiles.value = [];
        updateSelectedFile.value = null;

        try {
            const response = await axios.get(
                `/mod-packs/${props.modPack.id}/mod-files`,
                {
                    params: {
                        mod_id: item.curseforge_mod_id,
                        source: "curseforge",
                    },
                },
            );

            updateModFiles.value = response.data.data || [];

            // Auto-select the latest file if available
            if (updateModFiles.value.length > 0) {
                updateSelectedFile.value = updateModFiles.value[0];
            }
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error("Error loading mod files:", error);
            updateModFiles.value = [];
        } finally {
            isLoadingUpdateFiles.value = false;
        }
    } else if (item.modrinth_project_id) {
        updateSelectedMod.value = {
            id: item.modrinth_project_id,
            name: item.mod_name,
            slug: item.modrinth_slug,
            _source: "modrinth",
        };
        updateModSearchQuery.value = item.mod_name;

        // Load files for this mod
        isLoadingUpdateFiles.value = true;
        updateModFiles.value = [];
        updateSelectedFile.value = null;

        try {
            const response = await axios.get(
                `/mod-packs/${props.modPack.id}/mod-files`,
                {
                    params: {
                        mod_id: item.modrinth_project_id,
                        source: "modrinth",
                    },
                },
            );

            updateModFiles.value = response.data.data || [];

            // Auto-select the latest file if available
            if (updateModFiles.value.length > 0) {
                updateSelectedFile.value = updateModFiles.value[0];
            }
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error("Error loading mod files:", error);
            updateModFiles.value = [];
        } finally {
            isLoadingUpdateFiles.value = false;
        }
    } else {
        // If no mod ID, start with search
        updateModStep.value = "search";
        updateSelectedMod.value = null;
        updateModSearchQuery.value = item.mod_name;
    }
};

const closeUpdateModModal = () => {
    showUpdateModModal.value = false;
    updateModStep.value = "search";
    updateModSearchQuery.value = "";
    updateModSearchResults.value = [];
    updateSearchPerformed.value = false;
    updateSelectedMod.value = null;
    updateModFiles.value = [];
    updateSelectedFile.value = null;
    updateModError.value = "";
    currentUpdateItem.value = null;
};

const debouncedUpdateSearch = () => {
    if (updateSearchTimeout) {
        clearTimeout(updateSearchTimeout);
    }

    if (updateModSearchQuery.value.length < 2) {
        updateModSearchResults.value = [];
        updateSearchPerformed.value = false;
        return;
    }

    updateSearchTimeout = setTimeout(() => {
        searchUpdateMods();
    }, 500);
};

watch(updateModSearchQuery, () => {
    debouncedUpdateSearch();
});

const handleUpdatePaste = () => {
    // The watcher on updateModSearchQuery will handle triggering the search
    // after the v-model updates from the paste event
};

const searchUpdateMods = async () => {
    if (updateModSearchQuery.value.length < 2) {
        return;
    }

    isUpdateSearching.value = true;
    updateSearchPerformed.value = true;

    try {
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/search-mods`,
            {
                params: {
                    query: updateModSearchQuery.value,
                },
            },
        );

        updateModSearchResults.value = response.data.data || [];
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error searching mods:", error);
        updateModSearchResults.value = [];
    } finally {
        isUpdateSearching.value = false;
    }
};

const selectUpdateMod = async (mod) => {
    updateSelectedMod.value = mod;
    updateModStep.value = "selectVersion";
    isLoadingUpdateFiles.value = true;
    updateModFiles.value = [];
    updateSelectedFile.value = null;
    updateModError.value = "";

    try {
        // Determine source: check _source property or infer from mod structure
        // Modrinth mods have string IDs, CurseForge mods have numeric IDs
        const source =
            mod._source ||
            (typeof mod.id === "string" ? "modrinth" : "curseforge");

        // Get the correct mod ID (Modrinth might use project_id)
        const modId = mod.id || mod.project_id;

        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/mod-files`,
            {
                params: {
                    mod_id: modId,
                    source: source,
                },
            },
        );

        updateModFiles.value = response.data.data || [];

        // Auto-select the latest file if available
        if (updateModFiles.value.length > 0) {
            updateSelectedFile.value = updateModFiles.value[0];
        }
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error loading mod files:", error);
        updateModFiles.value = [];
    } finally {
        isLoadingUpdateFiles.value = false;
    }
};

const selectUpdateFile = (file) => {
    updateSelectedFile.value = file;
};

const updateMod = () => {
    if (!updateSelectedMod.value || !updateSelectedFile.value) {
        return;
    }

    // Clear any previous errors
    updateModError.value = "";

    if (!currentUpdateItem.value) {
        updateModError.value = t("modpacks.show.update_failed");
        return;
    }

    // Determine source: check _source property or infer from mod structure
    const source =
        updateSelectedMod.value._source ||
        (typeof updateSelectedMod.value.id === "string"
            ? "modrinth"
            : "curseforge");

    // Get the correct mod ID (Modrinth might use project_id)
    const modId =
        updateSelectedMod.value.id || updateSelectedMod.value.project_id;

    // Build form data based on source
    // Modrinth search results use 'title', while CurseForge uses 'name'
    const modName =
        updateSelectedMod.value.name || updateSelectedMod.value.title || "";

    if (!modName) {
        updateModError.value =
            t("modpacks.show.update_failed") + ": Missing mod name";
        return;
    }

    const formData = {
        mod_name: modName,
        source: source,
    };

    if (source === "curseforge") {
        formData.mod_version =
            updateSelectedFile.value.displayName ||
            updateSelectedFile.value.fileName;
        formData.curseforge_mod_id = modId;
        formData.curseforge_file_id = updateSelectedFile.value.id;
        if (updateSelectedMod.value.slug) {
            formData.curseforge_slug = updateSelectedMod.value.slug;
        }
    } else if (source === "modrinth") {
        // Modrinth versions use version_number or name
        formData.mod_version =
            updateSelectedFile.value.version_number ||
            updateSelectedFile.value.name ||
            updateSelectedFile.value.id;
        formData.modrinth_project_id = modId;
        formData.modrinth_version_id = updateSelectedFile.value.id;
        if (updateSelectedMod.value.slug) {
            formData.modrinth_slug = updateSelectedMod.value.slug;
        }
    }

    const form = useForm(formData);

    form.put(
        `/mod-packs/${props.modPack.id}/items/${currentUpdateItem.value.id}`,
        {
            onSuccess: () => {
                closeUpdateModModal();
            },
            onError: (errors) => {
                // Handle backend validation errors
                // eslint-disable-next-line no-console
                console.error("Update mod error:", errors);

                // Check for specific field errors
                if (errors.curseforge_mod_id) {
                    updateModError.value = errors.curseforge_mod_id;
                } else if (errors.modrinth_project_id) {
                    updateModError.value = errors.modrinth_project_id;
                } else if (errors.mod_version) {
                    updateModError.value = errors.mod_version;
                } else if (errors.mod_name) {
                    updateModError.value = errors.mod_name;
                } else if (Object.keys(errors).length > 0) {
                    // Show first error found
                    const firstError = Object.values(errors)[0];
                    updateModError.value = Array.isArray(firstError)
                        ? firstError[0]
                        : firstError;
                } else {
                    updateModError.value = t("modpacks.show.update_failed");
                }
            },
        },
    );
};

const updateBulkToLatest = async () => {
    if (selectedItems.value.size === 0) {
        return;
    }

    const itemIds = Array.from(selectedItems.value);
    const selectedMods = filteredMods.value.filter((item) =>
        itemIds.includes(item.id),
    );

    // Filter to mods with either curseforge_mod_id or modrinth_project_id
    const modsToUpdate = selectedMods.filter(
        (item) =>
            (item.curseforge_mod_id && item.curseforge_file_id) ||
            (item.modrinth_project_id && item.modrinth_version_id),
    );

    if (modsToUpdate.length === 0) {
        alert(t("modpacks.show.no_mods_to_update"));
        return;
    }

    // Show preview first
    isUpdatingBulk.value = true;
    isPreviewLoading.value = true;
    pendingUpdateType.value = "bulk";
    pendingItemIds.value = modsToUpdate.map((item) => item.id);

    try {
        const response = await axios.post(
            `/mod-packs/${props.modPack.id}/items/preview-bulk-to-latest`,
            {
                item_ids: pendingItemIds.value,
            },
        );

        updatePreview.value = response.data.updates || [];

        if (updatePreview.value.length === 0) {
            alert(t("modpacks.show.no_updates_available"));
            isUpdatingBulk.value = false;
            isPreviewLoading.value = false;
            return;
        }

        // Reset loading state after preview is loaded (before showing modal)
        isUpdatingBulk.value = false;
        showUpdatePreviewModal.value = true;
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error previewing updates:", error);
        alert(t("modpacks.show.preview_failed"));
        isUpdatingBulk.value = false;
    } finally {
        isPreviewLoading.value = false;
    }
};

const updateAllToLatest = async () => {
    if (props.modPack.items.length === 0) {
        return;
    }

    // Filter to mods with either curseforge_mod_id or modrinth_project_id
    const modsToUpdate = props.modPack.items.filter(
        (item) =>
            (item.curseforge_mod_id && item.curseforge_file_id) ||
            (item.modrinth_project_id && item.modrinth_version_id),
    );

    if (modsToUpdate.length === 0) {
        alert(t("modpacks.show.no_mods_to_update"));
        return;
    }

    // Show preview first
    isUpdatingAll.value = true;
    isPreviewLoading.value = true;
    pendingUpdateType.value = "all";
    pendingItemIds.value = [];

    try {
        const response = await axios.get(
            `/mod-packs/${props.modPack.id}/items/preview-all-to-latest`,
        );

        updatePreview.value = response.data.updates || [];

        if (updatePreview.value.length === 0) {
            alert(t("modpacks.show.no_updates_available"));
            isUpdatingAll.value = false;
            isPreviewLoading.value = false;
            return;
        }

        // Reset loading state after preview is loaded (before showing modal)
        isUpdatingAll.value = false;
        showUpdatePreviewModal.value = true;
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error previewing updates:", error);
        alert(t("modpacks.show.preview_failed"));
        isUpdatingAll.value = false;
    } finally {
        isPreviewLoading.value = false;
    }
};

const closeUpdatePreviewModal = () => {
    showUpdatePreviewModal.value = false;
    updatePreview.value = [];
    pendingUpdateType.value = null;
    pendingItemIds.value = [];
    // Reset all loading states when modal is closed
    isUpdatingAll.value = false;
    isUpdatingBulk.value = false;
    isPreviewLoading.value = false;
};

const confirmUpdatePreview = async () => {
    if (pendingUpdateType.value === "all") {
        isUpdatingAll.value = true;
        try {
            const response = await axios.post(
                `/mod-packs/${props.modPack.id}/items/update-all-to-latest`,
            );

            if (response.data.success) {
                closeUpdatePreviewModal();
                // Reload the page to show updated mods
                router.reload();
            } else {
                alert(t("modpacks.show.update_all_failed"));
            }
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error("Error updating all mods:", error);
            alert(t("modpacks.show.update_all_failed"));
        } finally {
            isUpdatingAll.value = false;
        }
    } else if (pendingUpdateType.value === "bulk") {
        isUpdatingBulk.value = true;
        try {
            const response = await axios.post(
                `/mod-packs/${props.modPack.id}/bulk-items/update-to-latest`,
                {
                    item_ids: pendingItemIds.value,
                },
            );

            if (response.data.success) {
                closeUpdatePreviewModal();
                clearSelection();
                // Reload the page to show updated mods
                router.reload();
            } else {
                alert(t("modpacks.show.bulk_update_failed"));
            }
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error("Error updating mods:", error);
            alert(t("modpacks.show.bulk_update_failed"));
        } finally {
            isUpdatingBulk.value = false;
        }
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

const handleLogoError = (event) => {
    // Hide the image if it fails to load
    if (event.target) {
        event.target.style.display = "none";
    }
};

const getModUrl = (mod) => {
    if (!mod || !mod.slug) {
        return null;
    }

    const source =
        mod._source ||
        (mod.id && typeof mod.id === "number" ? "curseforge" : "modrinth");

    if (source === "modrinth") {
        return `https://modrinth.com/mod/${mod.slug}`;
    }

    // Default to CurseForge
    return `https://www.curseforge.com/minecraft/mc-mods/${mod.slug}`;
};

const getItemModUrl = (item) => {
    if (!item) {
        return null;
    }

    // Check source field first
    if (item.source === "modrinth" && item.modrinth_slug) {
        return `https://modrinth.com/mod/${item.modrinth_slug}`;
    }

    if (item.source === "curseforge" && item.curseforge_slug) {
        return `https://www.curseforge.com/minecraft/mc-mods/${item.curseforge_slug}`;
    }

    // Fallback: check which slug exists
    if (item.modrinth_slug) {
        return `https://modrinth.com/mod/${item.modrinth_slug}`;
    }

    if (item.curseforge_slug) {
        return `https://www.curseforge.com/minecraft/mc-mods/${item.curseforge_slug}`;
    }

    return null;
};

const downloadModItem = async (item) => {
    const isCurseForge = item.curseforge_mod_id && item.curseforge_file_id;
    const isModrinth = item.modrinth_project_id && item.modrinth_version_id;

    if (!isCurseForge && !isModrinth) {
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
    const selectedMods = filteredMods.value.filter((item) =>
        itemIds.includes(item.id),
    );

    if (selectedMods.length === 0) {
        return;
    }

    try {
        isDownloadingBulk.value = true;

        // Get download links for selected items
        const response = await axios.post(
            `/mod-packs/${props.modPack.id}/bulk-download-links`,
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

                    const blob = await downloadFileViaProxy(link.download_url);

                    if (blob.size === 0) {
                        throw new Error(`Downloaded file is empty`);
                    }

                    // Check if the blob is actually an error page (HTML response)
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
        // eslint-disable-next-line no-console
        console.error("Error downloading selected mods:", error);
        const errorMessage =
            error?.message || error?.toString() || "Unknown error";
        alert(t("modpacks.show.download_pack_failed", { error: errorMessage }));
    } finally {
        isDownloadingBulk.value = false;
    }
};

const deleteBulkSelected = () => {
    if (selectedItems.value.size === 0) {
        return;
    }

    const itemIds = Array.from(selectedItems.value);
    const count = itemIds.length;

    if (
        !confirm(
            t("modpacks.show.bulk_delete_confirm", {
                count,
            }),
        )
    ) {
        return;
    }

    isDeletingBulk.value = true;

    router.post(
        `/mod-packs/${props.modPack.id}/bulk-items/delete`,
        {
            item_ids: itemIds,
        },
        {
            onSuccess: () => {
                // Clear selection after successful delete
                clearSelection();
            },
            onError: (errors) => {
                // eslint-disable-next-line no-console
                console.error("Error deleting selected mods:", errors);
                alert(t("modpacks.show.bulk_delete_failed"));
            },
            onFinish: () => {
                isDeletingBulk.value = false;
            },
        },
    );
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

const exportModpack = async (format) => {
    try {
        if (
            !props.modPack ||
            !props.modPack.items ||
            props.modPack.items.length === 0
        ) {
            return;
        }

        isExporting.value = true;
        showExportMenu.value = false;

        const url = `/mod-packs/${props.modPack.id}/export/${format}`;

        // Use window.location for downloads (better browser compatibility)
        window.location.href = url;

        // Wait a bit for the download to start
        await new Promise((resolve) => setTimeout(resolve, 1000));
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error exporting modpack:", error);
        const errorMessage =
            error?.message || error?.toString() || "Unknown error";
        alert(t("modpacks.show.export_failed", { error: errorMessage }));
    } finally {
        isExporting.value = false;
    }
};

// Close export menu when clicking outside
const handleClickOutside = (event) => {
    if (!event.target.closest(".export-dropdown")) {
        showExportMenu.value = false;
    }
};

// Attach click outside listener
onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutside);
});

// Drag & drop handlers
const handleDragStart = (event, itemId, index) => {
    draggedItemId.value = itemId;
    draggedItemIndex.value = index;
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/html", itemId);
    // Add a slight delay to allow the drag image to be set
    setTimeout(() => {
        if (event.target) {
            event.target.style.opacity = "0.5";
        }
    }, 0);
};

const handleDragEnd = (event) => {
    draggedItemId.value = null;
    draggedItemIndex.value = null;
    dragOverItemId.value = null;
    if (event.target) {
        event.target.style.opacity = "1";
    }
};

const handleDragOver = (event, itemId) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = "move";

    // Only update dragOverItemId if it's different and not the dragged item
    if (dragOverItemId.value !== itemId && draggedItemId.value !== itemId) {
        dragOverItemId.value = itemId;
    }
};

const handleDragLeave = () => {
    dragOverItemId.value = null;
};

const handleDrop = async (event, targetItemId) => {
    event.preventDefault();
    dragOverItemId.value = null;

    if (!draggedItemId.value || draggedItemId.value === targetItemId) {
        return;
    }

    // Get the current order of items (from props, not filtered)
    const items = [...props.modPack.items];
    const draggedIndex = items.findIndex(
        (item) => item.id === draggedItemId.value,
    );
    const targetIndex = items.findIndex((item) => item.id === targetItemId);

    if (draggedIndex === -1 || targetIndex === -1) {
        return;
    }

    // Reorder: remove dragged item and insert at target position
    const [draggedItem] = items.splice(draggedIndex, 1);
    // Adjust target index if we removed an item before the target
    // If dragging down, target index decreases by 1 after removal
    // If dragging up, target index stays the same
    const insertIndex =
        draggedIndex < targetIndex ? targetIndex - 1 : targetIndex;
    items.splice(insertIndex, 0, draggedItem);

    // Get the new order of item IDs
    const newOrder = items.map((item) => item.id);

    // Update the backend
    isReordering.value = true;
    try {
        await axios.post(`/mod-packs/${props.modPack.id}/items/reorder`, {
            item_ids: newOrder,
        });

        // Reload the page to reflect the new order
        router.reload();
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Error reordering items:", error);
        alert(t("modpacks.show.reorder_failed") || "Failed to reorder mods");
    } finally {
        isReordering.value = false;
        draggedItemId.value = null;
        draggedItemIndex.value = null;
    }
};
</script>
