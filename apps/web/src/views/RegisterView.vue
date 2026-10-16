<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
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
import { registerSchema } from "@/schemas/auth.schema";

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
  } catch (e) {
    errorMsg.value = getApiErrorMessage(e, {
      default: "Couldn't create your account. Please try again.",
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
      <FormField v-slot="{ componentField }" name="name">
        <FormItem>
          <FormLabel>Full name</FormLabel>
          <FormControl>
            <Input type="text" autocomplete="name" placeholder="Jane Doe" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
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
          <FormLabel>Password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>
      <FormField v-slot="{ componentField }" name="confirmPassword">
        <FormItem>
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
