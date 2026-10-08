<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useForm } from 'vee-validate';
import { toTypedSchema } from '@vee-validate/zod';
import * as z from 'zod';
import { api } from "@/services/api";
import { notifyError, notifySuccess } from "@/utils/toast";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { useAuth } from "@/composables/useAuth";

const auth = useAuth();
const route = useRoute();
const router = useRouter();
const step = ref<'request' | 'verify'>('request');

const requestSchema = toTypedSchema(z.object({
  current_password: z.string().min(1, 'Current password is required'),
  new_email: z.string().email('Invalid email address').min(1, 'New email is required')
}));

const verifySchema = toTypedSchema(z.object({
  token: z.string().min(1, 'Token is required')
}));

const {
  handleSubmit: handleRequestSubmit,
  defineField: defineRequestField,
  errors: requestErrors,
  isSubmitting: isRequestSubmitting,
  resetForm: resetRequestForm,
  setErrors: setRequestErrors
} = useForm({
  validationSchema: requestSchema,
});

const [currentPassword, currentPasswordProps] = defineRequestField('current_password');
const [newEmail, newEmailProps] = defineRequestField('new_email');

const onRequestSubmit = handleRequestSubmit(async (values) => {
  try {
    await api.post('/v1/user/email/request', {
      current_password: values.current_password,
      new_email: values.new_email
    });
    notifySuccess("Verification code sent to your new email.");
    resetRequestForm();
    step.value = 'verify';
  } catch (e: any) {
    if (e?.response?.data?.errors) {
      const errors = e.response.data.errors;
      const formattedErrors: Record<string, string> = {};
      for (const key in errors) {
        formattedErrors[key] = errors[key][0];
      }
      setRequestErrors(formattedErrors);
    } else {
      notifyError(e?.response?.data?.message || "Failed to request email change.");
    }
  }
});

const {
  handleSubmit: handleVerifySubmit,
  defineField: defineVerifyField,
  errors: verifyErrors,
  isSubmitting: isVerifySubmitting,
  resetForm: resetVerifyForm,
  setErrors: setVerifyErrors
} = useForm({
  validationSchema: verifySchema,
});

const [token, tokenProps] = defineVerifyField('token');

const onVerifySubmit = handleVerifySubmit(async (values) => {
  try {
    const response = await api.post('/v1/user/email/verify', {
      token: values.token
    });
    if (auth.user) {
       Object.assign(auth.user, response.data.data);
    }
    notifySuccess("Email changed successfully.");
    resetVerifyForm();
    step.value = 'request';
  } catch (e: any) {
    if (e?.response?.data?.errors) {
      const errors = e.response.data.errors;
      const formattedErrors: Record<string, string> = {};
      for (const key in errors) {
        formattedErrors[key] = errors[key][0];
      }
      setVerifyErrors(formattedErrors);
    } else {
      notifyError(e?.response?.data?.message || "Invalid or expired token.");
    }
  }
});

onMounted(() => {
  if (route.query.token) {
    step.value = 'verify';
    token.value = route.query.token as string;
    
    // Xoá token khỏi thanh URL để nhìn gọn hơn
    const query = { ...route.query };
    delete query.token;
    router.replace({ query });
  }
});
</script>

<template>
  <div>
    <div class="mb-5">
      <h2 class="text-lg font-medium">Change Email</h2>
      <p class="text-sm text-muted-foreground">Update your email address.</p>
    </div>

    <div v-if="step === 'request'">
      <form class="space-y-4" @submit.prevent="onRequestSubmit">
        <div class="space-y-1.5">
          <Label for="currentPassword">Current password</Label>
          <Input id="currentPassword" v-model="currentPassword" v-bind="currentPasswordProps" type="password" autocomplete="current-password" :aria-invalid="Boolean(requestErrors.current_password)" />
          <p v-if="requestErrors.current_password" class="text-xs text-destructive">{{ requestErrors.current_password }}</p>
        </div>

        <div class="space-y-1.5">
          <Label for="newEmail">New email</Label>
          <Input id="newEmail" v-model="newEmail" v-bind="newEmailProps" type="email" autocomplete="email" :aria-invalid="Boolean(requestErrors.new_email)" />
          <p v-if="requestErrors.new_email" class="text-xs text-destructive">{{ requestErrors.new_email }}</p>
        </div>

        <div class="flex justify-start pt-2">
          <Button type="submit" :disabled="isRequestSubmitting">
            <Spinner v-if="isRequestSubmitting" />
            {{ isRequestSubmitting ? "Requesting..." : "Request Email Change" }}
          </Button>
        </div>
      </form>
    </div>

    <div v-else>
      <form class="space-y-4" @submit.prevent="onVerifySubmit">
        <div class="space-y-1.5">
          <Label for="token">Verification Token</Label>
          <Input id="token" v-model="token" v-bind="tokenProps" type="text" :aria-invalid="Boolean(verifyErrors.token)" />
          <p v-if="verifyErrors.token" class="text-xs text-destructive">{{ verifyErrors.token }}</p>
          <p class="text-xs text-muted-foreground">Please check your new email for the verification token.</p>
        </div>

        <div class="flex justify-start gap-2 pt-2">
          <Button type="button" variant="outline" @click="step = 'request'">
            Back
          </Button>
          <Button type="submit" :disabled="isVerifySubmitting">
            <Spinner v-if="isVerifySubmitting" />
            {{ isVerifySubmitting ? "Verifying..." : "Verify & Update Email" }}
          </Button>
        </div>
      </form>
    </div>
  </div>
</template>

