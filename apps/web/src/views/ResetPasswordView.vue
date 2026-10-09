<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getPasswordReset } from "@/api/generated/endpoints";
import { useForm, Field as FormField } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import {
  FormControl,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { email as emailRule, newPassword as newPasswordRule } from "@/schemas/auth";

const route = useRoute();
const router = useRouter();

const resetPasswordSchema = toTypedSchema(
  z.object({
    email: emailRule,
    password: newPasswordRule,
    password_confirmation: z.string().min(1, "Please confirm your password"),
  }).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match",
    path: ["password_confirmation"],
  })
);

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
  } catch (e: any) {
    if (e.response?.status === 422) {
      errorMsg.value = e.response.data?.message || "Validation failed.";
    } else {
      errorMsg.value = "Failed to reset password. The link might be expired or invalid.";
    }
  } finally {
    loading.value = false;
  }
});
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
      
      <FormField v-slot="{ componentField }: any" name="email">
        <FormItem class="space-y-1.5">
          <FormLabel>Email</FormLabel>
          <FormControl>
            <Input type="email" autocomplete="email" readonly class="opacity-70 cursor-not-allowed" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }: any" name="password">
        <FormItem class="space-y-1.5">
          <FormLabel>New Password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }: any" name="password_confirmation">
        <FormItem class="space-y-1.5">
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
