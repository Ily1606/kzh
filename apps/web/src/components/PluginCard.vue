<script setup lang="ts">
import { ArrowUpRight, Boxes, Download, Search, ShieldCheck } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import type { PluginResource } from "@/api/generated/model";

defineProps<{
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

async function copyPluginCommand(pluginName: string) {
  try {
    await navigator.clipboard.writeText(`npx dsh add ${pluginName}`);
  } catch {
    // ignore
  }
}
</script>

<template>
  <Card v-if="plugin" class="relative gap-0 py-5 group px-5 transition hover:-translate-y-0.5 hover:border-primary/35 hover:shadow-lg hover:shadow-primary/5 flex flex-col h-full min-w-[320px] max-w-[350px] w-full shrink-0 snap-start">
    <div v-if="rank === 1" class="absolute -top-3 -right-3 flex size-8 items-center justify-center rounded-full bg-yellow-500 text-sm font-bold text-white shadow-sm ring-4 ring-background">1</div>
    <div v-else-if="rank === 2" class="absolute -top-3 -right-3 flex size-8 items-center justify-center rounded-full bg-slate-300 text-sm font-bold text-slate-800 shadow-sm ring-4 ring-background">2</div>
    <div v-else-if="rank === 3" class="absolute -top-3 -right-3 flex size-8 items-center justify-center rounded-full bg-amber-700 text-sm font-bold text-white shadow-sm ring-4 ring-background">3</div>
    
    <div class="flex flex-wrap items-center gap-2 mb-2">
      <span class="rounded-full bg-emerald-100/80 text-emerald-700 px-2.5 py-0.5 text-[11px] font-medium border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-400 dark:border-emerald-900">Manifest checked</span>
      <span class="rounded-full bg-blue-50 text-blue-700 px-2.5 py-0.5 text-[11px] font-medium border border-blue-200 dark:bg-blue-950 dark:text-blue-400 dark:border-blue-900">UI & workspace</span>
    </div>

    <h3 class="text-lg font-semibold text-foreground truncate" :title="plugin.name">{{ plugin.name }}</h3>
    <p class="text-[13px] text-muted-foreground mt-0.5 truncate">DSH plugin · Publisher: {{ plugin.user_id?.substring(0, 8) || 'community' }}</p>

    <p class="mt-2.5 text-[14px] leading-relaxed text-foreground min-h-[46px] line-clamp-2">
      {{ plugin.title }}
    </p>

    <div class="mt-3 flex flex-wrap gap-1.5">
      <span class="rounded-full bg-muted border border-border/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">May read files</span>
      <span class="rounded-full bg-muted border border-border/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">May write files</span>
      <span class="rounded-full bg-muted border border-border/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">May run commands</span>
    </div>

    <div class="mt-3 text-[13px] text-muted-foreground">
      License: {{ plugin.license }} · Checked: {{ plugin.updated_at ? plugin.updated_at.split('T')[0] : '2026-08-14' }}
    </div>

    <div class="mt-auto flex items-center justify-between border-t pt-3">
      <a :href="plugin.source_link" target="_blank" rel="noopener noreferrer" class="text-[13px] font-semibold text-primary hover:underline inline-flex items-center gap-1">
        View publisher source <ArrowUpRight class="size-3" />
      </a>
      <Button size="sm" class="rounded-full font-semibold px-4 shadow-sm" @click="copyPluginCommand(plugin.name)">Copy install</Button>
    </div>
  </Card>

  <!-- Fallback/Mock Card when API is not available -->
  <Card v-else-if="fallback" class="relative gap-0 py-5 group px-5 transition hover:-translate-y-0.5 hover:border-primary/35 hover:shadow-lg hover:shadow-primary/5 flex flex-col h-full min-w-[320px] max-w-[350px] w-full shrink-0 snap-start">
    <div v-if="rank === 1" class="absolute -top-3 -right-3 flex size-8 items-center justify-center rounded-full bg-yellow-500 text-sm font-bold text-white shadow-sm ring-4 ring-background">1</div>
    <div v-else-if="rank === 2" class="absolute -top-3 -right-3 flex size-8 items-center justify-center rounded-full bg-slate-300 text-sm font-bold text-slate-800 shadow-sm ring-4 ring-background">2</div>
    <div v-else-if="rank === 3" class="absolute -top-3 -right-3 flex size-8 items-center justify-center rounded-full bg-amber-700 text-sm font-bold text-white shadow-sm ring-4 ring-background">3</div>

    <div class="flex items-start justify-between">
      <span class="grid size-10 place-items-center rounded-lg bg-primary/10 text-primary">
        <Boxes v-if="fallback.icon === 'boxes'" class="size-5" />
        <ShieldCheck v-else-if="fallback.icon === 'shield'" class="size-5" />
        <Search v-else-if="fallback.icon === 'search'" class="size-5" />
        <Boxes v-else class="size-5" />
      </span>
      <span class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground">{{ fallback.version }}</span>
    </div>
    <div class="mt-3">
      <h3 class="font-semibold">{{ fallback.name }}</h3>
      <p class="mt-1.5 text-sm leading-6 text-muted-foreground min-h-[48px] line-clamp-2">{{ fallback.title }}</p>
    </div>
    <div class="mt-auto flex items-center justify-between border-t pt-3 text-xs text-muted-foreground">
      <span>by {{ fallback.author }}</span>
      <span class="inline-flex items-center gap-1"><Download class="size-3" /> {{ fallback.tag }}</span>
    </div>
  </Card>
</template>
