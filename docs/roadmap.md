# 開發路線圖

本文件將 Todo MVP 的第一階段（見 [architecture.md](architecture.md) 「第一階段範圍」）拆成可以一步一步完成、每步都能獨立驗證的小任務。建議一次只做一步，做完就實際測試（curl、瀏覽器或資料庫工具）再進到下一步。

## 第一階段：資料庫先通

1. 依 [database.md](database.md) 的 `todos` 資料表規格寫一支 SQL migration（欄位：`id`、`title`、`description`、`completed`、`created_at`、`updated_at`；先不加 `user_id`，因為認證功能還沒開始做）。
2. 決定怎麼執行 migration——初期可以先手動用 `psql` 跑一次，不用急著導入 migration 工具（例如 node-pg-migrate）。
3. 驗證：`docker compose up db`，用 psql 或 DBeaver 連進去，確認資料表已建立。

## 第二階段：後端能讀寫資料庫

4. 後端加裝 `pg`（PostgreSQL client），寫一支簡單的連線設定，讀取 `DATABASE_URL` 環境變數。
5. 先做一個端點：`GET /api/v1/todos`，回傳資料庫查詢結果（沒資料就回傳空陣列），確認 API 能連到 DB。
6. 加上 `POST /api/v1/todos`（建立 Todo），用 curl 或 Postman 測試。
7. 依序加上 `GET /api/v1/todos/:id`、`PATCH /api/v1/todos/:id`、`DELETE /api/v1/todos/:id`，一個一個做，每做完一個就手動測試。

## 第三階段：前端串接

8. 前端寫一個簡單的 `fetch` 函式呼叫 `GET /todos`，先把資料印在畫面上（不用管樣式）。
9. 做「新增 Todo」的表單，串接 `POST`。
10. 做「勾選完成」的 checkbox，串接 `PATCH`。
11. 做「刪除」按鈕，串接 `DELETE`。

## 第四階段：補強

12. 加上基本的欄位驗證（`title` 必填）與錯誤處理，回應格式依 [api.md](api.md) 定義的錯誤格式。
13. 調整畫面樣式，堪用即可。

## 範圍之外（暫緩）

依 [architecture.md](architecture.md) 「後續規劃」，認證、註冊、登入、登出、忘記密碼與使用者權限（含 `todos.user_id`）先不做，待上述 Todo 主流程完成後再加入。[api.md](api.md) 與 [database.md](database.md) 雖然已經先把這部分規格寫好，但目前不在實作範圍內。