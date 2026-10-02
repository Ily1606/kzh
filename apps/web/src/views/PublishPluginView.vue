<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { getPlugin } from '@/api/generated/endpoints'
import { SubmitPluginRequestLicense } from '@/api/generated/model'
import type { SubmitPluginRequest } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card } from '@/components/ui/card'
import { Alert } from '@/components/ui/alert'

const router = useRouter()
const { pluginStore } = getPlugin()

// Sử dụng kiểu dữ liệu SubmitPluginRequest do Orval tự động sinh
const form = ref<SubmitPluginRequest>({
  name: '',
  title: '',
  license: SubmitPluginRequestLicense.MIT, // Default license
  source_link: ''
})

const error = ref('')
const isSubmitting = ref(false)

async function onSubmit() {
  error.value = ''
  isSubmitting.value = true

  try {
    // Gọi API submit plugin qua hàm của Orval
    await pluginStore(form.value)

    // Đẩy về trang chủ (hoặc trang dashboard)
    router.push('/')
  } catch (err: any) {
    // Bắt lỗi trả về từ Backend (thường là 422 Validation Error)
    error.value = err?.response?.data?.message || 'Có lỗi xảy ra khi submit plugin.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-xl py-12 px-4 sm:px-6">
    <Card class="border-primary/20 bg-card shadow-lg p-6">
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Submit a Plugin</h2>
        <p class="text-sm text-muted-foreground mt-1">
          Publish your plugin to the DeepSeek Harness registry. It will be reviewed by administrators.
        </p>
      </div>

      <div>
        <form @submit.prevent="onSubmit" class="space-y-5">
          <!-- Tên Plugin -->
          <div class="space-y-2">
            <Label for="name">Plugin Name <span class="text-destructive">*</span></Label>
            <Input
              id="name"
              v-model="form.name"
              placeholder="e.g., @my-org/my-plugin"
              required
            />
            <p class="text-xs text-muted-foreground">The npm package name of your plugin.</p>
          </div>

          <!-- Tiêu đề -->
          <div class="space-y-2">
            <Label for="title">Display Title <span class="text-destructive">*</span></Label>
            <Input
              id="title"
              v-model="form.title"
              placeholder="My Awesome Plugin"
              required
            />
          </div>

          <!-- License (Liệt kê dựa trên enum từ Orval) -->
          <div class="space-y-2">
            <Label for="license">License <span class="text-destructive">*</span></Label>
            <select
              id="license"
              v-model="form.license"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <option
                v-for="lic in Object.values(SubmitPluginRequestLicense)"
                :key="lic"
                :value="lic"
              >
                {{ lic }}
              </option>
            </select>
          </div>

          <!-- Đường dẫn Source Code -->
          <div class="space-y-2">
            <Label for="source">Source Code Link <span class="text-destructive">*</span></Label>
            <Input
              id="source"
              type="url"
              v-model="form.source_link"
              placeholder="https://github.com/username/repo"
              required
            />
          </div>

          <!-- Hiển thị Lỗi -->
          <Alert v-if="error" variant="destructive" class="py-2 px-3 text-sm">
            {{ error }}
          </Alert>

          <!-- Nút Submit -->
          <Button type="submit" class="w-full" :disabled="isSubmitting">
            {{ isSubmitting ? 'Submitting...' : 'Submit Plugin' }}
          </Button>
        </form>
      </div>
    </Card>
  </div>
</template>
