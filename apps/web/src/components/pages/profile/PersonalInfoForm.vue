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
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

const auth = useAuth();
const { profileUpdateProfile, profileUpdateAvatar } = getProfile();

const avatarPreview = ref<string | null>(null);
const avatarUploading = ref(false);

// Ưu tiên ảnh vừa upload, không có thì dùng avatar từ server
const currentAvatar = computed(() => avatarPreview.value || auth.user?.avatarLink || '');

// Component Avatar.vue đã kiểm tra định dạng + dung lượng trước khi emit ra file
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
  defineField,
  errors,
  isSubmitting,
  setValues,
} = useForm({
  validationSchema: profileSchema,
});

const [name, nameProps] = defineField('name');
const [githubName, githubNameProps] = defineField('githubName');
const [githubLink, githubLinkProps] = defineField('githubLink');

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

// Pre-fill from current user
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

    <!-- Avatar -->
    <div class="mb-6">
      <Avatar
        :src="currentAvatar"
        :alt="auth.user?.name"
        :uploading="avatarUploading"
        @select="uploadAvatar"
      />
    </div>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <div class="space-y-1.5">
        <Label for="profileName">Name</Label>
        <Input id="profileName" v-model="name" v-bind="nameProps" type="text" autocomplete="name" placeholder="Your name" :aria-invalid="Boolean(errors.name)" />
        <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="githubName">GitHub username</Label>
        <Input id="githubName" v-model="githubName" v-bind="githubNameProps" type="text" placeholder="johndoe" :aria-invalid="Boolean(errors.githubName)" />
        <p v-if="errors.githubName" class="text-xs text-destructive">{{ errors.githubName }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="githubLink">GitHub URL</Label>
        <Input id="githubLink" v-model="githubLink" v-bind="githubLinkProps" type="url" placeholder="https://github.com/johndoe" :aria-invalid="Boolean(errors.githubLink)" />
        <p v-if="errors.githubLink" class="text-xs text-destructive">{{ errors.githubLink }}</p>
      </div>

      <div class="flex justify-start pt-2">
        <Button type="submit" :disabled="isSubmitting">
          <Spinner v-if="isSubmitting" />
          {{ isSubmitting ? "Saving..." : "Save changes" }}
        </Button>
      </div>
    </form>
  </div>
</template>
