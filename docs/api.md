# API 規格

## 基本約定

- Base URL：`/api/v1`
- 資料格式：請求與回應皆使用 JSON。
- 認證：登入後以 HTTP-only Cookie 保存 Session 或 JWT；需要登入的端點必須帶上有效憑證。
- 日期格式：使用 ISO 8601 UTC 字串，例如 `2026-08-05T12:00:00.000Z`。

成功回應使用 `2xx` 狀態碼並直接回傳資源資料；失敗回應統一使用下列格式：

```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["Email is invalid"]
  }
}
```

## 認證

### 註冊

`POST /auth/register`

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "secure-password"
}
```

回傳 `201 Created` 與新使用者資料。`email` 必須唯一，密碼至少 8 個字元。

### 登入

`POST /auth/login`

```json
{
  "email": "jane@example.com",
  "password": "secure-password"
}
```

回傳 `200 OK`、設定認證 Cookie，並回傳目前使用者資料。帳號或密碼錯誤時回傳 `401 Unauthorized`。

### 登出

`POST /auth/logout`

需要登入。回傳 `204 No Content`，並清除認證 Cookie。

### 忘記密碼

`POST /auth/forgot-password`

```json
{
  "email": "jane@example.com"
}
```

無論帳號是否存在皆回傳 `202 Accepted`，避免洩漏帳號資訊。系統會寄出含重設 Token 的連結。

### 重設密碼

`POST /auth/reset-password`

```json
{
  "token": "reset-token",
  "password": "new-secure-password"
}
```

回傳 `204 No Content`。Token 無效、過期或已使用時回傳 `422 Unprocessable Entity`。

## Todo

Todo 資源格式：

```json
{
  "id": "uuid",
  "title": "Buy groceries",
  "description": "Milk and bread",
  "completed": false,
  "createdAt": "2026-08-05T12:00:00.000Z",
  "updatedAt": "2026-08-05T12:00:00.000Z"
}
```

所有 Todo 端點都需要登入，且使用者只能存取自己的 Todo。

| 方法 | 路徑 | 說明 |
| --- | --- | --- |
| `GET` | `/todos` | 取得目前使用者的 Todo 清單。可使用 `?completed=true` 篩選。 |
| `POST` | `/todos` | 建立 Todo。 |
| `GET` | `/todos/:id` | 取得單筆 Todo。 |
| `PATCH` | `/todos/:id` | 更新標題、說明或完成狀態。 |
| `DELETE` | `/todos/:id` | 刪除 Todo，回傳 `204 No Content`。 |

建立 Todo 的請求範例：

```json
{
  "title": "Buy groceries",
  "description": "Milk and bread"
}
```

`title` 為必填且不可為空；`description` 選填。若資源不存在或不屬於目前使用者，一律回傳 `404 Not Found`。

## 常用狀態碼

| 狀態碼 | 使用時機 |
| --- | --- |
| `200` | 成功讀取或更新資源。 |
| `201` | 成功建立資源。 |
| `202` | 已接受非同步處理請求。 |
| `204` | 成功完成且沒有回應內容。 |
| `401` | 未登入或登入資訊無效。 |
| `404` | 找不到資源，或資源不屬於目前使用者。 |
| `422` | 請求欄位或 Token 驗證失敗。 |
