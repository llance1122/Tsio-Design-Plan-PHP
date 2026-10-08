<?php
// ============================================================
//  後台共用（只有 admin/ 底下的頁面會載入這支）
//  - 帳號存在資料表 admin_users：一個主帳號（owner，老師）＋多個副帳號（editor，工讀生）
//      主帳號：管理文章（含刪除）＋管理帳號（新增、停用副帳號，重設他們的密碼）
//      副帳號：新增、修改文章（不能刪除），只能改自己的密碼與顯示名稱
//  - 密碼只存 password_hash() 的雜湊；初始主帳號 admin／admin 與主帳號發的臨時密碼，
//    第一次登入都要先改密碼
//  - 主帳號忘記密碼：執行 php tools/reset-admin.php，帳號密碼變回 admin／admin
//  - 登入狀態存在 PHP session，關閉瀏覽器就會登出；改密碼、被停用時已登入的裝置會登出
//  - 同一 IP 在時間窗內失敗太多次會暫停登入（紀錄在資料表 login_attempts）
//  - 所有送出的表單都要帶 CSRF token，防止其他網站冒用登入狀態
//  - 後台頁面的外框、圖片上傳也在這支
// ============================================================
defined('TSIO') || exit;

session_set_cookie_params([
	'lifetime' => 0,          // 關閉瀏覽器即失效
	'path'     => '/admin',   // 不加結尾斜線：網址打 /admin 或 /admin/ 都要帶到同一個 session
	'httponly' => true,       // JS 讀不到 session cookie
	'samesite' => 'Strict',
]);
// 專用的 cookie 名稱：瀏覽器裡若殘留舊路徑（/admin/）的 PHPSESSID，會被直接忽略，不會蓋掉登入狀態
session_name('tsio_admin');
session_start();

// ---- 目前登入的帳號 ----
// session 記住帳號 id 與登入當時的 session_version；帳號被停用或改了密碼（版本加一）就視為登出
function current_user(bool $fresh = false): ?array
{
	static $user = false;
	if ($user === false || $fresh) {
		$user = null;
		if (isset($_SESSION['uid'], $_SESSION['ver'])) {
			$row = find_user((int) $_SESSION['uid']);
			if ($row && $row['active'] && (int) $row['session_version'] === $_SESSION['ver']) {
				$user = $row;
			}
		}
	}
	return $user;
}

function is_logged_in(): bool
{
	return current_user() !== null;
}

function is_owner(): bool
{
	return (current_user()['role'] ?? '') === 'owner';
}

// 後台頁面開頭呼叫：沒登入就回登入頁；要先改密碼的人只能待在「我的帳號」
function require_login(): void
{
	if (!is_logged_in()) {
		redirect('admin/');
	}
	if (current_user()['must_change_password'] && basename($_SERVER['SCRIPT_NAME']) !== 'account.php') {
		redirect('admin/account.php');
	}
}

// 畫面上顯示的名字：有顯示名稱用顯示名稱，沒有就用帳號
function user_name(?array $user): string
{
	return $user ? ($user['display_name'] ?: $user['username']) : '';
}

const ROLE_NAMES = ['owner' => '主帳號', 'editor' => '副帳號'];

// ---- CSRF ----
function csrf_token(): string
{
	if (empty($_SESSION['csrf'])) {
		$_SESSION['csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf'];
}

function csrf_field(): string
{
	return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
	if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
		http_response_code(400);
		exit('表單已過期，請回上一頁重新整理後再試一次。');
	}
}

// ---- 登入與失敗次數限制 ----
// 回傳 null = 成功；否則回傳錯誤訊息
function attempt_login(string $username, string $password): ?string
{
	$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$max = (int) config('login_max_fails');
	$window = (int) config('login_window_minutes');
	$pdo = db();

	$stmt = $pdo->prepare('SELECT fail_count, first_failed FROM login_attempts WHERE ip = ?');
	$stmt->execute([$ip]);
	$rec = $stmt->fetch();
	if ($rec && strtotime($rec['first_failed']) < time() - $window * 60) {
		$pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]); // 時間窗已過，重新計算
		$rec = null;
	}
	if ($rec && $rec['fail_count'] >= $max) {
		$minutes = (int) ceil((strtotime($rec['first_failed']) + $window * 60 - time()) / 60);
		return "嘗試次數過多，請 $minutes 分鐘後再試";
	}

	$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
	$stmt->execute([$username]);
	$user = $stmt->fetch();
	// 帳號不存在也拿一個真的雜湊照樣比對一次，回應時間才不會透露「這個帳號存在」
	$dummy = '$2y$10$YwKv6UKmrueHgeTATJpo4O5P0YlCRqRCcuYTr.6OENdonWXcSUqQ2';
	if (!password_verify($password, $user['password_hash'] ?? $dummy) || !$user) {
		// 時間由 PHP 產生（不用資料庫的 NOW()），避免兩邊時區設定不同算錯
		$pdo->prepare('INSERT INTO login_attempts (ip, fail_count, first_failed) VALUES (?, 1, ?)
		               ON DUPLICATE KEY UPDATE fail_count = fail_count + 1')->execute([$ip, date('Y-m-d H:i:s')]);
		return '帳號或密碼錯誤';
	}
	if (!$user['active']) {
		return '這個帳號已停用，請聯絡主帳號（老師）';
	}

	$pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
	$pdo->prepare('UPDATE admin_users SET last_login_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $user['id']]);
	session_regenerate_id(true); // 登入後換新的 session id，防止 session 固定攻擊
	$_SESSION['uid'] = (int) $user['id'];
	$_SESSION['ver'] = (int) $user['session_version'];
	return null;
}

function logout(): void
{
	$_SESSION = [];
	session_destroy();
}

// ---- 一次性訊息（例如「發布成功！」），顯示一次就清掉 ----
function flash(?string $message = null): ?string
{
	if ($message !== null) {
		$_SESSION['flash'] = $message;
		return null;
	}
	$m = $_SESSION['flash'] ?? null;
	unset($_SESSION['flash']);
	return $m;
}

// ---- 帳號資料 ----
function find_user(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ?');
	$stmt->execute([$id]);
	return $stmt->fetch() ?: null;
}

// 全部帳號：主帳號在最上面，其餘依建立順序
function list_users(): array
{
	return db()->query("SELECT * FROM admin_users ORDER BY role = 'owner' DESC, id")->fetchAll();
}

// 臨時密碼：10 個字，去掉容易看錯的 0/O、1/l/I
function temp_password(): string
{
	$chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
	$out = '';
	for ($i = 0; $i < 10; $i++) {
		$out .= $chars[random_int(0, strlen($chars) - 1)];
	}
	return $out;
}

// 帳號規則：英文字母、數字、底線、點、減號，3～50 個字。回傳 null = 合格
function check_username(string $username, int $exceptId = 0): ?string
{
	if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
		return '帳號只能用英文字母、數字、底線、點、減號，3～50 個字';
	}
	$stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ? AND id <> ?');
	$stmt->execute([$username, $exceptId]);
	return $stmt->fetchColumn() ? '這個帳號已經有人用了' : null;
}

// 新密碼的規則；回傳 null = 合格
function check_new_password(string $password, string $confirm): ?string
{
	if (mb_strlen($password) < 8) {
		return '新密碼至少要 8 個字';
	}
	return $password === $confirm ? null : '兩次輸入的新密碼不一樣';
}

// 主帳號新增副帳號；回傳臨時密碼（只會顯示這一次）
function create_user(string $username, string $displayName): string
{
	$password = temp_password();
	db()->prepare("INSERT INTO admin_users (username, display_name, password_hash, role, must_change_password) VALUES (?, ?, ?, 'editor', 1)")
		->execute([$username, $displayName, password_hash($password, PASSWORD_DEFAULT)]);
	return $password;
}

// 主帳號修改帳號、顯示名稱
function update_user(int $id, string $username, string $displayName): void
{
	db()->prepare('UPDATE admin_users SET username = ?, display_name = ? WHERE id = ?')->execute([$username, $displayName, $id]);
}

// 主帳號幫副帳號重設密碼：發臨時密碼、下次登入要改，已登入的裝置會登出
function reset_user_password(int $id): string
{
	$password = temp_password();
	db()->prepare('UPDATE admin_users SET password_hash = ?, must_change_password = 1, session_version = session_version + 1 WHERE id = ?')
		->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
	return $password;
}

// 停用／啟用副帳號；停用時已登入的裝置會登出
function set_user_active(int $id, bool $active): void
{
	db()->prepare('UPDATE admin_users SET active = ?, session_version = session_version + ? WHERE id = ?')
		->execute([(int) $active, $active ? 0 : 1, $id]);
}

// 刪除副帳號：他已登入的裝置會登出；他寫過的文章保留，作者顯示為「已刪除的帳號」
function delete_user(int $id): void
{
	db()->prepare("DELETE FROM admin_users WHERE id = ? AND role = 'editor'")->execute([$id]);
}

// 改自己的密碼：其他裝置登出，目前這個裝置保持登入
function change_own_password(string $newPassword): void
{
	$id = current_user()['id'];
	db()->prepare('UPDATE admin_users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?')
		->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
	$_SESSION['ver'] = (int) find_user($id)['session_version'];
	current_user(true);
}

// ---- 後台頁面外框 ----
// 每個後台頁面：admin_page_start('標題') → 內容 → admin_page_end()
// $styles／$scripts：這一頁另外要載入的檔案（assets/ 底下的路徑），例如文章頁的時間選擇器
function admin_page_start(string $title, array $styles = []): void
{
	?>
<!doctype html>
<html lang="zh-Hant">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex">
	<title><?= e($title) ?>｜<?= e(config('site_name')) ?></title>
	<?php /* 分享到 LINE／FB 時的預覽：固定顯示後台的介紹，不然它們會抓頁面上的文字（例如登入頁的忘記密碼說明） */ ?>
	<?php $shareText = config('site_name') . ' 網站後台，供設醮團隊發布與管理文章，需登入帳號使用。'; ?>
	<meta name="description" content="<?= e($shareText) ?>">
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="<?= e(config('site_name')) ?>">
	<meta property="og:title" content="設醮後台｜<?= e(config('site_name')) ?>">
	<meta property="og:description" content="<?= e($shareText) ?>">
	<meta property="og:url" content="<?= e(site_origin() . url('admin/')) ?>">
	<meta property="og:image" content="<?= e(site_origin() . url('assets/og/default.jpg')) ?>">
	<meta property="og:image:width" content="1200">
	<meta property="og:image:height" content="630">
	<link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=">
	<?php /* 套件的樣式放在 app.css 前面，app.css 裡的配色才蓋得過去 */ ?>
	<?php foreach ($styles as $css): ?>
	<link rel="stylesheet" href="<?= e(asset($css)) ?>">
	<?php endforeach; ?>
	<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-neutral-100">
	<?php
}

function admin_page_end(array $scripts = []): void
{
	?>
	<?php foreach ($scripts as $js): ?>
	<script src="<?= e(asset($js)) ?>"></script>
	<?php endforeach; ?>
	<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
	<?php
}

// 登入後的上方標題列；$current = 'articles' 或 'account'
function admin_header(string $current): void
{
	$user = current_user();
	$link = fn(string $key, string $href, string $label) => '<a href="' . e(url($href)) . '" class="text-sm whitespace-nowrap '
		. ($key === $current ? 'text-neutral-900 font-semibold' : 'text-neutral-500 hover:text-neutral-800') . '">' . $label . '</a>';
	?>
	<header class="bg-white border-b border-neutral-200 px-6 py-4 flex justify-between items-center gap-4 sticky top-0 z-10">
		<div class="flex items-center gap-6 min-w-0">
			<h1 class="font-semibold shrink-0">設醮後台</h1>
			<?php if (!$user['must_change_password']): ?>
				<nav class="flex gap-4"><?= $link('articles', 'admin/', '文章管理') ?><?= $link('account', 'admin/account.php', is_owner() ? '帳號管理' : '我的帳號') ?></nav>
			<?php endif; ?>
		</div>
		<div class="flex items-center gap-4 min-w-0">
			<span class="text-sm text-neutral-500 truncate"><?= e(user_name($user)) ?>（<?= ROLE_NAMES[$user['role']] ?>）</span>
			<form method="post" action="<?= e(url('admin/')) ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="logout">
				<button class="text-sm text-neutral-500 hover:text-neutral-800 whitespace-nowrap">登出</button>
			</form>
		</div>
	</header>
	<?php
}

// ---- 圖片上傳（文章海報、內文圖片共用）----
// 回傳存好的檔名；沒選檔案回傳 null；格式或大小不符丟出例外
function save_uploaded_image(array $file): ?string
{
	if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
		return null;
	}
	$maxMb = (int) config('upload_max_mb');
	if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > $maxMb * 1024 * 1024) {
		throw new RuntimeException("圖片太大，上限 {$maxMb}MB");
	}
	if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
		throw new RuntimeException('上傳失敗，請再試一次');
	}
	// 用檔案實際內容判斷格式（不信任副檔名）
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
	if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'], true)) {
		throw new RuntimeException('只接受圖片檔（jpg / png / webp / avif / gif）');
	}
	$name = time() . '-' . bin2hex(random_bytes(8)) . '.webp';
	convert_to_webp($file['tmp_name'], $mime, ROOT_DIR . '/uploads/' . $name);
	return $name;
}

// 把圖片縮到最大寬度（config 的 upload_max_width）並存成 webp。
// 重新編碼也會去掉照片裡的 EXIF（拍攝地點等個資）
function convert_to_webp(string $src, string $mime, string $dest): void
{
	// 大張照片解碼後很吃記憶體（6000×4000 約 100MB），預設的 128M 不夠
	ini_set('memory_limit', '512M');

	$image = match ($mime) {
		'image/jpeg' => @imagecreatefromjpeg($src),
		'image/png'  => @imagecreatefrompng($src),
		'image/webp' => @imagecreatefromwebp($src),
		'image/avif' => @imagecreatefromavif($src),
		'image/gif'  => @imagecreatefromgif($src),  // 動畫 GIF 只會留下第一格
	};
	if (!$image) {
		throw new RuntimeException('圖片無法讀取，檔案可能已損毀');
	}

	// 手機直拍的照片靠 EXIF 記錄方向，重新編碼前先轉正（需要 PHP 的 exif 擴充，沒開就略過）
	if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
		$orientation = @exif_read_data($src)['Orientation'] ?? 1;
		$image = match ($orientation) {
			3       => imagerotate($image, 180, 0),
			6       => imagerotate($image, -90, 0),
			8       => imagerotate($image, 90, 0),
			default => $image,
		};
	}

	// GIF、8 位元 PNG 是調色盤格式，要先轉成全彩，webp 才能保留透明背景
	imagepalettetotruecolor($image);
	$maxWidth = (int) config('upload_max_width');
	if (imagesx($image) > $maxWidth) {
		$image = imagescale($image, $maxWidth, -1, IMG_BICUBIC);
	}
	imagealphablending($image, false);
	imagesavealpha($image, true);

	if (!imagewebp($image, $dest, (int) config('upload_webp_quality'))) {
		throw new RuntimeException('圖片存檔失敗，請確認 uploads 資料夾可以寫入');
	}
}
