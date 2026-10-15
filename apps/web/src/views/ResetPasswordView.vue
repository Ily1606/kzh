<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
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
import { resetPasswordSchema } from "@/schemas/auth.schema";

const route = useRoute();
const router = useRouter();

const { handleSubmit, setValues } = useForm({
  validationSchema: resetPasswordSchema,
  initialValues: {
    email: "",
    password: "",
    password_confirmation: "",
  },
});

const token = ref("");
const errorMsg = ref("");
const successMsg = ref("");
const loading = ref(false);

const { passwordResetResetPassword } = getPasswordReset();

onMounted(() => {
  setValues({
    email: route.query.email as string || "",
  });
  token.value = route.query.token as string || "";
});

const onSubmit = handleSubmit(async (values) => {
  if (!token.value) {
    errorMsg.value = "Invalid or missing password reset token.";
    return;
  }

  loading.value = true;
  errorMsg.value = "";
  successMsg.value = "";

  try {
    await passwordResetResetPassword({
      email: values.email,
      token: token.value,
      password: values.password,
      password_confirmation: values.password_confirmation,
    });

    successMsg.value = "Your password has been reset. You will be redirected to login.";

    setTimeout(() => {
      router.push("/login");
    }, 2000);
  } catch (e) {
    const err = e as AxiosError<{ message?: string }>;
    const status = err.response?.status;
    errorMsg.value =
      status === 429
        ? "Too many attempts. Please try again later."
        : status === 422
          ? (err.response?.data?.message || "Validation failed.")
          : "Failed to reset password. The link might be expired or invalid.";
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Card variant="glass" class="gap-0 overflow-hidden py-0 shadow-xl shadow-primary/10">
    <div class="px-6 pt-6 pb-2">
      <h2 class="text-xl font-semibold text-foreground">Reset Password</h2>
      <p class="text-sm text-muted-foreground mt-1">Please enter your new password below.</p>
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
            <Input type="email" autocomplete="email" readonly class="opacity-70 cursor-not-allowed" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }" name="password">
        <FormItem>
          <FormLabel>New Password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }" name="password_confirmation">
        <FormItem>
          <FormLabel>Confirm New Password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <Button class="w-full" type="submit" :disabled="loading || Boolean(successMsg)"><Spinner v-if="loading" />{{ loading ? "Resetting" : "Reset Password" }}</Button>
    </form>
  </Card>
</template>
