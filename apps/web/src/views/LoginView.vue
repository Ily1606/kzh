<script setup lang="ts">
import { ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuth } from "@/composables/useAuth";
import { useForm } from "vee-validate";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { loginSchema } from "@/schemas/auth.schema";

const { handleSubmit, defineField, errors } = useForm({
  validationSchema: loginSchema,
  initialValues: {
    email: "",
    password: "",
  },
});

const [email, emailProps] = defineField("email");
const [password, passwordProps] = defineField("password");

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
  } catch (e: any) {
    errorMsg.value = "Incorrect email or password.";
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
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" v-bind="emailProps" type="email" autocomplete="email" placeholder="you@example.com" :aria-invalid="Boolean(errors.email)" />
        <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email }}</p>
      </div>
      <div class="space-y-1.5">
        <div class="flex items-center justify-between">
          <Label for="password">Password</Label>
          <RouterLink to="/forgot-password" class="text-sm font-medium text-primary hover:underline">Forgot password?</RouterLink>
        </div>
        <Input id="password" v-model="password" v-bind="passwordProps" type="password" autocomplete="current-password" :aria-invalid="Boolean(errors.password)" />
        <p v-if="errors.password" class="text-xs text-destructive">{{ errors.password }}</p>
      </div>
      <Button class="w-full" type="submit" :disabled="loading"><Spinner v-if="loading" />{{ loading ? "Signing in" : "Sign in" }}</Button>
    </form>
    <p class="border-t px-6 py-4 text-center text-sm text-muted-foreground">New to the registry? <RouterLink to="/register" class="font-medium text-primary hover:underline">Create a publisher account</RouterLink></p>
  </Card>
</template>
