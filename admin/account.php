<?php
// ============================================================
//  我的帳號／帳號管理（/admin/account.php）
//  - 每個人：改自己的顯示名稱與密碼
//  - 拿到初始或臨時密碼的人：登入後只會看到「設定新密碼」，改完才能用後台
//  - 主帳號（老師）另外可以：新增副帳號、修改帳號與顯示名稱、重設密碼（發臨時密碼）、停用／啟用、刪除
// ============================================================
require __DIR__ . '/../includes/init.php';
require ROOT_DIR . '/includes/auth.php';
require_login();

$me = current_user();
$error = null;

// 主帳號操作的對象：只能是副帳號
function target_editor(): array
{
	$user = find_user((int) ($_POST['id'] ?? 0));
	if (!$user || $user['role'] !== 'editor') {
		throw new RuntimeException('找不到這個副帳號');
	}
	return $user;
}

// 臨時密碼只顯示一次：存在 session，下一個畫面顯示後就清掉
function show_temp_password(array $user, string $password, string $what): void
{
	$_SESSION['temp_password'] = ['name' => user_name($user), 'username' => $user['username'], 'password' => $password, 'what' => $what];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	check_csrf();
	$action = $_POST['action'] ?? '';
	$new = (string) ($_POST['new_password'] ?? '');
	$confirm = (string) ($_POST['confirm_password'] ?? '');
	try {
		if ($me['must_change_password'] && $action !== 'first_password') {
			throw new RuntimeException('請先設定新密碼');
		}
		switch ($action) {
			// ---- 第一次登入：設定新密碼 ----
			case 'first_password':
				if ($msg = check_new_password($new, $confirm)) {
					throw new RuntimeException($msg);
				}
				if (password_verify($new, $me['password_hash'])) {
					throw new RuntimeException('新密碼不能跟目前的密碼一樣');
				}
				change_own_password($new);
				flash('密碼已設定，歡迎使用後台！');
				redirect('admin/');

			// ---- 我的帳號：顯示名稱（主帳號也能改自己的帳號）----
			case 'profile':
				$username = $me['role'] === 'owner' ? trim((string) ($_POST['username'] ?? '')) : $me['username'];
				$display = trim((string) ($_POST['display_name'] ?? ''));
				if ($msg = check_username($username, $me['id'])) {
					throw new RuntimeException($msg);
				}
				update_user($me['id'], $username, mb_substr($display, 0, 50));
				flash('已更新！');
				break;

			// ---- 我的帳號：改密碼 ----
			case 'password':
				if (!password_verify((string) ($_POST['current_password'] ?? ''), $me['password_hash'])) {
					throw new RuntimeException('目前的密碼不正確');
				}
				if ($msg = check_new_password($new, $confirm)) {
					throw new RuntimeException($msg);
				}
				change_own_password($new);
				flash('密碼已更新！其他裝置上的登入都會被登出。');
				break;

			// ---- 以下只有主帳號 ----
			case 'add_user':
			case 'edit_user':
			case 'reset_user':
			case 'toggle_user':
			case 'delete_user':
				if (!is_owner()) {
					throw new RuntimeException('只有主帳號可以管理帳號');
				}
				if ($action === 'add_user') {
					$username = trim((string) ($_POST['username'] ?? ''));
					if ($msg = check_username($username)) {
						throw new RuntimeException($msg);
					}
					$password = create_user($username, mb_substr(trim((string) ($_POST['display_name'] ?? '')), 0, 50));
					$user = find_user((int) db()->lastInsertId());
					show_temp_password($user, $password, '新帳號已建立');
				} elseif ($action === 'edit_user') {
					$user = target_editor();
					$username = trim((string) ($_POST['username'] ?? ''));
					if ($msg = check_username($username, $user['id'])) {
						throw new RuntimeException($msg);
					}
					update_user($user['id'], $username, mb_substr(trim((string) ($_POST['display_name'] ?? '')), 0, 50));
					flash('已更新「' . $username . '」');
				} elseif ($action === 'reset_user') {
					$user = target_editor();
					show_temp_password($user, reset_user_password($user['id']), '密碼已重設');
				} elseif ($action === 'delete_user') {
					$user = target_editor();
					delete_user($user['id']);
					flash('已刪除「' . user_name($user) . '」');
				} else {
					$user = target_editor();
					set_user_active($user['id'], !$user['active']);
					flash(($user['active'] ? '已停用' : '已啟用') . '「' . user_name($user) . '」');
				}
				break;

			default:
				throw new RuntimeException('不認得的操作');
		}
		redirect('admin/account.php');
	} catch (RuntimeException $e) {
		$error = [$action, $e->getMessage()];
	}
}

$message = flash();
$temp = $_SESSION['temp_password'] ?? null;
unset($_SESSION['temp_password']);
$users = is_owner() ? list_users() : [];
$input = 'w-full border border-neutral-300 rounded-lg px-3 py-2';
$button = 'bg-neutral-800 text-white rounded-lg px-5 py-2 text-sm hover:bg-neutral-700';
$small = 'text-sm px-3 py-1.5 rounded-lg border border-neutral-300 hover:bg-neutral-50 whitespace-nowrap';
// 某個表單的錯誤訊息（錯誤只顯示在送出的那個表單下面）
$errorFor = fn(string ...$actions) => $error && in_array($error[0], $actions, true) ? '<p class="text-red-600 text-sm">' . e($error[1]) . '</p>' : '';

admin_page_start(is_owner() ? '帳號管理' : '我的帳號');
?>
<div class="min-h-screen">
	<?php admin_header('account'); ?>
	<main class="max-w-4xl mx-auto p-6 space-y-6">
		<?php if ($message): ?><p class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-2"><?= e($message) ?></p><?php endif; ?>

		<?php if ($me['must_change_password']): ?>
			<?php /* ================= 第一次登入：設定新密碼 ================= */ ?>
			<form method="post" class="max-w-md mx-auto bg-white rounded-xl shadow p-6 space-y-4">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="first_password">
				<h2 class="font-semibold text-lg">請先設定新密碼</h2>
				<p class="text-sm text-neutral-500">
					<?= $me['role'] === 'owner' && $me['username'] === 'admin' ? '目前是初始帳號密碼（admin／admin），' : '你目前用的是臨時密碼，' ?>請改成只有你知道的密碼，才能開始使用後台。
				</p>
				<input type="password" name="new_password" placeholder="新密碼（至少 8 個字）" autocomplete="new-password" minlength="8" required autofocus class="<?= $input ?>">
				<input type="password" name="confirm_password" placeholder="再輸入一次新密碼" autocomplete="new-password" required class="<?= $input ?>">
				<?= $errorFor('first_password') ?>
				<button class="<?= $button ?> w-full">設定密碼</button>
			</form>
		<?php else: ?>

			<?php if ($temp): ?>
				<?php /* 臨時密碼只出現這一次 */ ?>
				<div class="bg-amber-50 border border-amber-300 rounded-xl p-5 space-y-2">
					<p class="font-semibold text-amber-900"><?= e($temp['what']) ?>：<?= e($temp['name']) ?></p>
					<p class="text-sm text-amber-900">請把下面的帳號與臨時密碼交給他。他第一次登入時會被要求改成自己的密碼。<b>臨時密碼只會顯示這一次</b>，離開這頁就看不到了。</p>
					<div class="flex flex-wrap gap-x-8 gap-y-1 font-mono text-lg">
						<span>帳號：<?= e($temp['username']) ?></span>
						<span>臨時密碼：<span class="select-all bg-white px-2 rounded border border-amber-200"><?= e($temp['password']) ?></span></span>
					</div>
				</div>
			<?php endif; ?>

			<?php /* ================= 我的帳號 ================= */ ?>
			<div class="grid gap-6 md:grid-cols-2 md:items-start">
				<form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
					<?= csrf_field() ?>
					<input type="hidden" name="action" value="profile">
					<h2 class="font-semibold">我的帳號<span class="ml-2 text-xs text-neutral-400"><?= ROLE_NAMES[$me['role']] ?></span></h2>
					<label class="block space-y-1">
						<span class="text-sm text-neutral-600">帳號（登入用）</span>
						<?php if ($me['role'] === 'owner'): ?>
							<input name="username" value="<?= e($me['username']) ?>" autocomplete="username" required class="<?= $input ?>">
						<?php else: ?>
							<input value="<?= e($me['username']) ?>" disabled class="<?= $input ?> bg-neutral-50 text-neutral-500">
							<span class="block text-xs text-neutral-400">要改帳號請找老師</span>
						<?php endif; ?>
					</label>
					<label class="block space-y-1">
						<span class="text-sm text-neutral-600">顯示名稱（文章清單上標示作者用）</span>
						<input name="display_name" value="<?= e($me['display_name']) ?>" placeholder="例：王小明" maxlength="50" class="<?= $input ?>">
					</label>
					<?= $errorFor('profile') ?>
					<button class="<?= $button ?>">儲存</button>
				</form>

				<form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
					<?= csrf_field() ?>
					<input type="hidden" name="action" value="password">
					<h2 class="font-semibold">修改密碼</h2>
					<input type="password" name="current_password" placeholder="目前的密碼" autocomplete="current-password" required class="<?= $input ?>">
					<input type="password" name="new_password" placeholder="新密碼（至少 8 個字）" autocomplete="new-password" minlength="8" required class="<?= $input ?>">
					<input type="password" name="confirm_password" placeholder="再輸入一次新密碼" autocomplete="new-password" required class="<?= $input ?>">
					<p class="text-xs text-neutral-400">改了密碼之後，其他裝置上的登入都會被登出。</p>
					<?= $errorFor('password') ?>
					<button class="<?= $button ?>">更新密碼</button>
				</form>
			</div>

			<?php if (is_owner()): ?>
				<?php /* ================= 帳號管理（主帳號）================= */ ?>
				<section class="bg-white rounded-xl shadow p-6 space-y-5">
					<div>
						<h2 class="font-semibold">帳號管理</h2>
						<p class="text-sm text-neutral-500 mt-1">副帳號可以發文、修改所有文章，但不能刪除文章、不能管理帳號。工讀生忘記密碼時，在這裡按「重設密碼」給他一組臨時密碼；暫時不讓他登入用「停用」，確定不再使用才「刪除」。</p>
					</div>

					<ul class="divide-y divide-neutral-100 border-y border-neutral-100">
						<?php foreach ($users as $u): ?>
							<li class="py-3 space-y-2 <?= $u['active'] ? '' : 'opacity-60' ?>">
								<div class="flex flex-wrap items-center justify-between gap-3">
									<div class="min-w-0">
										<p class="font-medium truncate">
											<?= e(user_name($u)) ?>
											<span class="ml-1 text-xs px-2 py-0.5 rounded-full <?= $u['role'] === 'owner' ? 'bg-neutral-800 text-white' : 'bg-neutral-100 text-neutral-600' ?>"><?= ROLE_NAMES[$u['role']] ?></span>
											<?php if (!$u['active']): ?><span class="ml-1 text-xs px-2 py-0.5 rounded-full bg-neutral-200 text-neutral-600">已停用</span><?php endif; ?>
											<?php if ($u['must_change_password']): ?><span class="ml-1 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">待改密碼</span><?php endif; ?>
										</p>
										<p class="text-xs text-neutral-400">帳號 <?= e($u['username']) ?> · 最後登入 <?= $u['last_login_at'] ? e(substr($u['last_login_at'], 0, 16)) : '還沒登入過' ?></p>
									</div>
									<?php if ($u['role'] === 'editor'): ?>
										<div class="flex flex-wrap gap-2">
											<form method="post" data-confirm="要重設「<?= e(user_name($u)) ?>」的密碼嗎？他目前的登入會被登出，下次要用新的臨時密碼登入。">
												<?= csrf_field() ?>
												<input type="hidden" name="action" value="reset_user">
												<input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
												<button class="<?= $small ?>" <?= $u['active'] ? '' : 'disabled' ?>>重設密碼</button>
											</form>
											<form method="post" <?= $u['active'] ? 'data-confirm="要停用「' . e(user_name($u)) . '」嗎？停用後不能登入，之後可以再啟用；他寫過的文章不受影響。"' : '' ?>>
												<?= csrf_field() ?>
												<input type="hidden" name="action" value="toggle_user">
												<input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
												<button class="<?= $small ?>"><?= $u['active'] ? '停用' : '啟用' ?></button>
											</form>
											<form method="post" data-confirm="確定刪除「<?= e(user_name($u)) ?>」的帳號？此動作無法復原。&#10;&#10;他寫過的文章會保留，但作者會顯示為「已刪除的帳號」。只是暫時不讓他登入的話，請改用「停用」。">
												<?= csrf_field() ?>
												<input type="hidden" name="action" value="delete_user">
												<input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
												<button class="<?= $small ?> text-red-600">刪除</button>
											</form>
										</div>
									<?php endif; ?>
								</div>
								<?php if ($u['role'] === 'editor'): ?>
									<details <?= $error && $error[0] === 'edit_user' && (int) ($_POST['id'] ?? 0) === (int) $u['id'] ? 'open' : '' ?>>
										<summary class="text-sm text-neutral-500 cursor-pointer hover:text-neutral-800 w-fit">修改帳號／顯示名稱</summary>
										<form method="post" class="mt-2 flex flex-wrap items-end gap-3">
											<?= csrf_field() ?>
											<input type="hidden" name="action" value="edit_user">
											<input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
											<label class="space-y-1"><span class="block text-xs text-neutral-500">帳號</span><input name="username" value="<?= e($u['username']) ?>" required class="<?= $input ?> w-48"></label>
											<label class="space-y-1"><span class="block text-xs text-neutral-500">顯示名稱</span><input name="display_name" value="<?= e($u['display_name']) ?>" maxlength="50" class="<?= $input ?> w-48"></label>
											<button class="<?= $small ?>">儲存</button>
										</form>
										<?php if ((int) ($_POST['id'] ?? 0) === (int) $u['id']): ?><?= $errorFor('edit_user', 'reset_user', 'toggle_user', 'delete_user') ?><?php endif; ?>
									</details>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<form method="post" class="space-y-3">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="add_user">
						<h3 class="text-sm font-semibold">新增副帳號（工讀生）</h3>
						<div class="flex flex-wrap items-end gap-3">
							<label class="space-y-1"><span class="block text-xs text-neutral-500">帳號（英文、數字）</span><input name="username" value="<?= e($error && $error[0] === 'add_user' ? $_POST['username'] : '') ?>" placeholder="例：xiaoming" required class="<?= $input ?> w-48"></label>
							<label class="space-y-1"><span class="block text-xs text-neutral-500">顯示名稱</span><input name="display_name" value="<?= e($error && $error[0] === 'add_user' ? $_POST['display_name'] : '') ?>" placeholder="例：王小明" maxlength="50" class="<?= $input ?> w-48"></label>
							<button class="<?= $button ?>">新增</button>
						</div>
						<p class="text-xs text-neutral-400">新增後會顯示一組臨時密碼，交給工讀生；他第一次登入時要改成自己的密碼。</p>
						<?= $errorFor('add_user') ?>
					</form>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</main>
</div>
<?php admin_page_end(); ?>
