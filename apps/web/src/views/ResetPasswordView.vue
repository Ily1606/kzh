<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import { validatePassword, validateConfirmPassword } from '@/utils/validation'

const auth = useAuth()
const router = useRouter()
const route = useRoute()

const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')

const passwordError = ref('')
const confirmError = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const isSuccess = ref(false)

onMounted(() => {
  token.value = route.query.token as string || ''
  email.value = route.query.email as string || ''
})

function validateForm() {
  passwordError.value = validatePassword(password.value)
  confirmError.value = validateConfirmPassword(password.value, passwordConfirm.value)

  return !passwordError.value && !confirmError.value
}

async function handleSubmit() {
  if (!validateForm()) return

  if (!token.value || !email.value) {
    errorMessage.value = 'Invalid or missing token. Please request a new password reset link.'
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    await auth.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirm.value
    })
    isSuccess.value = true

    // Automatically redirect to login after a few seconds
    setTimeout(() => {
      router.push('/login')
    }, 3000)
  } catch (error: any) {
    if (error.response?.data?.errors?.password) {
      passwordError.value = error.response.data.errors.password[0]
    } else {
      errorMessage.value = error.response?.data?.message || 'Failed to reset password. The link might be expired.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <Card class="p-8">
    <div v-if="isSuccess" class="text-center">
      <div class="rounded-md bg-green-50 p-4 mb-6">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-green-800">Password reset successful</h3>
            <div class="mt-2 text-sm text-green-700">
              <p>Your password has been successfully reset. Redirecting to login...</p>
            </div>
          </div>
        </div>
      </div>
      <RouterLink to="/login" class="text-sm font-medium text-primary hover:text-primary/90">
        Click here if not redirected automatically
      </RouterLink>
    </div>

    <form v-else class="space-y-6" @submit.prevent="handleSubmit">
      <div v-if="errorMessage" class="rounded-md bg-red-50 p-4">
        <div class="flex">
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">{{ errorMessage }}</h3>
          </div>
        </div>
      </div>

      <div>
        <Input
          id="email"
          type="email"
          label="Email address"
          v-model="email"
          disabled
          class="bg-gray-50"
        />
      </div>

      <div>
        <Input
          id="password"
          type="password"
          label="New Password"
          v-model="password"
          :error="passwordError"
          required
          @input="passwordError = validatePassword(password)"
        />
      </div>

      <div>
        <Input
          id="password_confirmation"
          type="password"
          label="Confirm New Password"
          v-model="passwordConfirm"
          :error="confirmError"
          required
          @input="confirmError = validateConfirmPassword(password, passwordConfirm)"
        />
      </div>

      <div>
        <Button
          type="submit"
          class="w-full"
          :disabled="isLoading"
        >
          {{ isLoading ? 'Resetting...' : 'Reset password' }}
        </Button>
      </div>
    </form>
  </Card>
</template>
