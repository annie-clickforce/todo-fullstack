# 部署上線與維運路線圖

本文件依完整上線的兩點需求，拆成可以一步一步完成、每步都能獨立驗證的小任務：

1. 把目前的版本部署上線（網域、HTTPS、Nginx）。
2. 上線後做資料庫備份還原、看 log 除錯。

前置條件：[roadmap.md](roadmap.md) 第七階段（Laravel 重構）已完成。跟 roadmap.md 一樣，建議一次只做一步，做完就照「✔ 驗證」實際確認，再進到下一步。

## 第一階段：部署上線（網域、HTTPS、Nginx）

目標：把目前版本部署到 AWS EC2，用自己的網域、走 HTTPS、由 Nginx 對外提供服務。以免費或最低成本為原則。

> **費用規劃**
> - 網域：GoDaddy 自費購買，只買 1 年並關閉自動續約；SSL、信箱、網站建置等加購一律不買（HTTPS 用免費的 Let's Encrypt）。注意續約價通常比首年貴很多。
> - 伺服器：AWS 新帳號屬於「Free plan」——註冊送 US$100 抵用金，完成 Billing 主控台列出的任務（例如設定預算、啟動 EC2）最多再拿 US$100，**期限 6 個月**；Free plan 帳號不會被扣款，但 6 個月到期後若沒升級成付費方案，帳號會被關閉、資源會被刪除。所以這兩個階段要在 6 個月內做完並 Demo。
> - 主機規格：t3.micro（1GB RAM，需加 swap）＋ 20GB gp3。公有 IPv4 也要計費（約 US$3.6／月），合計約 US$13–16／月，6 個月約 US$80–95，在抵用金範圍內。記憶體真的不夠時再升 t3.small（費用約翻倍）。
> - 實際條件以註冊當下的 AWS 官網說明為準。

### 先搞懂這些名詞（不用動手，看懂再開始）

| 名詞 | 白話解釋 |
|---|---|
| 伺服器／EC2 | 一台租來、放在 AWS 機房、24 小時開著的電腦。EC2 是 AWS 這項服務的名字，一台叫一個 instance（實例）。 |
| IP 位址 | 網路上每台電腦的門牌號碼，例如 `54.12.34.56`。 |
| Elastic IP | AWS 給你一個「固定不變」的 IP。沒有它的話，主機每次關機重開 IP 都會變。 |
| 網域 | 好記的名字，例如 `xxx.com`，用來取代難記的 IP。 |
| DNS／A 記錄 | 網路上的「電話簿」。A 記錄就是在電話簿裡寫一行：「`todo.xxx.com` 的門牌是 `54.12.34.56`」。 |
| Port（連接埠） | 同一台電腦上的不同「窗口」。80 是 http、443 是 https、22 是 SSH、5432 是 PostgreSQL。 |
| Security Group | AWS 幫主機加的防火牆，決定哪些 Port 可以讓外面的人連進來。 |
| SSH／金鑰（.pem） | 從自己電腦「遠端登入」主機下指令的方式。.pem 檔就是登入用的鑰匙，弄丟就進不去。 |
| Nginx | 主機的「櫃台」：所有從網路進來的請求都先到它這裡，它再決定交給前端檔案還是 Laravel。 |
| HTTPS／憑證 | 網址前面那個鎖頭。需要一張「憑證」證明這個網域真的是你的；Let's Encrypt 免費發這張憑證，效期 90 天、可以自動續約。 |
| build | 把前端的 Vue 原始碼「打包」成瀏覽器直接看得懂的 html／js／css 檔案（放在 `dist/` 資料夾），之後就不需要 Node.js 也能跑。 |
| php-fpm | 正式環境跑 PHP 的方式。目前用的 `php -S` 是 PHP 官方說「只能開發用」的簡易伺服器，一次只能處理一個請求。 |

### 上線後長什麼樣子

```
你的瀏覽器
   │  https://todo.你的網域.com
   ▼
GoDaddy DNS：查到 todo.你的網域.com = Elastic IP
   ▼
AWS EC2 主機（Security Group 只開 22／80／443）
   └─ Nginx（直接裝在主機上，負責 HTTPS 憑證）
        ├─ 網址是 /api/... 或 /sanctum/... → 交給 Laravel（php-fpm 容器，只聽主機內部 127.0.0.1:9000）
        │                                        └─ PostgreSQL 容器（完全不對外）
        └─ 其他網址 → 直接回傳前端 build 好的檔案（/var/www/todo）
```

跟本機開發版最大的差別：

| | 本機開發版（現在） | 正式版（要做的） |
|---|---|---|
| 前端 | `npm run dev` 跑 Vite 開發伺服器（port 3010） | `npm run build` 出靜態檔，由 Nginx 直接給 |
| 後端 | `php -S`（port 3011） | php-fpm，前面由 Nginx 轉進來 |
| 資料庫 | 5432 對外開放，方便用 DBeaver 連 | 不對外開放 |
| 網址 | 前後端不同 port | 前後端同一個網域（Cookie 同源，CORS 問題最少） |
| 錯誤畫面 | `APP_DEBUG=true`，會顯示詳細錯誤 | `APP_DEBUG=false`，不能讓外人看到程式細節 |

### 1-1　出發前：先確定本機版本沒問題

1. 照 [roadmap.md](roadmap.md) 第 57 步，在本機瀏覽器親自走一次完整流程：註冊 → 登入 → 新增／編輯／勾選／刪除 Todo → 登出。
   ✔ 驗證：每個動作畫面都正常，DevTools 的 Network 分頁沒有紅色的 419、401、CORS 錯誤。
   （為什麼要先做：如果本機就有問題，上線後再壞，會分不清是程式的錯還是部署的錯。）

### 1-2　本機：準備「正式版」的設定檔

2. 在本機試著 build 前端：`frontend/` 底下執行 `npm run build`。
   ✔ 驗證：產生 `frontend/dist/` 資料夾，裡面有 `index.html` 和 `assets/`。用檔案總管打開看看，這些就是之後要放到主機上的全部前端檔案。
3. 理解 `VITE_API_BASE_URL` 是「build 的時候寫死」的：用 `VITE_API_BASE_URL=https://example.com/api/v1 npm run build` 再 build 一次，然後在 `dist/assets/` 的 js 檔裡搜尋 `example.com`。
   ✔ 驗證：搜得到。代表前端 build 完之後就不會再讀環境變數了，**正式版一定要在 build 之前就設定好正式網址**。
   **注意**：`frontend/src/api.js` 用 `new URL(apiBaseUrl)` 解析這個值，所以一定要寫完整網址（`https://todo.你的網域.com/api/v1`），不能只寫 `/api/v1`。
4. 新增 `backend-laravel/Dockerfile.prod`（正式版用的 Laravel 映像檔，原本的 `Dockerfile` 留給本機開發）：
   - 基底映像檔從 `php:8.1-cli-alpine` 改成 `php:8.1-fpm-alpine`。
   - 在映像檔裡執行 `composer install --no-dev --optimize-autoloader`（`--no-dev` 是不裝開發用套件）。
   - 程式碼用 `COPY` 放進映像檔，不再像開發版一樣掛載本機資料夾。
   - `storage/` 和 `bootstrap/cache/` 要讓 php-fpm 的執行身分（`www-data`）有寫入權限，不然 Laravel 寫 log、session 會失敗。
   ✔ 驗證：`docker build -f Dockerfile.prod -t todo-api-prod .` 能成功跑完。
5. 新增根目錄的 `docker-compose.prod.yml`，跟現在的 `docker-compose.yml` 比，差在：
   - **沒有 `frontend` 服務**：前端只是一包靜態檔，交給 Nginx 就好，不用跑 Node。
   - `api` 改用 `Dockerfile.prod`，port 寫成 `127.0.0.1:9000:9000`——前面加 `127.0.0.1` 代表「只有主機自己連得到」，外面連不進來。
   - `db` **不寫 `ports`**，只有同一個 compose 裡的 `api` 連得到它。
   - 每個服務加 `restart: unless-stopped`：主機重開機後容器會自己啟動。
   ✔ 驗證：`docker compose -f docker-compose.prod.yml config` 沒有報錯（這個指令會檢查設定檔格式，並印出合併後的結果）。
6. 新增 `.env.production.example`（**會進 git**，只列欄位、不填真實值，當作範本），列出正式環境要的變數，每個都寫註解：
   - `APP_ENV=production`、`APP_DEBUG=false`
   - `APP_KEY=`（Laravel 用來加密 Cookie 的鑰匙，正式環境要重新產生一組，用 `php artisan key:generate --show`）
   - `APP_URL=https://todo.你的網域.com`
   - `POSTGRES_PASSWORD=`（正式環境要用很長的隨機密碼，不要用 `change-me`）
   - `SESSION_SECURE_COOKIE=true`（Cookie 只在 https 下傳送）
   - `SESSION_DOMAIN=todo.你的網域.com`
   - `SANCTUM_STATEFUL_DOMAINS=todo.你的網域.com`
   - `CORS_ORIGIN=https://todo.你的網域.com`
   - `VITE_API_BASE_URL=https://todo.你的網域.com/api/v1`
   ✔ 驗證：`git status` 看得到 `.env.production.example`；確認根目錄 `.gitignore` 有 `.env`（真正填密碼的檔案不會被 commit）。
7. 在本機用正式版設定試跑：`docker compose -f docker-compose.prod.yml up -d --build`，再執行 `docker compose -f docker-compose.prod.yml exec api php artisan migrate --force`（正式環境 Laravel 會要求加 `--force` 才肯跑 migration，避免手滑）。
   ✔ 驗證：`docker compose -f docker-compose.prod.yml ps` 看到 `api`、`db` 都是 `Up`；`exec api php artisan migrate:status` 能列出 migration 清單（代表連得到資料庫）。完整的網頁測試要等主機裝好 Nginx 才能做。
   測完記得 `docker compose -f docker-compose.prod.yml down`，避免跟開發版搶資源。
8. 把以上改動 commit 並 push 到 GitHub——等一下主機要從 GitHub 把程式碼抓下來。
   ✔ 驗證：GitHub 網頁上看得到 `Dockerfile.prod`、`docker-compose.prod.yml`、`.env.production.example`。

### 1-3　AWS 帳號：先把安全和費用顧好

9. 到 AWS 官網註冊帳號，方案選 **Free plan**（需要綁信用卡驗證身分，但 Free plan 不會扣款）。
   ✔ 驗證：能登入 AWS 主控台（Console）。
10. 選定區域（Region）：主控台右上角選「亞太區域（東京）ap-northeast-1」，離台灣近、速度快。**之後所有東西都在同一個區域建立**，不然會發生「我的主機怎麼不見了」——其實只是切到別的區域。
    ✔ 驗證：右上角顯示「東京」。
11. 幫 root 帳號（註冊時用的那個 email）開啟 MFA（多重驗證）：右上角帳號名稱 → 安全憑證 → 指派 MFA，用手機的 Google Authenticator 掃 QR Code。
    ✔ 驗證：登出再登入，除了密碼還要輸入手機上的 6 位數字。
12. 建立一個 IAM 使用者給自己日常用（IAM → 使用者 → 建立使用者，權限先給 `AdministratorAccess`），之後都用這個帳號登入，root 帳號收起來不用。也幫這個使用者開 MFA。
    （為什麼：root 權限大到可以關掉整個帳號，萬一密碼外洩後果最嚴重。）
    ✔ 驗證：用 IAM 使用者登入主控台成功。
13. 設定預算警示：Billing → Budgets → 建立預算，選「成本預算」，金額填 US$5，警示寄到自己的 email。
    ✔ 驗證：Budgets 列表看得到這筆預算。順便到 Billing 的 Free plan 頁面看剩多少抵用金、還有哪些任務可以做（做完會再送抵用金）。

### 1-4　開一台主機（EC2）

14. EC2 → 執行個體 → 啟動執行個體，依序填：
    - 名稱：`todo-server`
    - 作業系統映像檔（AMI）：**Ubuntu Server 24.04 LTS**
    - 執行個體類型：**t3.micro**（確認旁邊有標示符合免費方案）
    - 金鑰對：點「建立新的金鑰對」，名稱 `todo-key`，類型 RSA，格式 `.pem`，下載下來**放到安全的地方並備份**（AWS 只讓你下載這一次）
    - 儲存：改成 **20 GiB、gp3**
    - 網路設定先用預設，下一步再調防火牆
    ✔ 驗證：執行個體列表看到 `todo-server`，狀態是「執行中」。
15. 設定 Security Group（防火牆）的「傳入規則」：
    | 類型 | Port | 來源 | 用途 |
    |---|---|---|---|
    | SSH | 22 | 我的 IP | 只有你自己能遠端登入 |
    | HTTP | 80 | 0.0.0.0/0（所有人） | 網站 http |
    | HTTPS | 443 | 0.0.0.0/0（所有人） | 網站 https |
    **不要**開 5432、9000、3010、3011。
    **注意**：家裡、公司、咖啡廳的 IP 都不一樣，而且可能會變。之後 SSH 突然連不上，第一件事先回來把「我的 IP」更新一次。
    ✔ 驗證：Security Group 的傳入規則剛好就是上面三條。
16. 申請固定 IP：EC2 → 彈性 IP（Elastic IP）→ 配置彈性 IP 地址 → 選剛剛那筆 → 動作 →「關聯」到 `todo-server`。把這個 IP 記下來，後面會一直用到。
    ✔ 驗證：執行個體詳細資料裡的「公有 IPv4 地址」就是這個彈性 IP。
17. 從自己的電腦 SSH 登入主機（Windows 內建就有 ssh，打開 PowerShell）：
    ```
    ssh -i C:\路徑\todo-key.pem ubuntu@你的彈性IP
    ```
    第一次會問要不要信任這台主機，輸入 `yes`。如果出現 `UNPROTECTED PRIVATE KEY FILE` 錯誤，是 Windows 的檔案權限太寬鬆，要把 `.pem` 檔的權限改成只有自己能讀（`icacls` 移除其他使用者的權限）。
    ✔ 驗證：看到 `ubuntu@ip-xxx-xxx-xxx-xxx:~$` 這樣的提示字元，代表你已經在操作那台遠端主機了。輸入 `exit` 可以離開。

### 1-5　買網域並指向主機

這一段可以早點做，因為 DNS 設定生效需要等幾分鐘到幾小時，可以邊等邊做下一段。

18. 到 GoDaddy 搜尋並購買網域：
    - 年限只選 **1 年**。
    - 結帳頁取消所有加購（SSL 憑證、專業信箱、網站建置等）；網域隱私保護如果是免費附贈就保留。
    - 買完到帳戶的網域設定裡，把**自動續約關掉**。
    ✔ 驗證：GoDaddy「我的產品」裡看得到這個網域。
19. 決定網址：建議用子網域 `todo.你的網域.com`，之後這個網域還能拿來放別的東西。
20. 在 GoDaddy 的 DNS 管理頁新增一筆記錄：
    | 類型 | 名稱 | 值 | TTL |
    |---|---|---|---|
    | A | `todo` | 你的彈性 IP | 600 秒（或最短的選項） |
    ✔ 驗證：在自己電腦的 PowerShell 執行 `nslookup todo.你的網域.com`，回傳的 Address 是你的彈性 IP。還沒生效就晚點再試。

### 1-6　主機初始設定

以下指令都是 SSH 登入主機之後執行的。

21. 更新系統套件：`sudo apt update && sudo apt upgrade -y`（`sudo` 是用管理員身分執行）。
    ✔ 驗證：指令跑完沒有紅字錯誤。如果提示要重開機，執行 `sudo reboot`，等一分鐘再 SSH 連回來。
22. 加 2GB swap（記憶體不夠用時，拿一塊硬碟空間暫時頂著用；t3.micro 只有 1GB 記憶體，跑 Docker build 很容易不夠）：
    ```
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
    ```
    （最後一行是讓主機重開機後 swap 還在。）
    ✔ 驗證：`free -h` 的 Swap 那一行顯示約 2.0Gi。
23. 安裝 Docker：照 Docker 官方文件「Install Docker Engine on Ubuntu」的 apt repository 方式安裝（會一併裝好 `docker compose`）。裝完執行 `sudo usermod -aG docker ubuntu`，讓 `ubuntu` 這個使用者不用每次都打 `sudo`，然後 `exit` 再重新 SSH 登入讓設定生效。
    ✔ 驗證：`docker run hello-world` 印出 Hello from Docker；`docker compose version` 印出版本號。
24. 把程式碼抓到主機上。GitHub repo 如果是私人的，主機要有一把鑰匙才能下載：
    - 在主機上產生金鑰：`ssh-keygen -t ed25519 -C "todo-server"`（一路按 Enter）。
    - 顯示公鑰：`cat ~/.ssh/id_ed25519.pub`，整行複製。
    - 到 GitHub repo → Settings → Deploy keys → Add deploy key，貼上，**不要勾** Allow write access（主機只需要讀）。
    - 在主機上 `git clone git@github.com:annie-clickforce/todo-fullstack.git`。
    （repo 如果是公開的，直接 `git clone https://github.com/annie-clickforce/todo-fullstack.git` 就好。）
    ✔ 驗證：`ls todo-fullstack` 看得到 `backend-laravel`、`frontend`、`docker-compose.prod.yml`。
25. 在主機上建立真正的 `.env`：
    ```
    cd ~/todo-fullstack
    cp .env.production.example .env
    nano .env
    ```
    把每個欄位填上真實的值（網域換成你買的那個）。產生隨機密碼可以用 `openssl rand -base64 24`。`nano` 編輯器存檔是 `Ctrl+O` → Enter，離開是 `Ctrl+X`。存好後執行 `chmod 600 .env`，讓只有自己能讀。
    ✔ 驗證：`cat .env` 檢查沒有漏填、沒有殘留 `change-me` 或範本文字。
26. 啟動後端和資料庫：
    ```
    docker compose -f docker-compose.prod.yml up -d --build
    docker compose -f docker-compose.prod.yml exec api php artisan migrate --force
    ```
    第一次 build 會比較久（t3.micro 可能要好幾分鐘），耐心等。
    ✔ 驗證：`docker compose -f docker-compose.prod.yml ps` 兩個服務都是 `Up`；`docker compose -f docker-compose.prod.yml logs api` 沒有錯誤訊息。
27. build 前端並放到 Nginx 等一下要讀的位置（主機上沒有裝 Node，所以借用一個暫時的 Node 容器來 build，build 完容器就自動刪掉）：
    ```
    docker run --rm -v "$PWD/frontend":/app -w /app \
      -e VITE_API_BASE_URL=https://todo.你的網域.com/api/v1 \
      node:22-alpine sh -c "npm ci && npm run build"
    sudo mkdir -p /var/www/todo
    sudo cp -r frontend/dist/* /var/www/todo/
    ```
    ✔ 驗證：`ls /var/www/todo` 看得到 `index.html` 和 `assets`。

### 1-7　安裝並設定 Nginx

28. 安裝 Nginx：`sudo apt install -y nginx`。
    ✔ 驗證：在自己電腦的瀏覽器打開 `http://你的彈性IP`，看到「Welcome to nginx!」。
29. 建立網站設定檔：`sudo nano /etc/nginx/sites-available/todo`，內容分三塊：
    - `server_name todo.你的網域.com;`、`listen 80;`
    - 前端：`root /var/www/todo;`，`location /` 裡寫 `try_files $uri /index.html;`
      （為什麼：前端用的是 Vue Router 的 history 模式，使用者直接打開 `/todos` 這種網址時，主機上其實沒有這個檔案，要一律回傳 `index.html`，讓 Vue 自己決定顯示哪一頁。）
    - 後端：`location ~ ^/(api|sanctum)/` 裡寫 `fastcgi_pass 127.0.0.1:9000;`、`include fastcgi_params;`、`fastcgi_param SCRIPT_FILENAME /app/public/index.php;`
      （為什麼：Nginx 不會自己跑 PHP，要把請求轉給 php-fpm。`SCRIPT_FILENAME` 填的是**容器裡面**的路徑，Laravel 所有請求都從 `public/index.php` 進去。）
    ✔ 驗證：`sudo nginx -t` 顯示 `syntax is ok` 和 `test is successful`。
30. 啟用這個網站並停用預設頁：
    ```
    sudo ln -s /etc/nginx/sites-available/todo /etc/nginx/sites-enabled/
    sudo rm /etc/nginx/sites-enabled/default
    sudo nginx -t && sudo systemctl reload nginx
    ```
    （`sites-available` 放所有寫好的設定，`sites-enabled` 放「真的要啟用」的；用捷徑（`ln -s`）連過去就代表啟用。）
    ✔ 驗證：
    - 瀏覽器打開 `http://todo.你的網域.com`，看到 Todo 的登入頁。
    - 打開 `http://todo.你的網域.com/api/v1/health`，看到健康檢查的 JSON。
    - 這時候還**不能登入**是正常的：`SESSION_SECURE_COOKIE=true` 要求 https 才送 Cookie，下一段裝好 HTTPS 就會好。

### 1-8　設定 HTTPS（免費的 Let's Encrypt）

31. 安裝 certbot（自動申請、安裝、續約 Let's Encrypt 憑證的工具）：`sudo apt install -y certbot python3-certbot-nginx`。
    ✔ 驗證：`certbot --version` 印出版本號。
32. 申請憑證：`sudo certbot --nginx -d todo.你的網域.com`。過程會問 email（憑證快過期時通知用）、要你同意服務條款，照著回答就好。certbot 會自動幫你改 Nginx 設定，加上 443 和「http 自動轉到 https」。
    ✔ 驗證：
    - 瀏覽器打開 `https://todo.你的網域.com`，網址列有鎖頭。
    - 打開 `http://todo.你的網域.com`（沒有 s），會自動跳到 https。
33. 確認憑證會自動續約（憑證只有 90 天效期）：`sudo certbot renew --dry-run`（`--dry-run` 是模擬，不會真的續約）。
    ✔ 驗證：最後顯示 `Congratulations, all simulated renewals succeeded`；`systemctl list-timers | grep certbot` 看得到排程。

### 1-9　上線驗收與安全檢查

34. 在正式網址走一次完整流程：註冊 → 登入 → 新增／編輯／勾選／刪除 Todo → 登出 → 重新整理頁面確認被導回登入頁。
    ✔ 驗證：全部正常，DevTools 的 Network 分頁沒有紅色的 419、401、CORS 錯誤。
35. 檢查 Cookie 的安全設定：DevTools → Application → Cookies → 選你的網域。
    ✔ 驗證：`laravel_session` 的 `HttpOnly` 和 `Secure` 都有打勾；`XSRF-TOKEN` 的 `Secure` 有打勾。
36. 確認錯誤畫面不會洩漏程式細節：打開 `https://todo.你的網域.com/api/v1/不存在的路徑`。
    ✔ 驗證：只看到簡單的 JSON 錯誤訊息，沒有檔案路徑、程式碼、stack trace。
37. 確認資料庫和 php-fpm 沒有對外開放：在自己電腦的 PowerShell 執行
    ```
    Test-NetConnection todo.你的網域.com -Port 5432
    Test-NetConnection todo.你的網域.com -Port 9000
    ```
    ✔ 驗證：兩個的 `TcpTestSucceeded` 都是 `False`。
38. 確認密碼沒有進 git：在本機專案執行 `git ls-files | grep .env`。
    ✔ 驗證：只看到 `.env.example`、`.env.production.example` 這類範本，沒有真正的 `.env`。
39. 幫登入、註冊加上速率限制（避免有人一直猜密碼），順便解掉 [roadmap.md](roadmap.md)「範圍之外」的速率限制項目：在 `routes/api.php` 的登入／註冊路由加上 Laravel 內建的 middleware，例如 `throttle:5,1`（每分鐘最多 5 次）。在本機改好、測好，commit 後照第 40 步的方式更新到主機。
    ✔ 驗證：連續登入失敗第 6 次，回傳 `429 Too Many Requests`。
40. 重開機測試：在主機上執行 `sudo reboot`，等 1～2 分鐘。
    ✔ 驗證：什麼都不用做，`https://todo.你的網域.com` 就能正常使用（代表 `restart: unless-stopped` 和 Nginx 開機自動啟動都有生效）。

### 1-10　寫下部署文件

41. 新增 `docs/deployment.md`，記錄這次學到的、之後會重複用到的東西（**不要寫密碼**）：
    - 主機資訊：區域、執行個體名稱、網域、SSH 登入指令。
    - 「怎麼更新版本」的步驟：SSH 登入 → `cd ~/todo-fullstack` → `git pull` → `docker compose -f docker-compose.prod.yml up -d --build` → `docker compose -f docker-compose.prod.yml exec api php artisan migrate --force` → 前端有改的話重做第 27 步。
    - 這次踩過的坑和解法。
    ✔ 驗證：自己照著文件從頭到尾更新一次版本，不用翻其他資料就能完成。

## 第二階段：維運（資料庫備份還原、看 log 除錯）

### 2-1　備份

42. 先手動備份一次：`docker compose exec db pg_dump -Fc ...`，輸出為 `~/backups/todo_YYYYMMDD.dump`。
43. 寫一支 `backup.sh` 並加進 cron，每天凌晨自動備份，只保留最近 7 天。
44. 異地備份：備份檔跟資料庫放在同一台機器上，機器壞掉就會一起不見。最省錢的做法是定期用 `scp` 抓回自己電腦；或放到 S3（檔案很小，費用幾乎為 0）。

### 2-2　還原演練

45. 先還原到另一個測試資料庫（`todo_restore_test`），比對兩邊的資料筆數是否一致。
46. 模擬事故：記下目前資料 → 刪掉幾筆 Todo → 用備份還原正式 DB → 確認資料都回來了。記錄還原花了多久（RTO），以及最多會遺失多少資料（RPO；每天備份一次就是最多遺失一天）。
47. 把還原流程寫成 SOP，放進 `docs/operations.md`。

### 2-3　看 log 除錯

48. 搞清楚每一層的 log 在哪裡：Nginx（`/var/log/nginx/access.log`、`error.log`）、Laravel（`storage/logs/`，建議改成 `LOG_CHANNEL=daily`）、容器（`docker compose logs -f api`、`docker compose logs db`）。
49. 設定 log 輪替：Docker 設 `max-size`／`max-file`；Laravel 每天一個檔、保留 14 天，避免 20GB 的硬碟被 log 塞滿。
50. 故意製造錯誤來練習，每個情境都記下「看到什麼現象 → 查哪個 log → 原因是什麼」：
    - 停掉 api 容器 → 瀏覽器出現 502 → Nginx `error.log` 會有 `connect() failed`
    - 把 DB 密碼改錯 → 500 → `laravel.log` 會有 `SQLSTATE[08006]`
    - `SANCTUM_STATEFUL_DOMAINS` 填錯 → 登入後出現 401 或 419
    - 打一個不存在的路徑 → `access.log` 會記到 404
51. （可選）讓 Nginx 幫每個請求加上 request id，傳給 Laravel 一起寫進 log，就能從 access log 一路追到應用程式的 log。
52. 把上面的除錯情境整理進 `docs/operations.md`，當作這階段的成果紀錄。

### 收尾

53. Demo 完如果不再需要：terminate EC2、**釋放 Elastic IP**（沒釋放會持續計費）、刪除快照。務必在 Free plan 的 6 個月期限前處理完。
