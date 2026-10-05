<script setup lang="ts">
import { computed, ref } from "vue";
import { UserRound } from "lucide-vue-next";
import { notifyError } from "@/utils/toast";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";

const props = withDefaults(defineProps<{
  /** URL ảnh hiện tại. Rỗng thì hiện icon fallback. */
  src?: string;
  alt?: string;
  uploading?: boolean;
  /** Giới hạn dung lượng theo spec backend (UpdateAvatarRequest: 2048 kilobytes) */
  maxSizeKb?: number;
  types?: string[];
}>(), {
  src: "",
  alt: "",
  uploading: false,
  maxSizeKb: 2048,
  types: () => ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
});

const emit = defineEmits<{ select: [file: File] }>();

const fileInput = ref<HTMLInputElement | null>(null);

// "image/png" -> "PNG", hiện "PNG / JPEG / GIF / WEBP"
const typeLabel = computed(() =>
  props.types.map((t) => t.replace('image/', '').toUpperCase()).join(' / '),
);

function onSelected(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  // Cho phép chọn lại đúng file vừa bị lỗi
  input.value = '';
  if (!file) return;

  if (!props.types.includes(file.type)) {
    notifyError(`Avatar must be a ${typeLabel.value} image.`);
    return;
  }
  if (file.size > props.maxSizeKb * 1024) {
    notifyError(`Avatar must be smaller than ${props.maxSizeKb} KB.`);
    return;
  }

  emit('select', file);
}
</script>

<template>
  <div class="flex items-center gap-4">
    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-full bg-muted">
      <img v-if="src" :src="src" :alt="alt" class="size-full object-cover" />
      <UserRound v-else class="size-7 text-muted-foreground" />
    </div>
    <div class="space-y-2">
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="uploading"
        @click="fileInput?.click()"
      >
        <Spinner v-if="uploading" />
        {{ uploading ? "Uploading..." : "Change avatar" }}
      </Button>
      <p class="text-xs text-muted-foreground">{{ typeLabel }}. Max {{ maxSizeKb }} KB.</p>
      <input
        ref="fileInput"
        type="file"
        :accept="types.join(',')"
        class="hidden"
        @change="onSelected"
      />
    </div>
  </div>
</template>