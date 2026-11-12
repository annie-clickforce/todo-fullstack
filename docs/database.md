# 資料庫設計

本專案使用 MySQL 8（本機開發用 Docker 的 `mysql:8.0`，正式環境用 Hostinger 共享主機提供的 MySQL）。資料表由 Laravel migration 建立（`backend-laravel/database/migrations/`），字元集為 `utf8mb4`（`utf8mb4_unicode_ci`），中文與 emoji 都能正常儲存。

- **主鍵**：採用 UUID，在 MySQL 中以 `char(36)` 儲存。UUID 由應用程式產生（Model 建立時以 `Str::uuid()` 填入），資料庫端沒有預設值。
- **時間**：時間欄位以 `timestamp` 儲存 UTC 時間（Laravel 與 MySQL 的時區都設為 UTC），API 回應時輸出 ISO 8601 UTC 格式。`created_at`／`updated_at` 由 Laravel（Eloquent）在建立與更新資料時自動寫入。
- **布林值**：MySQL 沒有真正的布林型別，`boolean` 欄位會建成 `tinyint(1)`（`0`／`1`）。

## `users`

| 欄位 | 型別 | 限制 | 說明 |
| --- | --- | --- | --- |
| `id` | `char(36)` | PK | 使用者識別碼（UUID）。 |
| `name` | `varchar(100)` | NOT NULL | 顯示名稱。 |
| `email` | `varchar(255)` | NOT NULL、UNIQUE | 登入帳號，儲存時應轉小寫。 |
| `password_hash` | `text` | NOT NULL | 使用 Argon2 或 bcrypt 雜湊後的密碼。 |
| `created_at` | `timestamp` | NULL | 建立時間，由 Laravel 寫入。 |
| `updated_at` | `timestamp` | NULL | 最後更新時間，由 Laravel 寫入。 |

## `todos`

| 欄位 | 型別 | 限制 | 說明 |
| --- | --- | --- | --- |
| `id` | `char(36)` | PK | Todo 識別碼（UUID）。 |
| `user_id` | `char(36)` | NOT NULL、FK | 所屬使用者，參照 `users.id`。 |
| `title` | `varchar(255)` | NOT NULL | Todo 標題。 |
| `description` | `text` | NULL | 補充說明。 |
| `completed` | `tinyint(1)` | NOT NULL、`DEFAULT 0` | 是否已完成。 |
| `created_at` | `timestamp` | NULL | 建立時間，由 Laravel 寫入。 |
| `updated_at` | `timestamp` | NULL | 最後更新時間，由 Laravel 寫入。 |

建立索引 `idx_todos_user_completed_created`：`todos(user_id, completed, created_at)`，支援依使用者取得清單與完成狀態篩選；依建立時間由新到舊排序時，MySQL 可以反向掃描這個索引。外鍵刪除規則使用 `ON DELETE CASCADE`，刪除使用者時一併移除其 Todo。

## `password_reset_tokens`

| 欄位 | 型別 | 限制 | 說明 |
| --- | --- | --- | --- |
| `id` | `char(36)` | PK | Token 記錄識別碼（UUID）。 |
| `user_id` | `char(36)` | NOT NULL、FK | 參照 `users.id`，`ON DELETE CASCADE`。 |
| `token_hash` | `varchar(64)` | NOT NULL、UNIQUE | 重設 Token 的 SHA-256 雜湊值（64 字元十六進位），不保存明文。用 `varchar(64)` 而不是 `text`，因為 MySQL 的 `TEXT` 欄位不能直接加 UNIQUE。 |
| `expires_at` | `timestamp` | NOT NULL | 到期時間。 |
| `used_at` | `timestamp` | NULL | 使用完成時間。 |
| `created_at` | `timestamp` | NOT NULL、`DEFAULT CURRENT_TIMESTAMP` | 建立時間。 |

重設密碼時只接受未使用且未過期的 Token。成功重設後應寫入 `used_at`，並使同一使用者其他有效 Token 失效。

## 其他資料表

Laravel 內建的 `migrations`（記錄跑過哪些 migration）、`password_resets`、`failed_jobs`、`personal_access_tokens` 也會一併建立，目前程式沒有用到後三張。

## 關聯

```text
users 1 --- N todos
users 1 --- N password_reset_tokens
```

資料庫帳號應採最小權限原則，連線資訊透過環境變數提供（Laravel 的 `DB_HOST`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD` 等）；不可將正式環境密碼納入版本控制。
