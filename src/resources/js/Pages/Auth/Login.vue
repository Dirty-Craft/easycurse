<template>
    <Head title="Login" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">Welcome Back</h1>
                        <p class="auth-subtitle">
                            Sign in to your account to continue
                        </p>
                    </div>

                    <form class="auth-form" @submit.prevent="submit">
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="form-input"
                                required
                                autofocus
                                autocomplete="email"
                            />
                            <div v-if="form.errors.email" class="form-error">
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label"
                                >Password</label
                            >
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="form-input"
                                required
                                autocomplete="current-password"
                            />
                            <div v-if="form.errors.password" class="form-error">
                                {{ form.errors.password }}
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-checkbox">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                />
                                <span>Remember me</span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary btn-full"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? "Signing in..." : "Sign In" }}
                        </button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            Don't have an account?
                            <Link href="/register" class="auth-link"
                                >Sign up</Link
                            >
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../../Layouts/AppLayout.vue";

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post("/login", {
        onFinish: () => form.reset("password"),
    });
};
</script>
