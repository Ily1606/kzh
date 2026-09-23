<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { HealthResponse } from "@dsh/shared";
import { api } from "@/services/api";
import Card from "@/components/ui/Card.vue";

const health = ref<HealthResponse | null>(null);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    health.value = await api.get<HealthResponse>('/api/v1/health');
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Unknown error";
  }
});
</script>

<template>
  <div class="flex flex-col items-center justify-center space-y-8 py-12 text-center">
    <div>
      <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl md:text-6xl dark:text-gray-100">
        Welcome to <span class="text-primary">DSH</span>
      </h1>
      <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-500 dark:text-gray-400">
        A modern monorepo setup with Vue 3, Vite, Tailwind CSS v4, and Laravel API.
      </p>
    </div>

    <Card class="w-full max-w-md p-6 text-left">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Backend Status</h2>
      <div class="mt-4 flex items-center space-x-3">
        <template v-if="health">
          <span class="relative flex h-3 w-3">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
            <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
          </span>
          <span class="text-sm font-medium text-green-600 dark:text-green-400">
            Online ({{ health.status }})
          </span>
        </template>

        <template v-else-if="error">
          <span class="relative flex h-3 w-3">
            <span class="relative inline-flex h-3 w-3 rounded-full bg-red-500"></span>
          </span>
          <span class="text-sm font-medium text-red-600 dark:text-red-400">
            Offline: {{ error }}
          </span>
        </template>

        <template v-else>
          <span class="relative flex h-3 w-3">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-yellow-400 opacity-75"></span>
            <span class="relative inline-flex h-3 w-3 rounded-full bg-yellow-500"></span>
          </span>
          <span class="text-sm font-medium text-yellow-600 dark:text-yellow-400">
            Checking...
          </span>
        </template>
      </div>
    </Card>
  </div>
</template>
