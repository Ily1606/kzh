<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { Loader2, Star } from '@lucide/vue'
import type { PluginResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useAuth } from '@/composables/useAuth'
import { usePluginsStore } from '@/stores'

const props = withDefaults(
  defineProps<{
    plugin: PluginResource
    size?: 'default' | 'sm' | 'lg' | 'icon'
    fullWidth?: boolean
    showCount?: boolean
  }>(),
  {
    size: 'default',
    fullWidth: true,
    showCount: true,
  }
)

const auth = useAuth()
const route = useRoute()
const store = usePluginsStore()

const livePlugin = computed(() => store.findById(props.plugin.id) ?? props.plugin)
const isStarred = computed(() => livePlugin.value.is_star === true)
const starCount = computed(() => livePlugin.value.star_count || 0)
const isStarring = computed(() => store.isStarring(props.plugin.id))

async function toggleStar() {
  if (!auth.isAuthenticated) {
    return;
  }

  await store.setStar(props.plugin.author.username, props.plugin.name, !isStarred.value)
}
</script>

<template>
  <!-- The star button is disabled for a guest -->
  <Popover v-if="!auth.isAuthenticated">
    <PopoverTrigger as-child data-test="star-popover-trigger">
      <span class="inline-flex" :class="{ 'flex-1': fullWidth }">
        <Button
          variant="outline"
          :size="size"
          disabled
          class="gap-1.5 font-semibold text-foreground/80"
          data-test="star-button"
          :class="[
            fullWidth ? 'w-full' : '',
            size === 'sm' ? 'h-8 px-2.5 text-xs' : 'gap-2'
          ]"
        >
          <Star :class="size === 'sm' ? 'size-3.5' : 'size-4'" class="text-foreground/70" />
          <span class="font-bold">Star</span>
          <span
            v-if="showCount"
            class="ml-1 rounded-full bg-muted-foreground/15 px-1.5 py-0.5 font-mono text-xs font-bold text-foreground"
          >
            {{ starCount }}
          </span>
        </Button>
      </span>
    </PopoverTrigger>

    <PopoverContent @click.stop data-test="star-popover-content">
      <p class="font-bold text-foreground">Sign in to star this plugin</p>
      <p class="mt-1 text-sm font-medium text-foreground/80">
        Starring needs an account so the count means something.
      </p>
      <RouterLink
        :to="{ name: 'login', query: { redirect: route.fullPath } }"
        class="mt-3 inline-flex text-sm font-bold text-primary hover:underline"
      >
        Sign in
      </RouterLink>
    </PopoverContent>
  </Popover>

  <Button
    v-else
    variant="outline"
    :size="size"
    class="cursor-pointer gap-1.5 font-semibold text-foreground transition-all"
    data-test="star-button"
    :disabled="isStarring"
    :aria-pressed="isStarred"
    :class="[
      fullWidth ? 'flex-1' : '',
      size === 'sm' ? 'h-8 px-2.5 text-xs' : 'gap-2',
      {
        'border-amber-400 bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-950/50': isStarred
      }
    ]"
    @click="toggleStar"
  >
    <Loader2 v-if="isStarring" :class="size === 'sm' ? 'size-3.5' : 'size-4'" class="animate-spin" />
    <Star
      v-else
      :class="[
        size === 'sm' ? 'size-3.5' : 'size-4',
        isStarred ? 'scale-110 fill-amber-500 text-amber-500' : 'text-foreground/80'
      ]"
      class="transition-transform duration-200"
    />
    <span class="font-bold">{{ isStarred ? 'Starred' : 'Star' }}</span>
    <span
      v-if="showCount"
      class="ml-1 rounded-full px-1.5 py-0.5 font-mono text-xs font-bold"
      :class="isStarred ? 'bg-amber-200 text-amber-950 dark:bg-amber-900 dark:text-amber-100' : 'bg-muted-foreground/15 text-foreground'"
    >
      {{ starCount }}
    </span>
  </Button>
</template>
