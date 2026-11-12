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
- ✅️ Todo 列表頁加上「編輯」功能：點擊後標題／說明變成輸入框，可儲存（串 `PATCH /todos/:id`）或取消，原本後端已支援但前端沒有入口。

## 範圍之外（暫緩）

依 [architecture.md](architecture.md) 「後續規劃」，忘記密碼先不做，待註冊、登入、登出與 Todo 主流程完成後再加入。

其他先記錄、暫不處理的資安／穩健性項目：

- 正式環境需設定 `NODE_ENV=production`，作為上面全域錯誤處理器之外多一層防護。
- `POST /auth/register` 的 `name`／`email` 目前沒有後端長度驗證，超過資料庫欄位長度時會被 DB 擋下、回傳 `500`，語意上應該回 `422`。
- ✅️ 登入／註冊端點沒有速率限制（rate limiting），有暴力破解風險。（Laravel 版已加上：同一個 IP 每分鐘最多 5 次，見 [deployment-roadmap.md](deployment-roadmap.md) 第 8 步。）
- Session Cookie 目前沒設定 `secure`／`sameSite`（本機 http 開發環境本來就不該設 `secure:true`）；若之後部署到正式環境（https），`express-session` 的 cookie 需加上 `secure: true`。

## ✅️ 第七階段：後端改用 Laravel 8 重構（MVP 完成後新增需求）

後端從 Node.js／Express 改用 Laravel 8 重構（同一個專案繼續做，不是另開 Repository）。前端（Vue 3）不換，API 規格沿用 [api.md](api.md)、資料模型沿用 [database.md](database.md)，目標是讓前端只要換 `VITE_API_BASE_URL` 就能無痛接上新後端。

> 標記說明：✅️ 是你自己一步步做完、實際測過的；🧪 是一次做完一大批、我用 curl 完整測過但你還沒親自驗證的，之後有空建議照第 48／56／58 步的測試方式自己過一次。

### ✅️ 7-1　專案初始化

29. ✅️ 本機安裝 PHP（8.1 版本即可）與 Composer（PHP 的套件管理工具，角色跟 Node.js 的 `npm` 一樣）。
30. ✅️ 用 Composer 建立一個全新的 Laravel 8 專案：`composer create-project laravel/laravel:^8.0 backend-laravel`（先跟舊的 `backend` 資料夾並存，不要急著刪，確認新的能動再取代）。
31. ✅️ 打開新專案的 `.env` 檔案（跟現在 Node 版本的 `.env` 是同樣的概念），把資料庫連線資訊改成連到現有的 PostgreSQL（`docker-compose.yml` 裡的 `db` 服務）。
32. ✅️ 用 Composer 加裝 `laravel/sanctum`：`composer require laravel/sanctum`。Sanctum 是 Laravel 官方的登入驗證套件，這裡要用它的「SPA（單頁應用）認證」模式——效果等同你現在手刻的 `express-session` 加 Cookie：前端登入後靠 Cookie 記住登入狀態，只是換成 Laravel 內建的做法，不用自己刻 session 邏輯。
33. ✅️ 把 `docker-compose.yml` 裡原本跑 Node.js 的 `api` 服務，改成跑 Laravel（先用最簡單的 `php artisan serve` 就好，不用一開始就搞正式部署那套 Nginx + php-fpm 架構）。
34. ✅️ Laravel 沒有內建健康檢查路由，自己在 `routes/api.php`（定義 API 路徑要對應到哪段程式碼的檔案，等同你現在 `app.js` 裡那些 `app.get(...)` 的角色）加一個 `GET /api/v1/health`，回傳固定 JSON。跑起來後用瀏覽器或 curl 打這支，確認服務有正常啟動、能連上資料庫。

### ✅️ 7-2　資料庫（比照 database.md）

35. ✅️ Laravel 管理資料庫結構的方式叫「migration」——概念跟你之前手寫的 SQL migration 檔案一樣（都是「版本化的資料庫變更腳本」），差別是 Laravel 要求用 PHP 語法寫，不是直接寫 SQL。（實際做法：Laravel 8 建立專案時已內建一支 `create_users_table` migration，直接改用這支即可，不用另外跑 `make:migration`，跑了會撞名。）
36. ✅️ 打開產生的檔案，照 database.md 的 `users` 資料表規格填欄位。**要特別注意**：Laravel 預設用「自動遞增的數字」當主鍵（1, 2, 3...），但你原本的資料庫是用 UUID 當主鍵，要手動改成 `$table->uuid('id')->primary()`。（實際做法：直接在資料庫層用 `DEFAULT gen_random_uuid()` 讓 UUID 由資料庫自動產生，跟原本 Node 版本的 SQL migration 做法一致，Model 不需要額外寫產生 UUID 的邏輯，只需設定 `$incrementing = false`、`$keyType = 'string'`。）
37. ✅️ 同樣方式，用 `php artisan make:migration create_todos_table` 建立 `todos` 資料表的 migration，欄位、外鍵、索引都比照 database.md。
38. ✅️ 執行 `php artisan migrate` 跑 migration，用 psql 或 DBeaver 連進資料庫，確認資料表建立正確、欄位型別符合 database.md。（過程中發現舊的 Node.js 版本已經在同一個資料庫建過 `users`／`todos` 表，含測試資料；因為 Laravel 要接手同一個資料庫，把舊表刪掉讓 Laravel 重建。）

### 🧪 7-3　認證（比照 api.md）

39. 🧪 依照 Sanctum 官方文件設定「SPA 認證」，在設定檔裡把前端網址 `localhost:3010` 填進去，讓 Laravel 知道要信任這個前端送過來的登入 Cookie。
40. 🧪 Laravel 預設就內建一個 User Model（對應 `users` 資料表），稍微調整讓欄位符合我們的規格就好，不用整支重寫。
41. 🧪 建立一個 Controller（Laravel 把「處理某類請求的邏輯」獨立寫成檔案，角色等同你現在 `app.js` 裡 `app.post("/auth/register", async (req, res) => {...})` 那段函式）：`php artisan make:controller AuthController`。
42. 🧪 在 `AuthController` 寫 `register` 方法：驗證輸入資料（email 唯一、密碼至少 8 碼——Laravel 有內建驗證機制，用 `$request->validate([...])` 就能做，效果跟你原本手寫的 if/errors 判斷式一樣，只是換一種語法），用 `Hash::make()` 把密碼雜湊（等同原本用的 bcrypt），存進資料庫，回傳 `201`。
43. 🧪 寫 `login` 方法：驗證帳密、登入成功回傳使用者資料；失敗回 `401`。
44. 🧪 寫 `logout` 方法：登出、清除登入狀態，回傳 `204`。
45. 🧪 寫一個回傳「目前登入者資料」的方法，對應 `GET /auth/me`。
46. 🧪 在 `routes/api.php` 把上面四個方法對應到正確的網址（比照 api.md 定義的路徑）。
47. 🧪 幫 Todo 相關路由加上「要求登入」的保護——Laravel 把這種攔截邏輯叫做 middleware，效果等同你現在寫的 `requireAuth` 函式，只是這裡用 Laravel 內建的 `auth:sanctum`，不用自己刻。（Todo 路由本身還沒建立，這步先把保護機制準備好，等 7-4 建路由時掛上。）
48. 🧪 手動測試整條流程：註冊 → 登入 → 打受保護的路由確認能通 → 登出 → 再打一次確認回 `401`（跟本文件第 13 步的測試方式一樣）。過程中修掉兩個 bug：① `php artisan serve` 在 Docker 裡的子行程不會繼承容器環境變數，導致連錯資料庫——改成直接用 `php -S` 啟動，不透過 `artisan serve`。② UUID 主鍵用資料庫端 `DEFAULT` 產生時，Eloquent 建立資料後讀不到剛產生的 id——改成在 Model 的 `creating` 事件裡由應用程式產生 UUID。另外因為是純 API、沒有登入頁面，調整 `Authenticate` middleware 跟 Exception Handler，讓未登入一律回 JSON `401`，不要嘗試導頁。**（curl 已測過，建議自己在瀏覽器/Postman 再走一次確認。）**

### 🧪 7-4　Todo CRUD（比照 api.md）

49. 🧪 建立 Todo Model（`php artisan make:model Todo`），設定它跟 User 的關聯（一個使用者對應多筆 Todo）。
50. 🧪 建立 `TodoController`（`php artisan make:controller TodoController`），寫一個列出「目前登入者所有 Todo」的方法，對應 `GET /todos`。
51. 🧪 寫新增一筆 Todo 的方法，對應 `POST /todos`。
52. 🧪 依序寫查單筆／更新／刪除的方法，對應 `GET/PATCH/DELETE /todos/:id`——每一支都要測「查到不是自己的 Todo 要回 `404`」，不能讓使用者看到別人的資料。（用 UUID 格式驗證擋掉不合法的 id，避免直接查詢 Postgres 報錯。）
53. 🧪 把 `TodoController` 的方法對應到 `routes/api.php` 的路徑，並掛上第 47 步做的登入保護。
54. 🧪 確認 Laravel 預設驗證失敗時回傳的 JSON 格式（跟 api.md 定義的 `{ "message": ..., "errors": {...} }` 其實很接近），如果有落差要自己調整。（實際做法：手動組錯誤訊息而非用 Laravel 預設格式，確保跟 Node 版本、api.md 文字完全一致。過程中也發現並修掉 `$fillable` 沒列 `user_id`／建立時沒指定 `completed` 預設值的兩個 bug——都是 Eloquent「批次賦值白名單」與「資料庫端預設值讀不回來」這兩個模式重複出現的坑。）**（curl 已測過建立/清單/單筆/更新/刪除與跨帳號 404，建議自己再走一次。）**

### 🧪 7-5　忘記密碼（Node 版沒做，api.md 已定義，重構時順便補齊）

55. 🧪 寫 `POST /auth/forgot-password`：不管帳號存不存在都回 `202`，避免被猜出哪些 email 有註冊過。（新建 `password_reset_tokens` migration，比照 database.md：只存 Token 的 sha256 雜湊值，沒有配寄信服務，先只把 Token 記錄寫進資料庫。）
56. 🧪 寫 `POST /auth/reset-password`：檢查 Token 有效、沒過期、沒用過，成功後讓同一個使用者的其他 Token 都失效。（curl 測試：假 token 回 422、真 token 成功改密碼並可用新密碼登入、同一個 token 用第二次回 422，建議自己再走一次確認。）

### 7-6　整合收尾

57. 🧪 把前端的 `VITE_API_BASE_URL` 改成指向新的 Laravel API，實際跑一次完整流程（註冊 → 登入 → 新增/編輯/勾選/刪除 Todo → 登出），確認畫面完全正常。（`VITE_API_BASE_URL` 本身不用改，因為第 33 步就已經把 Docker 的 `api` 服務換成 Laravel、port 沒變。但發現前端完全沒處理 Sanctum 需要的 CSRF Cookie 流程，補在 `frontend/src/api.js`：非 GET 請求前先確保有 `XSRF-TOKEN` Cookie（沒有就先打 `/sanctum/csrf-cookie`），並把它讀出來放進 `X-XSRF-TOKEN` header，不然瀏覽器實際操作會收到 `419`。**這步強烈建議親自在瀏覽器測一次，前端行為目前只有靜態檢查，沒有真的跑過畫面。**）
58. 🧪 設定 CORS（跨來源請求，等同你現在 Node 版本裝的 `cors` 套件那段設定），讓前端網址可以打這支新 API，並且能帶 Cookie。（這步在 7-3 認證測試時就已經先做了，因為 Sanctum SPA 認證從一開始就需要 CORS 支援憑證才能測。）
59. 都確認沒問題後，把舊的 Node.js `backend` 資料夾刪掉或封存。

## 新需求：完整上線

部署上線（網域、HTTPS）與上線後的資料庫備份還原、看 log 除錯，獨立拆成另一份路線圖，見 [deployment-roadmap.md](deployment-roadmap.md)（Hostinger 共享主機）。自己架伺服器的完整版本（AWS EC2、Docker、Nginx）是更正規的部署方式，目前先不採用，保留為備案，見 [deployment-roadmap-aws.md](deployment-roadmap-aws.md)。
