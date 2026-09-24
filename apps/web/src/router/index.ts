import { createRouter, createWebHistory } from "vue-router";
import HomeView from "@/views/HomeView.vue";
import LoginView from "@/views/LoginView.vue";
import RegisterView from "@/views/RegisterView.vue";
import { useAuth } from "@/composables/useAuth";

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
      meta: { layout: 'auth', title: 'Sign in to publish plugins', guestOnly: true }
    },
    {
      path: "/register",
      name: "register",
      component: RegisterView,
      meta: { layout: 'auth', title: 'Create a publisher account', guestOnly: true }
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

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' };
  }
});
