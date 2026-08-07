import { createRouter, createWebHistory } from "vue-router";
import LoginView from "../views/LoginView.vue";
import RegisterView from "../views/RegisterView.vue";
import TodosView from "../views/TodosView.vue";
import { apiFetch } from "../api.js";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/", redirect: "/login" },
    { path: "/login", name: "login", component: LoginView },
    { path: "/register", name: "register", component: RegisterView },
    { path: "/todos", name: "todos", component: TodosView, meta: { requiresAuth: true } },
  ],
});

router.beforeEach(async (to) => {
  if (!to.meta.requiresAuth) {
    return true;
  }

  try {
    await apiFetch("/auth/me");
    return true;
  } catch {
    return { name: "login" };
  }
});

export default router;
