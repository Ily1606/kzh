<script setup lang="ts">
import { ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/composables/useAuth";
import { validateEmail, validatePassword } from "@/utils/validation";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

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
    errorMsg.value = "Incorrect email or password.";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <Card class="gap-0 overflow-hidden border-border/80 bg-card/90 py-0 shadow-xl shadow-primary/5">
    <form class="space-y-4 p-6" @submit.prevent="onSubmit">
      <Alert v-if="errorMsg" variant="destructive">{{ errorMsg }}</Alert>
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" type="email" autocomplete="email" placeholder="you@example.com" :aria-invalid="Boolean(emailError)" />
        <p v-if="emailError" class="text-xs text-destructive">{{ emailError }}</p>
      </div>
      <div class="space-y-1.5">
        <Label for="password">Password</Label>
        <Input id="password" v-model="password" type="password" autocomplete="current-password" :aria-invalid="Boolean(passwordError)" />
        <p v-if="passwordError" class="text-xs text-destructive">{{ passwordError }}</p>
      </div>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Signing in" : "Sign in" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">New to the registry? <RouterLink to="/register" class="font-medium text-primary hover:underline">Create a publisher account</RouterLink></p>
  </Card>
</template>
