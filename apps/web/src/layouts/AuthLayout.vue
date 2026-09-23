<script setup lang="ts">
// Auth layout puts content in the center
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4 dark:bg-[#111115]">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold tracking-tight text-primary">DSH</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Sign in to your account</p>
      </div>
      <slot />
    </div>
  </div>
</template>

