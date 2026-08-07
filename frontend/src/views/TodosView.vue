<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { apiFetch, ApiError } from "../api.js";

const router = useRouter();

const userName = ref("");
const userEmail = ref("");

const todos = ref([]);
const isLoading = ref(true);
const loadError = ref("");

const newTodo = reactive({
  title: "",
  description: "",
});
const addError = ref("");

function redirectIfSessionExpired(error) {
  if (error instanceof ApiError && error.status === 401) {
    router.push("/login");
    return true;
  }
  return false;
}

onMounted(async () => {
  try {
    const user = await apiFetch("/auth/me");
    userName.value = user.name;
    userEmail.value = user.email;
  } catch {
    userName.value = "";
    userEmail.value = "";
  }

  try {
    todos.value = await apiFetch("/todos");
  } catch (error) {
    if (!redirectIfSessionExpired(error)) {
      loadError.value = "讀取待辦事項失敗，請重新整理再試一次";
    }
  } finally {
    isLoading.value = false;
  }
});

async function addTodo() {
  if (!newTodo.title.trim()) {
    return;
  }

  addError.value = "";
  try {
    const todo = await apiFetch("/todos", {
      method: "POST",
      body: JSON.stringify({
        title: newTodo.title.trim(),
        description: newTodo.description.trim(),
      }),
    });
    todos.value.unshift(todo);
    newTodo.title = "";
    newTodo.description = "";
  } catch (error) {
    if (!redirectIfSessionExpired(error)) {
      addError.value = error instanceof ApiError ? error.message : "新增失敗，請稍後再試";
    }
  }
}

async function toggleTodo(id) {
  const todo = todos.value.find((item) => item.id === id);
  if (!todo) {
    return;
  }

  const previousState = todo.completed;
  todo.completed = !previousState;

  try {
    await apiFetch(`/todos/${id}`, {
      method: "PATCH",
      body: JSON.stringify({ completed: todo.completed }),
    });
  } catch (error) {
    todo.completed = previousState;
    redirectIfSessionExpired(error);
  }
}

async function removeTodo(id) {
  try {
    await apiFetch(`/todos/${id}`, { method: "DELETE" });
    todos.value = todos.value.filter((item) => item.id !== id);
  } catch (error) {
    if (!redirectIfSessionExpired(error)) {
      loadError.value = "刪除失敗，請稍後再試";
    }
  }
}

async function handleLogout() {
  try {
    await apiFetch("/auth/logout", { method: "POST" });
  } finally {
    router.push("/login");
  }
}
</script>

<template>
  <div class="page page--top">
    <div class="card card--wide">
      <div class="page-header">
        <div>
          <h1><span class="title-highlight">{{ userName }}</span> 的待辦事項</h1>
          <p class="subtitle">登入帳號：{{ userEmail }}</p>
        </div>
        <button type="button" class="btn btn-secondary" @click="handleLogout">登出</button>
      </div>

      <form class="todo-form" @submit.prevent="addTodo">
        <div class="field">
          <label for="todo-title">標題</label>
          <input id="todo-title" v-model="newTodo.title" type="text" maxlength="255" required />
        </div>
        <div class="field">
          <label for="todo-description">說明</label>
          <input id="todo-description" v-model="newTodo.description" type="text" />
        </div>
        <button type="submit" class="btn btn-primary" style="width: auto; align-self: flex-end">
          新增
        </button>
      </form>
      <p v-if="addError" class="error-text">{{ addError }}</p>
      <p v-if="loadError" class="error-text">{{ loadError }}</p>

      <p v-if="isLoading" class="empty-state">載入中…</p>
      <ul v-else-if="todos.length > 0" class="todo-list">
        <li v-for="todo in todos" :key="todo.id" class="todo-item">
          <input type="checkbox" :checked="todo.completed" @change="toggleTodo(todo.id)" />
          <div class="todo-item__body">
            <div class="todo-item__title" :class="{ 'todo-item__title--done': todo.completed }">
              {{ todo.title }}
            </div>
            <p v-if="todo.description" class="todo-item__description">{{ todo.description }}</p>
          </div>
          <button type="button" class="btn btn-danger" @click="removeTodo(todo.id)">刪除</button>
        </li>
      </ul>
      <p v-else class="empty-state">目前沒有待辦事項</p>
    </div>
  </div>
</template>
