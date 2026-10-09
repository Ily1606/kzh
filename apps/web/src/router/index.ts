import { createRouter, createWebHistory } from "vue-router";
import HomeView from "@/views/HomeView.vue";
import { useAuth } from "@/composables/useAuth";

export const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    if (to.hash) {
      return { el: to.hash, behavior: 'smooth' }
    }
    return { top: 0 }
  },
  routes: [
    {
      path: "/",
      name: "home",
      component: HomeView,
      meta: { layout: 'default' }
    },
    {
      path: "/plugins",
      name: "plugins",
      component: () => import("@/views/PluginsView.vue"),
      meta: { layout: 'default' }
    },
    {
      path: "/publish",
      name: "publish",
      component: () => import("@/views/PublishPluginView.vue"),
      meta: { layout: 'default', requiresAuth: true }
    },
    {
      path: "/resources",
      name: "my-plugins",
      component: () => import("@/views/MyPluginsView.vue"),
      meta: { layout: 'default', requiresAuth: true }
    },
    {
      path: "/login",
      name: "login",
      component: () => import("@/views/LoginView.vue"),
      meta: { layout: 'auth', title: 'Sign in to publish plugins', guestOnly: true }
    },
    {
      path: "/register",
      name: "register",
      component: () => import("@/views/RegisterView.vue"),
      meta: { layout: 'auth', title: 'Create a publisher account', guestOnly: true }
    },
    {
      path: "/forgot-password",
      name: "forgot-password",
      component: () => import('@/views/ForgotPasswordView.vue'),
      meta: { layout: 'auth', title: 'Forgot Password', guestOnly: true }
    },
    {
      path: "/reset-password",
      name: "reset-password",
      component: () => import('@/views/ResetPasswordView.vue'),
      meta: { layout: 'auth', title: 'Reset Password', guestOnly: true }
    },
    {
      path: "/plugins/:id",
      name: "plugin-detail",
      component: () => import("@/views/PluginDetailView.vue"),
      meta: { layout: 'default' }
    },
    {
      path: "/resources/:id/edit",
      name: "plugin-edit",
      component: () => import("@/views/EditPluginView.vue"),
      meta: { layout: 'default', requiresAuth: true }
    },
    {
      path: "/profile",
      name: "profile",
      component: () => import("@/views/ProfileView.vue"),
      meta: { layout: 'default', requiresAuth: true }
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { layout: 'default' }
    }
  ],
});

router.beforeEach((to) => {
  const auth = useAuth();

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' };
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' };
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
});
