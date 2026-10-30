<script setup lang="ts">
import { ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { getApiErrorMessage } from "@/utils/error";
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
import { safeRedirect } from "@/utils/safe-redirect";

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
    router.push(safeRedirect(route.query.redirect));
  } catch (e) {
    errorMsg.value = getApiErrorMessage(e, {
      401: "Incorrect email or password.",
    });
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Card variant="glass" class="gap-0 overflow-hidden py-0 shadow-xl shadow-primary/10">
    <div class="px-6 pt-6 pb-2">
      <h2 class="text-xl font-semibold text-foreground">Sign in to publish plugins</h2>
      <p class="text-sm text-muted-foreground mt-1">Sign in to publish and manage your plugins. </p>
    </div>
    <form class="space-y-4 p-6" @submit.prevent="onSubmit">
      <Alert v-if="errorMsg" variant="destructive">
        <AlertDescription>{{ errorMsg }}</AlertDescription>
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
      <FormField v-slot="{ componentField }" name="password">
        <FormItem>
          <div class="flex items-center justify-between">
            <FormLabel>Password</FormLabel>
            <RouterLink to="/forgot-password" class="text-sm font-medium text-primary hover:underline">Forgot password?</RouterLink>
          </div>
          <FormControl>
            <Input type="password" autocomplete="current-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Signing in" : "Sign in" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">New to the registry? <RouterLink :to="{ path: '/register', query: route.query.redirect ? { redirect: route.query.redirect } : undefined }" class="font-medium text-primary hover:underline">Create a publisher account</RouterLink></p>
  </Card>
</template>
