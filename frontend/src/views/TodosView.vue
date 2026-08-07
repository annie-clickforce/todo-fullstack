<script setup>
import { onMounted, reactive, ref } from "vue";
import { apiFetch } from "../api.js";

const userName = ref("");
const userEmail = ref("");

onMounted(async () => {
  try {
    const user = await apiFetch("/auth/me");
    userName.value = user.name;
    userEmail.value = user.email;
  } catch {
    userName.value = "";
    userEmail.value = "";
  }
});

const todos = ref([
  { id: 1, title: "買牛奶", description: "順便買麵包", completed: false },
  { id: 2, title: "寫周報", description: "", completed: true },
]);

const newTodo = reactive({
  title: "",
  description: "",
});

let nextId = todos.value.length + 1;

function addTodo() {
  if (!newTodo.title.trim()) {
    return;
  }
  todos.value.push({
    id: nextId++,
    title: newTodo.title.trim(),
    description: newTodo.description.trim(),
    completed: false,
  });
  newTodo.title = "";
  newTodo.description = "";
}

function toggleTodo(id) {
  const todo = todos.value.find((item) => item.id === id);
  if (todo) {
    todo.completed = !todo.completed;
  }
}

function removeTodo(id) {
  todos.value = todos.value.filter((item) => item.id !== id);
}

function handleLogout() {}
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
          <input id="todo-title" v-model="newTodo.title" type="text" required />
        </div>
        <div class="field">
          <label for="todo-description">說明</label>
          <input id="todo-description" v-model="newTodo.description" type="text" />
        </div>
        <button type="submit" class="btn btn-primary" style="width: auto; align-self: flex-end">
          新增
        </button>
      </form>

      <ul v-if="todos.length > 0" class="todo-list">
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
