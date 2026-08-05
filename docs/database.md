# 資料庫設計

本專案使用 PostgreSQL。主鍵採用 UUID，時間欄位統一以 `timestamptz` 儲存 UTC 時間。

## `users`

| 欄位 | 型別 | 限制 | 說明 |
| --- | --- | --- | --- |
| `id` | `uuid` | PK、預設產生 | 使用者識別碼。 |
| `name` | `varchar(100)` | NOT NULL | 顯示名稱。 |
| `email` | `varchar(255)` | NOT NULL、UNIQUE | 登入帳號，儲存時應轉小寫。 |
| `password_hash` | `text` | NOT NULL | 使用 Argon2 或 bcrypt 雜湊後的密碼。 |
| `created_at` | `timestamptz` | NOT NULL | 建立時間。 |
| `updated_at` | `timestamptz` | NOT NULL | 最後更新時間。 |

## `todos`

| 欄位 | 型別 | 限制 | 說明 |
| --- | --- | --- | --- |
| `id` | `uuid` | PK、預設產生 | Todo 識別碼。 |
| `user_id` | `uuid` | NOT NULL、FK | 所屬使用者，參照 `users.id`。 |
| `title` | `varchar(255)` | NOT NULL | Todo 標題。 |
| `description` | `text` | NULL | 補充說明。 |
| `completed` | `boolean` | NOT NULL、預設 `false` | 是否已完成。 |
| `created_at` | `timestamptz` | NOT NULL | 建立時間。 |
| `updated_at` | `timestamptz` | NOT NULL | 最後更新時間。 |

建立索引 `todos(user_id, completed, created_at DESC)`，支援依使用者取得清單與完成狀態篩選。外鍵刪除規則使用 `ON DELETE CASCADE`，刪除使用者時一併移除其 Todo。

## `password_reset_tokens`

| 欄位 | 型別 | 限制 | 說明 |
| --- | --- | --- | --- |
| `id` | `uuid` | PK、預設產生 | Token 記錄識別碼。 |
| `user_id` | `uuid` | NOT NULL、FK | 參照 `users.id`。 |
| `token_hash` | `text` | NOT NULL、UNIQUE | 重設 Token 的雜湊值，不保存明文。 |
| `expires_at` | `timestamptz` | NOT NULL | 到期時間。 |
| `used_at` | `timestamptz` | NULL | 使用完成時間。 |
| `created_at` | `timestamptz` | NOT NULL | 建立時間。 |

重設密碼時只接受未使用且未過期的 Token。成功重設後應寫入 `used_at`，並使同一使用者其他有效 Token 失效。

## 關聯

```text
users 1 --- N todos
users 1 --- N password_reset_tokens
```

資料庫帳號應採最小權限原則，並透過環境變數提供連線字串，例如 `DATABASE_URL`；不可將正式環境密碼納入版本控制。
