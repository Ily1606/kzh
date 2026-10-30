<script setup lang="ts">
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { ArrowUpRight, Boxes, Check, Download, ShieldCheck } from "@lucide/vue";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import Author from "@/components/common/Author.vue";
import type { PluginResource } from "@/api/generated/model";
import { formatRelativeDate } from '@/utils/date';

const props = defineProps<{
  plugin?: PluginResource;
  fallback?: {
    id: string;
    name: string;
    title: string;
    icon: string;
    version: string;
    author: string;
    tag: string;
  };
  rank?: number;
}>();

const authorName = computed(() => props.plugin?.author?.name ?? "community");
const authorAvatar = computed(() => props.plugin?.author?.avatar_url ?? null);
const authorUsername = computed(() => props.plugin?.author?.username ?? null);

const router = useRouter();
const copied = ref(false);

async function copyPluginCommand(pluginName: string, event?: Event) {
  event?.stopPropagation();
  try {
    await navigator.clipboard.writeText(`npx dsh add ${pluginName}`);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch {
    // ignore
  }
}

function goToDetail(username: string | undefined, pluginName: string | undefined) {
  if (username && pluginName) {
    router.push({ name: 'plugin-detail', params: { username, pluginName } });
  }
}

const rankClasses = computed(() => {
  const rank = props.rank;
  if (!rank) return '';
  const base = 'inline-flex size-6 shrink-0 items-center justify-center rounded-full font-mono text-xs font-normal ring-1 ring-inset';
  if (rank === 1) return `${base} bg-amber-100 text-amber-900 ring-amber-400 dark:bg-amber-400 dark:text-amber-950 dark:ring-amber-300`;
  if (rank === 2) return `${base} bg-slate-200 text-slate-800 ring-slate-400 dark:bg-slate-200 dark:text-slate-900 dark:ring-slate-100`;
  if (rank === 3) return `${base} bg-orange-100 text-orange-900 ring-orange-400 dark:bg-orange-400 dark:text-orange-950 dark:ring-orange-300`;
  return `${base} bg-muted text-muted-foreground ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700`;
});

const rankLabel = computed(() => props.rank ? `#${props.rank}` : '');

const rankTopRightClasses = computed(() => {
  const rank = props.rank;
  if (!rank) return '';
  if (rank === 1) return 'absolute -top-2.5 right-4 px-2.5 py-0.5 rounded-full bg-linear-to-r from-amber-300 via-yellow-400 to-amber-500 text-amber-950 font-mono text-xs font-black tracking-wider shadow-sm shadow-amber-500/30 ring-2 ring-background border border-amber-300 select-none z-10';
  if (rank === 2) return 'absolute -top-2.5 right-4 px-2.5 py-0.5 rounded-full bg-linear-to-r from-slate-100 via-slate-200 to-slate-400 text-slate-800 font-mono text-xs font-black tracking-wider shadow-sm shadow-slate-400/20 ring-2 ring-background border border-slate-300/80 select-none z-10';
  if (rank === 3) return 'absolute -top-2.5 right-4 px-2.5 py-0.5 rounded-full bg-linear-to-r from-amber-500 via-orange-600 to-amber-700 text-white font-mono text-xs font-black tracking-wider shadow-sm shadow-orange-600/25 ring-2 ring-background border border-amber-400/50 select-none z-10';
  return '';
});
</script>

<template>
  <!-- Main Plugin Card -->
  <Card
    v-if="plugin"
    @click="goToDetail(plugin.author?.username, plugin.name)"
    class="relative gap-0 py-4.5 group px-4 sm:px-5 transition-all duration-200 hover:-translate-y-1 flex flex-col h-full min-w-65 sm:min-w-70 max-w-85 w-full shrink-0 snap-start cursor-pointer"
    :class="{
      'border-amber-400/50 hover:border-amber-400 hover:shadow-xl hover:shadow-amber-500/10': rank === 1,
      'border-slate-300 dark:border-slate-600 hover:border-slate-400 hover:shadow-xl hover:shadow-slate-400/10': rank === 2,
      'border-amber-600/40 hover:border-amber-600 hover:shadow-xl hover:shadow-orange-500/10': rank === 3,
      'hover:border-primary/35 hover:shadow-lg hover:shadow-primary/5': !rank,
    }"
  >
    <!-- Rank 1: Gold -->
    <div
      v-if="rank === 1"
      :class="rankTopRightClasses"
    >
      #1
    </div>

    <!-- Rank 2: Silver -->
    <div
      v-else-if="rank === 2"
      :class="rankTopRightClasses"
    >
      #2
    </div>

    <!-- Rank 3: Bronze -->
    <div
      v-else-if="rank === 3"
      :class="rankTopRightClasses"
    >
      #3
    </div>

    <!-- Category/Tag Badges -->
    <div v-if="plugin.category || plugin.tags.length" class="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-2">
      <span class="flex items-center gap-x-1 rounded-full bg-accent text-accent-foreground px-2 py-0.5 text-xs font-medium border border-primary/20">
        <ShieldCheck class="size-3" />
        Manifest checked
      </span>
      <span
        v-if="plugin.category"
        class="rounded-full bg-muted text-muted-foreground px-2 py-0.5 text-xs font-medium border border-border"
      >{{ plugin.category }}</span>
    </div>

    <!-- Name & Title -->
    <div class="mb-2">
      <h3 class="truncate font-mono text-sm font-medium text-foreground group-hover:text-primary transition-colors" :title="plugin.name">
        {{ plugin.name }}
      </h3>
      <p class="mt-1 truncate max-w-xs text-xs text-muted-foreground">{{ plugin.title }}</p>
    </div>

    <!-- Tags -->
    <div v-if="plugin.tags.length" class="mt-2 flex flex-wrap gap-1">
      <span
        v-for="tag in plugin.tags"
        :key="tag"
        class="rounded-full bg-muted border border-border/50 px-2 py-0.5 text-xs font-medium text-muted-foreground"
      >{{ tag }}</span>
    </div>

    <!-- Meta Row: License, Published, Author -->
    <div class="mt-3 flex flex-col gap-2 text-xs text-muted-foreground">
      <!-- License & Published (same line) -->
      <div class="flex items-center gap-4 flex-wrap">
        <span class="flex items-baseline gap-1">
          <span class="font-semibold">License:</span>
          <span>{{ plugin.license }}</span>
        </span>
        <span class="flex items-baseline gap-1">
          <span class="font-semibold">Published:</span>
          <span>{{ formatRelativeDate(plugin.approved_at) }}</span>
        </span>
      </div>
      <!-- Author with By: label -->
      <div class="flex items-center gap-2">
        <span class="font-semibold">By:</span>
        <Author
          :name="authorName"
          :src="authorAvatar"
          :username="authorUsername"
          size="sm"
          class="shrink-0"
        />
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-3 z-10">
      <a
        v-if="plugin.source_link"
        :href="plugin.source_link"
        target="_blank"
        rel="noopener noreferrer"
        class="text-xs sm:text-sm font-semibold text-primary hover:underline inline-flex items-center gap-1 cursor-pointer"
        @click.stop
      >
        View source <ArrowUpRight class="size-3" />
      </a>
      <Button
        size="sm"
        class="rounded-lg text-xs font-semibold px-3 h-8 shadow-sm cursor-pointer inline-flex items-center gap-1"
        @click.stop="copyPluginCommand(plugin.name, $event)"
      >
        <Check v-if="copied" class="size-3.5" />
        <Download v-else class="size-3.5" />
        <span>{{ copied ? 'Copied' : 'Copy install' }}</span>
      </Button>
    </div>
  </Card>

  <!-- Fallback/Mock Card when API is not available -->
  <Card
    v-else-if="fallback"
    class="relative gap-0 py-4.5 group px-4 sm:px-5 transition-all duration-200 hover:-translate-y-1 flex flex-col h-full min-w-65 sm:min-w-70 max-w-85 w-full shrink-0 snap-start"
    :class="{
      'border-amber-400/50 hover:border-amber-400 hover:shadow-xl hover:shadow-amber-500/10': rank === 1,
      'border-slate-300 dark:border-slate-600 hover:border-slate-400 hover:shadow-xl hover:shadow-slate-400/10': rank === 2,
      'border-amber-600/40 hover:border-amber-600 hover:shadow-xl hover:shadow-orange-500/10': rank === 3,
      'hover:border-primary/35 hover:shadow-lg hover:shadow-primary/5': !rank,
    }"
  >
    <div v-if="rank === 1" :class="rankTopRightClasses">#1</div>
    <div v-else-if="rank === 2" :class="rankTopRightClasses">#2</div>
    <div v-else-if="rank === 3" :class="rankTopRightClasses">#3</div>

    <div class="flex items-start justify-between">
      <span class="grid size-9 place-items-center rounded-lg bg-primary/10 text-primary">
        <Boxes v-if="fallback.icon === 'boxes'" class="size-4.5" />
        <ShieldCheck v-else-if="fallback.icon === 'shield'" class="size-4.5" />
        <Search v-else-if="fallback.icon === 'search'" class="size-4.5" />
        <Boxes v-else class="size-4.5" />
      </span>
      <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ fallback.version }}</span>
    </div>
    <div class="mt-2.5">
      <h3 class="truncate font-mono text-sm font-medium text-foreground">{{ fallback.name }}</h3>
      <p class="mt-1 truncate max-w-xs text-xs text-muted-foreground">{{ fallback.title }}</p>
    </div>
    <div class="mt-2.5 text-xs text-muted-foreground space-y-0.5">
      <div class="flex items-baseline gap-1">
        <span class="font-semibold">License:</span>
        <span>MIT</span>
      </div>
      <div class="flex items-baseline gap-1">
        <span class="font-semibold">Published:</span>
        <span>{{ formatRelativeDate('2026-10-01T00:00:00Z') }}</span>
      </div>
    </div>
    <div class="mt-auto flex items-center justify-between border-t pt-3 text-xs text-muted-foreground">
      <span>by {{ fallback.author }}</span>
      <span class="inline-flex items-center gap-1"><Download class="size-3" /> {{ fallback.tag }}</span>
    </div>
  </Card>
</template>
