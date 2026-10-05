<script setup lang="ts">
import { ref } from "vue";
import { Toaster } from "vue-sonner";
import "vue-sonner/style.css";
import { ArrowLeft, UserRound, Lock } from "lucide-vue-next";
import { SUCCESS_DURATION } from "@/utils/toast";
import PersonalInfoForm from "@/components/pages/profile/PersonalInfoForm.vue";
import ChangePasswordForm from "@/components/pages/profile/ChangePasswordForm.vue";

const activeTab = ref<'profile' | 'password'>('profile');
</script>

<template>
  <div class="py-8 sm:py-12">
    <div class="mb-8">
      <RouterLink to="/" class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground">
        <ArrowLeft class="size-4" />
        Back to home
      </RouterLink>
      <h1 class="text-2xl font-semibold tracking-tight">Profile Settings</h1>
      <p class="mt-1 text-sm text-muted-foreground">Manage your account information and security.</p>
    </div>

    <div class="flex flex-col md:flex-row gap-8">
      <!-- Sidebar -->
      <aside class="md:w-64 flex-shrink-0">
        <nav class="flex flex-col space-y-1">
          <button
            @click="activeTab = 'profile'"
            class="flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md transition-colors"
            :class="activeTab === 'profile' ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
          >
            <UserRound class="size-4" />
            Personal Information
          </button>
          <button
            @click="activeTab = 'password'"
            class="flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md transition-colors"
            :class="activeTab === 'password' ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
          >
            <Lock class="size-4" />
            Change Password
          </button>
        </nav>
      </aside>

      <!-- Main Content -->
      <main class="flex-1">
        <div class="max-w-xl">
          <PersonalInfoForm v-if="activeTab === 'profile'" />
          <ChangePasswordForm v-else />
        </div>
      </main>
    </div>

    <Toaster
      position="top-center"
      rich-colors
      close-button
      :duration="SUCCESS_DURATION"
      :visible-toasts="3"
    />
  </div>
</template>
