<script setup lang="ts">
import { useForm } from 'vee-validate';
import { getProfile } from "@/api/generated/endpoints";
import { passwordSchema } from "@/schemas/profile.schema";
import { notifyError, notifySuccess } from "@/utils/toast";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

const { profileUpdatePassword } = getProfile();

const {
  handleSubmit,
  defineField,
  errors,
  isSubmitting,
  resetForm,
} = useForm({
  validationSchema: passwordSchema,
});

const [currentPassword, currentPasswordProps] = defineField('current_password');
const [newPassword, newPasswordProps] = defineField('new_password');
const [confirmPassword, confirmPasswordProps] = defineField('new_password_confirmation');

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

    <form class="space-y-4" @submit.prevent="onSubmit">
      <div class="space-y-1.5">
        <Label for="currentPassword">Current password</Label>
        <Input id="currentPassword" v-model="currentPassword" v-bind="currentPasswordProps" type="password" autocomplete="current-password" :aria-invalid="Boolean(errors.current_password)" />
        <p v-if="errors.current_password" class="text-xs text-destructive">{{ errors.current_password }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="newPassword">New password</Label>
        <Input id="newPassword" v-model="newPassword" v-bind="newPasswordProps" type="password" autocomplete="new-password" :aria-invalid="Boolean(errors.new_password)" />
        <p v-if="errors.new_password" class="text-xs text-destructive">{{ errors.new_password }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="confirmPassword">Confirm new password</Label>
        <Input id="confirmPassword" v-model="confirmPassword" v-bind="confirmPasswordProps" type="password" autocomplete="new-password" :aria-invalid="Boolean(errors.new_password_confirmation)" />
        <p v-if="errors.new_password_confirmation" class="text-xs text-destructive">{{ errors.new_password_confirmation }}</p>
      </div>

      <div class="flex justify-start pt-2">
        <Button type="submit" :disabled="isSubmitting">
          <Spinner v-if="isSubmitting" />
          {{ isSubmitting ? "Updating..." : "Change password" }}
        </Button>
      </div>
    </form>
  </div>
</template>
