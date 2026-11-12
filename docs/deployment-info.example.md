# 部署資訊紀錄

> 這是範本。使用方式：複製一份並命名為 `deployment-info.md`（同一個 `docs/` 資料夾），把值填在那份裡。`deployment-info.md` 已加入 `.gitignore`，不會進 git，**請自行另外備份**（換電腦或刪掉專案資料夾就會不見）。
>
> 即使不進 git，**密碼類的值（`APP_KEY`、`DB_PASSWORD`、Hostinger／GoDaddy／SSH 密碼）也不要寫在這裡**，它們只存在主機的 `.env` 或你自己的密碼管理工具裡。

部署過程中有幾個值會在後面的步驟反覆用到，拿到時就填在這裡並打勾，之後不用回頭翻找。步驟編號對應 [deployment-roadmap.md](deployment-roadmap.md)。

- [ ] 正式網址（第 2 步）：實際為 `__________`（例如 `todo.example.com`，不含 `https://`）
- [ ] GoDaddy 登入帳號（第 1 步）：實際為 `__________`（例如用 Google 帳號登入，就寫那個 Gmail）
- [ ] 網域到期日（第 1 步）：實際為 `__________`
  - [ ] GoDaddy 已關閉自動續約（第 1 步）：到 GoDaddy 網域頁「註冊設定」分頁關掉
- [ ] GoDaddy 網域聯絡人驗證信已點擊（第 1 步）
- [ ] Hostinger 登入帳號（第 3 步）：實際為 `__________`
- [ ] Hostinger 方案名稱與到期日（第 3 步）：實際為 `__________`（已確認有 SSH）
  - [ ] Hostinger 已關閉自動續約（第 4 步）：建好網站後到 hPanel 右上角人像圖示 → Billing → Subscriptions → Manage 關掉
- [ ] 子網域資料夾路徑（第 10 步）：實際為 `__________`（例如 `~/domains/example.com/public_html/todo`）
- [ ] 主機 IP（第 10 步）：實際為 `__________`
- [ ] MySQL 資料庫名稱／使用者名稱（第 12 步）：實際為 `__________`／`__________`（密碼不要寫在這裡）
- [ ] SSH 登入指令（第 13 步）：實際為 `ssh -p 65002 __________@__________`
- [ ] 主機 `.env` 的 `APP_KEY`、`DB_PASSWORD` 已備份到密碼管理工具（第 16 步，值不要寫在這裡）
