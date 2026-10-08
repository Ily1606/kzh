<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import type { PluginResource } from '@/api/generated/model'
import { ArrowUpRight, Check, Copy, Terminal, Eye, Star, FileText, BookOpen, Scale, Calendar, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import { formatRelativeDate } from '@/utils/date'
import PluginComments from '@/components/pages/comment/PluginComments.vue'
import { useAuth } from '@/composables/useAuth'
import { usePluginsStore } from '@/stores'

const route = useRoute()
const pluginId = route.params.id as string

const auth = useAuth()
const store = usePluginsStore()

const plugin = computed<PluginResource | null>(() => store.findById(pluginId))
const isStarred = computed(() => plugin.value?.is_star === true)

const copied = ref(false)

const readmeHtml = ref('')
const isReadmeLoading = ref(false)
const readmeError = ref('')
const isReadmeExpanded = ref(false)
const isReadmeLong = ref(false)
const readmeContainer = ref<HTMLElement | null>(null)

function toRawGithubUrls(url: string): string[] {
  if (!url) return []
  const cleanUrl = url.trim()

  if (cleanUrl.includes('raw.githubusercontent.com') || cleanUrl.endsWith('.md')) {
    return [cleanUrl]
  }

  const blobMatch = cleanUrl.match(/^https?:\/\/github\.com\/([^/]+)\/([^/]+)\/blob\/([^/]+)\/(.+)$/)
  if (blobMatch) {
    const [, owner, repo, branch, path] = blobMatch
    return [`https://raw.githubusercontent.com/${owner}/${repo}/${branch}/${path}`]
  }

  const repoMatch = cleanUrl.match(/^https?:\/\/github\.com\/([^/]+)\/([^/]+?)(?:\.git|\/)?$/)
  if (repoMatch) {
    const [, owner, repo] = repoMatch
    return [
      `https://raw.githubusercontent.com/${owner}/${repo}/main/README.md`,
      `https://raw.githubusercontent.com/${owner}/${repo}/master/README.md`
    ]
  }

  return [cleanUrl]
}

async function fetchReadme(sourceUrl: string) {
  if (!sourceUrl) return
  isReadmeLoading.value = true
  readmeError.value = ''
  readmeHtml.value = ''

  const candidateUrls = toRawGithubUrls(sourceUrl)
  let markdownText = ''

  for (const candidate of candidateUrls) {
    try {
      const res = await fetch(candidate)
      if (res.ok) {
        markdownText = await res.text()
        break
      }
    } catch {
      // thử link kế tiếp nếu có
    }
  }

  if (markdownText) {
    try {
      const parsed = await marked.parse(markdownText)
      readmeHtml.value = DOMPurify.sanitize(parsed, { ADD_ATTR: ['target'] })
    } catch {
      readmeError.value = 'Failed to parse README markdown.'
    }
  } else {
    readmeError.value = 'No README available for this plugin.'
  }
  isReadmeLoading.value = false

  await nextTick()
  if (readmeContainer.value && readmeContainer.value.scrollHeight > 1200) {
    isReadmeLong.value = true
  }

  scrollToHash()
}

async function scrollToHash() {
  await nextTick()
  if (route.hash) {
    // We add a tiny timeout because Markdown might contain images that cause reflows right after render
    setTimeout(() => {
      const el = document.querySelector(route.hash)
      if (el) el.scrollIntoView({ behavior: 'smooth' })
    }, 100)
  }
}

async function copyInstallCommand() {
  if (plugin.value) {
    try {
      await navigator.clipboard.writeText(`npx dsh add ${plugin.value.name}`)
      copied.value = true
      setTimeout(() => {
        copied.value = false
      }, 2000)
    } catch {
      // ignore
    }
  }
}

async function toggleStar() {
  if (!plugin.value || !auth.isAuthenticated) return

  await store.setStar(plugin.value.id, !isStarred.value)
}

function onCommentAdded() {
  if (!plugin.value) return

  store.patchPlugin(plugin.value.id, {
    comment_count: (plugin.value.comment_count ?? 0) + 1,
  })
}

onMounted(async () => {
  // Costs nothing when an earlier page already cached this plugin, which is the
  // case for every arrival from the catalogue, trending or Resources. A hard
  // refresh or a shared link arrives with an empty store and pays one request.
  const loaded = await store.ensurePlugin(pluginId)

  if (loaded?.source_link) {
    fetchReadme(loaded.source_link)
  }

  scrollToHash()
})
</script>

<template>
  <div class="mx-auto max-w-4xl py-12 px-4 sm:px-6">
    <div v-if="store.isFetchingPlugin && !plugin" class="text-center py-12">
      <div class="animate-pulse flex flex-col items-center">
        <div class="h-12 w-12 bg-muted rounded-full mb-4"></div>
        <div class="h-6 w-1/3 bg-muted rounded mb-2"></div>
        <div class="h-4 w-1/4 bg-muted rounded"></div>
      </div>
    </div>

    <div v-else-if="store.fetchError && !plugin" class="text-center py-12 text-destructive">
      {{ store.fetchError }}
    </div>

    <div v-else-if="plugin" class="space-y-8">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-8 border-b">
        <div class="space-y-3">
          <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight">{{ plugin.name }}</h1>
          </div>

          <p class="text-lg text-muted-foreground">{{ plugin.title }}</p>

          <!-- Thông tin chung: Tác giả, License, Ngày cập nhật, Views và Stars -->
          <div class="@container grid grid-cols-1 items-center gap-y-2 gap-x-4 text-sm text-muted-foreground pt-1">
            <span class="flex items-center gap-x-2">
              <BookOpen class="size-5 text-muted-foreground" />
              <span>By: <span class="font-medium text-foreground">{{ plugin.author?.name }}</span></span>
            </span>
            <span class="flex items-center gap-x-2">
              <Scale class="size-5" />
              <span>{{ plugin.license }} license</span>
            </span>
            <span class="flex items-center gap-x-2">
              <Calendar class="size-5" />
              <span>{{ formatRelativeDate(plugin.updated_at) }}</span>
          </span>
            <span class="flex items-center gap-x-2">
              <Eye class="size-4 text-muted-foreground" />
              <span>{{ plugin.view_count || 0 }}</span> views
            </span>
            <span class="flex items-center gap-x-2">
              <Star class="size-4 text-muted-foreground" />
              <span>{{ plugin.star_count || 0 }}</span> stars
            </span>
          </div>
        </div>

        <div class="flex flex-col gap-3 min-w-65">
          <div class="bg-muted/50 rounded-lg p-3 border border-border/50 font-mono text-sm flex items-center justify-between">
            <span class="flex items-center gap-2 truncate">
              <Terminal class="size-4 shrink-0 text-muted-foreground" />
              npx dsh add {{ plugin.name.split('/').pop() }}
            </span>
            <button
              @click="copyInstallCommand"
              class="inline-flex items-center gap-1.5 rounded-md p-1 transition-colors"
              :class="copied ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
              :title="copied ? 'Copied' : 'Copy command'"
            >
              <Check v-if="copied" class="size-4" />
              <Copy v-else class="size-4" />
            </button>
          </div>

          <div class="flex items-center gap-2">
            <!--
              The star button is disabled for a guest, and `Button` carries
              `disabled:pointer-events-none` — so the disabled button cannot be
              the popover trigger and would swallow the click. `asChild` makes
              the span wrapping it the trigger instead.
            -->
            <Popover v-if="!auth.isAuthenticated">
              <PopoverTrigger as-child>
                <span class="inline-flex flex-1">
                  <Button
                    variant="outline"
                    disabled
                    class="w-full gap-2 font-medium"
                  >
                    <Star class="size-4 text-muted-foreground" />
                    <span>Star</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs font-mono bg-muted text-muted-foreground">
                      {{ plugin.star_count || 0 }}
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
              class="flex-1 gap-2 transition-all cursor-pointer font-medium"
              :disabled="store.isStarring(plugin.id)"
              :aria-pressed="isStarred"
              :class="{
                'border-amber-400 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 hover:bg-amber-100': isStarred
              }"
              @click="toggleStar"
            >
              <Loader2 v-if="store.isStarring(plugin.id)" class="size-4 animate-spin" />
              <Star
                v-else
                class="size-4 transition-transform duration-200"
                :class="isStarred ? 'fill-amber-500 text-amber-500 scale-110' : 'text-muted-foreground'"
              />
              <span>{{ isStarred ? 'Starred' : 'Star' }}</span>
              <span
                class="ml-1 px-1.5 py-0.5 rounded-full text-xs font-mono"
                :class="isStarred ? 'bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-100' : 'bg-muted text-muted-foreground'"
              >
                {{ plugin.star_count || 0 }}
              </span>
            </Button>

            <!-- Nút View Source -->
            <a
              :href="plugin.source_link"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex justify-center items-center gap-1.5 flex-1 px-4 py-2 bg-primary text-primary-foreground hover:bg-primary/90 rounded-md font-medium text-sm transition-colors shadow-sm"
            >
              Source <ArrowUpRight class="size-4" />
            </a>
          </div>
        </div>
      </div>

      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold">About this plugin</h2>
          <span v-if="readmeHtml" class="text-xs text-muted-foreground flex items-center gap-1 font-mono">
            <FileText class="size-3.5" /> README.md
          </span>
        </div>

        <div v-if="isReadmeLoading" class="p-12 rounded-xl border border-border/50 bg-muted/20 flex flex-col items-center justify-center space-y-3 text-muted-foreground">
          <div class="h-6 w-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
          <span class="text-sm">Loading README from repository...</span>
        </div>

        <div v-else-if="readmeHtml" class="relative group">
          <div
            ref="readmeContainer"
            class="p-6 md:p-8 rounded-xl border border-border/60 bg-card shadow-sm prose dark:prose-invert max-w-none prose-headings:font-semibold prose-a:text-primary prose-a:underline hover:prose-a:text-primary/80 prose-pre:bg-muted prose-pre:border prose-pre:text-foreground prose-img:rounded-lg transition-all duration-300 relative"
            :style="isReadmeLong && !isReadmeExpanded ? { maxHeight: '1200px', overflow: 'hidden' } : { overflowX: 'auto' }"
            v-html="readmeHtml"
          ></div>

          <div
            v-if="isReadmeLong && !isReadmeExpanded"
            class="absolute bottom-0 inset-x-0 h-40 bg-linear-to-t from-background via-background/80 to-transparent flex items-end justify-center pb-6 rounded-b-xl"
          >
            <Button
              variant="outline"
              size="sm"
              @click="isReadmeExpanded = true"
              class="rounded-full bg-background/95 backdrop-blur-sm shadow-md border-border/80 hover:bg-muted font-medium px-6"
            >
              Read full documentation
            </Button>
          </div>

          <!-- Collapse button -->
          <div v-else-if="isReadmeLong && isReadmeExpanded" class="flex justify-center mt-4">
            <Button
              variant="ghost"
              size="sm"
              @click="() => { isReadmeExpanded = false; scrollToHash() }"
              class="text-muted-foreground hover:text-foreground"
            >
              Show less
            </Button>
          </div>
        </div>

        <div v-else class="p-8 bg-muted/30 rounded-xl border border-border/50 min-h-40 flex flex-col items-center justify-center text-muted-foreground space-y-2">
          <FileText class="size-8 stroke-[1.5] text-muted-foreground/60" />
          <p class="text-sm">{{ readmeError || 'No README available for this plugin.' }}</p>
        </div>
      </section>

      <!-- Comments Section -->
      <PluginComments
        :plugin-id="plugin.id"
        :total-comment-count="plugin.comment_count"
        @comment-added="onCommentAdded"
      />
    </div>
  </div>
</template>
