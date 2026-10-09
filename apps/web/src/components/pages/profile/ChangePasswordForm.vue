<script setup lang="ts">
import { useForm, Field as FormField } from 'vee-validate';
import { getProfile } from "@/api/generated/endpoints";
import { passwordSchema } from "@/schemas/profile.schema";
import { notifyError, notifySuccess } from "@/utils/toast";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import {
  FormControl,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

const { profileUpdatePassword } = getProfile();

const {
  handleSubmit,
  isSubmitting,
  resetForm,
} = useForm({
  validationSchema: passwordSchema,
});

const onSubmit = handleSubmit(async (values) => {
  try {
    await profileUpdatePassword({
      current_password: values.current_password,
      new_password: values.new_password,
      new_password_confirmation: values.new_password_confirmation,
    });
    notifySuccess("Password changed successfully.");
    resetForm();
  } catch (e: any) {
    notifyError(e?.response?.data?.message || "Failed to change password. Please check your current password.");
  }
});
</script>

<template>
  <div>
    <div class="mb-5">
      <h2 class="text-lg font-medium">Change Password</h2>
      <p class="text-sm text-muted-foreground">Update your password to keep your account secure.</p>
    </div>

    <form class="space-y-4" @submit="onSubmit">
      <FormField v-slot="{ componentField }" name="current_password">
        <FormItem class="space-y-1.5">
          <FormLabel>Current password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="current-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }" name="new_password">
        <FormItem class="space-y-1.5">
          <FormLabel>New password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }" name="new_password_confirmation">
        <FormItem class="space-y-1.5">
          <FormLabel>Confirm new password</FormLabel>
          <FormControl>
            <Input type="password" autocomplete="new-password" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <div class="flex justify-start pt-2">
        <Button type="submit" :disabled="isSubmitting">
          <Spinner v-if="isSubmitting" />
          {{ isSubmitting ? "Updating..." : "Change password" }}
        </Button>
      </div>
    </form>
  </div>
</template>
