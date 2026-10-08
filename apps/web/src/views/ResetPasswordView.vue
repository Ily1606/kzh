<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
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

const route = useRoute();
const router = useRouter();

const resetPasswordSchema = toTypedSchema(
  z.object({
    email: z.string().min(1, "Email is required").email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z.string().min(1, "Please confirm your password"),
  }).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match",
    path: ["password_confirmation"],
  })
);

const { handleSubmit, defineField, errors, setValues } = useForm({
  validationSchema: resetPasswordSchema,
  initialValues: {
    email: "",
    password: "",
    password_confirmation: "",
  },
});

const [email, emailProps] = defineField("email");
const [password, passwordProps] = defineField("password");
const [password_confirmation, passwordConfirmationProps] = defineField("password_confirmation");

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
      
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" v-bind="emailProps" type="email" autocomplete="email" readonly class="opacity-70 cursor-not-allowed" />
        <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="password">New Password</Label>
        <Input id="password" v-model="password" v-bind="passwordProps" type="password" autocomplete="new-password" :aria-invalid="Boolean(errors.password)" />
        <p v-if="errors.password" class="text-xs text-destructive">{{ errors.password }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="password_confirmation">Confirm New Password</Label>
        <Input id="password_confirmation" v-model="password_confirmation" v-bind="passwordConfirmationProps" type="password" autocomplete="new-password" :aria-invalid="Boolean(errors.password_confirmation)" />
        <p v-if="errors.password_confirmation" class="text-xs text-destructive">{{ errors.password_confirmation }}</p>
      </div>

      <Button class="w-full" type="submit" :disabled="loading || Boolean(successMsg)"><Spinner v-if="loading" />{{ loading ? "Resetting" : "Reset Password" }}</Button>
    </form>
  </Card>
</template>
