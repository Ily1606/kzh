<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/composables/useAuth";
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

const registerSchema = toTypedSchema(
  z.object({
    name: z.string().min(1, "Full name is required"),
    email: emailRule,
    password: newPasswordRule,
    confirmPassword: z.string().min(1, "Please confirm your password"),
  }).refine((data) => data.password === data.confirmPassword, {
    message: "Passwords do not match",
    path: ["confirmPassword"],
  })
);

const { handleSubmit } = useForm({
  validationSchema: registerSchema,
  initialValues: {
    name: "",
    email: "",
    password: "",
    confirmPassword: "",
  },
});

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
      <FormField v-slot="{ componentField }: any" name="name">
        <FormItem class="space-y-1.5">
          <FormLabel>Full name</FormLabel>
          <FormControl>
            <Input type="text" autocomplete="name" placeholder="Jane Doe" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <FormField v-slot="{ componentField }: any" name="email">
        <FormItem class="space-y-1.5">
          <FormLabel>Email</FormLabel>
          <FormControl>
            <Input type="email" autocomplete="email" placeholder="you@example.com" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <FormField v-slot="{ componentField }: any" name="password">
        <FormItem class="space-y-1.5">
          <FormLabel>Password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <FormField v-slot="{ componentField }: any" name="confirmPassword">
        <FormItem class="space-y-1.5">
          <FormLabel>Confirm password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Creating account" : "Create account" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">Already a publisher? <RouterLink to="/login" class="font-medium text-primary hover:underline">Sign in</RouterLink></p>
  </Card>
</template>
