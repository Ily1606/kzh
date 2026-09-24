<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import { validateEmail } from '@/utils/validation'

const auth = useAuth()

const email = ref('')
const emailError = ref('')
const isLoading = ref(false)
const isSuccess = ref(false)
const errorMessage = ref('')

function validateForm() {
  emailError.value = validateEmail(email.value)
  return !emailError.value
}

async function handleSubmit() {
  if (!validateForm()) return

  isLoading.value = true
  errorMessage.value = ''

  try {
    await auth.forgotPassword(email.value)
    isSuccess.value = true
  } catch (error: any) {
    if (error.response?.data?.errors?.email) {
      emailError.value = error.response.data.errors.email[0]
    } else {
      errorMessage.value = error.response?.data?.message || 'Something went wrong. Please try again.'
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
            <h3 class="text-sm font-medium text-green-800">Email sent successfully</h3>
            <div class="mt-2 text-sm text-green-700">
              <p>Please check your email inbox for a link to complete the reset.</p>
            </div>
          </div>
        </div>
      </div>
      <RouterLink to="/login" class="text-sm font-medium text-primary hover:text-primary/90">
        Return to login
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
          :error="emailError"
          autocomplete="email"
          required
          @input="emailError = validateEmail(email)"
        />
      </div>

      <div>
        <Button
          type="submit"
          class="w-full"
          :disabled="isLoading"
        >
          {{ isLoading ? 'Sending link...' : 'Send reset link' }}
        </Button>
      </div>

      <p class="text-center text-sm text-gray-500">
        Remember your password?
        <RouterLink to="/login" class="font-semibold leading-6 text-primary hover:text-primary/90">
          Sign in
        </RouterLink>
      </p>
    </form>
  </Card>
</template>
