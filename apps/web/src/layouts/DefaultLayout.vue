<script setup lang="ts">
import AppNavbar from '@/components/common/AppNavbar.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <AppNavbar />
    <main class="flex-1">
      <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <slot></slot>
      </div>
    </main>
  </div>
</template>

