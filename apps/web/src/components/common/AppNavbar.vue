<script setup lang="ts">
import { RouterLink } from 'vue-router'
// Note: Normally you would import useAuth here
// import { useAuth } from '@/composables/useAuth'
// const auth = useAuth()
</script>

<template>
  <nav class="sticky top-0 z-40 w-full border-b border-gray-200 bg-white/80 backdrop-blur-sm dark:border-gray-800 dark:bg-[#16171d]/80">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
      <div class="flex items-center gap-6">
        <RouterLink to="/" class="flex items-center gap-2">
          <span class="text-xl font-bold tracking-tight text-primary">DSH</span>
        </RouterLink>
        <div class="hidden space-x-4 md:flex">
          <RouterLink to="/" class="text-sm font-medium text-gray-700 hover:text-primary dark:text-gray-300 dark:hover:text-primary">
            Home
          </RouterLink>
        </div>
      </div>

      <div class="flex items-center gap-4">
        <!-- We can dynamically show this based on auth store later -->
        <RouterLink to="/login" class="text-sm font-medium text-gray-700 hover:text-primary dark:text-gray-300 dark:hover:text-primary">
          Sign in
        </RouterLink>
      </div>
    </div>
  </nav>
</template>
