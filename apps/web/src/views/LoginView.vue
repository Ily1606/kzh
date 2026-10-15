<script setup lang="ts">
import { ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import type { AxiosError } from "axios";
import { useAuth } from "@/composables/useAuth";
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
import { loginSchema } from "@/schemas/auth.schema";

const { handleSubmit } = useForm({
  validationSchema: loginSchema,
  initialValues: {
    email: "",
    password: "",
  },
});

const errorMsg = ref("");
const loading = ref(false);

const router = useRouter();
const route = useRoute();
const auth = useAuth();

const onSubmit = handleSubmit(async (values) => {
  loading.value = true;
  errorMsg.value = "";

  try {
    await auth.login({
      email: values.email,
      password: values.password,
    });
    const redirectPath = route.query.redirect as string;
    router.push(redirectPath || "/");
  } catch (e) {
    const status = (e as AxiosError).response?.status;
    errorMsg.value =
      status === 429
        ? "Too many attempts. Please try again later."
        : status === 401 || status === 422
          ? "Incorrect email or password."
          : "Something went wrong. Please try again.";
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Card class="gap-0 overflow-hidden border-border/80 bg-card/90 py-0 shadow-xl shadow-primary/5">
    <form class="space-y-4 p-6" @submit.prevent="onSubmit">
      <Alert v-if="errorMsg" variant="destructive">
        <AlertDescription>{{ errorMsg }}</AlertDescription>
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
      <FormField v-slot="{ componentField }" name="password">
        <FormItem>
          <div class="flex items-center justify-between">
            <FormLabel>Password</FormLabel>
            <RouterLink to="/forgot-password" class="text-sm font-medium text-primary hover:underline">Forgot password?</RouterLink>
          </div>
          <FormControl>
            <Input id="password" type="password" autocomplete="current-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Signing in" : "Sign in" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">New to the registry? <RouterLink to="/register" class="font-medium text-primary hover:underline">Create a publisher account</RouterLink></p>
  </Card>
</template>
