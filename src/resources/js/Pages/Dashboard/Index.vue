<template>
    <Head title="Dashboard" />
    <DashboardLayout>
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Dashboard</h1>
                <p class="dashboard-subtitle">
                    Welcome back, {{ $page.props.auth.user.name }}!
                </p>
            </div>

            <div class="dashboard-main">
                <div class="dashboard-card">
                    <p class="dashboard-placeholder">
                        Your dashboard content will appear here.
                    </p>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import DashboardLayout from "../../Layouts/DashboardLayout.vue";
</script>
