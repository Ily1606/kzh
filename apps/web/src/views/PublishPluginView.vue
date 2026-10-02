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
const fieldErrors = ref<Record<string, string[]>>({})
const isSubmitting = ref(false)
const isSuccess = ref(false)

async function onSubmit() {
  error.value = ''
  fieldErrors.value = {}
  isSubmitting.value = true
  isSuccess.value = false

  try {
    // Gọi API submit plugin qua hàm của Orval
    await pluginStore(form.value)

    // Đánh dấu thành công và hiển thị confirmation
    isSuccess.value = true
    
    // Tự động chuyển trang sau 2 giây
    setTimeout(() => {
      router.push('/')
    }, 2000)
  } catch (err: any) {
    // Bắt lỗi trả về từ Backend (thường là 422 Validation Error)
    if (err?.response?.status === 422 && err?.response?.data?.errors) {
      fieldErrors.value = err.response.data.errors
    }
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

      <div v-if="isSuccess" class="text-center py-10 space-y-4">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 class="text-xl font-medium">Plugin Submitted Successfully!</h3>
        <p class="text-muted-foreground">Your plugin is now pending review by an administrator. You will be redirected shortly...</p>
      </div>

      <div v-else>
        <form @submit.prevent="onSubmit" class="space-y-5">
          <!-- Tên Plugin -->
          <div class="space-y-2">
            <Label for="name">Plugin Name <span class="text-destructive">*</span></Label>
            <Input
              id="name"
              v-model="form.name"
              placeholder="e.g., @my-org/my-plugin"
              required
              :class="{ 'border-destructive focus-visible:ring-destructive': fieldErrors.name }"
            />
            <p v-if="fieldErrors.name" class="text-xs text-destructive">{{ fieldErrors.name[0] }}</p>
            <p v-else class="text-xs text-muted-foreground">The npm package name of your plugin.</p>
          </div>

          <!-- Tiêu đề -->
          <div class="space-y-2">
            <Label for="title">Display Title <span class="text-destructive">*</span></Label>
            <Input
              id="title"
              v-model="form.title"
              placeholder="My Awesome Plugin"
              required
              :class="{ 'border-destructive focus-visible:ring-destructive': fieldErrors.title }"
            />
            <p v-if="fieldErrors.title" class="text-xs text-destructive">{{ fieldErrors.title[0] }}</p>
          </div>

          <!-- License (Liệt kê dựa trên enum từ Orval) -->
          <div class="space-y-2">
            <Label for="license">License <span class="text-destructive">*</span></Label>
            <select
              id="license"
              v-model="form.license"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              :class="{ 'border-destructive focus-visible:ring-destructive': fieldErrors.license }"
            >
              <option
                v-for="lic in Object.values(SubmitPluginRequestLicense)"
                :key="lic"
                :value="lic"
              >
                {{ lic }}
              </option>
            </select>
            <p v-if="fieldErrors.license" class="text-xs text-destructive">{{ fieldErrors.license[0] }}</p>
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
              :class="{ 'border-destructive focus-visible:ring-destructive': fieldErrors.source_link }"
            />
            <p v-if="fieldErrors.source_link" class="text-xs text-destructive">{{ fieldErrors.source_link[0] }}</p>
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
