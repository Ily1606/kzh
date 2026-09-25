<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { HealthResponse } from "@dsh/shared";
import { apiGet } from "../api/client";

const health = ref<HealthResponse | null>(null);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    health.value = await apiGet<HealthResponse>("/api/v1/health");
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Unknown error";
  }
});
</script>

<template>
  <main>
    <h1>DSH</h1>
    <p v-if="health">API status: {{ health.status }}</p>
    <p v-else-if="error">Failed to reach API: {{ error }}</p>
    <p v-else>Checking API…</p>
  </main>
</template>
