<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { Button } from '@/components/ui/button'
</script>

<template>
  <div class="flex min-h-[60vh] flex-col items-center justify-center text-center">
    <p class="font-mono text-sm text-muted-foreground">404</p>
    <h1 class="mt-3 text-2xl font-semibold tracking-tight">Page not found</h1>
    <p class="mt-2 text-sm text-muted-foreground">This page isn't in the registry.</p>
    <Button as-child class="mt-6"><RouterLink to="/">Browse plugins</RouterLink></Button>
  </div>
</template>

