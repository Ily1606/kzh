<script setup lang="ts">
import { ref } from "vue";

const email = ref("user@example.com");
const password = ref("password");
const message = ref("");

async function onSubmit() {
  message.value = "Login stub — use Sanctum CSRF flow when wiring forms.";
}
</script>

<template>
  <main>
    <h1>Login</h1>
    <form @submit.prevent="onSubmit">
      <label>Email <input v-model="email" type="email" /></label>
      <label>Password <input v-model="password" type="password" /></label>
      <button type="submit">Sign in</button>
    </form>
    <p>{{ message }}</p>
  </main>
</template>
