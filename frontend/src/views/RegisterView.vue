<script setup>
import { reactive } from "vue";

const form = reactive({
  name: "",
  email: "",
  password: "",
});

function handleSubmit() {}
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>註冊</h1>
      <form @submit.prevent="handleSubmit">
        <div class="field">
          <label for="register-name">名稱</label>
          <input id="register-name" v-model="form.name" type="text" autocomplete="name" required />
        </div>
        <div class="field">
          <label for="register-email">Email</label>
          <input id="register-email" v-model="form.email" type="email" autocomplete="email" required />
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
        </div>
        <button type="submit" class="btn btn-primary">註冊</button>
      </form>
      <p class="helper-text">
        已經有帳號？<router-link to="/login">前往登入</router-link>
      </p>
    </div>
  </div>
</template>
