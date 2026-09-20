<script setup lang="ts">
import { RouterLink, RouterView } from "vue-router";
</script>

<template>
  <div>
    <nav>
      <RouterLink to="/">Home</RouterLink>
      |
      <RouterLink to="/login">Login</RouterLink>
    </nav>
    <RouterView />
  </div>
</template>
