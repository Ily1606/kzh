<script setup lang="ts">
import { ref } from "vue";
import { getApiErrorMessage } from "@/utils/error";
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
    errorMsg.value = getApiErrorMessage(e, {
      default: "Failed to send reset link. Please try again.",
    });
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Card variant="glass" class="gap-0 overflow-hidden py-0 shadow-xl shadow-primary/10">
    <form class="space-y-4 p-6" @submit.prevent="onSubmit">
      <Alert v-if="errorMsg" variant="destructive">
        <AlertDescription>{{ errorMsg }}</AlertDescription>
      </Alert>
      <Alert v-if="successMsg" variant="success">
        <AlertDescription>{{ successMsg }}</AlertDescription>
      </Alert>
      <FormField v-slot="{ componentField }" name="email">
        <FormItem>
          <FormLabel>Email</FormLabel>
          <FormControl>
            <Input type="email" autocomplete="email" placeholder="you@example.com" v-bind="componentField" />
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
