<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useForm } from 'vee-validate';
import { useAuth } from "@/composables/useAuth";
import { getProfile } from "@/api/generated/endpoints";
import { profileSchema } from "@/schemas/profile.schema";
import { notifyError, notifySuccess } from "@/utils/toast";
import Avatar from "@/components/pages/profile/Avatar.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

const auth = useAuth();
const { profileUpdateProfile, profileUpdateAvatar } = getProfile();

const avatarPreview = ref<string | null>(null);
const avatarUploading = ref(false);

const currentAvatar = computed(() => avatarPreview.value || auth.user?.avatarLink || '');

async function uploadAvatar(file: File) {
  avatarUploading.value = true;
  try {
    const response = await profileUpdateAvatar({ avatar: file });
    if (auth.user) Object.assign(auth.user, response.data);
    revokePreview();
    avatarPreview.value = URL.createObjectURL(file);
    notifySuccess("Avatar updated successfully.");
  } catch (e: any) {
    notifyError(e?.response?.data?.message || "Failed to update avatar. Please try again.");
  } finally {
    avatarUploading.value = false;
  }
}

function revokePreview() {
  if (avatarPreview.value) {
    URL.revokeObjectURL(avatarPreview.value);
    avatarPreview.value = null;
  }
}
onUnmounted(revokePreview);

const {
  handleSubmit,
  isSubmitting,
  setValues,
} = useForm({
  validationSchema: profileSchema,
});

const onSubmit = handleSubmit(async (values) => {
  try {
    const response = await profileUpdateProfile({
      name: values.name,
      githubName: values.githubName || null,
      githubLink: values.githubLink || null,
    });
    if (auth.user) Object.assign(auth.user, response.data);
    notifySuccess("Profile updated successfully.");
  } catch (e: any) {
    notifyError(e?.response?.data?.message || "Failed to update profile. Please try again.");
  }
});

onMounted(() => {
  if (auth.user) {
    setValues({
      name: auth.user.name ?? "",
      githubName: auth.user.githubName ?? "",
      githubLink: auth.user.githubLink ?? "",
    });
  }
});
</script>

<template>
  <div>
    <div class="mb-5">
      <h2 class="text-lg font-medium">Personal Information</h2>
      <p class="text-sm text-muted-foreground">Update your name and GitHub details.</p>
    </div>

    <div class="mb-6">
      <Avatar
        :src="currentAvatar"
        :alt="auth.user?.name"
        :uploading="avatarUploading"
        @select="uploadAvatar"
      />
    </div>

    <form class="space-y-4" @submit="onSubmit">
      <FormField v-slot="{ componentField }: any" name="name">
        <FormItem class="space-y-1.5">
          <FormLabel>Name</FormLabel>
          <FormControl>
            <Input type="text" autocomplete="name" placeholder="Your name" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }: any" name="githubName">
        <FormItem class="space-y-1.5">
          <FormLabel>GitHub username</FormLabel>
          <FormControl>
            <Input type="text" placeholder="johndoe" v-bind="componentField" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField v-slot="{ componentField }: any" name="githubLink">
        <FormItem class="space-y-1.5">
          <FormLabel>GitHub URL</FormLabel>
          <FormControl>
            <Input
            type="url"
            placeholder="https://github.com/johndoe"
            v-bind="componentField"
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <div class="flex justify-start pt-2">
        <Button type="submit" :disabled="isSubmitting">
          <Spinner v-if="isSubmitting" />
          {{ isSubmitting ? "Saving..." : "Save changes" }}
        </Button>
      </div>
    </form>
  </div>
</template>
