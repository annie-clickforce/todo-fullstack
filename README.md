# Todo Full Stack

## 專案目的

先理解前後端分離架構與運作流程，再透過 AI 協助完成 Todo List，學習 Full Stack 開發流程。

## 專案範圍

- 前後端分離：Vue 3 前端透過 REST API 與 Laravel 後端溝通。
- 使用 MySQL 保存應用程式資料。
- 提供註冊、登入、登出與 Todo CRUD。
- 維護 API 文件、Markdown 文件與 Mermaid 架構圖。
- 以 Docker Compose 啟動前端、後端與資料庫服務。

## 技術

- Vue 3
- Laravel 8（PHP）
- MySQL 8
- Docker（本機開發環境）
- Hostinger 共享主機（正式環境）

更正規的自架方式（AWS EC2＋Docker＋Nginx）目前先不採用，保留為備案，見 [AWS 備案路線圖](docs/deployment-roadmap-aws.md)。

## 功能

- 註冊
- 登入
- 登出
- Todo CRUD

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
7. ✅️ Demo
8. 🚧 部署上線（進行中，見[部署上線與維運路線圖](docs/deployment-roadmap.md)）
9. 資料庫備份還原、看 log 除錯
