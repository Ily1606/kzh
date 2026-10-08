<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/composables/useAuth";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

const registerSchema = toTypedSchema(
  z.object({
    name: z.string().min(1, "Full name is required"),
    email: z.string().min(1, "Email is required").email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    confirmPassword: z.string().min(1, "Please confirm your password"),
  }).refine((data) => data.password === data.confirmPassword, {
    message: "Passwords do not match",
    path: ["confirmPassword"],
  })
);

const { handleSubmit, defineField, errors } = useForm({
  validationSchema: registerSchema,
  initialValues: {
    name: "",
    email: "",
    password: "",
    confirmPassword: "",
  },
});

const [name, nameProps] = defineField("name");
const [email, emailProps] = defineField("email");
const [password, passwordProps] = defineField("password");
const [confirmPassword, confirmPasswordProps] = defineField("confirmPassword");

const errorMsg = ref("");
const loading = ref(false);

const router = useRouter();
const auth = useAuth();

const onSubmit = handleSubmit(async (values) => {
  loading.value = true;
  errorMsg.value = "";

  try {
    await auth.register({
      name: values.name,
      email: values.email,
      password: values.password,
      password_confirmation: values.confirmPassword,
    });
    router.push("/");
  } catch (e: any) {
    errorMsg.value = "Couldn't create your account. Please try again.";
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Card class="gap-0 overflow-hidden border-border/80 bg-card/90 py-0 shadow-xl shadow-primary/5">
    <form class="space-y-4 p-6" @submit.prevent="onSubmit">
      <Alert v-if="errorMsg" variant="destructive">{{ errorMsg }}</Alert>
      <div class="space-y-1.5">
        <Label for="fullName">Full name</Label>
        <Input id="fullName" v-model="name" v-bind="nameProps" type="text" autocomplete="name" placeholder="Jane Doe" :aria-invalid="Boolean(errors.name)" />
        <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
      </div>
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" v-bind="emailProps" type="email" autocomplete="email" placeholder="you@example.com" :aria-invalid="Boolean(errors.email)" />
        <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email }}</p>
      </div>
      <div class="space-y-1.5">
        <Label for="password">Password</Label>
        <Input id="password" v-model="password" v-bind="passwordProps" type="password" autocomplete="new-password" :aria-invalid="Boolean(errors.password)" />
        <p v-if="errors.password" class="text-xs text-destructive">{{ errors.password }}</p>
      </div>
      <div class="space-y-1.5">
        <Label for="confirmPassword">Confirm password</Label>
        <Input id="confirmPassword" v-model="confirmPassword" v-bind="confirmPasswordProps" type="password" autocomplete="new-password" :aria-invalid="Boolean(errors.confirmPassword)" />
        <p v-if="errors.confirmPassword" class="text-xs text-destructive">{{ errors.confirmPassword }}</p>
      </div>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Creating account" : "Create account" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">Already a publisher? <RouterLink to="/login" class="font-medium text-primary hover:underline">Sign in</RouterLink></p>
  </Card>
</template>
