# 部署文件

正式環境的現況、日常更新版本的步驟，以及第一次部署時踩過的坑。第一次從零部署的完整過程見 [deployment-roadmap.md](deployment-roadmap.md)。

> **這份文件會進 git（Repository 是 public）**，只寫通用的步驟與觀念。正式環境的實際設定（主機 IP、SSH 帳號、資料庫名稱、網站根目錄路徑、`.env` 內容）一律不寫在這裡，記在 `docs/deployment-info.md`（已加入 `.gitignore`，只存在本機，請自行備份）。密碼不寫在任何文件裡。文件中的 `u123456789`、`你的主機IP`、`todo.你的網域.com` 都是代稱。

## 正式環境總覽

| 項目 | 內容 |
|---|---|
| 主機 | Hostinger 共享主機（LiteSpeed），用 hPanel 管理 |
| 網域與 DNS | 網域在 GoDaddy，DNS 留在 GoDaddy，只用一筆 A 記錄把子網域指到主機 |
| HTTPS | hPanel 自動安裝的免費 SSL（Let's Encrypt，自動續期），`http://` 會 301 跳到 `https://` |
| PHP | 8.2（Hostinger 最低只能選 8.2；Laravel 8 已實測相容） |
| 資料庫 | MySQL，在 hPanel 建立，只有主機內部連得到 |
| 實際設定值、到期日 | 見 `deployment-info.md`（不進 git） |

### 主機上的檔案配置

```
~/                                    ← SSH 登入後的家目錄
├─ todo-fullstack/                    ← git clone 下來的整個專案（不在網站根目錄裡，外人下載不到）
│   └─ backend-laravel/
│       ├─ .env                       ← 正式版設定（權限 600，不進 git）
│       └─ storage/logs/              ← Laravel log，每天一個檔、保留 14 天
└─ （子網域的網站根目錄，$WEBROOT）
    ├─ .htaccess      ← /api、/sanctum 交給 laravel.php，其他交給 Vue
    ├─ laravel.php    ← 一行 require，轉給家目錄裡的 Laravel
    ├─ index.html     ← 前端 build 出來的檔案
    └─ assets/        ← 前端 build 出來的 js、css（資料夾權限必須是 755）
```

SSH 登入後可以直接用 `$APP_DOMAIN`（正式網址）、`$WEBROOT`（網站根目錄）兩個變數，設定方式見 [deployment-roadmap.md](deployment-roadmap.md) 第 14 步。

### 正式版 `.env` 的原則

實際內容只存在主機上（權限 600），這裡只記要注意的原則：

- `APP_ENV=production`、`APP_DEBUG=false`：出錯時不顯示程式細節。
- `SESSION_SECURE_COOKIE=true`：Cookie 只在 HTTPS 下傳送。
- `SESSION_DOMAIN`、`SANCTUM_STATEFUL_DOMAINS` 填網址本身（不含 `https://`）；`APP_URL`、`CORS_ORIGIN` 要含 `https://`。四個值都要跟瀏覽器網址列完全一致，否則登入會出現 419／401。
- 要確認主機上的設定時，用不會印出密碼的指令：`grep -E "^(APP_|SESSION_|SANCTUM|CORS)" .env | grep -v KEY`。

## 登入主機

【本機】PowerShell：

```powershell
ssh -p 65002 u123456789@你的主機IP
```

- Port 是 `65002`，不是 22。
- 輸入密碼時畫面不會顯示任何字，打完按 Enter。
- 貼上指令要按**滑鼠右鍵**，不是 `Ctrl+V`。
- 看命令列開頭分辨自己在哪裡：`PS C:\...>` 是本機，`[u123456789@...]$` 是主機。

## 更新版本

### 1. 【本機】改完程式後 commit、push 到 GitHub

### 2. 【SSH】更新後端

```bash
cd ~/todo-fullstack && git pull
cd backend-laravel
composer install --no-dev --optimize-autoloader
php artisan package:discover --ansi
php artisan migrate --force
```

- `composer install` 最後一定會出現紅色的 `proc_open ... not available`，是正常的，下一行手動執行 `package:discover` 補上即可（原因見下方「踩過的坑」）。
- 只有改到 `composer.json`／`composer.lock` 時才一定要跑 `composer install`；有新的 migration 時才一定要跑 `migrate`。不確定就全部跑，重複執行不會壞。
- 後端改完不用重啟任何東西，下一個請求就會用新程式。

### 3. `deploy/hostinger/.htaccess` 有改的話，重新複製到網站根目錄

網站實際讀的是 `$WEBROOT/.htaccess`（第一次部署時複製過去的），`git pull` 只會更新 `~/todo-fullstack` 裡的那份，**不會**自動更新網站根目錄：

```bash
cp ~/todo-fullstack/deploy/hostinger/.htaccess $WEBROOT/
```

`.htaccess` 寫錯會讓整個網站變成 500，複製後馬上打開網站確認；出問題時用 `cd ~/todo-fullstack && git log --oneline -- deploy/hostinger/.htaccess` 找上一版，`git show <commit>:deploy/hostinger/.htaccess > $WEBROOT/.htaccess` 還原。

### 4. 前端有改的話，重新 build 並上傳

【本機】build（不用在電腦上裝 Node）：

```powershell
docker run --rm -v "${PWD}\frontend:/app" -v /app/node_modules -w /app -e VITE_API_BASE_URL=https://todo.你的網域.com/api/v1 node:22-alpine sh -c "npm ci && npm run build"
```

【SSH】先確認主機上沒有上次留下的 `~/todo-dist`（有的話 `scp` 會多包一層）：

```bash
rm -rf ~/todo-dist
```

【本機】上傳（`scp` 的 `-P` 是**大寫**，只執行一次）：

```powershell
scp -P 65002 -r frontend\dist u123456789@你的主機IP:~/todo-dist
```

【SSH】換掉網站根目錄的前端檔案：

```bash
rm -rf $WEBROOT/assets $WEBROOT/index.html
cp -r ~/todo-dist/. $WEBROOT/
rm -rf ~/todo-dist
chmod 755 $WEBROOT/assets
ls -a $WEBROOT
```

`ls -a` 應該只看到 `.htaccess`、`assets`、`index.html`、`laravel.php`。

### 5. 確認

- 【本機】`curl -s https://todo.你的網域.com/api/v1/health` 回傳 `{"status":"ok"}`。
- 【網頁】打開正式網址按 `Ctrl+Shift+R` 強制重新整理，登入後操作一次改到的功能。

## 踩過的坑

| 現象 | 原因 | 解法 |
|---|---|---|
| `composer install` 最後出現 `The Process class relies on proc_open, which is not available on your PHP installation.` | Hostinger 共享主機關掉了 `proc_open`，Composer 無法自動執行 `artisan package:discover`。套件其實已經裝好 | 手動執行 `php artisan package:discover --ansi`，看到 `Package manifest generated successfully.` 即可 |
| `php artisan migrate` 出現 `SQLSTATE[HY000] [1045] Access denied for user` | `.env` 的 `DB_PASSWORD` 打錯 | 先用 `mysql -u u123456789_todo -p u123456789_todo -e "SELECT 1;"` 確認密碼本身正確，再 `nano .env` 修正。密碼含 `#`、`$`、空白時用單引號包起來 |
| 在 `nano` 一行一行改 `.env` 很容易漏改或打錯 | — | 用 `sed -i` 一次改好密碼以外的值，`nano` 只改密碼（指令見 [deployment-roadmap.md](deployment-roadmap.md) 第 16 步） |
| 網站根目錄多出一個 `dist` 資料夾 | `scp` 執行了兩次：`~/todo-dist` 已存在時，`dist` 會被放進 `~/todo-dist/dist` | `rm -rf $WEBROOT/dist`；之後上傳前先 `rm -rf ~/todo-dist` |
| SSH 指令貼到本機 PowerShell，出現「找不到 `C:\Users\...\todo-dist` 路徑」 | 指令在錯的視窗執行 | 看命令列開頭，`[u123456789@...]$` 才是主機 |
| 網站一片空白，js、css 回傳的是 `index.html` | 從 Windows `scp` 上傳後，`assets` 資料夾權限變成 `drwx------`（700），Web Server 進不去，就當成檔案不存在 | `chmod 755 $WEBROOT/assets`，瀏覽器 `Ctrl+Shift+R` |
| 截圖時不小心露出 `APP_KEY` | — | `php artisan key:generate --force` 重新產生，影響只有所有人被登出。`APP_KEY` 不需要另外備份，遺失時同樣重新產生 |

## 正式環境的安全狀態（第 21～26 步驗收結果）

- 註冊 → 登入 → 新增／編輯／勾選／刪除 → 登出 全部正常，登出後打開 `/todos` 會被導回登入頁。
- `todoapi_session` Cookie 有 `Secure`、`HttpOnly`；`XSRF-TOKEN` 有 `Secure`（沒有 `HttpOnly` 是正常的，前端要讀它）。
- `APP_DEBUG=false`：錯誤只回簡單訊息，不顯示檔案路徑或 stack trace。
- `/.env`、`/.htaccess` 都回 `403`；`.env` 本來就不在網站根目錄裡。
- 登入、註冊有速率限制：同一個 IP 每分鐘 5 次，第 6 次回 `429`。
- 安全性 HTTP 標頭（2026-11-13 依安全檢測報告加入，設定在 `deploy/hostinger/.htaccess`）：HSTS、`X-Frame-Options: DENY`（防 clickjacking）、`X-Content-Type-Options: nosniff`、`Referrer-Policy`、只允許自身網域資源的 CSP，並移除 `X-Powered-By`。2026-11-13 上線後實測：五個標頭都有出現在頁面、靜態檔與 API 回應，`X-Powered-By` 已消失，Hostinger 原本的 `Content-Security-Policy: upgrade-insecure-requests` 也被我們的 CSP 取代（`upgrade-insecure-requests` 已包含在內）。`Server: LiteSpeed`、`platform: hostinger`、`panel: hpanel` 仍然存在，推測是 Hostinger 主機層加的，尚未嘗試移除。

## 相關文件

- [deployment-roadmap.md](deployment-roadmap.md)：從零部署的完整步驟、維運（備份還原、看 log）、常見錯誤速查
- [operations.md](operations.md)：資料庫還原 SOP、log 除錯紀錄（第二階段完成後撰寫）
- `docs/deployment-info.md`：主機 IP、SSH 指令、資料庫名稱、到期日等實際值（不進 git）
