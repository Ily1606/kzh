<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { Motion } from 'motion-v'
import { ArrowLeft, Compass } from '@lucide/vue'
import { Button } from '@/components/ui/button'
</script>

<template>
  <div class="relative isolate flex min-h-[70vh] flex-col items-center justify-center overflow-hidden py-20 text-center">
    <div class="pointer-events-none absolute inset-0 -z-10">
      <div class="aurora absolute inset-0 animate-aurora-pan opacity-60" />
      <div
        class="grid-dots absolute inset-0 opacity-30 [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,black,transparent)]"
      />
    </div>

    <Motion
      :initial="{ opacity: 0, y: 16 }"
      :animate="{ opacity: 1, y: 0 }"
      :transition="{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }"
    >
      <!-- The 404 is the page's only real content, so it is set large and
           gradient-filled rather than tucked away as a small mono label. -->
      <p class="gradient-text font-mono text-6xl font-bold tracking-tighter sm:text-7xl">404</p>
      <h1 class="mt-4 text-2xl font-semibold tracking-tight">Page not found</h1>
      <p class="mt-2 text-sm text-muted-foreground">This page isn't in the registry.</p>

      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <Button as-child size="lg">
          <RouterLink to="/plugins">
            <Compass /> Browse plugins
          </RouterLink>
        </Button>
        <Button as-child size="lg" variant="outline">
          <RouterLink to="/">
            <ArrowLeft /> Back to home
          </RouterLink>
        </Button>
      </div>
    </Motion>
  </div>
</template>
