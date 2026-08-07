<script setup>
import { reactive } from "vue";

const form = reactive({
  email: "",
  password: "",
});

function handleSubmit() {}
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
        <button type="submit" class="btn btn-primary">登入</button>
      </form>
      <p class="helper-text">
        還沒有帳號？<router-link to="/register">前往註冊</router-link>
      </p>
    </div>
  </div>
</template>
