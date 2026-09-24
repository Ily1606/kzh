<script setup lang="ts">
import AppNavbar from '@/components/common/AppNavbar.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col bg-background">
    <AppNavbar />
    <main class="flex-1">
      <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><slot /></div>
    </main>
  </div>
</template>
