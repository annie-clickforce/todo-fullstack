<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { apiFetch, ApiError } from "../api.js";

const router = useRouter();

const form = reactive({
  name: "",
  email: "",
  password: "",
});

const errors = ref({});
const isSubmitting = ref(false);

async function handleSubmit() {
  errors.value = {};
  isSubmitting.value = true;
  try {
    await apiFetch("/auth/register", {
      method: "POST",
      body: JSON.stringify(form),
    });
    router.push("/login");
  } catch (error) {
    if (error instanceof ApiError) {
      errors.value = error.errors;
    } else {
      errors.value = { general: ["註冊失敗，請稍後再試"] };
    }
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>註冊</h1>
      <form @submit.prevent="handleSubmit">
        <div class="field">
          <label for="register-name">名稱</label>
          <input id="register-name" v-model="form.name" type="text" autocomplete="name" required />
          <p v-if="errors.name" class="error-text">{{ errors.name[0] }}</p>
        </div>
        <div class="field">
          <label for="register-email">Email</label>
          <input id="register-email" v-model="form.email" type="email" autocomplete="email" required />
          <p v-if="errors.email" class="error-text">{{ errors.email[0] }}</p>
        </div>
        <div class="field">
          <label for="register-password">密碼</label>
          <input
            id="register-password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            minlength="8"
            required
          />
          <p v-if="errors.password" class="error-text">{{ errors.password[0] }}</p>
        </div>
        <p v-if="errors.general" class="error-text">{{ errors.general[0] }}</p>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">註冊</button>
      </form>
      <p class="helper-text">
        已經有帳號？<router-link to="/login">前往登入</router-link>
      </p>
    </div>
  </div>
</template>
