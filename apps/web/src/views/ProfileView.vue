<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { AlertCircle, LoaderCircle, PackageOpen, UserRound } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import AuthorSummary from '@/components/pages/profile/AuthorSummary.vue'
import AuthorPluginRow from '@/components/pages/profile/AuthorPluginRow.vue'
import { useAuthorProfile } from '@/composables/useAuthorProfile'

const route = useRoute()
const router = useRouter()

const {
  author,
  plugins,
  isLoadingProfile,
  isLoadingPlugins,
  notFound,
  error,
  currentPage,
  lastPage,
  total,
  loadProfile,
  loadPlugins,
  retry,
} = useAuthorProfile()

const username = computed(() => {
  const raw = route.params.username

  return Array.isArray(raw) ? (raw[0] ?? '') : (raw ?? '')
})

const page = computed(() => {
  const raw = route.query.page
  const parsed = Number.parseInt(Array.isArray(raw) ? (raw[0] ?? '') : (raw ?? ''), 10)

  return Number.isNaN(parsed) || parsed < 1 ? 1 : parsed
})

const isLoading = computed(() => isLoadingProfile.value || isLoadingPlugins.value)

// Load author profile
watch(username, async (name) => {
  if (!name) {
    return
  }

  await loadProfile(name)
}, { immediate: true })

// Load author's plugins
watch([username, page], async ([name, targetPage]) => {
  if (!name) {
    return
  }

  await loadPlugins(name, targetPage)
}, { immediate: true })

async function goToPage(target: number) {
  await router.replace({
    name: 'author-profile',
    params: { username: username.value },
    query: target > 1 ? { page: String(target) } : {},
  })

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

</script>

<template>
  <div class="py-12">
    <!-- Loading -->
    <div v-if="isLoading && !author" class="flex flex-col items-center justify-center py-20">
      <LoaderCircle class="size-10 animate-spin text-primary mb-4" />
      <p class="text-muted-foreground text-sm">Loading profile...</p>
    </div>

    <!-- No such author -->
    <div v-else-if="notFound" class="glass py-16 text-center rounded-2xl p-8 space-y-3">
      <div class="mx-auto size-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
        <UserRound class="size-6" />
      </div>
      <h1 class="text-lg font-semibold">Profile not found</h1>
      <p class="text-sm text-muted-foreground max-w-sm mx-auto">
        There is no publisher with this username.
      </p>
      <Button as-child variant="outline" size="sm">
        <RouterLink to="/plugins">Browse plugins</RouterLink>
      </Button>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="glass py-16 text-center rounded-2xl p-8 space-y-3">
      <div class="mx-auto size-12 rounded-full bg-destructive/10 flex items-center justify-center text-destructive">
        <AlertCircle class="size-6" />
      </div>
      <h1 class="text-lg font-semibold">Something went wrong</h1>
      <p class="text-sm text-muted-foreground max-w-sm mx-auto">{{ error }}</p>
      <Button variant="outline" size="sm" @click="retry(username, page)">Try again</Button>
    </div>

    <div v-else-if="author" class="grid gap-8 md:grid-cols-[16rem_minmax(0,1fr)]">
      <!-- Author -->
      <AuthorSummary :author="author" :plugins-count="total" />

      <!-- Published plugins -->
      <section class="min-w-0">
        <!-- Loading -->
        <div v-if="isLoadingPlugins" class="flex flex-col items-center justify-center py-16">
          <LoaderCircle class="size-8 animate-spin text-primary mb-3" />
          <p class="text-muted-foreground text-sm">Loading plugins...</p>
        </div>

        <!-- Nothing published yet -->
        <div v-else-if="plugins.length === 0" class="glass py-16 text-center rounded-2xl p-8 space-y-3">
          <div class="mx-auto size-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
            <PackageOpen class="size-6" />
          </div>
          <h3 class="text-base font-semibold">No published plugins yet</h3>
          <p class="text-sm text-muted-foreground max-w-sm mx-auto">
            This publisher has not published anything so far.
          </p>
        </div>

        <ul v-else class="glass rounded-2xl overflow-hidden">
          <AuthorPluginRow v-for="plugin in plugins" :key="plugin.id" :plugin="plugin" />
        </ul>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="pt-6 flex items-center justify-center gap-4">
          <Button
            variant="outline"
            :disabled="currentPage === 1 || isLoadingPlugins"
            @click="goToPage(currentPage - 1)"
          >
            Previous
          </Button>
          <span class="text-sm font-medium text-muted-foreground">
            Page {{ currentPage }} of {{ lastPage }}
          </span>
          <Button
            variant="outline"
            :disabled="currentPage === lastPage || isLoadingPlugins"
            @click="goToPage(currentPage + 1)"
          >
            Next
          </Button>
        </div>
      </section>
    </div>
  </div>
</template>
