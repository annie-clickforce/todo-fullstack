# 部署上線與維運路線圖

本文件依完整上線的兩點需求，拆成可以一步一步完成、每步都能獨立驗證的小任務：

1. 把目前的版本部署上線（網域、HTTPS、Nginx）。
2. 上線後做資料庫備份還原、看 log 除錯。

前置條件：[roadmap.md](roadmap.md) 第七階段（Laravel 重構）已完成。跟 roadmap.md 一樣，建議一次只做一步，做完就照「✔ 驗證」實際確認，再進到下一步。

## 文件慣例

每一步開頭標示執行位置：

| 標示 | 執行位置 |
|---|---|
| 【本機】 | 自己電腦的 PowerShell，於專案根目錄執行 |
| 【主機】 | SSH 登入 EC2 主機後執行（登入方式見第 18 步） |
| 【網頁】 | 瀏覽器操作（AWS 主控台、GoDaddy、GitHub、正式網站） |

`todo.你的網域.com` 替換成實際網址；`你的彈性IP` 替換成第 16 步取得的 IP。

## 第一階段：部署上線（網域、HTTPS、Nginx）

目標：把目前版本部署到 AWS EC2，用自己的網域、走 HTTPS、由 Nginx 對外提供服務。以免費或最低成本為原則。

> **費用規劃**
> - 網域：GoDaddy 自費購買，只買 1 年並關閉自動續約；SSL、信箱、網站建置等加購一律不買（HTTPS 用免費的 Let's Encrypt）。注意續約價通常比首年貴很多。
> - 伺服器：AWS 新帳號屬於「Free plan」——註冊送 US$100 抵用金，完成 Billing 主控台列出的任務（例如設定預算、啟動 EC2）最多再拿 US$100，**期限 6 個月**；Free plan 帳號不會被扣款，但 6 個月到期後若沒升級成付費方案，帳號會被關閉、資源會被刪除。所以這兩個階段要在 6 個月內做完並 Demo。
> - 主機規格：t3.micro（1GB RAM，需加 swap）＋ 20GB gp3。公有 IPv4 也要計費（約 US$3.6／月），合計約 US$13–16／月，6 個月約 US$80–95，在抵用金範圍內。記憶體真的不夠時再升 t3.small（費用約翻倍）。
> - 實際條件以註冊當下的 AWS 官網說明為準。

### 名詞說明

| 名詞 | 說明 |
|---|---|
| EC2 | AWS 的虛擬主機服務，一台主機稱為一個 instance（執行個體）。 |
| Elastic IP（彈性 IP） | 固定的公有 IP。未綁定時，主機停止再啟動後公有 IP 會改變。 |
| DNS／A 記錄 | DNS 負責把網域解析成 IP；A 記錄即「網域 → IPv4 位址」的對應。 |
| Port | 80：http、443：https、22：SSH、5432：PostgreSQL、9000：php-fpm。 |
| Security Group | EC2 的防火牆規則，控制哪些來源可以連到哪些 Port。 |
| SSH 金鑰（.pem） | 登入 EC2 用的私鑰，只能在建立時下載一次，遺失就無法登入。 |
| Nginx | 反向代理／Web Server，負責接收所有請求，依網址分派給前端靜態檔或 Laravel。 |
| Let's Encrypt／certbot | 免費的 HTTPS 憑證，效期 90 天；certbot 負責申請、安裝與自動續約。 |
| build | 將 Vue 原始碼編譯成靜態的 html／js／css（輸出到 `dist/`），執行時不需要 Node.js。 |
| php-fpm | 正式環境用的 PHP 執行方式。目前的 `php -S` 是 PHP 內建的開發用伺服器，一次只能處理一個請求，不適合正式環境。 |

### 上線架構

```
你的瀏覽器
   │  https://todo.你的網域.com
   ▼
GoDaddy DNS：查到 todo.你的網域.com = 你的彈性IP
   ▼
AWS EC2 主機（Security Group 只開 22／80／443）
   └─ Nginx（直接裝在主機上，負責 HTTPS 憑證）
        ├─ 網址是 /api/... 或 /sanctum/... → 交給 Laravel（php-fpm 容器，只聽主機內部 127.0.0.1:9000）
        │                                        └─ PostgreSQL 容器（完全不對外）
        └─ 其他網址 → 直接回傳前端 build 好的檔案（/var/www/todo）
```

與本機開發版的差異：

| | 本機開發版（現在） | 正式版（要做的） |
|---|---|---|
| 前端 | `npm run dev` 跑 Vite 開發伺服器（port 3010） | `npm run build` 出靜態檔，由 Nginx 直接給 |
| 後端 | `php -S`（port 3011） | php-fpm，前面由 Nginx 轉進來 |
| 資料庫 | 5432 對外開放，方便用 DBeaver 連 | 不對外開放 |
| 網址 | 前後端不同 port | 前後端同一個網域（Cookie 同源，CORS 問題最少） |
| 錯誤畫面 | `APP_DEBUG=true`，會顯示詳細錯誤 | `APP_DEBUG=false`，不能讓外人看到程式細節 |

### 1-1　先買網域

先買的原因：後面準備設定檔時就會用到網址，先決定好比較不會亂。

1. 【網頁】到 GoDaddy 搜尋並購買網域：
   - 年限只選 **1 年**。
   - 結帳頁取消所有加購（SSL 憑證、專業信箱、網站建置等）；網域隱私保護如果是免費附贈就保留。
   - 買完到帳戶的網域設定裡，把**自動續約關掉**。
   ✔ 驗證：GoDaddy「我的產品」裡看得到這個網域。
2. 決定網址：建議用子網域 `todo.你的網域.com`，之後這個網域還能拿來放別的東西。把完整網址記下來，後面所有 `todo.你的網域.com` 都換成它。
   （DNS 設定要先拿到主機的 IP，所以放在第 17 步才做。）

### 1-2　本機：準備正式版要用的檔案

這一段是在本機寫好幾個新檔案，commit 推上 GitHub，等一下主機再把它們抓下來用。原本開發用的 `Dockerfile`、`docker-compose.yml` 都不動，正式版另外開新檔案。

3. 【本機】新增 `backend-laravel/.dockerignore`，告訴 Docker 打包時不要把這些東西放進去：
   ```
   .env
   vendor
   node_modules
   storage/logs/*
   storage/framework/sessions/*
   ```
   （原因：本機的 `.env` 是開發用設定（`APP_DEBUG=true`），萬一被打包進正式版，Laravel 可能會讀到它；`vendor` 則是要在映像檔裡重新安裝，不用本機的。）
   ✔ 驗證：檔案存在，內容如上。
4. 【本機】新增 `backend-laravel/Dockerfile.prod`（正式版的 Laravel 映像檔），內容：
   ```dockerfile
   FROM php:8.1-fpm-alpine

   # 跟開發版一樣，安裝 Laravel 連 PostgreSQL 需要的 PHP 擴充
   RUN apk add --no-cache postgresql-dev oniguruma-dev libxml2-dev curl-dev \
       && docker-php-ext-install pdo_pgsql pgsql mbstring xml bcmath fileinfo curl

   # 從官方 composer 映像檔複製 composer 指令進來
   COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

   WORKDIR /app

   # 先只複製套件清單並安裝套件（--no-dev：不裝開發用套件）
   COPY composer.json composer.lock ./
   RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

   # 再複製全部程式碼，產生自動載入檔，並讓 php-fpm 的執行身分 www-data 能寫入 storage
   COPY . .
   RUN composer dump-autoload --optimize --no-dev \
       && chown -R www-data:www-data storage bootstrap/cache

   EXPOSE 9000
   CMD ["php-fpm"]
   ```
   跟開發版 `Dockerfile` 的差別：基底從 `php:8.1-cli-alpine` 換成 `php:8.1-fpm-alpine`；在映像檔裡自己安裝 `vendor`；最後跑的是 `php-fpm`，不是 `php -S`。
   ✔ 驗證：【本機】`cd backend-laravel` 後執行 `docker build -f Dockerfile.prod -t todo-api-prod .`，最後顯示成功、沒有 ERROR。
5. 【本機】在專案根目錄新增 `docker-compose.prod.yml`：
   ```yaml
   services:
     api:
       build:
         context: ./backend-laravel
         dockerfile: Dockerfile.prod
       restart: unless-stopped          # 主機重開機後自動啟動
       ports:
         - "127.0.0.1:9000:9000"        # 前面加 127.0.0.1：只有主機自己連得到，外面連不進來
       environment:
         APP_ENV: production
         APP_DEBUG: "false"             # 出錯時不顯示程式細節
         APP_KEY: ${APP_KEY}
         APP_URL: https://${APP_DOMAIN}
         LOG_CHANNEL: daily             # log 每天一個檔，保留 14 天
         DB_CONNECTION: pgsql
         DB_HOST: db
         DB_PORT: 5432
         DB_DATABASE: ${POSTGRES_DB}
         DB_USERNAME: ${POSTGRES_USER}
         DB_PASSWORD: ${POSTGRES_PASSWORD}
         SESSION_DOMAIN: ${APP_DOMAIN}
         SESSION_SECURE_COOKIE: "true"  # Cookie 只在 https 下傳送
         SANCTUM_STATEFUL_DOMAINS: ${APP_DOMAIN}
         CORS_ORIGIN: https://${APP_DOMAIN}
       volumes:
         - api_storage:/app/storage     # log、session 存在這裡，重建容器也不會不見
       depends_on:
         db:
           condition: service_healthy

     db:
       image: postgres:16-alpine
       restart: unless-stopped
       # 注意：這裡故意沒有 ports，資料庫只有同一個 compose 裡的 api 連得到
       environment:
         POSTGRES_DB: ${POSTGRES_DB}
         POSTGRES_USER: ${POSTGRES_USER}
         POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
       volumes:
         - postgres_data:/var/lib/postgresql/data
       healthcheck:
         test: ["CMD-SHELL", "pg_isready -U $$POSTGRES_USER -d $$POSTGRES_DB"]
         interval: 5s
         timeout: 5s
         retries: 10

   volumes:
     api_storage:
     postgres_data:
   ```
   重點：
   - **沒有 `frontend` 服務**：前端是靜態檔，由 Nginx 直接提供，不需要 Node。
   - `${APP_DOMAIN}` 這種寫法代表「去 `.env` 檔裡讀這個值」，所以真正要填的設定只有下一步的幾個。
6. 【本機】在專案根目錄新增 `.env.production.example`（**會進 git**，只是範本，不填真實值）：
   ```
   # 讓主機上打 docker compose 時自動使用正式版設定檔，不用每次加 -f docker-compose.prod.yml
   COMPOSE_FILE=docker-compose.prod.yml

   # 你的正式網址（不含 https://）
   APP_DOMAIN=todo.你的網域.com

   # Laravel 加密用的鑰匙，在主機上用指令產生（見第 24 步）
   APP_KEY=

   # 資料庫設定，密碼在主機上用指令產生（見第 24 步）
   POSTGRES_DB=todo_app
   POSTGRES_USER=todo_app
   POSTGRES_PASSWORD=
   ```
   ✔ 驗證：【本機】執行
   ```
   docker compose -f docker-compose.prod.yml --env-file .env.production.example config
   ```
   沒有報錯，並且印出的內容裡 `APP_URL` 變成 `https://todo.你的網域.com`（代表 `.env` 的值有被正確帶進去）。
7. 【本機】幫登入、註冊加上速率限制，避免有人一直猜密碼（順便解掉 [roadmap.md](roadmap.md)「範圍之外」的速率限制項目）。在 `backend-laravel/routes/api.php` 把這兩行：
   ```php
   Route::post('/auth/register', [AuthController::class, 'register']);
   Route::post('/auth/login', [AuthController::class, 'login']);
   ```
   改成（`throttle:5,1` 意思是「同一個 IP 每 1 分鐘最多 5 次」）：
   ```php
   Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
   Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
   ```
   ✔ 驗證：本機開發版連續用錯誤密碼登入 6 次，第 6 次回傳 `429 Too Many Requests`。等一分鐘後又可以登入。
8. 【本機】把第 3～7 步的改動 commit 並 push 到 GitHub——等一下主機要從 GitHub 把程式碼抓下來。
   ✔ 驗證：GitHub 網頁上看得到 `Dockerfile.prod`、`docker-compose.prod.yml`、`.env.production.example`。

### 1-3　AWS 帳號：先把安全和費用顧好

9. 【網頁】到 AWS 官網註冊帳號，方案選 **Free plan**（需要綁信用卡驗證身分，但 Free plan 不會扣款）。
    ✔ 驗證：能登入 AWS 主控台（Console）。
10. 【網頁】選定區域（Region）：主控台右上角選「亞太區域（東京）ap-northeast-1」，離台灣近、速度快。**之後所有東西都在同一個區域建立**，不然會發生「我的主機怎麼不見了」——其實只是切到別的區域。
    ✔ 驗證：右上角顯示「東京」。
11. 【網頁】幫 root 帳號（註冊時用的那個 email）開啟 MFA（多重驗證）：右上角帳號名稱 → 安全憑證 → 指派 MFA，用手機的 Google Authenticator 掃 QR Code。
    ✔ 驗證：登出再登入，除了密碼還要輸入手機上的 6 位數字。
12. 【網頁】建立一個 IAM 使用者給自己日常用（IAM → 使用者 → 建立使用者，權限先給 `AdministratorAccess`），之後都用這個帳號登入，root 帳號收起來不用。也幫這個使用者開 MFA。
    （原因：root 權限大到可以關掉整個帳號，萬一密碼外洩後果最嚴重。）
    ✔ 驗證：用 IAM 使用者登入主控台成功。
13. 【網頁】設定預算警示：Billing → Budgets → 建立預算，選「成本預算」，金額填 US$5，警示寄到自己的 email。
    ✔ 驗證：Budgets 列表看得到這筆預算。順便到 Billing 的 Free plan 頁面看剩多少抵用金、還有哪些任務可以做（做完會再送抵用金）。

### 1-4　開一台主機（EC2）並連上它

14. 【網頁】EC2 → 執行個體 → 啟動執行個體，依序填：
    - 名稱：`todo-server`
    - 作業系統映像檔（AMI）：**Ubuntu Server 24.04 LTS**
    - 執行個體類型：**t3.micro**（確認旁邊有標示符合免費方案）
    - 金鑰對：點「建立新的金鑰對」，名稱 `todo-key`，類型 RSA，格式 `.pem`，下載下來**放到安全的地方並備份**（AWS 只讓你下載這一次）
    - 儲存：改成 **20 GiB、gp3**
    - 網路設定先用預設，下一步再調防火牆
    ✔ 驗證：執行個體列表看到 `todo-server`，狀態是「執行中」。
15. 【網頁】設定 Security Group（防火牆）的「傳入規則」，只留這三條：
    | 類型 | Port | 來源 | 用途 |
    |---|---|---|---|
    | SSH | 22 | 我的 IP | 只有你自己能遠端登入 |
    | HTTP | 80 | 0.0.0.0/0（所有人） | 網站 http |
    | HTTPS | 443 | 0.0.0.0/0（所有人） | 網站 https |

    **不要**開 5432、9000、3010、3011。
    **注意**：家裡、公司、咖啡廳的 IP 都不一樣，而且可能會變。之後 SSH 突然連不上，第一件事先回來把「我的 IP」更新一次。
    ✔ 驗證：Security Group 的傳入規則剛好就是上面三條。
16. 【網頁】申請固定 IP：EC2 → 彈性 IP → 配置彈性 IP 地址 → 選剛剛那筆 → 動作 →「關聯彈性 IP 地址」到 `todo-server`。把這個 IP 記下來，後面會一直用到。
    ✔ 驗證：執行個體詳細資料裡的「公有 IPv4 地址」就是這個彈性 IP。
17. 【網頁】設定 DNS：到 GoDaddy 的網域 → DNS 管理，新增一筆記錄：
    | 類型 | 名稱 | 值 | TTL |
    |---|---|---|---|
    | A | `todo` | 你的彈性IP | 600 秒（或最短的選項） |

    DNS 生效需要數分鐘到數小時，可先進行下一步。
    ✔ 驗證：【本機】執行 `nslookup todo.你的網域.com`，回傳的 Address 是你的彈性 IP。還沒生效就晚點再試。
18. 【本機】第一次 SSH 登入主機。Windows 會擋權限太寬鬆的 .pem 檔，所以先把它的權限改成「只有自己能讀」（路徑換成你放 .pem 的位置）：
    ```powershell
    icacls C:\路徑\todo-key.pem /inheritance:r
    icacls C:\路徑\todo-key.pem /grant:r "$($env:USERNAME):(R)"
    ```
    然後登入：
    ```powershell
    ssh -i C:\路徑\todo-key.pem ubuntu@你的彈性IP
    ```
    第一次會問要不要信任這台主機，輸入 `yes`。
    ✔ 驗證：看到 `ubuntu@ip-xxx-xxx-xxx-xxx:~$` 這樣的提示字元，表示已登入主機，之後【主機】的步驟都在這裡執行。輸入 `exit` 離開。

### 1-5　主機初始設定

19. 【主機】更新系統套件（`sudo` 是用管理員身分執行）：
    ```bash
    sudo apt update && sudo apt upgrade -y
    ```
    ✔ 驗證：指令跑完沒有紅字錯誤。如果提示要重開機，執行 `sudo reboot`，等一分鐘再照第 18 步 SSH 連回來。
20. 【主機】加 2GB swap（記憶體不夠用時，拿一塊硬碟空間暫時頂著用；t3.micro 只有 1GB 記憶體，跑 Docker build 很容易不夠）：
    ```bash
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
    ```
    （最後一行是讓主機重開機後 swap 還在。）
    ✔ 驗證：`free -h` 的 Swap 那一行顯示約 2.0Gi。
21. 【主機】安裝 Docker（用 Docker 官方提供的安裝腳本，會一併裝好 `docker compose`），再讓 `ubuntu` 這個使用者不用每次都打 `sudo`：
    ```bash
    curl -fsSL https://get.docker.com | sudo sh
    sudo usermod -aG docker ubuntu
    exit
    ```
    `exit` 之後照第 18 步重新 SSH 登入，設定才會生效。
    ✔ 驗證：`docker run hello-world` 印出 `Hello from Docker!`；`docker compose version` 印出版本號。
22. 【主機】把程式碼抓下來。先試公開的方式：
    ```bash
    git clone https://github.com/annie-clickforce/todo-fullstack.git
    ```
    如果跳出要輸入帳號密碼，代表 repo 是私人的，改用「Deploy key」（一把只能讀這個 repo 的鑰匙）：
    - 【主機】`ssh-keygen -t ed25519 -C "todo-server"`，提示問題皆按 Enter 使用預設值。
    - 【主機】`cat ~/.ssh/id_ed25519.pub`，把印出來的那一整行複製起來。
    - 【網頁】GitHub repo → Settings → Deploy keys → Add deploy key，貼上，**不要勾** Allow write access（主機只需要讀）。
    - 【主機】`git clone git@github.com:annie-clickforce/todo-fullstack.git`，問要不要信任時輸入 `yes`。
    ✔ 驗證：`ls ~/todo-fullstack` 看得到 `backend-laravel`、`frontend`、`docker-compose.prod.yml`。
23. 【主機】從範本複製出真正的設定檔：
    ```bash
    cd ~/todo-fullstack
    cp .env.production.example .env
    ```
    （這個 `.env` 只存在主機上，不會進 git。）
24. 【主機】產生兩組隨機值，等一下要貼進 `.env`：
    ```bash
    echo "base64:$(openssl rand -base64 32)"   # 這是 APP_KEY
    openssl rand -hex 16                        # 這是 POSTGRES_PASSWORD
    ```
    兩行輸出先複製到記事本。然後編輯 `.env`：`nano .env`，填好 `APP_DOMAIN`、`APP_KEY`、`POSTGRES_PASSWORD`。
    `nano`：`Ctrl+O` 存檔（再按 Enter），`Ctrl+X` 離開。
    存好後執行 `chmod 600 .env`，讓只有自己能讀。
    ✔ 驗證：`cat .env`，三個欄位都有值，`APP_DOMAIN` 是你的網址（沒有 `https://`、沒有「你的網域」字樣）。
25. 【主機】啟動後端和資料庫，並建立資料表（因為 `.env` 裡有 `COMPOSE_FILE`，這裡的 `docker compose` 會自動用正式版設定檔）：
    ```bash
    docker compose up -d --build
    docker compose exec api php artisan migrate --force
    ```
    第一次 build 在 t3.micro 上約需數分鐘。`APP_ENV=production` 時 Laravel 預設拒絕執行 migration，需加 `--force`。
    ✔ 驗證：`docker compose ps` 看到 `api`、`db` 都是 `Up`（db 會顯示 `healthy`）；`docker compose logs api` 最後幾行沒有 ERROR。
26. 【主機】build 前端，並放到 Nginx 等一下要讀的位置。主機上沒有裝 Node，所以借用一個暫時的 Node 容器來 build，build 完容器會自動刪掉：
    ```bash
    cd ~/todo-fullstack
    docker run --rm -v "$PWD/frontend":/app -w /app \
      -e VITE_API_BASE_URL=https://todo.你的網域.com/api/v1 \
      node:22-alpine sh -c "npm ci && npm run build"
    sudo mkdir -p /var/www/todo
    sudo cp -r frontend/dist/* /var/www/todo/
    ```
    > **網址為何不放在 `.env`**：`VITE_API_BASE_URL` 是在 build 的那一刻被「寫死」進前端 js 檔的，build 完之後前端就不會再讀任何環境變數。所以它只在 build 這一步有用，而且**網址改了就要重新 build**。另外 `frontend/src/api.js` 用 `new URL()` 解析這個值，一定要寫完整網址（含 `https://`），不能只寫 `/api/v1`。

    ✔ 驗證：`ls /var/www/todo` 看得到 `index.html` 和 `assets`。

### 1-6　安裝並設定 Nginx

27. 【主機】安裝 Nginx：
    ```bash
    sudo apt install -y nginx
    ```
    ✔ 驗證：【網頁】瀏覽器打開 `http://你的彈性IP`，看到「Welcome to nginx!」。
28. 【主機】建立網站設定檔：`sudo nano /etc/nginx/sites-available/todo`，貼上以下內容（把 `todo.你的網域.com` 換成你的網址），存檔離開：
    ```nginx
    server {
        listen 80;
        server_name todo.你的網域.com;

        # 前端：build 好的檔案放在這裡
        root /var/www/todo;
        index index.html;

        # 後端：網址以 /api/ 或 /sanctum/ 開頭的，轉給 Laravel（php-fpm）
        location ~ ^/(api|sanctum)/ {
            include fastcgi_params;
            fastcgi_pass 127.0.0.1:9000;
            # 這是「容器裡面」的路徑，Laravel 所有請求都從 public/index.php 進去
            fastcgi_param SCRIPT_FILENAME /app/public/index.php;
            fastcgi_param SCRIPT_NAME /index.php;
        }

        # 其他網址：找得到檔案就給檔案，找不到一律給 index.html
        location / {
            try_files $uri $uri/ /index.html;
        }
    }
    ```
    最後一段 `try_files ... /index.html` 的原因：前端用的是 Vue Router 的 history 模式，使用者直接打開 `/todos` 這種網址時，主機上其實沒有這個檔案，要回傳 `index.html`，讓 Vue 自己決定顯示哪一頁。
    ✔ 驗證：`sudo nginx -t` 顯示 `syntax is ok` 和 `test is successful`。
29. 【主機】啟用這個網站、停用預設的歡迎頁，再重新載入 Nginx：
    ```bash
    sudo ln -s /etc/nginx/sites-available/todo /etc/nginx/sites-enabled/
    sudo rm /etc/nginx/sites-enabled/default
    sudo nginx -t && sudo systemctl reload nginx
    ```
    （`sites-available` 放所有寫好的設定，`sites-enabled` 放「真的要啟用」的；用 `ln -s` 建一個捷徑過去就代表啟用。）
    ✔ 驗證：
    - 【網頁】打開 `http://todo.你的網域.com`，看到 Todo 的登入頁。
    - 【網頁】打開 `http://todo.你的網域.com/api/v1/health`，看到 `{"status":"ok"}`。
    - 這時候還**不能登入**是正常的：`SESSION_SECURE_COOKIE=true` 要求 https 才送 Cookie，下一段裝好 HTTPS 就會好。

### 1-7　設定 HTTPS（免費的 Let's Encrypt）

30. 【主機】安裝 certbot（自動申請、安裝、續約 Let's Encrypt 憑證的工具）：
    ```bash
    sudo apt install -y certbot python3-certbot-nginx
    ```
    ✔ 驗證：`certbot --version` 印出版本號。
31. 【主機】申請憑證：
    ```bash
    sudo certbot --nginx -d todo.你的網域.com
    ```
    過程會問 email（憑證快過期時通知用）、同意服務條款。certbot 會自動修改第 28 步的 Nginx 設定，加上 443 和「http 自動轉到 https」。
    ✔ 驗證：
    - 【網頁】打開 `https://todo.你的網域.com`，網址列有鎖頭。
    - 【網頁】打開 `http://todo.你的網域.com`（沒有 s），會自動跳到 https。
32. 【主機】確認憑證會自動續約（憑證只有 90 天效期）：
    ```bash
    sudo certbot renew --dry-run
    ```
    （`--dry-run` 是模擬，不會真的續約。）
    ✔ 驗證：最後顯示 `Congratulations, all simulated renewals succeeded`。

### 1-8　上線驗收與安全檢查

33. 【網頁】在正式網址走一次完整流程：註冊 → 登入 → 新增／編輯／勾選／刪除 Todo → 登出 → 重新整理頁面確認被導回登入頁。
    ✔ 驗證：全部正常，DevTools（F12）的 Network 分頁沒有紅色的 419、401、CORS 錯誤。
    如果有某個功能不正常：【本機】用 `docker compose up` 啟動開發版，走一次同樣的流程（也就是 [roadmap.md](roadmap.md) 第 57 步）。本機也壞 → 是程式本身的問題，跟部署無關；本機正常、只有正式網址壞 → 才是部署設定的問題，回頭檢查 `.env`、Nginx 設定。
34. 【網頁】檢查 Cookie 的安全設定：DevTools → Application → Cookies → 選你的網域。
    ✔ 驗證：`laravel_session` 的 `HttpOnly` 和 `Secure` 都有打勾；`XSRF-TOKEN` 的 `Secure` 有打勾。
35. 【網頁】確認錯誤畫面不會洩漏程式細節：打開 `https://todo.你的網域.com/api/v1/不存在的路徑`。
    ✔ 驗證：只看到簡單的錯誤訊息，沒有檔案路徑、程式碼、stack trace。
36. 【本機】確認資料庫和 php-fpm 從外面連不到：
    ```powershell
    Test-NetConnection todo.你的網域.com -Port 5432
    Test-NetConnection todo.你的網域.com -Port 9000
    ```
    ✔ 驗證：兩個的 `TcpTestSucceeded` 都是 `False`。
37. 【本機】確認密碼沒有進 git：
    ```powershell
    git ls-files | Select-String "\.env"
    ```
    ✔ 驗證：只看到 `.env.example`、`.env.production.example` 這類範本，沒有單獨一個 `.env`。
38. 【網頁】在正式網址連續用錯誤密碼登入 6 次。
    ✔ 驗證：第 6 次出現 `429`（第 7 步加的速率限制在正式環境也有效）。
39. 【主機】重開機測試：執行 `sudo reboot`，等 1～2 分鐘。
    ✔ 驗證：什麼都不用做，`https://todo.你的網域.com` 就能正常使用（代表 `restart: unless-stopped` 和 Nginx 開機自動啟動都有生效）。

### 1-9　寫下部署文件

40. 【本機】新增 `docs/deployment.md`，記錄這次學到、之後會重複用到的東西（**不要寫密碼**）：
    - 主機資訊：區域、執行個體名稱、網域、SSH 登入指令。
    - 「怎麼更新版本」的步驟：
      1. 【本機】改完程式、commit、push。
      2. 【主機】`cd ~/todo-fullstack && git pull`
      3. 【主機】`docker compose up -d --build`
      4. 【主機】`docker compose exec api php artisan migrate --force`
      5. 前端有改的話，重做第 26 步。
    - 這次踩過的坑和解法。
    ✔ 驗證：之後要更新版本時，照著這份文件就能完成，不用再翻其他資料。

## 第二階段：維運（資料庫備份還原、看 log 除錯）

以下【主機】的指令都是在 `~/todo-fullstack` 資料夾裡執行（先 `cd ~/todo-fullstack`）。

### 名詞說明

| 名詞 | 說明 |
|---|---|
| `pg_dump`／`pg_restore` | PostgreSQL 內建的備份／還原工具，postgres 容器內已附帶。 |
| cron | Linux 的排程工具，依設定的時間自動執行指令。 |
| RTO（Recovery Time Objective） | 發生事故到服務恢復所需的時間。 |
| RPO（Recovery Point Objective） | 發生事故時最多會遺失多久的資料。每日備份一次，RPO 最多約 24 小時。 |
| log 輪替 | 定期切割、刪除舊 log，避免磁碟被寫滿。 |

### 2-1　備份

41. 【主機】先手動備份一次，熟悉指令：
    ```bash
    mkdir -p ~/backups
    docker compose exec -T db pg_dump -U todo_app -Fc todo_app > ~/backups/todo_manual.dump
    ```
    參數說明：`docker compose exec -T db` 是「到 db 容器裡執行」；`pg_dump -U todo_app` 是用 `todo_app` 這個帳號備份；`-Fc` 是存成 PostgreSQL 專用的壓縮格式；最後一個 `todo_app` 是資料庫名稱；`>` 是把結果存成主機上的檔案。（`-T` 一定要加，不然存出來的檔案會壞掉。）
    ✔ 驗證：`ls -lh ~/backups` 看到 `todo_manual.dump`，大小不是 0。
42. 【本機】在專案新增 `scripts/backup.sh`（放進 git 管理），內容：
    ```bash
    #!/bin/bash
    # 每日資料庫備份：存到 ~/backups，只保留最近 7 天
    set -e
    cd /home/ubuntu/todo-fullstack
    mkdir -p /home/ubuntu/backups

    FILE=/home/ubuntu/backups/todo_$(date +%Y%m%d_%H%M).dump
    docker compose exec -T db pg_dump -U todo_app -Fc todo_app > "$FILE"
    echo "$(date) 備份完成：$FILE"

    # 刪掉 7 天以前的備份檔
    find /home/ubuntu/backups -name 'todo_*.dump' -mtime +7 -delete
    ```
    commit、push，然後【主機】`git pull` 抓下來，再執行 `chmod +x scripts/backup.sh` 讓它可以執行。
    ✔ 驗證：【主機】手動跑一次 `./scripts/backup.sh`，印出「備份完成」，`ls ~/backups` 多了一個帶日期時間的檔案。
43. 【主機】設定 cron 每天自動備份。執行 `crontab -e`（第一次會問要用哪個編輯器，選 `nano`），在最下面加一行：
    ```
    0 19 * * * /home/ubuntu/todo-fullstack/scripts/backup.sh >> /home/ubuntu/backups/backup.log 2>&1
    ```
    格式說明：前五個欄位依序是「分 時 日 月 星期」，`0 19 * * *` 就是每天 19:00。**主機用的是 UTC 時間，UTC 19:00 等於台灣凌晨 3:00**。後面是要執行的腳本，`>> backup.log 2>&1` 是把執行結果（包括錯誤）記到 `backup.log`，出問題時才查得到原因。
    ✔ 驗證：`crontab -l` 看得到這一行；隔天檢查 `ls ~/backups` 有新檔案、`cat ~/backups/backup.log` 有「備份完成」。
44. 【本機】把備份抓回自己電腦（異地備份）。備份檔跟資料庫放在同一台主機上，主機壞掉就一起不見了，所以要另外存一份：
    ```powershell
    mkdir C:\todo-backups
    scp -i C:\路徑\todo-key.pem "ubuntu@你的彈性IP:~/backups/todo_*.dump" C:\todo-backups\
    ```
    ✔ 驗證：`C:\todo-backups` 裡有備份檔。建議每週抓一次，或 Demo 前抓一次。

### 2-2　還原演練

備份沒有實際還原過，就不能確定它真的能用。這一段是在「不影響正式資料」和「模擬真的出事」兩種情況下各練一次。

45. 【主機】先還原到一個測試用的資料庫，不碰正式資料：
    ```bash
    # 建一個空的測試資料庫
    docker compose exec db createdb -U todo_app todo_restore_test
    # 把備份還原進去（檔名換成 ~/backups 裡實際的檔名）
    docker compose exec -T db pg_restore -U todo_app -d todo_restore_test < ~/backups/todo_manual.dump
    ```
    ✔ 驗證：比對兩個資料庫的筆數一樣：
    ```bash
    docker compose exec db psql -U todo_app -d todo_app -c "SELECT count(*) FROM todos;"
    docker compose exec db psql -U todo_app -d todo_restore_test -c "SELECT count(*) FROM todos;"
    ```
    確認完把測試資料庫刪掉：`docker compose exec db dropdb -U todo_app todo_restore_test`。
46. 【網頁＋主機】模擬事故並還原正式資料庫：
    1. 【主機】先跑一次 `./scripts/backup.sh`，記下這次的檔名。
    2. 【網頁】在網站上記下目前有哪些 Todo，然後刪掉其中幾筆（假裝是誤刪）。
    3. 【主機】開始計時。先停掉 api，避免還原時有人在寫入資料：`docker compose stop api`
    4. 【主機】還原（`--clean --if-exists` 是「先把現有的資料表清掉再放回備份內容」）：
       ```bash
       docker compose exec -T db pg_restore -U todo_app -d todo_app --clean --if-exists < ~/backups/剛剛的檔名.dump
       ```
    5. 【主機】`docker compose start api`，停止計時。
    ✔ 驗證：【網頁】重新整理網站，被刪掉的 Todo 都回來了。記下這次花了幾分鐘（RTO），以及如果是靠每天的自動備份，最多會遺失多少資料（RPO）。
47. 【本機】新增 `docs/operations.md`，把第 45、46 步寫成「資料庫還原 SOP」：出事時照著一步一步做就能還原，不需要臨時查資料。

### 2-3　看 log 除錯

48. 【主機】認識每一層的 log 在哪裡、怎麼看：
    | 哪一層 | 記錄什麼 | 怎麼看 |
    |---|---|---|
    | Nginx 存取紀錄 | 每個請求：誰、什麼時間、打哪個網址、回什麼狀態碼 | `sudo tail -f /var/log/nginx/access.log` |
    | Nginx 錯誤紀錄 | Nginx 自己遇到的問題，例如轉不到 Laravel | `sudo tail -f /var/log/nginx/error.log` |
    | Laravel | 程式錯誤、資料庫連線失敗等 | `docker compose exec api ls storage/logs` 找到今天的檔案，再 `docker compose exec api tail -f storage/logs/laravel-日期.log` |
    | 容器輸出 | 容器啟動、php-fpm、PostgreSQL 的訊息 | `docker compose logs -f api`、`docker compose logs -f db` |

    `tail -f` 是「持續顯示最新的內容」，看完按 `Ctrl+C` 離開。
    ✔ 驗證：開著 `sudo tail -f /var/log/nginx/access.log`，用瀏覽器開啟網站，畫面即時出現新的紀錄，每行包含來源 IP、時間、請求網址與狀態碼。
49. 【主機】設定 log 輪替，避免 20GB 的硬碟被塞滿：
    - Nginx：安裝時已經內建每天輪替，不用設定。
    - Laravel：第 5 步已經設成 `LOG_CHANNEL: daily`，每天一個檔、自動保留 14 天。
    - Docker 容器輸出：預設會一直變大，要限制。執行 `sudo nano /etc/docker/daemon.json`，貼上：
      ```json
      {
        "log-driver": "json-file",
        "log-opts": { "max-size": "10m", "max-file": "3" }
      }
      ```
      （每個容器的 log 最多 3 個檔、每個 10MB。）存檔後執行：
      ```bash
      sudo systemctl restart docker
      docker compose up -d --force-recreate
      ```
      （新設定只對重新建立的容器有效，所以要 `--force-recreate`。）
    ✔ 驗證：`df -h /` 看硬碟使用率；網站重新整理後仍正常。
50. 故意製造錯誤來練習除錯。每個情境都照「做壞它 → 看到什麼現象 → 查哪個 log → 找到原因 → 修回來」走一次，並把過程記下來：
    - **502 Bad Gateway**：【主機】`docker compose stop api` → 【網頁】登入或打開 `/api/v1/health` 出現 502 → 【主機】`sudo tail /var/log/nginx/error.log` 看到 `connect() failed (111: Connection refused)`，代表 Nginx 找不到 Laravel → 修回來：`docker compose start api`。
    - **500 Internal Server Error**：【主機】`nano .env` 把 `POSTGRES_PASSWORD` 隨便改掉一個字，`docker compose up -d` → 【網頁】登入出現 500 → 【主機】看 Laravel log，找到 `SQLSTATE[08006]` 和 `password authentication failed`，代表資料庫密碼錯了（資料庫的密碼在第一次建立時就定下來了，改 `.env` 不會改到資料庫本身）→ 修回來：把密碼改回原本的值，再 `docker compose up -d`。
    - **404 Not Found**：【網頁】打開一個不存在的網址 → 【主機】`sudo tail /var/log/nginx/access.log` 看到那一行的狀態碼是 404。
    - **429 Too Many Requests**：【網頁】連續登入失敗 6 次 → 【主機】access.log 看到同一個 IP 連續幾行 `POST /api/v1/auth/login`，最後是 429。
    ✔ 驗證：四個情境都能說出「看到什麼 → 查哪裡 → 原因是什麼」，而且網站最後都恢復正常。
51. （可選）讓 Nginx 幫每個請求加上一個編號（request id），傳給 Laravel 一起寫進 log。這樣從 access.log 看到某一行有問題時，可以用同一個編號在 Laravel log 裡找到對應的錯誤。
52. 【本機】把第 48～50 步整理進 `docs/operations.md`：每一層 log 在哪、怎麼看，以及每個除錯情境的「現象 → 查哪裡 → 原因 → 解法」。這份文件就是這階段的成果紀錄。

### 收尾

53. 【網頁】Demo 完如果不再需要，到 AWS 主控台清乾淨，避免持續花錢：
    - EC2 → 執行個體 → 選 `todo-server` → 終止（terminate）。
    - EC2 → 彈性 IP → 先「取消關聯」再「釋放」（**沒釋放會持續計費**）。
    - EC2 → 快照、磁碟區：如果有留下來的也刪掉。
    務必在 Free plan 的 6 個月期限前處理完。
    ✔ 驗證：EC2 主控台裡執行個體、彈性 IP、磁碟區都是空的；Billing 頁面不再有新增費用。
