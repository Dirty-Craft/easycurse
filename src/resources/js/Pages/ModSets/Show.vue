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
                        <button
                            class="btn btn-primary"
                            @click="showAddModModal = true"
                        >
                            + Add Mod
                        </button>
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
                                        v{{ item.mod_version }}
                                    </div>
                                </div>
                            </div>
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
            <div class="modal-content" @click.stop>
                <div class="modal-header">
                    <h2>Add Mod</h2>
                    <button
                        class="modal-close"
                        @click="showAddModModal = false"
                    >
                        ×
                    </button>
                </div>
                <form class="modal-body" @submit.prevent="addMod">
                    <div class="form-group">
                        <label for="mod-name">Mod Name</label>
                        <input
                            id="mod-name"
                            v-model="modForm.mod_name"
                            type="text"
                            required
                            class="form-input"
                            placeholder="e.g., JEI"
                        />
                    </div>
                    <div class="form-group">
                        <label for="mod-version">Version</label>
                        <input
                            id="mod-version"
                            v-model="modForm.mod_version"
                            type="text"
                            required
                            class="form-input"
                            placeholder="e.g., 1.20.1-11.6.0.1015"
                        />
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            @click="showAddModModal = false"
                        >
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Add Mod
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import DashboardLayout from "../../Layouts/DashboardLayout.vue";

const props = defineProps({
    modSet: Object,
});

const showEditModal = ref(false);
const showAddModModal = ref(false);

const editForm = useForm({
    name: props.modSet.name,
    minecraft_version: props.modSet.minecraft_version,
    software: props.modSet.software.value,
    description: props.modSet.description || "",
});

const modForm = useForm({
    mod_name: "",
    mod_version: "",
});

const updateModSet = () => {
    editForm.put(`/mod-sets/${props.modSet.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const addMod = () => {
    modForm.post(`/mod-sets/${props.modSet.id}/items`, {
        onSuccess: () => {
            showAddModModal.value = false;
            modForm.reset();
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
</style>
