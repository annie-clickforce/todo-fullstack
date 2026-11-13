# Todo Full Stack

🔗 線上網站：<https://todo.annie-clickforce.xyz/todos>　<sub>2026-11-13 上線</sub>

## 專案目的

以 Todo List 為題材，從零實作一個前後端分離的全端 Web 應用，完整走過需求規格、系統設計、開發、容器化，到正式部署與維運的軟體開發流程。

## 功能

- **帳號**：註冊、登入、登出。採用 Laravel Sanctum 的 SPA Cookie 認證，Session 存在 HTTP-only Cookie，密碼以 bcrypt 雜湊儲存。
- **Todo 管理**：新增、列表、編輯、標記完成、刪除。每位使用者只能存取自己的 Todo，存取他人的資料一律回傳 `404`，不透露資料是否存在。
- **安全性**：登入與註冊有速率限制（同一 IP 每分鐘 5 次）、CSRF 防護、正式環境強制 HTTPS 並關閉錯誤細節輸出。
- **API 設計**：RESTful、JSON 格式、版本化路徑（`/api/v1`），錯誤回應格式統一為 `{ "message", "errors" }`。

尚未納入目前範圍：忘記密碼／重設密碼（規格已寫在 [API 規劃](docs/api.md)，前端與寄信功能尚未完成）。

## 技術架構

| 層級 | 技術 | 說明 |
|---|---|---|
| 前端 | Vue 3、Vue Router、Vite | 單頁應用（SPA），build 成靜態檔部署 |
| 後端 | Laravel 8（PHP 8.2）、Laravel Sanctum | REST API，流程為 Route → Controller → Eloquent Model |
| 資料庫 | MySQL 8 | `utf8mb4`、UUID 主鍵，資料表由 Laravel migration 管理 |
| 本機開發 | Docker Compose | 一個指令啟動前端、後端、資料庫三個服務 |
| 正式環境 | Hostinger 共享主機（LiteSpeed）、Let's Encrypt | 自訂網域＋HTTPS，前後端同網域，避免跨站 Cookie 問題 |
| 文件 | Markdown、Mermaid | API 規格、資料庫設計、架構圖、開發與部署路線圖 |

更正規的自架方式（AWS EC2＋Docker＋Nginx）目前先不採用，保留為備案，見 [AWS 備案路線圖](docs/deployment-roadmap-aws.md)。

## 文件

- [API 規劃](docs/api.md)
- [資料庫設計](docs/database.md)
- [系統架構圖](docs/architecture.md)
- [開發路線圖](docs/roadmap.md)
- [部署上線與維運路線圖](docs/deployment-roadmap.md)（部署到 Hostinger 共享主機、網域、HTTPS，以及資料庫備份還原、看 log 除錯）
- [部署上線與維運路線圖（AWS 備案）](docs/deployment-roadmap-aws.md)（AWS EC2、Docker、Nginx 的完整自架版本，是更正規的部署方式，目前先不採用，供參考）
- [部署文件](docs/deployment.md)
- [維運文件](docs/operations.md)

## 啟動專案

1. 複製環境變數範本：`cp .env.example .env`
2. 啟動服務：`docker compose up --build`
3. 建立資料表（第一次啟動或清掉資料後才需要）：另開一個終端機執行 `docker compose exec api php artisan migrate`
4. 開啟前端：`http://localhost:3010`
5. 確認 API 健康檢查：`http://localhost:3011/api/v1/health`

如果啟動時出現 `port is already allocated`（例如電腦上已有其他程式佔用 3306），在 `.env` 把 `MYSQL_PORT` 改成其他值（例如 `3307`）再重新啟動。

停止服務時執行 `docker compose down`。若要連同 MySQL 資料一併移除，執行 `docker compose down -v`。

## 開發流程

1. ✅️ 文件與架構規劃
2. ✅️ 建立專案及 Docker 環境
3. ✅️ API 開發
4. ✅️ 前端畫面
5. ✅️ 前後端串接
6. ✅️ 測試
7. ✅️ Demo（[v1](https://github.com/annie-clickforce/todo-fullstack/releases/tag/v1)）
8. ✅️ 部署上線（[v2](https://github.com/annie-clickforce/todo-fullstack/releases/tag/v2)）
9. ✅️ 上線後版本更新（[v3](https://github.com/annie-clickforce/todo-fullstack/releases/tag/v3)）
10. 資料庫備份還原、看 log 除錯

## 版本紀錄

| 版本 | 日期 | 說明 |
|---|---|---|
| [v3](https://github.com/annie-clickforce/todo-fullstack/releases/tag/v3) | 2026-11-13 | 上線後版本更新：依安全檢測報告加入安全性 HTTP 標頭 |
| [v2](https://github.com/annie-clickforce/todo-fullstack/releases/tag/v2) | 2026-11-13 | 部署上線：正式環境上線，啟用自訂網域與 HTTPS |
| [v1](https://github.com/annie-clickforce/todo-fullstack/releases/tag/v1) | 2026-08-07 | Demo：後端以 Laravel 8 重構完成，提供註冊、登入、登出與 Todo CRUD，於本機 Docker 環境（PostgreSQL）運行 |

<sub>日期為該版本程式碼完成的日期。v1 的版本號於 2026-11-12 補上。</sub>
