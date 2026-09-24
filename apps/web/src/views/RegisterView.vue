<script setup lang="ts">
import { ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/composables/useAuth";
import { isRequired, validateConfirmPassword, validateEmail, validatePassword } from "@/utils/validation";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

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
    errorMsg.value = "Couldn't create your account. Please try again.";
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
        <Label for="fullName">Full name</Label>
        <Input id="fullName" v-model="name" type="text" autocomplete="name" placeholder="Jane Doe" :aria-invalid="Boolean(nameError)" />
        <p v-if="nameError" class="text-xs text-destructive">{{ nameError }}</p>
      </div>
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" type="email" autocomplete="email" placeholder="you@example.com" :aria-invalid="Boolean(emailError)" />
        <p v-if="emailError" class="text-xs text-destructive">{{ emailError }}</p>
      </div>
      <div class="space-y-1.5">
        <Label for="password">Password</Label>
        <Input id="password" v-model="password" type="password" autocomplete="new-password" :aria-invalid="Boolean(passwordError)" />
        <p v-if="passwordError" class="text-xs text-destructive">{{ passwordError }}</p>
      </div>
      <div class="space-y-1.5">
        <Label for="confirmPassword">Confirm password</Label>
        <Input id="confirmPassword" v-model="confirmPassword" type="password" autocomplete="new-password" :aria-invalid="Boolean(confirmPasswordError)" />
        <p v-if="confirmPasswordError" class="text-xs text-destructive">{{ confirmPasswordError }}</p>
      </div>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Creating account" : "Create account" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">Already a publisher? <RouterLink to="/login" class="font-medium text-primary hover:underline">Sign in</RouterLink></p>
  </Card>
</template>
