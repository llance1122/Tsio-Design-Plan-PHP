<?php
// ============================================================
//  文章後台（/admin/）
//  - 未登入：顯示登入表單（帳號＋密碼）
//  - 已登入：上方是發文／編輯表單，下方是已發布文章列表
//  - ?edit=文章編號：把該篇帶入表單編輯；?preview=文章編號：預覽
//  - 刪除文章只有主帳號可以；要先改密碼的帳號會被導到「我的帳號」
//  表單都送回這支（POST），依 action 決定要做什麼，完成後導回列表
// ============================================================
require __DIR__ . '/../includes/init.php';
require ROOT_DIR . '/includes/auth.php';

// 內文編輯器的工具列：每一組之間有分隔線。[種類, 值, 按鈕文字, 滑鼠停留說明]
//  cmd    瀏覽器內建的編輯指令（粗體、清單…）
//  block  把游標所在的段落換成這個標籤
//  action 由 admin.js 另外處理（連結、圖片）
const EDITOR_TOOLBAR = [
	[['cmd', 'bold', '<b>B</b>', '粗體（Ctrl+B）'], ['cmd', 'italic', '<i>I</i>', '斜體（Ctrl+I）'], ['cmd', 'underline', '<u>U</u>', '底線（Ctrl+U）'], ['cmd', 'strikeThrough', '<s>S</s>', '刪除線']],
	[['block', 'h3', '標題', '標題'], ['block', 'h4', '小標題', '小標題'], ['block', 'p', '內文', '一般段落'], ['block', 'blockquote', '引言', '引言']],
	[['cmd', 'insertUnorderedList', '• 清單', '項目清單'], ['cmd', 'insertOrderedList', '1. 清單', '編號清單']],
	[['action', 'link', '連結', '把選取的文字加上連結'], ['cmd', 'unlink', '取消連結', '取消連結'], ['action', 'image', '圖片', '在游標位置插入圖片'], ['cmd', 'insertHorizontalRule', '分隔線', '分隔線']],
	[['cmd', 'removeFormat', '清除格式', '清除選取文字的粗體、斜體等格式']],
];

// ---- 上架／下架時間 ----
// 表單的時間欄位（datetime-local 送來 2026-10-05T09:00）→ 資料庫格式；空白回傳 null，格式不對回傳 false
function form_datetime(string $value): string|false|null
{
	if (trim($value) === '') {
		return null;
	}
	$t = DateTime::createFromFormat('!Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('!Y-m-d\TH:i:s', $value);
	return $t ? $t->format('Y-m-d H:i:s') : false;
}

// 資料庫格式 → 時間欄位的值（2026-10-05T09:00）
function datetime_input(?string $value): string
{
	return $value ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
}

// 文章的上架狀態標籤，後面附上相關的時間
function status_badge(array $a): void
{
	$time = fn($v) => substr($v, 0, 16);
	[$label, $class, $note] = match (article_status($a)) {
		'scheduled' => ['排程中', 'bg-amber-100 text-amber-800', $time($a['publish_at']) . ' 上架'],
		'expired'   => ['已下架', 'bg-neutral-200 text-neutral-600', $time($a['unpublish_at']) . ' 下架'],
		default     => ['上架中', 'bg-green-100 text-green-800', $a['unpublish_at'] ? $time($a['unpublish_at']) . ' 下架' : ''],
	};
	echo '<span class="inline-flex items-center gap-1.5 text-xs"><span class="px-2 py-0.5 rounded-full ' . $class . '">' . $label . '</span>'
		. ($note ? '<span class="text-neutral-400">' . e($note) . '</span>' : '') . '</span>';
}

$error = null;

// ---------------- 處理表單送出 ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	check_csrf();
	$action = $_POST['action'] ?? '';

	if ($action === 'login') {
		$error = attempt_login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
		if ($error === null) {
			redirect('admin/');
		}
	} elseif (!is_logged_in()) {
		redirect('admin/');
	} elseif ($action === 'logout') {
		logout();
		redirect('admin/');
	} elseif (current_user()['must_change_password']) {
		redirect('admin/account.php');
	} elseif ($action === 'delete') {
		if (is_owner()) {
			delete_article((int) $_POST['id']);
			flash('已刪除');
		} else {
			flash('只有主帳號可以刪除文章');
		}
		redirect('admin/');
	} elseif ($action === 'upload_image') {
		// 編輯器插入內文圖片（admin.js 用 fetch 送來），回傳 JSON：{url} 或 {error}
		header('Content-Type: application/json; charset=utf-8');
		try {
			$name = save_uploaded_image($_FILES['image'] ?? []) ?? throw new RuntimeException('沒有選擇圖片');
			echo json_encode(['url' => url('uploads/' . $name)]);
		} catch (RuntimeException $e) {
			http_response_code(400);
			echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
		}
		exit;
	} elseif ($action === 'save') {
		$id = (int) ($_POST['id'] ?? 0);
		$fields = [
			'title'       => trim((string) ($_POST['title'] ?? '')),
			'description' => (string) ($_POST['description'] ?? ''),
			'date'        => (string) ($_POST['date'] ?? ''),
			'location'    => (string) ($_POST['location'] ?? ''),
			'publish_at'   => form_datetime((string) ($_POST['publish_at'] ?? '')),
			'unpublish_at' => form_datetime((string) ($_POST['unpublish_at'] ?? '')),
			'user_id'      => current_user()['id'],
		];
		$content = sanitize_html((string) ($_POST['content'] ?? ''));
		$existing = $id ? find_article('id', $id) : null;

		if ($fields['title'] === '') {
			$error = '缺少文章標題';
		} elseif ($fields['publish_at'] === false || $fields['unpublish_at'] === false) {
			$error = '上架／下架時間的格式不正確';
		} elseif ($fields['publish_at'] && $fields['unpublish_at'] && $fields['unpublish_at'] <= $fields['publish_at']) {
			$error = '下架時間要晚於上架時間';
		} elseif ($id && !$existing) {
			$error = '找不到這篇文章';
		} else {
			try {
				$newCover = save_uploaded_image($_FILES['cover'] ?? []);
				if ($existing) {
					// 有上傳新海報才換圖，否則沿用原本的（換掉的舊檔由 update_article 刪除）
					update_article($id, $fields, $content, $newCover ?? $existing['cover']);
					flash('已更新！');
				} else {
					create_article($fields, $content, $newCover);
					flash('發布成功！');
				}
				redirect('admin/');
			} catch (RuntimeException $e) {
				$error = $e->getMessage();
			}
		}
	}
}

// ---------------- 準備畫面資料 ----------------
$loggedIn = is_logged_in();
if ($loggedIn) {
	require_login(); // 要先改密碼的帳號導到「我的帳號」
}
if ($loggedIn && isset($_GET['preview'])) {
	// 預覽：用前台的文章頁輸出，不論上架狀態（未上架的文章只有登入的人看得到）
	$previewArticle = find_article('id', (int) $_GET['preview']);
	if (!$previewArticle) {
		not_found();
	}
	require ROOT_DIR . '/Articles/view.php';
	exit;
}
if ($loggedIn) {
	$articles = list_articles(null, true);
	// 文章清單顯示「誰建立／誰修改」用：帳號 id → 顯示名稱
	$names = array_column(array_map(fn($u) => ['id' => $u['id'], 'name' => user_name($u)], list_users()), 'name', 'id');
	$editing = isset($_GET['edit']) ? find_article('id', (int) $_GET['edit']) : null;
	// 送出失敗時保留剛才填的內容，不必重打
	$form = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save'
		? ['id' => (int) $_POST['id'], 'title' => $_POST['title'], 'description' => $_POST['description'], 'date' => $_POST['date'], 'location' => $_POST['location'], 'content' => $content,
		   'publish_at' => $fields['publish_at'] ?: null, 'unpublish_at' => $fields['unpublish_at'] ?: null, 'cover' => $editing['cover'] ?? null]
		: ($editing ?? ['id' => 0, 'title' => '', 'description' => '', 'date' => '', 'location' => '', 'content' => '', 'publish_at' => null, 'unpublish_at' => null, 'cover' => null]);
	if ($form['id'] && !$editing) {
		$editing = find_article('id', $form['id']);
	}
	$message = flash();
}
$input = 'w-full border border-neutral-300 rounded-lg px-3 py-2';
admin_page_start($loggedIn ? '文章管理' : '登入', $loggedIn ? ['vendor/flatpickr/flatpickr.min.css'] : []);
?>
<?php if (!$loggedIn): ?>
	<?php /* ================= 登入 ================= */ ?>
	<div class="min-h-screen flex items-center justify-center p-4">
		<form method="post" class="w-full max-w-sm bg-white rounded-xl shadow p-8 space-y-5">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="login">
			<h1 class="text-xl font-semibold text-center">設醮後台登入</h1>
			<label class="block space-y-1">
				<span class="text-sm text-neutral-600">帳號</span>
				<input name="username" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" autofocus required class="w-full border border-neutral-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-neutral-400">
			</label>
			<label class="block space-y-1">
				<span class="text-sm text-neutral-600">密碼</span>
				<input type="password" name="password" autocomplete="current-password" required class="w-full border border-neutral-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-neutral-400">
			</label>
			<?php if ($error): ?><p class="text-red-600 text-sm"><?= e($error) ?></p><?php endif; ?>
			<button class="w-full bg-neutral-800 text-white rounded-lg py-2 hover:bg-neutral-700 disabled:opacity-50">登入</button>
			<?php /* 忘記密碼：副帳號找主帳號重設；主帳號執行 tools/reset-admin.php */ ?>
			<details class="text-sm text-neutral-500">
				<summary class="cursor-pointer text-center hover:text-neutral-800">忘記密碼？</summary>
				<ul class="mt-3 space-y-2 list-disc pl-5">
					<li><b>工讀生（副帳號）</b>：請老師在「帳號管理」幫你重設，會拿到一組臨時密碼。</li>
					<li><b>老師（主帳號）</b>：請管理網站的人執行 <code class="text-xs bg-neutral-100 px-1 rounded">php tools/reset-admin.php</code>，帳號密碼會變回 admin／admin。</li>
				</ul>
			</details>
		</form>
	</div>
<?php else: ?>
	<div class="min-h-screen">
		<?php admin_header('articles'); ?>

		<?php /* 電腦（lg 以上）：左邊標題＋內文、右邊發布側欄；手機：由上往下一欄 */ ?>
		<main class="max-w-3xl lg:max-w-[1280px] mx-auto p-6 lg:px-8 space-y-6">
			<?php if ($message): ?><p class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-2"><?= e($message) ?></p><?php endif; ?>

			<?php /* ================= 發文／編輯表單 ================= */ ?>
			<form method="post" enctype="multipart/form-data" data-article-form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="save">
				<input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
				<?php /* 內文 HTML：admin.js 讀出來放進編輯器，送出前再從編輯器寫回這個欄位 */ ?>
				<textarea name="content" hidden data-content><?= e($form['content']) ?></textarea>

				<?php /* ---------- 左欄：標題與內文 ---------- */ ?>
				<div class="bg-white rounded-xl shadow p-6 space-y-5 min-w-0">
					<div class="flex items-center justify-between gap-4">
						<h2 class="font-semibold text-lg truncate"><?= $editing ? '編輯文章：' . e($editing['title']) : '發布新文章' ?></h2>
						<?php if ($editing): ?><a href="<?= e(url('admin/')) ?>" class="shrink-0 text-sm text-neutral-500 hover:text-neutral-800">取消編輯</a><?php endif; ?>
					</div>

					<label class="block space-y-1">
						<span class="text-sm text-neutral-600">標題 *</span>
						<input name="title" value="<?= e($form['title']) ?>" required class="<?= $input ?> text-lg">
					</label>

					<?php /* ---- 內文編輯器（操作在 admin.js）：上方工具列、所見即所得編輯區、HTML 原始碼框 ---- */ ?>
					<div class="space-y-2">
						<span class="text-sm text-neutral-600">內文</span>
						<div class="border border-neutral-300 rounded-lg">
							<?php /* sticky：長文章往下捲時工具列跟著，top 是上方後台標題列的高度 */ ?>
							<div data-toolbar class="sticky top-[57px] z-[5] flex flex-wrap items-center gap-1 border-b border-neutral-200 bg-neutral-50 rounded-t-lg px-2 py-1.5">
								<?php foreach (EDITOR_TOOLBAR as $g => $group): ?>
									<?php if ($g > 0): ?><span class="w-px h-5 bg-neutral-300 mx-1" aria-hidden="true"></span><?php endif; ?>
									<?php foreach ($group as [$kind, $value, $label, $title]): ?>
										<button type="button" data-<?= $kind ?>="<?= $value ?>" title="<?= $title ?>" class="text-sm px-2 py-1 rounded hover:bg-neutral-200 aria-pressed:bg-neutral-800 aria-pressed:text-white disabled:opacity-30 disabled:hover:bg-transparent"><?= $label ?></button>
									<?php endforeach; ?>
								<?php endforeach; ?>
								<button type="button" data-action="source" title="切換成 HTML 原始碼編輯" class="ml-auto text-sm px-2 py-1 rounded font-mono hover:bg-neutral-200 aria-pressed:bg-neutral-800 aria-pressed:text-white" aria-pressed="false">&lt;/&gt; HTML</button>
							</div>
							<div data-editor contenteditable="true" data-placeholder="從這裡開始寫內文……" class="article-body min-h-[320px] lg:min-h-[560px] px-5 py-4 lg:px-10 lg:py-8 focus:outline-none"></div>
							<textarea data-source hidden spellcheck="false" class="block w-full min-h-[320px] lg:min-h-[560px] px-5 py-4 font-mono text-[13px] leading-relaxed focus:outline-none"></textarea>
						</div>
						<p class="text-xs text-neutral-400">按「HTML」可以直接編輯原始碼。存檔時只會保留排版用的標籤（標題、段落、清單、連結、圖片、表格、YouTube／Google 地圖嵌入），其他會自動拿掉。</p>
						<input type="file" accept="image/*" hidden data-image-input>
					</div>
				</div>

				<?php /* ---------- 右欄：發布設定（電腦版固定在畫面右側；手機排在內文下方，按鈕放最後） ---------- */ ?>
				<aside class="bg-white rounded-xl shadow p-6 flex flex-col gap-5 lg:sticky lg:top-[81px] lg:max-h-[calc(100vh-105px)] lg:overflow-y-auto">
					<div class="order-last lg:order-first space-y-3">
						<?php if ($error): ?><p class="text-red-600 text-sm"><?= e($error) ?></p><?php endif; ?>
						<div class="flex items-center gap-3">
							<button class="flex-1 bg-neutral-800 text-white rounded-lg px-6 py-2 hover:bg-neutral-700 disabled:opacity-50" data-submit-label="<?= $editing ? '更新中…' : '發布中…' ?>"><?= $editing ? '更新文章' : '發布文章' ?></button>
							<?php if ($editing): ?>
								<a href="<?= e(url('admin/?preview=' . $editing['id'])) ?>" target="_blank" class="text-sm text-neutral-500 hover:text-neutral-800" title="另開分頁看前台的樣子（未上架的文章也能看）">預覽</a>
							<?php endif; ?>
						</div>
					</div>

					<?php /* 上架／下架時間：留空 = 立即上架、不下架 */ ?>
					<div class="space-y-3 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
						<?php if ($editing): ?><div class="text-sm text-neutral-600">目前狀態：<?php status_badge($editing); ?></div><?php endif; ?>
						<div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
							<?php /* 時間選擇器（flatpickr，設定在 admin.js）：送出的值是 2026-10-05T09:00，畫面顯示 2026-10-05 09:00 */ ?>
							<?php foreach (['publish_at' => '上架時間', 'unpublish_at' => '下架時間'] as $name => $label): ?>
								<div class="space-y-1" data-datetime-field>
									<div class="flex items-center justify-between">
										<label for="<?= $name ?>" class="text-sm text-neutral-600"><?= $label ?></label>
										<button type="button" data-datetime-clear class="text-xs text-neutral-400 hover:text-neutral-800">清除</button>
									</div>
									<input id="<?= $name ?>" name="<?= $name ?>" value="<?= e(datetime_input($form[$name])) ?>" placeholder="<?= $name === 'publish_at' ? '立即上架' : '不下架' ?>" autocomplete="off" data-datetime class="<?= $input ?> bg-white cursor-pointer">
								</div>
							<?php endforeach; ?>
						</div>
						<p class="text-xs text-neutral-400">留空：存檔後立即上架、不會自動下架。時間到了前台會自動顯示／隱藏，不用再回來操作。</p>
					</div>

					<label class="block space-y-1">
						<span class="text-sm text-neutral-600">摘要／描述</span>
						<textarea name="description" rows="3" class="<?= $input ?>"><?= e($form['description']) ?></textarea>
					</label>

					<div class="grid grid-cols-2 lg:grid-cols-1 gap-4">
						<label class="block space-y-1">
							<span class="text-sm text-neutral-600">日期</span>
							<input name="date" value="<?= e($form['date']) ?>" placeholder="例：2026 07 29" class="<?= $input ?>">
						</label>
						<label class="block space-y-1">
							<span class="text-sm text-neutral-600">分類／標籤</span>
							<input name="location" value="<?= e($form['location']) ?>" placeholder="例：校友特稿" class="<?= $input ?>">
						</label>
					</div>

					<div class="space-y-2">
						<span class="text-sm text-neutral-600">海報圖</span>
						<?php if ($editing && $editing['cover']): ?>
							<?php /* 編輯時顯示目前海報，讓使用者知道不換就會保留 */ ?>
							<img src="<?= e(article_cover($editing)) ?>" alt="目前海報" class="w-full aspect-[2/1] object-cover rounded border border-neutral-200">
							<p class="text-xs text-neutral-400">目前的海報，不選新檔就會保留</p>
						<?php endif; ?>
						<input type="file" name="cover" accept="image/*" class="w-full text-sm file:mr-3 file:rounded file:border-0 file:bg-neutral-800 file:text-white file:px-3 file:py-1.5">
					</div>
				</aside>
			</form>

			<?php /* ================= 已發布文章 ================= */ ?>
			<section class="bg-white rounded-xl shadow p-6">
				<h2 class="font-semibold mb-4">已發布文章（<?= count($articles) ?>）</h2>
				<?php if (!$articles): ?>
					<p class="text-sm text-neutral-400">目前沒有文章。</p>
				<?php else: ?>
					<ul class="divide-y divide-neutral-100">
						<?php foreach ($articles as $a): ?>
							<li class="py-3 flex items-center justify-between gap-4 <?= $editing && $editing['id'] == $a['id'] ? 'bg-amber-50 -mx-2 px-2 rounded' : '' ?>">
								<div class="min-w-0 lg:flex lg:items-baseline lg:gap-6">
									<?php /* 上架中的連到前台；排程中、已下架的前台看不到，改連到預覽 */ ?>
									<p class="truncate"><a href="<?= e(article_status($a) === 'live' ? article_url($a) : url('admin/?preview=' . $a['id'])) ?>" target="_blank" class="hover:underline"><?= e($a['title']) ?></a></p>
									<div class="flex flex-wrap items-center gap-x-3 gap-y-1 lg:shrink-0">
										<?php status_badge($a); ?>
										<span class="text-xs text-neutral-400"><?= e($a['date']) ?> · /<?= e($a['slug']) ?></span>
										<?php /* 作者：建立者，最後修改的人不同時再加上修改者；預設文章沒有紀錄 */ ?>
										<?php if ($a['created_by'] || $a['updated_by']): ?>
											<span class="text-xs text-neutral-400">
												<?= e($names[$a['created_by']] ?? '（已刪除的帳號）') ?> 建立<?php if ($a['updated_by'] && $a['updated_by'] !== $a['created_by']): ?>・<?= e($names[$a['updated_by']] ?? '（已刪除的帳號）') ?> 修改<?php endif; ?>
											</span>
										<?php endif; ?>
									</div>
								</div>
								<div class="flex items-center gap-3 shrink-0">
									<a href="<?= e(url('admin/?edit=' . $a['id'])) ?>" class="text-sm text-neutral-600 hover:underline">編輯</a>
									<?php if (is_owner()): /* 只有主帳號可以刪除 */ ?>
										<form method="post" data-confirm="確定刪除「<?= e($a['title']) ?>」？此動作無法復原。">
											<?= csrf_field() ?>
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
											<button class="text-sm text-red-600 hover:underline">刪除</button>
										</form>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</main>
	</div>
<?php endif; ?>
<?php admin_page_end($loggedIn ? ['vendor/flatpickr/flatpickr.min.js', 'vendor/flatpickr/zh-tw.js'] : []); ?>
