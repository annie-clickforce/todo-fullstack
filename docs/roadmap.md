# 開發路線圖

本文件將 Todo MVP 第一階段（見 [architecture.md](architecture.md) 「第一階段範圍」，含註冊／登入／登出）拆成可以一步一步完成、每步都能獨立驗證的小任務。建議一次只做一步，做完就實際測試（curl、瀏覽器或資料庫工具）再進到下一步。

## ✅️ 第一階段：資料庫先通

1. ✅️ 依 [database.md](database.md) 的 `todos` 資料表規格寫一支 SQL migration（欄位：`id`、`title`、`description`、`completed`、`created_at`、`updated_at`；先不加 `user_id`，因為認證功能還沒開始做）。
2. ✅️ 決定怎麼執行 migration——初期可以先手動用 `psql` 跑一次，不用急著導入 migration 工具（例如 node-pg-migrate）。
3. ✅️ 驗證：`docker compose up db`，用 psql 或 DBeaver 連進去，確認資料表已建立。

## ✅️ 第二階段：後端能讀寫資料庫

4. ✅️ 後端加裝 `pg`（PostgreSQL client），寫一支簡單的連線設定，讀取 `DATABASE_URL` 環境變數。
5. ✅️ 先做一個端點：`GET /api/v1/todos`，回傳資料庫查詢結果（沒資料就回傳空陣列），確認 API 能連到 DB。

> **已知問題**：Windows 上 Docker 掛載的資料夾，檔案變更通知有時候傳不進容器，導致 `node --watch` 不會自動偵測到後端程式碼變更並重啟。改完 `backend/src` 的檔案後，如果打 API 發現行為沒變，先執行 `docker compose restart api` 再測試一次。

> **後續會回頭補的事**：`todos` 目前還沒有 `user_id`。第三階段會補一支 migration 加上這個欄位，並回頭把已完成的 `GET /api/v1/todos` 改成只回傳目前登入使用者的資料。

## ✅️ 第三階段：使用者認證（註冊、登入、登出）

6. ✅️ 依 [database.md](database.md) 的 `users` 資料表規格寫一支 migration（欄位：`id`、`name`、`email`、`password_hash`、`created_at`、`updated_at`）。
7. ✅️ 寫一支 migration 幫 `todos` 加上 `user_id` 欄位（FK 參照 `users.id`，`ON DELETE CASCADE`），並補上索引 `todos(user_id, completed, created_at DESC)`。
8. ✅️ 後端加裝密碼雜湊套件（bcrypt 或 argon2），實作 `POST /auth/register`：驗證 `email` 唯一、密碼至少 8 碼，雜湊密碼後寫入 `users`，回傳 `201`。
9. ✅️ 決定用 Session 還是 JWT 做登入狀態（先選一種做就好，不用兩個都做），實作 `POST /auth/login`：驗證帳密，設定 HTTP-only Cookie，回傳目前使用者資料；帳密錯誤回 `401`。
10. ✅️ 寫一支「需要登入」的 middleware，檢查 Cookie 是否有效，沒有就回 `401`；先掛在一個測試用路由確認 middleware 本身邏輯正確。
11. ✅️ 把 middleware 掛到所有 Todo 端點上，並把已完成的 `GET /api/v1/todos` 改成只用 `WHERE user_id = 目前登入的使用者`。
12. ✅️ 實作 `POST /auth/logout`：清除認證 Cookie，回傳 `204`。
13. ✅️ 手動測試整條認證流程：註冊 → 登入 → 打 `GET /api/v1/todos` 確認能通 → 登出 → 再打一次確認回 `401`。

## ✅️ 第四階段：Todo CRUD 剩餘端點

14. ✅️ 加上 `POST /api/v1/todos`（建立 Todo，寫入時帶上目前登入使用者的 `user_id`），用 curl 或 Postman 測試。
15. ✅️ 依序加上 `GET /api/v1/todos/:id`、`PATCH /api/v1/todos/:id`、`DELETE /api/v1/todos/:id`，一個一個做；每支都要確認「查到不屬於自己的 Todo 要回 `404`」。

## ✅️ 第五階段：前端串接

先把版面／路由骨架都建好（此階段先不呼叫任何 API，畫面上可以先用假資料或空狀態），確認整體動線沒問題後，再依序把每個功能串上對應的 API。

### ✅️ 5-1　版面與路由骨架

16. ✅️ 建立註冊頁面與登入頁面版面（表單 UI，先不串 API）。
17. ✅️ 建立 Todo 列表頁版面：新增表單、清單項目、完成 checkbox、刪除按鈕、登出按鈕，先用假資料或空陣列。
18. ✅️ 建立前端路由（註冊／登入／Todo 列表三個頁面互相切換），先不加路由守衛——守衛要判斷登入狀態，得等登入 API 串上去才有東西可以判斷。

### ✅️ 5-2　依序串接 API

19. ✅️ 註冊頁面串接 `POST /auth/register`。
20. ✅️ 登入頁面串接 `POST /auth/login`，成功後導向 Todo 列表頁。
21. ✅️ 加上路由守衛：未登入時導去登入頁（此時已有登入 API，可以用 session 是否有效來判斷）。
22. ✅️ 登出按鈕串接 `POST /auth/logout`。
23. ✅️ Todo 列表頁寫一個 `fetch` 函式呼叫 `GET /todos`，把資料顯示在畫面上。
24. ✅️ 「新增 Todo」的表單串接 `POST`。
25. ✅️ 「勾選完成」的 checkbox 串接 `PATCH`。
26. ✅️ 「刪除」按鈕串接 `DELETE`。

## ✅️ 第六階段：補強

27. ✅️ 加上基本的欄位驗證（`title` 必填）與錯誤處理，回應格式依 [api.md](api.md) 定義的錯誤格式。
28. ✅️ 調整畫面樣式，堪用即可。

## 額外補強（不算開發步驟，MVP 完成後自行檢查新增）

- ✅️ 加上全域錯誤處理 middleware（`backend/src/app.js`）：非預期錯誤（例如資料庫查詢失敗）統一回傳 `{ "message": "Internal server error" }` 的 JSON 格式，並在伺服器端 log 出來；避免 `NODE_ENV` 未設為 production 時，Express 預設錯誤頁把 stack trace（檔案路徑、程式碼結構）外洩給呼叫端。

## 範圍之外（暫緩）

依 [architecture.md](architecture.md) 「後續規劃」，忘記密碼先不做，待註冊、登入、登出與 Todo 主流程完成後再加入。

其他先記錄、暫不處理的資安／穩健性項目：

- 正式環境需設定 `NODE_ENV=production`，作為上面全域錯誤處理器之外多一層防護。
- `POST /auth/register` 的 `name`／`email` 目前沒有後端長度驗證，超過資料庫欄位長度時會被 DB 擋下、回傳 `500`，語意上應該回 `422`。
- 登入／註冊端點沒有速率限制（rate limiting），有暴力破解風險。
