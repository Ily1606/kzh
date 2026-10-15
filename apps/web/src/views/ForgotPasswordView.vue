<script setup lang="ts">
import { ref } from "vue";
import type { AxiosError } from "axios";
import { getPasswordReset } from "@/api/generated/endpoints";
import { useForm } from "vee-validate";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { forgotPasswordSchema } from "@/schemas/auth.schema";

const { handleSubmit } = useForm({
  validationSchema: forgotPasswordSchema,
  initialValues: {
    email: "",
  },
});

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
  } catch (e) {
    const err = e as AxiosError<{ message?: string }>;
    const status = err.response?.status;
    errorMsg.value =
      status === 429
        ? "Too many attempts. Please try again later."
        : status === 422
          ? (err.response?.data?.message || "Validation failed.")
          : "Failed to send reset link. Please try again.";
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
      <Alert v-if="errorMsg" variant="destructive">
        <AlertDescription>{{ errorMsg }}</AlertDescription>
      </Alert>
      <Alert v-if="successMsg" variant="default" class="border-primary/50 text-primary">
        <AlertDescription class="text-inherit">{{ successMsg }}</AlertDescription>
      </Alert>
      <FormField v-slot="{ componentField }" name="email">
        <FormItem>
          <FormLabel>Email</FormLabel>
          <FormControl>
            <Input id="email" type="email" autocomplete="email" placeholder="you@example.com" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Sending link" : "Email Password Reset Link" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">
      Remember your password? <RouterLink to="/login" class="font-medium text-primary hover:underline">Sign in</RouterLink>
    </p>
  </Card>
</template>
