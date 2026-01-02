<template>
    <Head title="My Mods" />
    <DashboardLayout>
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1 class="dashboard-title">My Mods</h1>
                <p class="dashboard-subtitle">Manage your mod sets</p>
            </div>

            <div class="dashboard-main">
                <div class="mod-sets-actions">
                    <button
                        class="btn btn-primary"
                        @click="showCreateModal = true"
                    >
                        + Create Mod Set
                    </button>
                </div>

                <div v-if="modSets.length === 0" class="dashboard-card">
                    <p class="dashboard-placeholder">
                        No mod sets yet. Create your first mod set to get
                        started!
                    </p>
                </div>

                <div v-else class="mod-sets-grid">
                    <div
                        v-for="modSet in modSets"
                        :key="modSet.id"
                        class="mod-set-card"
                    >
                        <div class="mod-set-header">
                            <h3 class="mod-set-name">{{ modSet.name }}</h3>
                            <div class="mod-set-actions">
                                <Link
                                    :href="`/mod-sets/${modSet.id}`"
                                    class="btn btn-sm btn-secondary"
                                >
                                    View
                                </Link>
                                <button
                                    class="btn btn-sm btn-danger"
                                    @click="deleteModSet(modSet.id)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div class="mod-set-info">
                            <div class="info-item">
                                <span class="info-label"
                                    >Minecraft Version:</span
                                >
                                <span class="info-value">{{
                                    modSet.minecraft_version
                                }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Software:</span>
                                <span class="info-value">{{
                                    modSet.software_label
                                }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Mods:</span>
                                <span class="info-value">{{
                                    modSet.items.length
                                }}</span>
                            </div>
                        </div>
                        <p
                            v-if="modSet.description"
                            class="mod-set-description"
                        >
                            {{ modSet.description }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div
            v-if="showCreateModal"
            class="modal-overlay"
            @click="showCreateModal = false"
        >
            <div class="modal-content" @click.stop>
                <div class="modal-header">
                    <h2>Create Mod Set</h2>
                    <button
                        class="modal-close"
                        @click="showCreateModal = false"
                    >
                        ×
                    </button>
                </div>
                <form class="modal-body" @submit.prevent="createModSet">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="form-input"
                        />
                    </div>
                    <div class="form-group">
                        <label for="minecraft_version">Minecraft Version</label>
                        <input
                            id="minecraft_version"
                            v-model="form.minecraft_version"
                            type="text"
                            required
                            class="form-input"
                            placeholder="e.g., 1.20.1"
                        />
                    </div>
                    <div class="form-group">
                        <label for="software">Software</label>
                        <select
                            id="software"
                            v-model="form.software"
                            required
                            class="form-input"
                        >
                            <option value="forge">Forge</option>
                            <option value="fabric">Fabric</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="form-input"
                            rows="3"
                        ></textarea>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            @click="showCreateModal = false"
                        >
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import DashboardLayout from "../../Layouts/DashboardLayout.vue";

defineProps({
    modSets: Array,
});

const showCreateModal = ref(false);
const form = ref({
    name: "",
    minecraft_version: "",
    software: "forge",
    description: "",
});

const createModSet = () => {
    router.post("/mod-sets", form.value, {
        onSuccess: () => {
            showCreateModal.value = false;
            form.value = {
                name: "",
                minecraft_version: "",
                software: "forge",
                description: "",
            };
        },
    });
};

const deleteModSet = (id) => {
    if (confirm("Are you sure you want to delete this mod set?")) {
        router.delete(`/mod-sets/${id}`);
    }
};
</script>

<style scoped>
.mod-sets-actions {
    margin-bottom: var(--spacing-xl);
}

.mod-sets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--spacing-lg);
}

.mod-set-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: var(--spacing-xl);
    transition: all var(--transition-base);
}

.mod-set-card:hover {
    border-color: var(--color-primary);
    box-shadow: var(--shadow-glow);
}

.mod-set-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-md);
    gap: var(--spacing-md);
}

.mod-set-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0;
    flex: 1;
}

.mod-set-actions {
    display: flex;
    gap: var(--spacing-sm);
}

.mod-set-info {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-md);
}

.info-item {
    display: flex;
    gap: var(--spacing-sm);
}

.info-label {
    color: var(--color-text-secondary);
    font-weight: 500;
}

.info-value {
    color: var(--color-text-primary);
}

.mod-set-description {
    color: var(--color-text-secondary);
    font-size: 0.875rem;
    margin: var(--spacing-md) 0 0 0;
    line-height: 1.5;
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
