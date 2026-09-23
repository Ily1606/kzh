import { createRouter, createWebHistory } from "vue-router";
import HomeView from "@/views/HomeView.vue";
import LoginView from "@/views/LoginView.vue";
import RegisterView from "@/views/RegisterView.vue";

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: "/",
      name: "home",
      component: HomeView,
      meta: { layout: 'default' }
    },
    {
      path: "/login",
      name: "login",
      component: LoginView,
      meta: { layout: 'auth', title: 'Sign in to your account' }
    },
    {
      path: "/register",
      name: "register",
      component: RegisterView,
      meta: { layout: 'auth', title: 'Create a new account' }
    },
    {
      path: "/forgot-password",
      name: "forgot-password",
      component: () => import('@/views/ForgotPasswordView.vue'),
      meta: { layout: 'auth', title: 'Reset your password' }
    },
    {
      path: "/reset-password",
      name: "reset-password",
      component: () => import('@/views/ResetPasswordView.vue'),
      meta: { layout: 'auth', title: 'Create new password' }
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { layout: 'default' }
    }
  ],
});
