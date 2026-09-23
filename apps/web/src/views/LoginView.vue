<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/composables/useAuth";
import Card from "@/components/ui/Card.vue";
import Input from "@/components/ui/Input.vue";
import Button from "@/components/ui/Button.vue";

const email = ref("user@example.com");
const password = ref("password");
const errorMsg = ref("");
const loading = ref(false);

const router = useRouter();
const auth = useAuth();

async function onSubmit() {
  loading.value = true;
  errorMsg.value = "";

  try {
    await auth.login({
      email: email.value,
      password: password.value,
    });
    router.push("/");
  } catch (e: any) {
    errorMsg.value = e.message || "Failed to sign in. Please try again.";
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
        required
        placeholder="Enter your email"
      />

      <Input
        id="password"
        type="password"
        label="Password"
        v-model="password"
        required
        placeholder="Enter your password"
      />

      <Button type="submit" class="w-full" :loading="loading">
        Sign in
      </Button>
    </form>

    <div class="mt-6 text-center text-sm">
      <RouterLink to="/" class="text-primary hover:underline">
        &larr; Back to home
      </RouterLink>
    </div>
  </Card>
</template>
