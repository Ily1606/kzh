<script setup lang="ts">
import { ref } from "vue";
import { getPasswordReset } from "@/api/generated/endpoints";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

const forgotPasswordSchema = toTypedSchema(
  z.object({
    email: z.string().min(1, "Email is required").email("Invalid email address"),
  })
);

const { handleSubmit, defineField, errors } = useForm({
  validationSchema: forgotPasswordSchema,
  initialValues: {
    email: "",
  },
});

const [email, emailProps] = defineField("email");

const errorMsg = ref("");
const successMsg = ref("");
const loading = ref(false);

const { passwordResetSendResetLinkEmail } = getPasswordReset();

const onSubmit = handleSubmit(async (values) => {
  loading.value = true;
  errorMsg.value = "";
  successMsg.value = "";

  try {
    await passwordResetSendResetLinkEmail({
      email: values.email,
    });
    successMsg.value = "We have emailed your password reset link.";
  } catch (e: any) {
    if (e.response?.status === 422) {
      errorMsg.value = e.response.data?.message || "Validation failed.";
    } else {
      errorMsg.value = "Failed to send reset link. Please try again.";
    }
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Card class="gap-0 overflow-hidden border-border/80 bg-card/90 py-0 shadow-xl shadow-primary/5">
    <div class="px-6 pt-6 pb-2">
      <h2 class="text-xl font-semibold text-foreground">Forgot your password?</h2>
      <p class="text-sm text-muted-foreground mt-1">Enter your email address and we will send you a link to reset your password.</p>
    </div>
    <form class="space-y-4 p-6 pt-2" @submit.prevent="onSubmit">
      <Alert v-if="errorMsg" variant="destructive">{{ errorMsg }}</Alert>
      <Alert v-if="successMsg" variant="default" class="border-primary/50 text-primary">{{ successMsg }}</Alert>
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" v-bind="emailProps" type="email" autocomplete="email" placeholder="you@example.com" :aria-invalid="Boolean(errors.email)" />
        <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email }}</p>
      </div>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Sending link" : "Email Password Reset Link" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">
      Remember your password? <RouterLink to="/login" class="font-medium text-primary hover:underline">Sign in</RouterLink>
    </p>
  </Card>
</template>
