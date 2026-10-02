<?php
// ============================================================
//  主帳號（老師）忘記密碼時用：把主帳號的帳號密碼重設成 admin／admin（在命令列執行）
//    php tools/reset-admin.php
//
//  - 像路由器的 Reset 鍵：只有能操作這台電腦的人才能執行
//  - 重設後第一次登入要先改密碼；原本已登入的裝置會被登出
//  - 副帳號（工讀生）不受影響，他們忘記密碼請老師在後台「帳號管理」重設
// ============================================================
if (PHP_SAPI !== 'cli') {
	exit;
}
require __DIR__ . '/../includes/init.php';

$pdo = db();
$owner = $pdo->query("SELECT id FROM admin_users WHERE role = 'owner' ORDER BY id LIMIT 1")->fetchColumn();
$hash = password_hash('admin', PASSWORD_DEFAULT);

// 帳號 admin 被副帳號用掉的話，主帳號會撞名，先請老師處理
$taken = $pdo->prepare("SELECT username FROM admin_users WHERE username = 'admin' AND id <> ?");
$taken->execute([(int) $owner]);
if ($taken->fetchColumn()) {
	fwrite(STDERR, "有副帳號正在使用帳號名稱 admin，請先在資料庫把那個副帳號改名，再執行一次。\n");
	exit(1);
}

if ($owner) {
	$pdo->prepare("UPDATE admin_users SET username = 'admin', password_hash = ?, must_change_password = 1, active = 1,
	               session_version = session_version + 1 WHERE id = ?")->execute([$hash, $owner]);
} else {
	// 主帳號不見了（例如被從資料庫刪掉）：重新建立一個
	$pdo->prepare("INSERT INTO admin_users (username, display_name, password_hash, role, must_change_password)
	               VALUES ('admin', '主帳號', ?, 'owner', 1)")->execute([$hash]);
}
$pdo->exec('DELETE FROM login_attempts'); // 清掉登入失敗紀錄，避免被暫停登入

echo "主帳號已重設：帳號 admin、密碼 admin。登入後台後請馬上設定新密碼。\n";
