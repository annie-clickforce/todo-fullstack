# 部署上線與維運路線圖

本文件依完整上線的兩點需求，拆成可以一步一步完成、每步都能獨立驗證的小任務：

1. 把目前的版本部署上線（網域、HTTPS、Nginx）。
2. 上線後做資料庫備份還原、看 log 除錯。

前置條件：[roadmap.md](roadmap.md) 第七階段（Laravel 重構）已完成。跟 roadmap.md 一樣，建議一次只做一步，做完就實際測試再進到下一步。

## 第一階段：部署上線（網域、HTTPS、Nginx）

目標：把目前版本部署到 AWS EC2，用自己的網域、走 HTTPS、由 Nginx 對外提供服務。前提是**公司不出費用**，所以全部以免費或自費最低成本為原則。

> **費用規劃**
> - 網域：GoDaddy 自費購買，只買 1 年並關閉自動續約；SSL、信箱、網站建置等加購一律不買（HTTPS 用免費的 Let's Encrypt）。注意續約價通常比首年貴很多。
> - 伺服器：AWS 新帳號屬於「Free plan」——註冊送 US$100 抵用金，完成 Billing 主控台列出的任務（例如設定預算、啟動 EC2）最多再拿 US$100，**期限 6 個月**；Free plan 帳號不會被扣款，但 6 個月到期後若沒升級成付費方案，帳號會被關閉、資源會被刪除。所以這兩個階段要在 6 個月內做完並 Demo。
> - 主機規格：t3.micro（1GB RAM，需加 swap）＋ 20GB gp3。公有 IPv4 也要計費（約 US$3.6／月），合計約 US$13–16／月，6 個月約 US$80–95，在抵用金範圍內。記憶體真的不夠時再升 t3.small（費用約翻倍）。
> - 實際條件以註冊當下的 AWS 官網說明為準。

> **上線架構**（前後端同一個網域，Sanctum Cookie 屬於同源，CORS 與跨站 Cookie 問題最少）
> ```
> 瀏覽器 → https://todo.你的網域.com
>           │
>      EC2：Nginx（裝在主機上，負責 443 與憑證）
>           ├─ /                → 前端 build 出來的靜態檔（dist）
>           └─ /api/、/sanctum/ → Laravel（php-fpm 容器，只監聽 127.0.0.1）
>                                   └─ PostgreSQL 容器（不對外開 port）
> ```

> **開始前**：[roadmap.md](roadmap.md) 第 57 步（前端 CSRF Cookie 流程）還沒有在瀏覽器實際跑過，請先在本機親自測完再部署，否則上線後出問題時，很難分辨是部署設定還是程式本身的錯。

### 1-1　本機準備正式版設定

1. 新增 `docker-compose.prod.yml`：Laravel 從 `php -S` 改成 php-fpm；前端改成 `npm run build` 產出靜態檔（不跑 Vite 開發伺服器）；`db` 拿掉 `ports`（目前 5432 對外開放，正式環境絕對不行）。
2. 準備正式環境的 `.env`（不可進 git）：`APP_ENV=production`、`APP_DEBUG=false`、重新產生的 `APP_KEY`、高強度 DB 密碼、`SESSION_SECURE_COOKIE=true`，`SESSION_DOMAIN`／`SANCTUM_STATEFUL_DOMAINS` 填正式網域。**注意**：`VITE_API_BASE_URL` 是在 build 時寫死進前端程式的，要在 build 之前就設定好。
3. 在本機用 prod compose 啟動一次，確認服務都能正常起來。

### 1-2　開 AWS 主機

4. 註冊 AWS：root 帳號開 MFA，另建一個 IAM 使用者做日常操作（不要用 root），並設定 AWS Budgets 警示（例如超過 US$5 就寄信通知）。
5. 建立 EC2：Ubuntu 24.04 LTS、t3.micro、20GB gp3。Security Group：22 只開放給自己的 IP，80／443 全開。綁定一個 Elastic IP。
6. SSH 登入主機：加 2GB swap → 安裝 Docker → clone 專案 → 放好 `.env` → 用 prod compose 啟動。用 `curl http://<Elastic IP>/api/v1/health` 驗證。

### 1-3　網域、Nginx、HTTPS

7. 在 GoDaddy 購買網域，DNS 新增一筆 A 記錄指向 Elastic IP，用 `nslookup` 確認已生效。
8. 在主機上安裝 Nginx：`/` 對應前端 dist，`/api/`、`/sanctum/` 用 `fastcgi_pass` 轉給 Laravel 容器。先用 `http://網域` 確認能打開。
9. 用 `certbot --nginx` 申請 Let's Encrypt 憑證並設定 http 自動轉 https；執行 `certbot renew --dry-run` 確認憑證會自動續約。
10. 在正式網址走一次完整流程（註冊 → 登入 → 新增／編輯／勾選／刪除 → 登出），用 DevTools 確認 Cookie 有 `Secure`、`HttpOnly`，且沒有 419 或 CORS 錯誤。
11. 上線前安全檢查：故意打錯網址確認不會露出 stack trace；從自己電腦連不到 5432；`.env` 沒有進 git；登入／註冊加上 Laravel 內建的 `throttle` 限流（順便解掉 [roadmap.md](roadmap.md)「範圍之外」的速率限制項目）。
12. 撰寫 `docs/deployment.md`，記錄之後如何更新版本：`git pull` → build → `up -d` → `php artisan migrate --force`。

## 第二階段：維運（資料庫備份還原、看 log 除錯）

### 2-1　備份

13. 先手動備份一次：`docker compose exec db pg_dump -Fc ...`，輸出為 `~/backups/todo_YYYYMMDD.dump`。
14. 寫一支 `backup.sh` 並加進 cron，每天凌晨自動備份，只保留最近 7 天。
15. 異地備份：備份檔跟資料庫放在同一台機器上，機器壞掉就會一起不見。最省錢的做法是定期用 `scp` 抓回自己電腦；或放到 S3（檔案很小，費用幾乎為 0）。

### 2-2　還原演練

16. 先還原到另一個測試資料庫（`todo_restore_test`），比對兩邊的資料筆數是否一致。
17. 模擬事故：記下目前資料 → 刪掉幾筆 Todo → 用備份還原正式 DB → 確認資料都回來了。記錄還原花了多久（RTO），以及最多會遺失多少資料（RPO；每天備份一次就是最多遺失一天）。
18. 把還原流程寫成 SOP，放進 `docs/operations.md`。

### 2-3　看 log 除錯

19. 搞清楚每一層的 log 在哪裡：Nginx（`/var/log/nginx/access.log`、`error.log`）、Laravel（`storage/logs/`，建議改成 `LOG_CHANNEL=daily`）、容器（`docker compose logs -f api`、`docker compose logs db`）。
20. 設定 log 輪替：Docker 設 `max-size`／`max-file`；Laravel 每天一個檔、保留 14 天，避免 20GB 的硬碟被 log 塞滿。
21. 故意製造錯誤來練習，每個情境都記下「看到什麼現象 → 查哪個 log → 原因是什麼」：
    - 停掉 api 容器 → 瀏覽器出現 502 → Nginx `error.log` 會有 `connect() failed`
    - 把 DB 密碼改錯 → 500 → `laravel.log` 會有 `SQLSTATE[08006]`
    - `SANCTUM_STATEFUL_DOMAINS` 填錯 → 登入後出現 401 或 419
    - 打一個不存在的路徑 → `access.log` 會記到 404
22. （可選）讓 Nginx 幫每個請求加上 request id，傳給 Laravel 一起寫進 log，就能從 access log 一路追到應用程式的 log。
23. 把上面的除錯情境整理進 `docs/operations.md`，當作這階段的成果紀錄。

### 收尾

24. Demo 完如果不再需要：terminate EC2、**釋放 Elastic IP**（沒釋放會持續計費）、刪除快照。務必在 Free plan 的 6 個月期限前處理完。
