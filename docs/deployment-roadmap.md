# 部署上線與維運路線圖

本文件依完整上線的兩點需求，拆成可以一步一步完成、每步都能獨立驗證的小任務：

1. 把目前的版本部署上線（網域、HTTPS）。
2. 上線後做資料庫備份還原、看 log 除錯。

前置條件：[roadmap.md](roadmap.md) 第七階段（Laravel 重構）已完成。跟 roadmap.md 一樣，建議一次只做一步，做完就照「✔ 驗證」實際確認，再進到下一步。

> **部署方式：Hostinger 共享主機＋MySQL。** 目標是用最低成本、最少維護工作把網站穩定放上去。
> 原本規劃的「AWS EC2＋Docker＋Nginx＋PostgreSQL」完整版是更正規的部署方式，但目前先不採用，保留在 [deployment-roadmap-aws.md](deployment-roadmap-aws.md) 當備案，之後如果要學自己架伺服器可以參考。

## 部署的技術組成

| 技術 | 正式環境中的角色 |
|---|---|
| Vue 3 | 前端，在本機 build 成靜態檔後上傳到網站根目錄 |
| Laravel 8（PHP） | 後端 API（`backend-laravel/`），由 Hostinger 的 PHP 直接執行 |
| MySQL | 資料庫，在 hPanel 建立，由 Hostinger 管理 |
| Hostinger 共享主機 | 主機與 Web Server（LiteSpeed），用 hPanel 網頁介面管理 |
| GoDaddy DNS | 網域在 GoDaddy，用 A 記錄指到 Hostinger |
| Let's Encrypt | 免費 HTTPS 憑證，在 hPanel 一鍵安裝 |

Docker 只在本機開發時使用，正式環境不使用。舊的 Node.js 後端（`backend/`）不部署。

## 文件慣例

每一步開頭標示執行位置：

| 標示 | 執行位置 |
|---|---|
| 【本機】 | 自己電腦的 PowerShell，於專案根目錄執行 |
| 【SSH】 | SSH 登入 Hostinger 後執行（登入方式見第 13 步） |
| 【網頁】 | 瀏覽器操作（Hostinger hPanel、GoDaddy、GitHub、正式網站） |

`todo.你的網域.com` 替換成實際網址；`你的主機IP` 替換成第 10 步查到的 IP。

> **網址與路徑只需要在 SSH 上設定一次**：第 14 步會把網址與網站根目錄設成 `$APP_DOMAIN`、`$WEBROOT` 兩個變數，之後【SSH】步驟直接使用，**不需要手動替換**。【本機】與【網頁】步驟才需要自己輸入網址。

遇到錯誤時，先查文件最後的「[常見錯誤速查](#常見錯誤速查)」。

SSH 登入後，要把文件中的指令貼進去時：先在文件中複製，再到 PowerShell 視窗**按滑鼠右鍵**即可貼上（不是 `Ctrl+V`）。在 `nano` 編輯器裡也是按右鍵貼上。

> **注意**：文件中 hPanel、GoDaddy 的選單與按鈕名稱僅供參考，**不保證位置與名稱跟實際畫面一致**——這些網站經常改版，中英文介面的用詞也不同。找不到時，以文件描述的「要設定什麼」為準，在畫面上找對應的功能，或直接問 Hostinger 客服（24 小時線上客服）。

## 第一階段：部署上線（網域、HTTPS）

目標：把目前版本部署到 Hostinger 共享主機，用自己的網域、走 HTTPS。以最低成本為原則。

> **費用規劃**
> - 網域：已在 GoDaddy 購買 1 年，記得關閉自動續約。
> - 主機：Hostinger 共享主機，**選有 SSH 的最便宜方案**（見第 3 步），只買 1 年、關閉自動續約，加購一律不買。注意續約價通常比首年貴很多。
> - HTTPS：hPanel 內建免費憑證，不用另外買。
> - 實際價格與方案內容以購買當下的 Hostinger 官網為準。

### 名詞說明

| 名詞 | 說明 |
|---|---|
| 共享主機 | 多個網站共用同一台主機，主機商負責維護系統，你只能管自己的檔案與資料庫，沒有 root 權限。 |
| hPanel | Hostinger 的管理介面，建立網站、資料庫、SSL、排程、備份都在這裡操作。 |
| 網站根目錄 | 瀏覽器打開網址時看到的檔案所在資料夾（本文件稱為 `$WEBROOT`）。放在這裡的檔案都可能被外人直接下載，所以 Laravel 程式碼與 `.env` **不能**放進來。 |
| `.htaccess` | 放在網站根目錄的設定檔，告訴 Web Server 哪些網址要交給誰處理（本專案用它把 `/api` 交給 Laravel、其他交給 Vue）。 |
| DNS／A 記錄 | DNS 負責把網域解析成 IP；A 記錄即「網域 → IPv4 位址」的對應。 |
| SSH | 用指令登入主機的方式。Hostinger 共享主機的 SSH port 通常是 `65002`，不是預設的 22。 |
| build | 將 Vue 原始碼編譯成靜態的 html／js／css（輸出到 `dist/`），主機上不需要 Node.js。 |

### 上線架構

```
你的瀏覽器
   │  https://todo.你的網域.com
   ▼
GoDaddy DNS：查到 todo.你的網域.com = 你的主機IP
   ▼
Hostinger 共享主機（LiteSpeed，HTTPS 憑證由 hPanel 管理）
   └─ 網站根目錄 $WEBROOT（.htaccess 依網址分派）
        ├─ 網址是 /api/... 或 /sanctum/... → laravel.php → ~/todo-fullstack/backend-laravel（Laravel）
        │                                                    └─ MySQL（hPanel 建立，只有主機內部連得到）
        └─ 其他網址 → 直接回傳前端 build 好的檔案（index.html、assets/）
```

Laravel 程式碼放在家目錄 `~/todo-fullstack`，**不在網站根目錄裡**，外人無法直接下載 `.env` 或原始碼；網站根目錄只放前端檔案、`.htaccess` 和一支只有一行的 `laravel.php`。

與本機開發版的差異：

| | 本機開發版（現在） | 正式版（要做的） |
|---|---|---|
| 前端 | `npm run dev` 跑 Vite 開發伺服器（port 3010） | `npm run build` 出靜態檔，上傳到網站根目錄 |
| 後端 | Docker 裡的 `php -S`（port 3011） | Hostinger 的 PHP 直接執行 |
| 資料庫 | Docker 裡的 MySQL，3306 對外開放方便用 DBeaver 連 | hPanel 建立的 MySQL，不對外開放 |
| 網址 | 前後端不同 port | 前後端同一個網域（Cookie 同源，CORS 問題最少） |
| 錯誤畫面 | `APP_DEBUG=true`，會顯示詳細錯誤 | `APP_DEBUG=false`，不能讓外人看到程式細節 |

### ✅️ 1-0　開始前：建立部署資訊紀錄

部署過程中有幾個值（網址、主機 IP、SSH 登入指令、資料庫名稱等）會在後面的步驟反覆用到，先準備好一份紀錄檔，拿到值時就填進去，之後不用回頭翻找。

0. ✅️ 【本機】把範本 [deployment-info.example.md](deployment-info.example.md) 複製一份，命名為 `docs/deployment-info.md`：
   ```powershell
   Copy-Item docs\deployment-info.example.md docs\deployment-info.md
   ```
   之後每拿到一個值，就填在 `deployment-info.md` 並打勾。`deployment-info.md` 已加入 `.gitignore`，不會進 git，**請自行另外備份**（換電腦或刪掉專案資料夾就會不見）。密碼類的值不要寫在裡面。
   ✔ 驗證：`docs/deployment-info.md` 存在；執行 `git status` 看不到這個檔案（代表已被忽略，不會被 commit）。

### ✅️ 1-1　網域

1. ✅️ 【網頁】到 GoDaddy 搜尋並購買網域：
   - 年限只選 **1 年**。
   - 結帳頁取消所有加購（SSL 憑證、專業信箱、網站建置等）；網域隱私保護如果是免費附贈就保留。
   - 買完到帳戶的網域設定裡，把**自動續約關掉**。
   - **到信箱點驗證信**：新註冊的網域通常會寄一封「驗證網域聯絡人 email」的信（寄件者是 GoDaddy 或網域註冊管理機構，也檢查垃圾郵件匣）。期限內沒點，網域可能被暫停解析，屆時網站會突然打不開，而且跟主機設定無關。
   ✔ 驗證：GoDaddy「我的產品」裡看得到這個網域；驗證信已點擊確認，網域設定頁沒有出現「待驗證」之類的警告。
2. ✅️ 決定網址：建議用子網域 `todo.你的網域.com`，之後這個網域還能拿來放別的東西。把完整網址記下來，後面所有 `todo.你的網域.com` 都換成它。
   （DNS 設定要先拿到主機的 IP，所以放在第 10 步才做。）

### 1-2　買 Hostinger 主機方案

3. 【網頁】到 Hostinger 官網，打開共享主機（Web Hosting）的**方案比較表**，選方案：
   - **一定要有「SSH access」**：Laravel 上線要執行 `composer install`、`php artisan migrate`，沒有 SSH 很難部署。最便宜的那一級可能沒有 SSH，有的話選最便宜的，沒有就選有 SSH 的最便宜那一級。看不出來就問線上客服：「這個方案有 SSH 嗎？可以用 Composer 和 Git 嗎？可以選 PHP 8.1 嗎？」
   - 年限只選 **1 年**；結帳頁取消所有加購（網域、信箱、SSL 等——網域已經在 GoDaddy 買了，HTTPS 用免費的）。
   - 買完到帳單設定把**自動續約關掉**。
   ✔ 驗證：能登入 hPanel；方案頁面或比較表上確認有 SSH。
4. 【網頁】hPanel 第一次登入時會跳出建立網站的引導，選「**我已經有網域**」（或「使用現有網域」），輸入 `你的網域.com`（主網域，不含 `todo.`）。如果引導要你選 WordPress 或網站建置工具，**跳過或選空白網站**，這個專案不需要。
   ✔ 驗證：hPanel 的網站列表看得到 `你的網域.com`。

### 1-3　本機：準備正式版（改用 MySQL）

Hostinger 共享主機只有 MySQL，沒有 PostgreSQL，所以後端要先改成 MySQL，**在本機確認一切正常後**再上線。本機的資料都是測試資料，不用搬，換完重新註冊帳號即可。

5. 【本機】修改 migration，拿掉 PostgreSQL 專用的寫法：
   - `backend-laravel/database/migrations/` 裡的 users、todos、password_reset_tokens 三個 migration：刪掉 `CREATE EXTENSION pgcrypto` 和 `ALTER TABLE ... SET DEFAULT gen_random_uuid()` 這幾行（UUID 本來就由 Model 用 `Str::uuid()` 產生，不需要資料庫預設值）。
   - todos 的 `CREATE INDEX ... created_at DESC` 改用 Laravel 寫法：`$table->index(['user_id', 'completed', 'created_at']);`。
   - password_reset_tokens 的 `token_hash` 從 `text()` 改成 `string('token_hash', 64)`（MySQL 的 `TEXT` 欄位不能直接加 UNIQUE）。
   ✔ 驗證：`grep -rn "pgcrypto\|gen_random_uuid" backend-laravel/database` 找不到任何結果。
6. 【本機】本機開發環境換成 MySQL：
   - `docker-compose.yml`：`db` 服務改用 `mysql:8.0` 映像檔，環境變數與 healthcheck 改成 MySQL 的寫法；`api` 的 `DB_CONNECTION` 改成 `mysql`、`DB_PORT` 改成 `3306`。
   - `backend-laravel/Dockerfile`：PHP 擴充從 `pdo_pgsql pgsql` 改成 `pdo_mysql`，拿掉 `postgresql-dev`。
   - `backend-laravel/.env.example`、根目錄 `.env.example`：改成 MySQL 的設定值。
   ✔ 驗證：【本機】`docker compose down -v` 清掉舊的 PostgreSQL 資料後，`docker compose up --build` 能正常啟動，`docker compose exec api php artisan migrate` 成功。
7. 【本機】在本機走一次完整流程：註冊 → 登入 → 新增／編輯／勾選／刪除 Todo → 登出。
   ✔ 驗證：全部正常；用 DBeaver 連 `localhost:3306` 看得到 `users`、`todos` 的資料。
8. 【本機】幫登入、註冊加上速率限制，避免有人一直猜密碼（順便解掉 [roadmap.md](roadmap.md)「範圍之外」的速率限制項目）。在 `backend-laravel/routes/api.php` 把這兩行：
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
9. 【本機】新增網站根目錄要用的兩個檔案，放在 `deploy/hostinger/`（進 git，等一下 SSH 上直接複製過去）：

   `deploy/hostinger/.htaccess`：
   ```apache
   Options -MultiViews -Indexes
   RewriteEngine On

   # /api/... 與 /sanctum/... 交給 Laravel
   RewriteRule ^(api|sanctum)(/.*)?$ laravel.php [L]

   # 實際存在的檔案（js、css、圖片）直接回傳
   RewriteCond %{REQUEST_FILENAME} -f
   RewriteRule ^ - [L]

   # 其他網址都回傳 index.html，交給 Vue Router 處理（例如直接打開 /login 或重新整理頁面）
   RewriteRule ^ index.html [L]
   ```

   `deploy/hostinger/laravel.php`（`__LARAVEL_PATH__` 會在第 18 步自動換成主機上的實際路徑）：
   ```php
   <?php
   // 把請求交給放在網站根目錄外面的 Laravel，Laravel 程式碼與 .env 都不在網站根目錄裡
   require '__LARAVEL_PATH__/public/index.php';
   ```
   然後把第 5～9 步的改動 commit 並 push 到 GitHub。
   ✔ 驗證：GitHub 網頁上看得到 `deploy/hostinger/.htaccess`、`deploy/hostinger/laravel.php`，以及改好的 migration。

### 1-4　hPanel 設定：子網域、DNS、HTTPS、PHP、資料庫、SSH

10. 【網頁】建立子網域並設定 DNS：
    1. hPanel → 網域 → **子網域（Subdomains）**，建立 `todo`。如果有「自訂資料夾」選項，資料夾填 `todo`。建立後畫面會顯示這個子網域的資料夾路徑，記到 `deployment-info.md`。
    2. hPanel 的方案或網站資訊頁找到**主機 IP（IP address）**，記到 `deployment-info.md`。
    3. 到 GoDaddy → 網域 → DNS 管理，新增一筆記錄：類型 `A`、名稱 `todo`、值填 `你的主機IP`、TTL 用預設。
    ✔ 驗證：等幾分鐘到幾小時，【本機】執行 `nslookup todo.你的網域.com`，回傳的 Address 是 `你的主機IP`。
11. 【網頁】設定 HTTPS（DNS 生效後才能申請）：hPanel → 安全性 → **SSL**，替 `todo.你的網域.com` 安裝免費 SSL；然後開啟「**強制 HTTPS（Force HTTPS）**」。
    ✔ 驗證：瀏覽器打開 `http://todo.你的網域.com` 會自動跳到 `https://`，網址列有鎖頭（這時頁面內容可能是 Hostinger 的預設頁，正常）。
12. 【網頁】設定 PHP 與資料庫：
    1. hPanel → 進階 → **PHP 設定（PHP Configuration）**，PHP 版本選 **8.1**（Laravel 8 支援到 8.1）。
    2. hPanel → 資料庫 → **MySQL 資料庫**，建立一個資料庫與使用者。Hostinger 會自動在名稱前面加前綴（像 `u123456789_todo`），**以畫面上顯示的完整名稱為準**。密碼用畫面上的產生器產生，存到自己的密碼管理工具（**不要**寫在 `deployment-info.md`）。
    ✔ 驗證：PHP 設定頁顯示 8.1；資料庫列表看得到剛建立的資料庫，資料庫名稱、使用者名稱記到 `deployment-info.md`。
13. 【網頁＋本機】開啟 SSH 並登入：
    1. 【網頁】hPanel → 進階 → **SSH 存取（SSH Access）**，啟用 SSH。畫面會顯示 IP、Port（通常是 `65002`）、使用者名稱（像 `u123456789`），以及完整的登入指令。可以順便設定 SSH 密碼。
    2. 【本機】用畫面上的指令登入，格式像：
       ```powershell
       ssh -p 65002 u123456789@你的主機IP
       ```
       第一次會問 `Are you sure you want to continue connecting`，輸入 `yes`；接著輸入 SSH 密碼（輸入時畫面不會顯示任何字，是正常的）。
    ✔ 驗證：登入成功，出現主機的命令列提示字元。完整登入指令記到 `deployment-info.md`。
14. 【SSH】確認工具都在，並設定網址與網站根目錄變數：
    ```bash
    php -v
    composer --version
    git --version
    ```
    `php -v` 要顯示 `8.1.x`；三個指令都要有版本號。
    接著設定變數（網址換成你的；路徑換成第 10 步記下的子網域資料夾，通常像 `~/domains/你的網域.com/public_html/todo`）：
    ```bash
    echo 'export APP_DOMAIN=todo.你的網域.com' >> ~/.bashrc
    echo 'export WEBROOT=~/domains/你的網域.com/public_html/todo' >> ~/.bashrc
    source ~/.bashrc
    ls $WEBROOT
    ```
    ✔ 驗證：`echo $APP_DOMAIN` 印出正確網址；`ls $WEBROOT` 不報錯（裡面可能有 Hostinger 放的預設檔案）。登出再登入後 `echo $APP_DOMAIN` 仍然有值。

### 1-5　部署後端（Laravel）

15. 【SSH】把程式碼抓到家目錄（**不是**網站根目錄）：
    ```bash
    cd ~
    git clone https://github.com/你的帳號/todo-fullstack.git
    ```
    如果 Repository 是 private，`git clone` 會要求登入：使用者名稱填 GitHub 帳號，密碼填 GitHub 的 **Personal Access Token**（GitHub → Settings → Developer settings → Personal access tokens 建立，權限只給這個 Repo 的 Contents 唯讀），不是 GitHub 密碼。
    ✔ 驗證：`ls ~/todo-fullstack/backend-laravel` 看得到 `artisan`、`composer.json`。
16. 【SSH】安裝套件並建立 `.env`：
    ```bash
    cd ~/todo-fullstack/backend-laravel
    composer install --no-dev --optimize-autoloader
    cp .env.example .env
    php artisan key:generate
    chmod 600 .env
    nano .env
    ```
    在 `nano` 裡把以下幾個值改成正式版設定（沒列到的保持原樣；資料庫三個值用第 12 步建立的；網址換成你的）：
    ```
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://todo.你的網域.com
    LOG_CHANNEL=daily

    DB_CONNECTION=mysql
    DB_HOST=localhost
    DB_PORT=3306
    DB_DATABASE=u123456789_todo
    DB_USERNAME=u123456789_todo
    DB_PASSWORD=第12步產生的密碼

    SESSION_DOMAIN=todo.你的網域.com
    SESSION_SECURE_COOKIE=true
    SANCTUM_STATEFUL_DOMAINS=todo.你的網域.com
    CORS_ORIGIN=https://todo.你的網域.com
    ```
    `Ctrl+O` → `Enter` 存檔，`Ctrl+X` 離開。（`APP_DEBUG=false`：出錯時不顯示程式細節；`LOG_CHANNEL=daily`：log 每天一個檔、保留 14 天；`SESSION_SECURE_COOKIE=true`：Cookie 只在 https 下傳送。）
    ✔ 驗證：`grep -E "APP_ENV|APP_DEBUG|APP_KEY|DB_CONNECTION" .env` 印出 `production`、`false`、一串 `base64:` 開頭的值、`mysql`。
17. 【SSH】建立資料表：
    ```bash
    cd ~/todo-fullstack/backend-laravel
    php artisan migrate --force
    ```
    （`--force`：正式環境下 Laravel 會再問一次是否確定，加上它就不會停下來問。）
    ✔ 驗證：每個 migration 都顯示 `Migrated`；【網頁】hPanel → 資料庫 → phpMyAdmin 看得到 `users`、`todos` 等資料表。
18. 【SSH】把 `.htaccess` 與 `laravel.php` 放進網站根目錄，並刪掉 Hostinger 的預設首頁：
    ```bash
    cp ~/todo-fullstack/deploy/hostinger/.htaccess $WEBROOT/
    sed "s#__LARAVEL_PATH__#$HOME/todo-fullstack/backend-laravel#" ~/todo-fullstack/deploy/hostinger/laravel.php > $WEBROOT/laravel.php
    rm -f $WEBROOT/default.php $WEBROOT/index.php
    cat $WEBROOT/laravel.php
    ```
    ✔ 驗證：`cat` 印出的 `require` 路徑是完整路徑（像 `/home/u123456789/todo-fullstack/backend-laravel/public/index.php`），不再是 `__LARAVEL_PATH__`；【網頁】打開 `https://todo.你的網域.com/api/v1/health` 回傳 JSON。

### 1-6　部署前端（Vue）

19. 【本機】build 前端。用暫時的 Node 容器 build，不用在電腦上另外裝 Node（網址換成你的）：
    ```powershell
    docker run --rm -v "${PWD}\frontend:/app" -v /app/node_modules -w /app -e VITE_API_BASE_URL=https://todo.你的網域.com/api/v1 node:22-alpine sh -c "npm ci && npm run build"
    ```
    （`-v /app/node_modules`：套件裝在容器裡，不會寫進你電腦的 `frontend/node_modules`；`VITE_API_BASE_URL` 會在 build 時寫死進 js 檔，所以網址錯了要重新 build。）
    ✔ 驗證：`frontend\dist` 裡有 `index.html` 和 `assets` 資料夾；`Select-String -Path frontend\dist\assets\*.js -Pattern "todo.你的網域.com/api/v1"` 找得到結果。
20. 【本機＋SSH】上傳到網站根目錄：
    1. 【本機】把 `dist` 資料夾傳到主機的家目錄（Port、使用者、IP 換成第 13 步的值）：
       ```powershell
       scp -P 65002 -r frontend\dist u123456789@你的主機IP:~/todo-dist
       ```
    2. 【SSH】刪掉網站根目錄舊的前端檔案，再把新的複製進去（`.htaccess`、`laravel.php` 不會被刪）：
       ```bash
       rm -rf $WEBROOT/assets $WEBROOT/index.html
       cp -r ~/todo-dist/. $WEBROOT/
       rm -rf ~/todo-dist
       ls -a $WEBROOT
       ```
    ✔ 驗證：`ls -a` 看得到 `.htaccess`、`laravel.php`、`index.html`、`assets`；【網頁】打開 `https://todo.你的網域.com` 看到登入頁，直接打開 `https://todo.你的網域.com/register` 或在任何頁面重新整理也不會出現 404。

### 1-7　上線驗收與安全檢查

21. 【網頁】在正式網址走一次完整流程：註冊 → 登入 → 新增／編輯／勾選／刪除 Todo → 登出 → 重新整理頁面確認被導回登入頁。
    ✔ 驗證：全部正常，DevTools（F12）的 Network 分頁沒有紅色的 419、401、500、CORS 錯誤。
    如果有某個功能不正常：【本機】用 `docker compose up` 啟動開發版，走一次同樣的流程。本機也壞 → 是程式本身的問題，跟部署無關；本機正常、只有正式網址壞 → 才是部署設定的問題，回頭檢查 `.env`、`.htaccess`。

    > **注意：https 下的登入流程尚未實際測試過。** 本機測試只能用 http，而正式版設定了 `SESSION_SECURE_COOKIE=true`，所以註冊／登入要到這一步才會第一次真正驗證。若出現 **419**（CSRF token mismatch）或登入後打 API 回 **401**，多半是網域設定不一致，依序檢查：
    > 1. 【SSH】`grep -E "APP_URL|SESSION_DOMAIN|SANCTUM|CORS" ~/todo-fullstack/backend-laravel/.env`：網域必須跟瀏覽器網址列**完全一致**——`SESSION_DOMAIN`、`SANCTUM_STATEFUL_DOMAINS` 不含 `https://`；結尾都沒有 `/`、沒有多餘空白。
    > 2. 【SSH】前端打的網址也要一樣：`grep -oh "https://[^\"]*/api/v1" $WEBROOT/assets/*.js | head -1`。不對 → 重做第 19、20 步。
    > 3. 【網頁】DevTools → Application → Cookies 刪除該網域的所有 Cookie，重新整理後再試一次，避免舊 Cookie 干擾。
    > 4. 【SSH】仍然失敗時，看 Laravel log 找錯誤訊息（看法見第 33 步）。
22. 【網頁】檢查 Cookie 的安全設定：DevTools → Application → Cookies → 選你的網域。
    ✔ 驗證：`laravel_session` 的 `HttpOnly` 和 `Secure` 都有打勾；`XSRF-TOKEN` 的 `Secure` 有打勾。
23. 【網頁】確認錯誤畫面不會洩漏程式細節：打開 `https://todo.你的網域.com/api/v1/不存在的路徑`。
    ✔ 驗證：只看到簡單的錯誤訊息，沒有檔案路徑、程式碼、stack trace。
24. 【網頁】確認機密檔案下載不到：分別打開 `https://todo.你的網域.com/.env`、`https://todo.你的網域.com/.htaccess`。
    ✔ 驗證：都**沒有**顯示檔案內容（顯示前端頁面、403 或 404 都可以）。`.env` 本來就不在網站根目錄裡，這一步是再次確認。
25. 【本機】確認密碼沒有進 git：
    ```powershell
    git ls-files | Select-String "\.env"
    ```
    ✔ 驗證：只看到 `.env.example` 這類範本，沒有單獨一個 `.env`。
26. 【網頁】在正式網址連續用錯誤密碼登入 6 次。
    ✔ 驗證：第 6 次出現 `429`（第 8 步加的速率限制在正式環境也有效）。

### 1-8　寫下部署文件

27. 【本機】填寫 [deployment.md](deployment.md)（檔案已建立，目前為空白），記錄這次學到、之後會重複用到的東西（**不要寫密碼**）：
    - 主機資訊：Hostinger 方案、網域、SSH 登入指令、網站根目錄路徑。
    - 「怎麼更新版本」的步驟：
      1. 【本機】改完程式、commit、push。
      2. 【SSH】`cd ~/todo-fullstack && git pull`
      3. 【SSH】`cd backend-laravel && composer install --no-dev --optimize-autoloader`
      4. 【SSH】`php artisan migrate --force`
      5. 前端有改的話，重做第 19、20 步。
    - 這次踩過的坑和解法。
    ✔ 驗證：之後要更新版本時，照著這份文件就能完成，不用再翻其他資料。

## 第二階段：維運（資料庫備份還原、看 log 除錯）

### 名詞說明

| 名詞 | 說明 |
|---|---|
| `mysqldump`／`mysql` | MySQL 的備份／還原工具，Hostinger 的 SSH 環境已內建。 |
| cron | 依設定的時間自動執行指令。共享主機在 hPanel 的「Cron Jobs」設定。 |
| RTO（Recovery Time Objective） | 發生事故到服務恢復所需的時間。 |
| RPO（Recovery Point Objective） | 發生事故時最多會遺失多久的資料。每日備份一次，RPO 最多約 24 小時。 |
| 維護模式 | `php artisan down` 讓網站暫時回 503，避免還原資料時有人寫入；`php artisan up` 恢復。 |

### 2-1　備份

28. 【SSH】讓備份指令不用每次輸入密碼：在家目錄建立 `~/.my.cnf`（只有自己能讀），之後 `mysqldump`、`mysql` 會自動讀取：
    ```bash
    nano ~/.my.cnf
    ```
    貼上（換成第 12 步的值）：
    ```
    [client]
    user=u123456789_todo
    password=第12步產生的密碼
    ```
    存檔後執行 `chmod 600 ~/.my.cnf`。接著先手動備份一次，熟悉指令：
    ```bash
    mkdir -p ~/backups
    mysqldump --single-transaction --no-tablespaces u123456789_todo | gzip > ~/backups/todo_manual.sql.gz
    ```
    參數說明：`--single-transaction` 是在不鎖表的情況下取得一致的資料；`--no-tablespaces` 避免共享主機上因權限不足而報錯；最後是資料庫名稱；`| gzip >` 是壓縮後存成檔案。
    ✔ 驗證：`ls -lh ~/backups` 看到 `todo_manual.sql.gz`，大小不是 0；`zcat ~/backups/todo_manual.sql.gz | grep "CREATE TABLE"` 看得到 `users`、`todos`。
29. 【本機】在專案新增 `scripts/backup.sh`（放進 git 管理），內容（資料庫名稱換成你的）：
    ```bash
    #!/bin/bash
    # 每日資料庫備份：存到 ~/backups，只保留最近 7 天（密碼從 ~/.my.cnf 讀取）
    set -e
    mkdir -p "$HOME/backups"

    FILE="$HOME/backups/todo_$(date +%Y%m%d_%H%M).sql.gz"
    mysqldump --single-transaction --no-tablespaces u123456789_todo | gzip > "$FILE"
    echo "$(date) 備份完成：$FILE"

    # 刪掉 7 天以前的備份檔
    find "$HOME/backups" -name 'todo_*.sql.gz' -mtime +7 -delete
    ```
    commit、push，然後【SSH】`cd ~/todo-fullstack && git pull` 抓下來。
    之後一律用 `bash scripts/backup.sh` 執行，**不要**在主機上執行 `chmod +x`：chmod 會讓 git 認為主機上的檔案被修改過，之後 `git pull` 會因為「本機有修改」而失敗。
    ✔ 驗證：【SSH】`bash ~/todo-fullstack/scripts/backup.sh` 印出「備份完成」，`ls ~/backups` 多了一個帶日期時間的檔案。
30. 【網頁】設定每天自動備份：hPanel → 進階 → **Cron Jobs**，新增一筆：
    - 指令：`/bin/bash /home/u123456789/todo-fullstack/scripts/backup.sh >> /home/u123456789/backups/backup.log 2>&1`（`u123456789` 換成你的 SSH 使用者名稱，可用【SSH】`echo $HOME` 確認完整路徑）
    - 時間：每天一次，選凌晨時段。主機時區不一定是台灣時間，可用【SSH】`date` 確認目前主機時間再換算。
    `>> backup.log 2>&1` 是把執行結果（包括錯誤）記到 `backup.log`，出問題時才查得到原因。
    ✔ 驗證：hPanel 的 Cron Jobs 列表看得到這筆；隔天【SSH】`ls ~/backups` 有新檔案、`cat ~/backups/backup.log` 有「備份完成」。
31. 【本機】把備份抓回自己電腦（異地備份）。備份檔跟資料庫放在同一台主機上，主機出事就一起不見了，所以要另外存一份：
    ```powershell
    mkdir C:\todo-backups
    scp -P 65002 "u123456789@你的主機IP:~/backups/todo_*.sql.gz" C:\todo-backups\
    ```
    另外，hPanel 的「備份（Backups）」也有 Hostinger 自動做的整站備份（頻率依方案而定），可以當作第二層保護，但不能取代自己的備份——要能自己掌握備份時間與檔案。
    ✔ 驗證：`C:\todo-backups` 裡有備份檔。建議每週抓一次，或 Demo 前抓一次。

### 2-2　還原演練

備份沒有實際還原過，就不能確定它真的能用。這一段是在「不影響正式資料」和「模擬真的出事」兩種情況下各練一次。

32. 【網頁＋SSH】還原演練：
    1. 【網頁】先還原到測試資料庫，不碰正式資料：hPanel 再建立一個資料庫（例如 `u123456789_restore`），**使用者選跟正式資料庫同一個**（或在 hPanel 把同一個使用者加到這個資料庫），這樣 `~/.my.cnf` 的帳密兩個資料庫都能用。
    2. 【SSH】還原進測試資料庫，並比對筆數：
       ```bash
       zcat ~/backups/todo_manual.sql.gz | mysql u123456789_restore
       mysql u123456789_todo -e "SELECT count(*) FROM todos;"
       mysql u123456789_restore -e "SELECT count(*) FROM todos;"
       ```
       ✔ 驗證：兩個筆數一樣。確認完到 hPanel 把測試資料庫刪掉。
    3. 模擬事故並還原正式資料庫：
       1. 【SSH】先跑一次 `bash ~/todo-fullstack/scripts/backup.sh`，記下這次的檔名。
       2. 【網頁】在網站上記下目前有哪些 Todo，然後刪掉其中幾筆（假裝是誤刪）。
       3. 【SSH】開始計時。先開維護模式，避免還原時有人寫入資料：`cd ~/todo-fullstack/backend-laravel && php artisan down`
       4. 【SSH】還原（`mysqldump` 產生的檔案裡已經有「先刪掉資料表再建立」的指令，會直接覆蓋現有資料）：
          ```bash
          zcat ~/backups/剛剛的檔名.sql.gz | mysql u123456789_todo
          ```
       5. 【SSH】`php artisan up`，停止計時。
       ✔ 驗證：【網頁】重新整理網站，被刪掉的 Todo 都回來了。記下這次花了幾分鐘（RTO），以及如果是靠每天的自動備份，最多會遺失多少資料（RPO）。
    4. 【本機】在 [operations.md](operations.md)（檔案已建立，目前為空白）中，把這一步寫成「資料庫還原 SOP」：出事時照著一步一步做就能還原，不需要臨時查資料。

### 2-3　看 log 除錯

33. 【SSH＋網頁】認識每一層的 log 在哪裡、怎麼看：
    | 哪一層 | 記錄什麼 | 怎麼看 |
    |---|---|---|
    | Laravel | 程式錯誤、資料庫連線失敗等 | 【SSH】`ls ~/todo-fullstack/backend-laravel/storage/logs` 找到今天的檔案，再 `tail -f ~/todo-fullstack/backend-laravel/storage/logs/laravel-日期.log` |
    | Web Server 存取紀錄 | 每個請求：誰、什麼時間、打哪個網址、回什麼狀態碼 | 【網頁】hPanel 的「存取紀錄（Access Logs）」或網站分析頁（位置依介面而定，找不到可問客服） |
    | Web Server 錯誤紀錄 | `.htaccess` 寫錯、PHP 執行錯誤等 | 【網頁】hPanel 的錯誤紀錄（Error Logs），或【SSH】看網站資料夾附近有沒有 `error_log` 檔案 |

    `tail -f` 是「持續顯示最新的內容」，看完按 `Ctrl+C` 離開。log 不用另外設定輪替：Laravel 在第 16 步已經設成 `LOG_CHANNEL=daily`，每天一個檔、自動保留 14 天。
    ✔ 驗證：開著 Laravel log 的 `tail -f`，在網站上故意用錯誤的格式送出註冊（例如 email 留空），確認知道去哪裡看；在 hPanel 找到存取紀錄，看得到自己剛剛的請求。
34. 故意製造錯誤來練習除錯。每個情境都照「做壞它 → 看到什麼現象 → 查哪個 log → 找到原因 → 修回來」走一次，並把過程記下來：
    - **500 Internal Server Error**：【SSH】`nano ~/todo-fullstack/backend-laravel/.env` 把 `DB_PASSWORD` 隨便改掉一個字 → 【網頁】登入出現 500 → 【SSH】看 Laravel log，找到 `SQLSTATE[HY000] [1045] Access denied for user`，代表資料庫密碼錯了 → 修回來：把密碼改回原本的值（不用重啟任何東西，下一個請求就會讀到新設定）。
    - **API 全部掛掉**：【SSH】`mv $WEBROOT/laravel.php $WEBROOT/laravel.php.bak` → 【網頁】登入失敗，DevTools 看到 `/api/...` 回傳的是 HTML 而不是 JSON → 原因：`.htaccess` 要交給 `laravel.php`，但檔案不見了 → 修回來：`mv $WEBROOT/laravel.php.bak $WEBROOT/laravel.php`。
    - **404 Not Found**：【網頁】打開 `https://todo.你的網域.com/api/v1/不存在的路徑` → 回傳 404 JSON → 存取紀錄看到那一行的狀態碼是 404。
    - **429 Too Many Requests**：【網頁】連續登入失敗 6 次 → 存取紀錄看到同一個 IP 連續幾行 `POST /api/v1/auth/login`，最後是 429。
    ✔ 驗證：四個情境都能說出「看到什麼 → 查哪裡 → 原因是什麼」，而且網站最後都恢復正常。
35. 【本機】把第 33～34 步整理進 [operations.md](operations.md)：每一層 log 在哪、怎麼看，以及每個除錯情境的「現象 → 查哪裡 → 原因 → 解法」。這份文件就是這階段的成果紀錄。

### 收尾

36. 【網頁】確認之後不會被自動扣款：
    - Hostinger：hPanel → 帳單，確認主機方案的自動續約已關閉，記下到期日。
    - GoDaddy：確認網域的自動續約已關閉，記下到期日。
    到期前決定要不要續約；不續約的話，到期前先把資料庫備份抓回本機（第 31 步）。
    ✔ 驗證：兩邊的自動續約都顯示關閉，到期日已記到 `deployment-info.md`。

## 常見錯誤速查

| 現象 | 常見原因 | 處理方式 |
|---|---|---|
| SSH 一直沒反應，最後 `Connection timed out` | Port 打錯（不是 22），或 SSH 尚未啟用 | 確認指令有 `-p 65002`（以 hPanel 顯示的為準），到 hPanel 確認 SSH 已啟用 |
| SSH 出現 `Permission denied` | 使用者名稱或密碼錯誤 | 使用者名稱用 hPanel SSH 頁顯示的（像 `u123456789`），密碼可在同一頁重設 |
| `php -v` 不是 8.1 | SSH 的 PHP 版本跟網站設定不同步 | 確認第 12 步已選 8.1，登出再登入 SSH；仍不對就問 Hostinger 客服「SSH 怎麼用 PHP 8.1」 |
| `composer install` 出現 PHP 版本或擴充套件錯誤 | PHP 版本不對，或缺少擴充 | 先確認 `php -v`；缺擴充的話到 hPanel PHP 設定的擴充（Extensions）頁勾選 |
| `git pull` 出現 `Your local changes ... would be overwritten` | 主機上改過被 git 管理的檔案 | `git status` 看是哪個檔案，用 `git checkout -- 檔名` 還原後再 `git pull` |
| 打開網站看到 Hostinger 預設頁 | 預設首頁沒刪，或前端還沒上傳 | 重做第 18 步的 `rm` 與第 20 步 |
| 重新整理頁面或直接打開 `/login` 出現 404 | `.htaccess` 沒放進網站根目錄 | `ls -a $WEBROOT` 確認有 `.htaccess`，沒有就重做第 18 步 |
| `/api/...` 回傳 HTML 而不是 JSON | `laravel.php` 不在或路徑錯誤 | `cat $WEBROOT/laravel.php` 確認路徑正確，重做第 18 步 |
| 網站出現 500 Server Error | Laravel 程式或設定出錯（常見是資料庫連線、`.env` 打錯） | 看 Laravel log（第 33 步），找 `ERROR` 那幾行 |
| 網站出現 500，Laravel log 卻沒有新紀錄 | `.htaccess` 語法錯誤，或 `laravel.php` 的路徑找不到 | 看 hPanel 的錯誤紀錄；對照第 9 步檢查 `.htaccess` 與 `laravel.php` |
| 登入出現 419 或登入後 401 | 網域設定不一致 | 照第 21 步的排查清單檢查 |
| SSL 安裝失敗 | DNS 尚未生效 | 【本機】`nslookup todo.你的網域.com` 確認 IP 正確，等 DNS 生效後再裝 |
| 網站完全打不開（連不上） | DNS 尚未生效或 A 記錄設錯 | `nslookup` 確認回傳的是 Hostinger 的主機 IP；到 GoDaddy 檢查 A 記錄 |
| `mysqldump` 出現 `Access denied` | `~/.my.cnf` 的帳密錯誤或權限不對 | 檢查 `~/.my.cnf` 內容與 `chmod 600 ~/.my.cnf` |

上表找不到的錯誤：把完整錯誤訊息複製下來，記錄當時執行的步驟與指令，再搜尋或詢問 Hostinger 客服。
