# 設醮 Tsio Design Plan（PHP 版）

長庚大學工業設計學系「設醮」計畫的形象網站。展覽、工作坊、講座等活動資訊，加上可以在後台即時發布的文章（報導）。

> 「設」是設計，「醮」是面對創作時的虔誠與反省。設醮是一場屬於設計者的精神儀式。

- 使用 **PHP + MariaDB**，在本機用 PHP 內建伺服器執行，不需要 Node 或任何打包流程
- 由原本的 React 版改寫而成，畫面與互動效果和原版一致
- 私人專案，僅供設醮團隊使用

---

## 本機開發

需要 PHP 8.2 以上、MariaDB（或 MySQL）。PHP 要開啟這幾個擴充功能：在 `php.ini` 把下面幾行前面的 `;` 拿掉。

| 擴充功能 | 用途 |
|---|---|
| `extension=pdo_mysql` | 連資料庫（沒開的話讀資料庫的頁面都會出錯） |
| `extension=gd` | 海報、內文圖片縮圖並轉成 webp |
| `extension=exif` | 手機直拍的照片自動轉正（沒開也能上傳，只是不轉正） |
| `extension=fileinfo` | 判斷上傳檔案的格式 |
| `extension=mbstring` | 中文字處理 |

另外會用到 `dom`（內文 HTML 過濾），Windows 版 PHP 內建就有，不用另外開。

```bash
# 1. 建立資料庫 tsio（編碼 utf8mb4_unicode_ci）與帳號，再複製設定檔填入帳密
cp includes/config.local.example.php includes/config.local.php

# 2. 建立資料表、匯入預設文章與初始主帳號
mariadb -u tsio -p tsio -e "source install/setup.sql"

# 3. 啟動 PHP 內建伺服器
php -S localhost:8000
```

打開 `http://localhost:8000`，後台在 `http://localhost:8000/admin/`（初始帳號、密碼都是 `admin`，第一次登入要先改密碼）。

**改了 Tailwind class 才需要重新編譯樣式**（只改文字、圖片、PHP 不用）：執行 `tools\build-css.bat`。第一次使用前依檔案開頭的說明下載 Tailwind 編譯工具（單一執行檔，不需要 Node）。

---

## 網站有哪些頁面

**一個資料夾 = 一個網址**，例如 `About/index.php` 就是 `/About/`。

| 網址 | 檔案 | 內容來源 |
|---|---|---|
| `/` | `index.php` | 首頁：主視覺輪播、公告、介紹、照片牆、展覽、工作坊、計畫、最新報導 |
| `/About/` | `About/index.php` | 關於設醮 |
| `/Plan/` | `Plan/index.php` | 計畫總覽 |
| `/Plan/ExhibitionList/` | `Plan/ExhibitionList/index.php` | 展覽總覽（資料：`data/activities.php`） |
| `/Plan/ExhibitionList/2024-E001/` | `Plan/ExhibitionList/2024-E001/index.php` | 「對話的對話」展覽內頁 |
| `/Plan/Workshop/` | `Plan/Workshop/index.php` | 工作坊介紹 |
| `/Plan/Workshop/List/`、`?id=…` | `Plan/Workshop/List/index.php` | 工作坊總覽；帶 `?id=` 時顯示單一工作坊（資料：`data/activities.php`） |
| `/Plan/Market/`、`/Plan/Lecture/`、`/Plan/Other/` | 各資料夾的 `index.php` | 市集、講座、戶外電影（內容目前還是範例資料） |
| `/Articles/` | `Articles/index.php` | 文章總覽（資料庫） |
| `/Articles/view.php?slug=…` | `Articles/view.php` | 文章內頁（資料庫） |
| `/Enroll/` | `Enroll/index.php` | 報名參與（資料：`data/activities.php`） |
| `/Contact/` | `Contact/index.php` | 聯絡我們 |
| `/admin/` | `admin/index.php` | 文章後台（帳號密碼登入） |
| `/admin/account.php` | `admin/account.php` | 我的帳號；主帳號另有帳號管理 |
| — | `404.php` | 找不到頁面 |

## 一個頁面檔長什麼樣子

```php
<?php
require __DIR__ . '/../includes/init.php';   // 每一頁的第一行：載入設定與共用函式

page_start([                                  // 輸出 <head>、導覽列
	'title'       => '關於設醮',               // 分享到 LINE / FB 時的標題
	'description' => '頁面描述',
]);
?>
<section class="…">                           <!-- 頁面內容就是一般的 HTML -->
	<?php title_block(['titleEN' => 'About', 'title' => '關於']); ?>
	<p class="bodyText">內文……</p>
</section>
<?php page_end(); ?>                          <!-- 輸出頁尾與 site.js -->
```

---

## 專案結構

| 檔案／資料夾 | 作用 |
|---|---|
| 各頁面資料夾 | 見上方「網站有哪些頁面」 |
| `includes/init.php` | **每一頁第一行載入**。檔內依序是：① 設定（網站名稱、資料庫、後台、上傳、時區）② 小工具（`e()` 跳脫、`url()`、`asset()`、`site_origin()`、`cover_image()`、`activities()`、`db()`）③ 文章的讀取與寫入、內文 HTML 白名單 `sanitize_html()` ④ 共用元件（標題、麵包屑、「查看更多」按鈕、各種卡片、圖片輪流淡入） |
| `includes/layout.php` | 網頁外框：`page_start()`（`<head>` 與導覽列）、`page_end()`（頁尾）、導覽列與頁尾共用的選單項目 |
| `includes/auth.php` | 後台才載入：帳號與權限（主帳號／副帳號）、登入、CSRF 防護、登入失敗次數限制、後台頁面外框、海報上傳（自動縮圖並轉成 webp） |
| `includes/config.local.example.php` | 機密設定範本；複製成 `config.local.php`（不進 git）填入資料庫密碼 |
| `data/activities.php` | 展覽、工作坊、報名活動三份清單（工作坊與報名目前是空的，檔內附格式範例） |
| `admin/index.php` | 文章後台：登入、發文／編輯、文章列表、刪除（只有主帳號）、內文圖片上傳；最上方是編輯器工具列的按鈕設定 |
| `admin/account.php` | 我的帳號（改顯示名稱、密碼）、第一次登入設定密碼；主帳號另有帳號管理（新增、停用／啟用、刪除副帳號，重設密碼） |
| `assets/css/tailwind.css` | **樣式原始檔**：Tailwind 設定、字級、動畫與間距參數、文章內文樣式（`.article-body`） |
| `assets/css/app.css` | 編譯後的樣式（網頁實際載入這支，不要手動修改） |
| `assets/js/site.js` | 前台互動效果：導覽列、捲動淡入、主視覺輪播、照片牆視差、報導輪播；最上方是動畫參數 |
| `assets/js/admin.js` | 後台的內文編輯器（所見即所得＋HTML 模式、插入圖片、貼上時清掉樣式） |
| `assets/vendor/swiper/` | 首頁報導輪播使用的 Swiper 套件 |
| `assets/vendor/flatpickr/` | 後台上架／下架時間的選擇器（flatpickr 4.6.13，含繁中語系），配色在 `tailwind.css` 最下方 |
| `assets/img/` | `banner/` 首頁主視覺、`photos/` 照片、`imgs/` 內容圖與預設封面、`dialoguesPhotos/` 參展人照片、`icons/` Logo 與圖示 |
| `assets/og/` | 分享到 LINE / FB 時的預覽圖（1200×630） |
| `uploads/` | 後台上傳的海報與內文圖片（不進 git） |
| `install/setup.sql` | **第一次安裝匯入這個**：資料表＋預設 8 篇文章＋初始主帳號 admin（重複匯入不會產生重複文章，也不會把改過的密碼變回 admin） |
| `tools/build-css.bat` | 重新編譯樣式 |
| `tools/reset-admin.php` | 主帳號忘記密碼時執行（`php tools/reset-admin.php`），帳號密碼變回 admin／admin |

## 資料放在哪

| 資料 | 存在哪 | 怎麼改 |
|---|---|---|
| 文章 | MariaDB 的 `articles` 資料表 | 後台 `/admin/`（見 [後台操作手冊](docs/後台操作手冊.md)） |
| 海報圖、內文圖片 | `uploads/` 資料夾 | 後台上傳（換掉或刪除文章時，沒有其他文章用到的圖會自動刪檔） |
| 展覽、工作坊、報名 | `data/activities.php`（PHP 陣列） | 直接改檔案 |
| 活動頁文案 | 各頁的 `index.php` | 直接改檔案 |
| 設定 | `includes/init.php` 最上方、`config.local.php` | 直接改檔案 |

資料表 `articles`：

| 欄位 | 說明 |
|---|---|
| `id` | 自動編號 |
| `slug` | 網址代碼。預設文章是 `ALUM-A00x`，新文章是 `a-{id}`；修改文章時不會變 |
| `title`、`description`、`date`、`location` | 標題、摘要、日期（純文字）、分類 |
| `cover` | 海報檔名（`uploads/` 裡），可為空 → 顯示預設封面 |
| `content` | 內文 HTML（後台編輯器產生，存檔前經過 `sanitize_html()` 白名單過濾） |
| `publish_at`、`unpublish_at` | 上架、下架時間，可為空（＝立即上架／不下架）。前台只列出 `ARTICLE_LIVE_SQL` 條件內的文章 |
| `created_at` | 建立時間。列表依「上架時間，沒有就用建立時間」排序（新 → 舊） |

文章另有 `created_by`、`updated_by`（建立者、最後修改者的帳號 id），預設文章是空的。

資料表 `admin_users`（後台帳號）：

| 欄位 | 說明 |
|---|---|
| `username`、`display_name` | 登入用的帳號、顯示名稱（文章清單標示作者用） |
| `password_hash` | 密碼的雜湊（`password_hash()`），看不到原本的密碼 |
| `role` | `owner` 主帳號（只有一個）／`editor` 副帳號 |
| `must_change_password` | 1 = 下次登入要先改密碼（初始帳號、臨時密碼） |
| `active` | 0 = 已停用，不能登入 |
| `session_version` | 改密碼、停用時加一，已登入的裝置就會被登出 |

另有 `login_attempts` 資料表，記錄後台登入失敗的 IP 與次數。

---

## 常見維護工作要改哪個檔案

| 要做的事 | 改哪裡 |
|---|---|
| 改某一頁的文字、圖片 | 該頁的 `index.php`（對照上方的頁面表） |
| 改導覽列選單、頁尾 | `includes/layout.php` |
| 隱藏／開放 Plan 底下的頁面（市集、講座、其他活動…） | `includes/layout.php` 的 `PROJECT_ITEMS`（導覽列下拉選單與頁尾共用）＋ `Plan/index.php` 的活動卡片 |
| 改某頁的分享標題、描述、預覽圖 | 該頁的 `page_start([...])` |
| 新增、修改、刪除文章，設定上架／下架時間 | 後台 `/admin/`，不用改程式 |
| 新增一個頁面 | 建立新資料夾＋`index.php`（複製一個現有頁面來改最快） |
| 新增一檔展覽 | `data/activities.php` 的 exhibitions 加一筆 ＋ 複製 `Plan/ExhibitionList/2024-E001/` 資料夾改名並修改內容 |
| 新增一個工作坊 | `data/activities.php` 的 workshops 加一筆 |
| 開放／關閉報名 | `data/activities.php` 的 enrolls |
| 改跑馬燈公告 | `index.php` 最上方的 `$marqueeMessage` |
| 換首頁主視覺 | 桌機：`index.php` 的 `$bannerLeft`、`$bannerRight`；手機／平板：直接換 `assets/img/banner/mobile`、`tablet` 裡的圖 |
| 調整動畫快慢 | CSS 動畫：`assets/css/tailwind.css` 最上方（改完要重新編譯）；JS 動畫：`assets/js/site.js` 最上方 |
| 改網站名稱 | `includes/init.php` 最上方的設定 |
| 改後台密碼、新增工讀生帳號 | 後台「帳號管理」（初始主帳號 admin／admin） |
| 主帳號忘記密碼 | 執行 `php tools/reset-admin.php` |

---

## 樣式與互動效果

- **樣式**：Tailwind CSS v4。編譯工具會掃描所有 `.php` 與 `assets/js/*.js` 找出用到的 class，所以**新增 class 後要重新編譯**。
- **設計參數**：動畫時長、緩動曲線、區塊間距是 `tailwind.css` 最上方的 CSS 變數；Tailwind class 以 `duration-[var(--motion-base)]`、`mt-[var(--title-gap)]` 讀取。
- **互動效果**：`assets/js/site.js`，每個效果都是「頁面上有對應元素才啟動」（例如首頁才有 `[data-banner]`），全站共用一支。JS 專用的參數（輪播間隔、視差幅度）在檔案最上方的 `MOTION`。
- **捲動淡入**：加上 `headline` class 的元素捲進畫面時由下往上淡入。只有 JavaScript 有執行時才會先隱藏，搜尋引擎看得到完整內容。

## 安全機制

| 風險 | 處理方式 |
|---|---|
| 網頁被插入惡意程式（XSS） | 輸出一律用 `e()` 跳脫；文章內文 HTML 存檔前用白名單過濾，只留排版標籤，`<script>`、`on…` 事件、`style`、`javascript:` 連結都會拿掉，`<iframe>` 只允許 YouTube／Google 地圖 |
| SQL 注入 | PDO 預處理語句，參數用 `?` 帶入 |
| 冒用登入狀態送出表單（CSRF） | 後台每個表單都帶 `csrf_field()`，送出時 `check_csrf()` 檢查 |
| 密碼外洩 | 只存 `password_hash()` 的雜湊；初始密碼與臨時密碼第一次登入就要改；新密碼至少 8 個字 |
| 暴力猜密碼 | 同一 IP 15 分鐘內失敗 5 次暫停登入；帳號不存在時照樣比對一次雜湊，回應時間不會透露帳號是否存在 |
| 權限 | 刪除文章、帳號管理在伺服器端檢查是否為主帳號（不只是畫面上隱藏按鈕） |
| 上傳可執行的檔案 | 以檔案內容判斷格式、只接受圖片、用 GD 重新編碼成 webp（縮到 1920 寬、去掉 EXIF）、改成隨機檔名 |
| 讀取設定檔、資料檔 | 內部 PHP 檔開頭 `defined('TSIO') \|\| exit;`，直接存取不輸出任何內容 |
| session 被竊用 | cookie 設 HttpOnly、SameSite=Strict，登入後更換 session id；改密碼、停用帳號時舊的登入全部失效 |

## 寫程式要注意的事

- **每一頁第一行都要 `require` `includes/init.php`**，`../` 的數量依資料夾深度調整。
- **輸出變數一律 `e()`**：`<?= e($x) ?>`。只有確定是自己寫的 HTML（例如 `descriptionHtml`），或已經過 `sanitize_html()` 的文章內文才直接輸出。
- **站內網址用 `url()`、靜態檔用 `asset()`**，不要寫死 `/About/`；`asset()` 會加上版本號，改了檔案瀏覽器才會重新下載。
- **新增的內部 PHP 檔**（不是頁面的）開頭加上 `defined('TSIO') || exit;`。
- **PHP 的 `//` 註解裡不能出現 `?>`**，PHP 會把它當成程式結束，後面的程式碼會直接顯示在網頁上。

## 其他文件

- [後台操作手冊](docs/後台操作手冊.md)：給發文的同學，登入、發文、修改、刪除文章（不需要程式知識）
