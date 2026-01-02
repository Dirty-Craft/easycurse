<template>
    <div class="page-container">
        <div class="content-wrapper">
            <h1 class="main-heading">Hello World</h1>
            <p class="subtitle">Welcome to CurseCool</p>
        </div>
    </div>
</template>

<script setup>
// This is the default page component
</script>

<style scoped>
.page-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f3f4f6;
}

.content-wrapper {
    text-align: center;
}

.main-heading {
    font-size: 6rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 1rem;
}

.subtitle {
    font-size: 1.125rem;
    color: #4b5563;
}
</style>
