<template>
    <Head title="Forgot Password" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">Forgot Password</h1>
                        <p class="auth-subtitle">
                            Enter your email address and we'll send you a link
                            to reset your password.
                        </p>
                    </div>

                    <div
                        v-if="status"
                        :key="status"
                        class="alert alert-success"
                    >
                        {{ status }}
                    </div>

                    <form class="auth-form" @submit.prevent="submit">
                        <FormGroup
                            label="Email"
                            input-id="email"
                            :error="form.errors.email"
                        >
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                            />
                        </FormGroup>

                        <Button type="submit" full :disabled="form.processing">
                            {{
                                form.processing
                                    ? "Sending..."
                                    : "Send Reset Link"
                            }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            Remember your password?
                            <Link href="/login" class="auth-link">Sign in</Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
import Input from "../../Components/Input.vue";
import FormGroup from "../../Components/FormGroup.vue";

const page = usePage();
const status = computed(() => page.props.flash?.status);

const form = useForm({
    email: "",
});

const submit = () => {
    form.post("/forgot-password", {
        preserveScroll: true,
    });
};
</script>

<style scoped>
.alert {
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-radius: 0.375rem;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}
</style>
