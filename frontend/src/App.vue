<script setup>
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;
</script>

<template>
  <main>
    <h1>Todo Full Stack</h1>
    <p>Vue 3 前端服務已啟動。</p>
    <p>API: {{ apiBaseUrl }}</p>
  </main>
</template>
