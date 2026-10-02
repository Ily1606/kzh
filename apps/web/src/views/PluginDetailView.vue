<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getPlugin } from '@/api/generated/endpoints'
import type { PluginResource } from '@/api/generated/model'
import { ArrowUpRight, Copy, Terminal, ShieldCheck, Eye, Star, FileText } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import PluginComments from '@/components/PluginComments.vue'

const route = useRoute()
const pluginId = route.params.id as string

const { pluginShow } = getPlugin()
const plugin = ref<PluginResource | null>(null)
const isLoading = ref(true)
const error = ref('')
const isStarred = ref(false)

const readmeHtml = ref('')
const isReadmeLoading = ref(false)
const readmeError = ref('')

function toRawGithubUrls(url: string): string[] {
  if (!url) return []
  const cleanUrl = url.trim()

  // Nếu đã là link raw hoặc file .md trực tiếp
  if (cleanUrl.includes('raw.githubusercontent.com') || cleanUrl.endsWith('.md')) {
    return [cleanUrl]
  }

  // Link blob cụ thể: https://github.com/:owner/:repo/blob/:branch/:filepath
  const blobMatch = cleanUrl.match(/^https?:\/\/github\.com\/([^/]+)\/([^/]+)\/blob\/([^/]+)\/(.+)$/)
  if (blobMatch) {
    const [, owner, repo, branch, path] = blobMatch
    return [`https://raw.githubusercontent.com/${owner}/${repo}/${branch}/${path}`]
  }

  // Link repository: https://github.com/:owner/:repo
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
}

async function fetchPlugin() {
  try {
    const res = await pluginShow(pluginId)
    const body = res.data as any
    plugin.value = body?.data?.plugin || body?.plugin || body
    if (plugin.value?.source_link) {
      fetchReadme(plugin.value.source_link)
    }
  } catch (err: any) {
    error.value = 'Failed to load plugin details.'
  } finally {
    isLoading.value = false
  }
}

async function copyInstallCommand() {
  if (plugin.value) {
    try {
      await navigator.clipboard.writeText(`npx dsh add ${plugin.value.name}`)
    } catch {
      // ignore
    }
  }
}

function toggleStar() {
  if (!plugin.value) return
  isStarred.value = !isStarred.value
  if (isStarred.value) {
    plugin.value.star_count = (plugin.value.star_count || 0) + 1
  } else {
    plugin.value.star_count = Math.max(0, (plugin.value.star_count || 1) - 1)
  }
  // TODO: Tích hợp API star/unstar plugin khi backend sẵn sàng:
  // await starPlugin(plugin.value.id)
}

function onCommentAdded() {
  if (plugin.value) {
    plugin.value.comment_count = (plugin.value.comment_count || 0) + 1
  }
}

onMounted(() => {
  fetchPlugin()
})
</script>

<template>
  <div class="mx-auto max-w-4xl py-12 px-4 sm:px-6">
    <div v-if="isLoading" class="text-center py-12">
      <div class="animate-pulse flex flex-col items-center">
        <div class="h-12 w-12 bg-muted rounded-full mb-4"></div>
        <div class="h-6 w-1/3 bg-muted rounded mb-2"></div>
        <div class="h-4 w-1/4 bg-muted rounded"></div>
      </div>
    </div>

    <div v-else-if="error" class="text-center py-12 text-destructive">
      {{ error }}
    </div>

    <div v-else-if="plugin" class="space-y-8">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-8 border-b">
        <div class="space-y-3">
          <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight">{{ plugin.name }}</h1>
            <span class="rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 px-2.5 py-1 text-xs font-semibold inline-flex items-center gap-1 border border-emerald-200 dark:border-emerald-800">
              <ShieldCheck class="size-3" />
              Verified
            </span>
          </div>

          <p class="text-lg text-muted-foreground">{{ plugin.title }}</p>

          <!-- Thông tin chung: Tác giả, License, Ngày cập nhật, Views và Stars -->
          <div class="flex flex-wrap items-center gap-y-2 gap-x-4 text-sm text-muted-foreground pt-1">
            <span>By <span class="font-medium text-foreground">{{ plugin.user_id?.substring(0, 8) || 'community' }}</span></span>
            <span>&bull;</span>
            <span>License: <span class="font-medium text-foreground">{{ plugin.license }}</span></span>
            <span>&bull;</span>
            <span>Updated: <span class="font-medium text-foreground">{{ plugin.updated_at ? plugin.updated_at.split('T')[0] : 'Unknown' }}</span></span>
            <span>&bull;</span>
            <span class="inline-flex items-center gap-1.5 text-foreground/80">
              <Eye class="size-4 text-muted-foreground" />
              <span class="font-medium text-foreground">{{ plugin.view_count || 0 }}</span> views
            </span>
            <span>&bull;</span>
            <span class="inline-flex items-center gap-1.5 text-foreground/80">
              <Star class="size-4 text-amber-500 fill-amber-500" />
              <span class="font-medium text-foreground">{{ plugin.star_count || 0 }}</span> stars
            </span>
          </div>
        </div>

        <div class="flex flex-col gap-3 min-w-[260px]">
          <!-- Install Command -->
          <div class="bg-muted/50 rounded-lg p-3 border border-border/50 font-mono text-sm flex items-center justify-between">
            <span class="flex items-center gap-2 truncate">
              <Terminal class="size-4 shrink-0 text-muted-foreground" />
              npx dsh add {{ plugin.name.split('/').pop() }}
            </span>
            <button @click="copyInstallCommand" class="text-muted-foreground hover:text-foreground transition-colors p-1" title="Copy command">
              <Copy class="size-4" />
            </button>
          </div>

          <!-- Nút Star & View Source -->
          <div class="flex items-center gap-2">
            <!-- Nút Star để sau này gắn API -->
            <Button
              variant="outline"
              class="flex-1 gap-2 transition-all cursor-pointer font-medium"
              :class="{
                'border-amber-400 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 hover:bg-amber-100': isStarred
              }"
              @click="toggleStar"
            >
              <Star
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

      <!-- Content: About this plugin / README -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold">About this plugin</h2>
          <span v-if="readmeHtml" class="text-xs text-muted-foreground flex items-center gap-1 font-mono">
            <FileText class="size-3.5" /> README.md
          </span>
        </div>

        <!-- Trạng thái đang tải README -->
        <div v-if="isReadmeLoading" class="p-12 rounded-xl border border-border/50 bg-muted/20 flex flex-col items-center justify-center space-y-3 text-muted-foreground">
          <div class="h-6 w-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
          <span class="text-sm">Loading README from repository...</span>
        </div>

        <!-- Hiển thị nội dung README Markdown đã parse an toàn -->
        <div
          v-else-if="readmeHtml"
          class="p-6 md:p-8 rounded-xl border border-border/60 bg-card shadow-sm prose dark:prose-invert max-w-none prose-headings:font-semibold prose-a:text-primary prose-a:underline hover:prose-a:text-primary/80 prose-pre:bg-muted prose-pre:border prose-pre:text-foreground prose-img:rounded-lg overflow-x-auto"
          v-html="readmeHtml"
        ></div>

        <!-- Trạng thái không có README hoặc không tải được -->
        <div v-else class="p-8 bg-muted/30 rounded-xl border border-border/50 min-h-[160px] flex flex-col items-center justify-center text-muted-foreground space-y-2">
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
