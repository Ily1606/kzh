<script setup lang="ts">
import { Moon, Sun } from "@lucide/vue";
import { Button } from "@/components/ui/button";
import { useTheme } from "@/composables/useTheme";

const { isDark, toggle } = useTheme();
</script>

<template>
  <Button
    variant="ghost"
    size="icon"
    class="relative text-muted-foreground"
    :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
    @click="toggle"
  >
    <Sun
      class="size-4 transition-all duration-300"
      :class="isDark ? 'rotate-0 scale-100 opacity-100' : 'rotate-90 scale-0 opacity-0'"
    />
    <Moon
      class="absolute size-4 transition-all duration-300"
      :class="isDark ? '-rotate-90 scale-0 opacity-0' : 'rotate-0 scale-100 opacity-100'"
    />
  </Button>
</template>
