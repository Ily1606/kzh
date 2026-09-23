<script setup lang="ts">
import { ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/composables/useAuth";
import { validateEmail, validatePassword } from "@/utils/validation";
import Card from "@/components/ui/Card.vue";
import Input from "@/components/ui/Input.vue";
import Button from "@/components/ui/Button.vue";

const email = ref("");
const password = ref("");
const emailError = ref("");
const passwordError = ref("");
const errorMsg = ref("");
const loading = ref(false);

const router = useRouter();
const auth = useAuth();

watch(email, (newVal) => {
  emailError.value = validateEmail(newVal);
});

watch(password, (newVal) => {
  passwordError.value = validatePassword(newVal);
});

async function onSubmit() {
  emailError.value = validateEmail(email.value);
  passwordError.value = validatePassword(password.value);

  if (emailError.value || passwordError.value) return;

  loading.value = true;
  errorMsg.value = "";

  try {
    await auth.login({
      email: email.value,
      password: password.value,
    });
    router.push("/");
  } catch (e: any) {
    errorMsg.value = "Failed to sign in. Please try again.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <Card class="p-8">
    <form @submit.prevent="onSubmit" class="space-y-6">
      <div v-if="errorMsg" class="rounded-md bg-red-50 p-4 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
        {{ errorMsg }}
      </div>

      <Input
        id="email"
        type="email"
        label="Email address"
        v-model="email"
        :error="emailError"
        placeholder="Enter your email"
      />

      <Input
        id="password"
        type="password"
        label="Password"
        v-model="password"
        :error="passwordError"
        placeholder="Enter your password"
      />

      <Button type="submit" class="w-full" :loading="loading">
        Sign in
      </Button>
    </form>

    <div class="mt-6 text-center text-sm">
      <p class="mb-4 text-gray-600 dark:text-gray-400">
        Don't have an account?
        <RouterLink to="/register" class="font-medium text-primary hover:underline">
          Sign up
        </RouterLink>
      </p>
      <RouterLink to="/" class="text-gray-500 hover:text-primary hover:underline dark:text-gray-400">
        &larr; Back to home
      </RouterLink>
    </div>
  </Card>
</template>
