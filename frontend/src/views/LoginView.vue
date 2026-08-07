<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { apiFetch, ApiError } from "../api.js";

const router = useRouter();

const form = reactive({
  email: "",
  password: "",
});

const errorMessage = ref("");
const isSubmitting = ref(false);

async function handleSubmit() {
  errorMessage.value = "";
  isSubmitting.value = true;
  try {
    await apiFetch("/auth/login", {
      method: "POST",
      body: JSON.stringify(form),
    });
    router.push("/todos");
  } catch (error) {
    errorMessage.value = error instanceof ApiError ? error.message : "登入失敗，請稍後再試";
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>登入</h1>
      <form @submit.prevent="handleSubmit">
        <div class="field">
          <label for="login-email">Email</label>
          <input id="login-email" v-model="form.email" type="email" autocomplete="email" required />
        </div>
        <div class="field">
          <label for="login-password">密碼</label>
          <input
            id="login-password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required
          />
        </div>
        <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">登入</button>
      </form>
      <p class="helper-text">
        還沒有帳號？<router-link to="/register">前往註冊</router-link>
      </p>
    </div>
  </div>
</template>
