<script setup lang="ts">
import { ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/composables/useAuth";
import { isRequired, validateConfirmPassword, validateEmail, validatePassword } from "@/utils/validation";
import Card from "@/components/ui/Card.vue";
import Input from "@/components/ui/Input.vue";
import Button from "@/components/ui/Button.vue";

const email = ref("");
const password = ref("");
const confirmPassword = ref("");
const name = ref("");
const emailError = ref("");
const passwordError = ref("");
const confirmPasswordError = ref("");
const nameError = ref("");
const errorMsg = ref("");
const loading = ref(false);

const router = useRouter();
const auth = useAuth();

watch(name, (newVal) => {
  nameError.value = isRequired(newVal, "Full name");
});

watch(email, (newVal) => {
  emailError.value = validateEmail(newVal);
});

watch(password, (newVal) => {
  passwordError.value = validatePassword(newVal);
});

watch(confirmPassword, (newVal) => {
  confirmPasswordError.value = validateConfirmPassword(password.value, newVal);
});

async function onSubmit() {
  nameError.value = isRequired(name.value, "Full name");
  emailError.value = validateEmail(email.value);
  passwordError.value = validatePassword(password.value);
  confirmPasswordError.value = validateConfirmPassword(password.value, confirmPassword.value);

  if (nameError.value || emailError.value || passwordError.value || confirmPasswordError.value) return;

  loading.value = true;
  errorMsg.value = "";

  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirmPassword.value,
    });
    router.push("/");
  } catch (e: any) {
    errorMsg.value = "Failed to sign up. Please check your information.";
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
        id="fullName"
        type="text"
        label="Full Name"
        v-model="name"
        :error="nameError"
        placeholder="Enter your full name"
      />

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

      <Input
        id="confirmPassword"
        type="password"
        label="Confirm Password"
        v-model="confirmPassword"
        :error="confirmPasswordError"
        placeholder="Confirm your password"
        />

      <Button type="submit" class="w-full" :loading="loading">
        Sign Up
      </Button>
    </form>

    <div class="mt-6 text-center text-sm">
      <RouterLink to="/" class="text-primary hover:underline">
        &larr; Back to home
      </RouterLink>
    </div>
  </Card>
</template>
