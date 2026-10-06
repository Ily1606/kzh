<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getPasswordReset } from "@/api/generated/endpoints";
import { validateEmail, validatePassword } from "@/utils/validation";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

const route = useRoute();
const router = useRouter();

const email = ref("");
const token = ref("");
const password = ref("");
const password_confirmation = ref("");

const emailError = ref("");
const passwordError = ref("");
const confirmPasswordError = ref("");

const errorMsg = ref("");
const successMsg = ref("");
const loading = ref(false);

const { passwordResetResetPassword } = getPasswordReset();

onMounted(() => {
  email.value = route.query.email as string || "";
  token.value = route.query.token as string || "";
});

watch(email, (newVal) => {
  emailError.value = validateEmail(newVal);
});

watch(password, (newVal) => {
  passwordError.value = validatePassword(newVal);
  if (password_confirmation.value && newVal !== password_confirmation.value) {
    confirmPasswordError.value = "Passwords do not match.";
  } else {
    confirmPasswordError.value = "";
  }
});

watch(password_confirmation, (newVal) => {
  if (newVal !== password.value) {
    confirmPasswordError.value = "Passwords do not match.";
  } else {
    confirmPasswordError.value = "";
  }
});

async function onSubmit() {
  emailError.value = validateEmail(email.value);
  passwordError.value = validatePassword(password.value);
  if (password.value !== password_confirmation.value) {
    confirmPasswordError.value = "Passwords do not match.";
  }

  if (emailError.value || passwordError.value || confirmPasswordError.value) return;
  if (!token.value) {
    errorMsg.value = "Invalid or missing password reset token.";
    return;
  }

  loading.value = true;
  errorMsg.value = "";
  successMsg.value = "";

  try {
    await passwordResetResetPassword({
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: password_confirmation.value,
    });
    
    successMsg.value = "Your password has been reset. You will be redirected to login.";
    
    setTimeout(() => {
      router.push("/login");
    }, 2000);
  } catch (e: any) {
    if (e.response?.status === 422) {
      errorMsg.value = e.response.data?.message || "Validation failed.";
    } else {
      errorMsg.value = "Failed to reset password. The link might be expired or invalid.";
    }
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <Card class="gap-0 overflow-hidden border-border/80 bg-card/90 py-0 shadow-xl shadow-primary/5">
    <div class="px-6 pt-6 pb-2">
      <h2 class="text-xl font-semibold text-foreground">Reset Password</h2>
      <p class="text-sm text-muted-foreground mt-1">Please enter your new password below.</p>
    </div>
    <form class="space-y-4 p-6 pt-2" @submit.prevent="onSubmit">
      <Alert v-if="errorMsg" variant="destructive">{{ errorMsg }}</Alert>
      <Alert v-if="successMsg" variant="default" class="border-primary/50 text-primary">{{ successMsg }}</Alert>
      
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" type="email" autocomplete="email" readonly class="opacity-70 cursor-not-allowed" />
        <p v-if="emailError" class="text-xs text-destructive">{{ emailError }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="password">New Password</Label>
        <Input id="password" v-model="password" type="password" autocomplete="new-password" :aria-invalid="Boolean(passwordError)" />
        <p v-if="passwordError" class="text-xs text-destructive">{{ passwordError }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="password_confirmation">Confirm New Password</Label>
        <Input id="password_confirmation" v-model="password_confirmation" type="password" autocomplete="new-password" :aria-invalid="Boolean(confirmPasswordError)" />
        <p v-if="confirmPasswordError" class="text-xs text-destructive">{{ confirmPasswordError }}</p>
      </div>

      <Button class="w-full" type="submit" :disabled="loading || Boolean(successMsg)"><Spinner v-if="loading" />{{ loading ? "Resetting" : "Reset Password" }}</Button>
    </form>
  </Card>
</template>
