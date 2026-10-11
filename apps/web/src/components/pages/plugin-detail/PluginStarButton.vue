<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { Loader2, Star } from 'lucide-vue-next'
import type { PluginResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useAuth } from '@/composables/useAuth'
import { usePluginsStore } from '@/stores'

const props = defineProps<{
  plugin: PluginResource
}>()

const auth = useAuth()
const route = useRoute()
const store = usePluginsStore()

const isStarred = computed(() => props.plugin.is_star === true)
const starCount = computed(() => props.plugin.star_count || 0)
const isStarring = computed(() => store.isStarring(props.plugin.id))

async function toggleStar() {
  if (!auth.isAuthenticated) {
    return;
  }

  await store.setStar(props.plugin.id, !isStarred.value)
}
</script>

<template>
  <!-- The star button is disabled for a guest -->
  <Popover v-if="!auth.isAuthenticated">
    <PopoverTrigger as-child>
      <span class="inline-flex flex-1">
        <Button variant="outline" disabled class="w-full gap-2 font-medium">
          <Star class="size-4 text-muted-foreground" />
          <span>Star</span>
          <span class="ml-1 rounded-full bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
            {{ starCount }}
          </span>
        </Button>
      </span>
    </PopoverTrigger>

    <PopoverContent>
      <p class="font-medium">Sign in to star this plugin</p>
      <p class="mt-1 text-sm text-muted-foreground">
        Starring needs an account so the count means something.
      </p>
      <RouterLink
        :to="{ name: 'login', query: { redirect: route.fullPath } }"
        class="mt-3 inline-flex text-sm font-medium text-primary hover:underline"
      >
        Sign in
      </RouterLink>
    </PopoverContent>
  </Popover>

  <Button
    v-else
    variant="outline"
    class="flex-1 cursor-pointer gap-2 font-medium transition-all"
    :disabled="isStarring"
    :aria-pressed="isStarred"
    :class="{
      'border-amber-400 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 hover:bg-amber-100': isStarred
    }"
    @click="toggleStar"
  >
    <Loader2 v-if="isStarring" class="size-4 animate-spin" />
    <Star
      v-else
      class="size-4 transition-transform duration-200"
      :class="isStarred ? 'scale-110 fill-amber-500 text-amber-500' : 'text-muted-foreground'"
    />
    <span>{{ isStarred ? 'Starred' : 'Star' }}</span>
    <span
      class="ml-1 rounded-full px-1.5 py-0.5 font-mono text-xs"
      :class="isStarred ? 'bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-100' : 'bg-muted text-muted-foreground'"
    >
      {{ starCount }}
    </span>
  </Button>
</template>
