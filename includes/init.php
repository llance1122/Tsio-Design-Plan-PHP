<?php
// ============================================================
//  每一頁的第一行都會載入這支：設定、共用函式、文章、共用元件
//  用法（依頁面所在的資料夾深度調整 ../ 的數量）：
//    require __DIR__ . '/../includes/init.php';
//
//  檔案內依序是：1. 設定  2. 小工具  3. 文章  4. 共用元件
//  導覽列與頁尾在 layout.php，後台登入在 auth.php
//  （注意：PHP 的 // 註解裡不能出現「? >」連在一起，會被當成結束標記）
// ============================================================
define('TSIO', true);
define('ROOT_DIR', dirname(__DIR__));

// ############################################################
//  1. 設定（預設值，會進版控）
//
//  ⚠️ 密碼、資料庫帳號等機密不要改在這裡！
//  請複製 config.local.example.php 成 config.local.php 再修改，
//  config.local.php 不會進 git，裡面的值會覆蓋這裡的預設值。
// ############################################################

$config = [
	// ---- 網站基本資訊 ----
	'site_name'   => '設醮 Tsio Design Plan',
	'description' => '「設醮」— 在建構之中成就一場創造。設計系的展覽、工作坊與活動，以作品為供品、以創作為祈願。',

	// ---- 資料庫（MariaDB / MySQL）----
	'db' => [
		'host'    => '127.0.0.1',
		'port'    => 3306,
		'name'    => 'tsio',
		'user'    => 'tsio',
		'pass'    => '',
	],

	// ---- 後台 ----
	// 後台帳號密碼存在資料庫（初始主帳號 admin／admin），在後台「帳號管理」修改
	'login_max_fails' => 5,      // 同一 IP 在時間窗內最多失敗幾次
	'login_window_minutes' => 15,

	// ---- 上傳 ----
	'upload_max_mb' => 8,
	'upload_max_width' => 1920,    // 海報寬度超過就等比縮小
	'upload_webp_quality' => 82,   // 轉 webp 的品質（0–100），82 肉眼幾乎看不出差別

	// 時區（登入失敗次數限制的計時用）
	'timezone' => 'Asia/Taipei',

	// 顯示 PHP 錯誤（除錯時在 config.local.php 打開）
	'debug' => false,
];

// config.local.php（不進 git）的值會覆蓋上面的預設值
if (is_file(__DIR__ . '/config.local.php')) {
	$config = array_replace_recursive($config, require __DIR__ . '/config.local.php');
}
$GLOBALS['CONFIG'] = $config;
date_default_timezone_set($config['timezone']);

if ($config['debug']) {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', '0');
}

// ############################################################
//  2. 小工具：網址、跳脫、活動資料、資料庫連線
// ############################################################

// 讀設定值，例如 config('site_name')、config('db')['name']
function config(string $key)
{
	return $GLOBALS['CONFIG'][$key] ?? null;
}

// 輸出到 HTML 前一律跳脫，避免 XSS。樣板裡寫：短標籤 echo e($x)
function e($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// 站內網址：url('About/') → "/About/"
function url(string $path = ''): string
{
	return '/' . ltrim($path, '/');
}

// 目前網站的網域，例如 "http://localhost:8000"；分享預覽（LINE / FB）需要完整網址
function site_origin(): string
{
	$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// 靜態檔網址（assets/ 底下），加上修改時間當版本號，改了檔案瀏覽器就會重新下載
function asset(string $path): string
{
	$file = ROOT_DIR . '/assets/' . ltrim($path, '/');
	$version = is_file($file) ? '?v=' . filemtime($file) : '';
	return url('assets/' . ltrim($path, '/')) . $version;
}

// 目前頁面相對於網站根目錄的路徑，例如 "/Plan/Workshop/List"（不含結尾斜線與 index.php）
function current_path(): string
{
	$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
	$path = preg_replace('#/(index|view)\.php$#', '', $path);
	return '/' . trim($path, '/');
}

// 導覽列用：目前頁面是否在某個區塊底下（例如 /Plan 包含 /Plan/Workshop）
function is_current(string $section): bool
{
	$path = current_path();
	return $path === $section || str_starts_with($path, rtrim($section, '/') . '/');
}

// 回傳 404 狀態碼並顯示「找不到頁面」，之後不再執行
function not_found(): void
{
	http_response_code(404);
	require ROOT_DIR . '/404.php';
	exit;
}

function redirect(string $path): void
{
	header('Location: ' . url($path));
	exit;
}

// 內容圖（assets/img/imgs/ 的檔名）；沒給就用預設封面
function cover_image(?string $file): string
{
	return asset('img/imgs/' . ($file ?: 'default-cover.webp'));
}

// 活動資料（data/activities.php）：activities('exhibitions')、'workshops'、'enrolls'
function activities(string $type): array
{
	static $data = null;
	$data ??= require ROOT_DIR . '/data/activities.php';
	return $data[$type] ?? [];
}

// ---- 資料庫連線（MariaDB / MySQL，使用 PDO）----
// 第一次呼叫 db() 時才連線；沒有用到資料庫的頁面不會連
function db(): PDO
{
	static $pdo = null;
	if ($pdo) {
		return $pdo;
	}
	$c = config('db');
	$dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
	$pdo = new PDO($dsn, $c['user'], $c['pass'], [
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		// 用資料庫真正的預處理語句，搭配 ? 參數可防 SQL 注入
		PDO::ATTR_EMULATE_PREPARES   => false,
	]);
	return $pdo;
}

// ############################################################
//  3. 文章：讀取（前台、後台共用）與寫入（後台）
// ############################################################

// ---- 上架／下架時間 ----
// publish_at 留空 = 存檔後立即上架；unpublish_at 留空 = 不下架。
// 「現在」由 PHP 產生再帶進 SQL，不用資料庫的 NOW()，避免兩邊時區設定不同算錯
const ARTICLE_LIVE_SQL = '(publish_at IS NULL OR publish_at <= ?) AND (unpublish_at IS NULL OR unpublish_at > ?)';

function now_sql(): string
{
	return date('Y-m-d H:i:s');
}

// 'live' 上架中 / 'scheduled' 排程中（還沒到上架時間）/ 'expired' 已下架
function article_status(array $article): string
{
	$now = now_sql();
	if ($article['publish_at'] && $article['publish_at'] > $now) {
		return 'scheduled';
	}
	return $article['unpublish_at'] && $article['unpublish_at'] <= $now ? 'expired' : 'live';
}

// 列表：不含內文，依上架時間新 → 舊。前台只列上架中的文章；後台傳 $includeHidden = true 列出全部
function list_articles(?int $limit = null, bool $includeHidden = false): array
{
	$where = $includeHidden ? '' : 'WHERE ' . ARTICLE_LIVE_SQL;
	$sql = "SELECT id, slug, title, description, date, location, cover, publish_at, unpublish_at, created_by, updated_by, created_at
	        FROM articles $where ORDER BY COALESCE(publish_at, created_at) DESC, id DESC";
	if ($limit) {
		$sql .= ' LIMIT ' . (int) $limit;
	}
	$stmt = db()->prepare($sql);
	$stmt->execute($includeHidden ? [] : [now_sql(), now_sql()]);
	return $stmt->fetchAll();
}

// 單篇（含內文 HTML）。找不到回傳 null；$liveOnly = true 時，沒上架的文章也當作找不到（前台用）
function find_article(string $column, $value, bool $liveOnly = false): ?array
{
	$column = $column === 'id' ? 'id' : 'slug'; // 只允許這兩個欄位
	$sql = "SELECT * FROM articles WHERE $column = ?" . ($liveOnly ? ' AND ' . ARTICLE_LIVE_SQL : '');
	$stmt = db()->prepare($sql);
	$stmt->execute($liveOnly ? [$value, now_sql(), now_sql()] : [$value]);
	return $stmt->fetch() ?: null;
}

// 文章內頁網址
function article_url(array $article): string
{
	return url('Articles/view.php?slug=' . rawurlencode($article['slug']));
}

// 封面：有上傳海報用海報，沒有就用預設圖（列表卡片與內頁大圖用同一張）
function article_cover(array $article): string
{
	return $article['cover']
		? url('uploads/' . rawurlencode($article['cover']))
		: cover_image(null);
}

// ---- 內文 HTML 白名單 ----
// 後台編輯器（或 HTML 模式）送來的內文，存進資料庫前一律經過 sanitize_html()：
// 只留下排版用的標籤與屬性，script、事件屬性（onclick…）、style 等全部拿掉，
// 前台才能直接輸出內文而不用擔心被插入惡意程式或把版面弄壞。
const HTML_ALLOWED = [            // 標籤 => 可保留的屬性
	'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [],
	'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
	'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
	'a' => ['href', 'title'],
	'img' => ['src', 'alt'],
	'figure' => [], 'figcaption' => [],
	'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
	'iframe' => ['src', 'title', 'allowfullscreen'],
];
const HTML_RENAME = ['div' => 'p', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];
// 連同內容整個拿掉的標籤（其他不在白名單的標籤，例如 span、font，只拿掉標籤、保留文字）
const HTML_DROP = ['script', 'style', 'noscript', 'template', 'object', 'embed', 'form', 'input', 'button',
	'select', 'textarea', 'head', 'title', 'meta', 'link', 'svg', 'math', 'frame', 'frameset'];
// 允許嵌入的網址：YouTube、Google 地圖
const HTML_IFRAME_SRC = '#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/|^https://(www\.|maps\.)?google\.com/maps(/embed|\?)#i';

function sanitize_html(string $html): string
{
	if (trim($html) === '') {
		return '';
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors(true); // 不完整的 HTML 也照樣解析，不顯示警告
	// 開頭的 xml encoding 宣告讓 DOMDocument 用 UTF-8 讀中文
	$doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	libxml_clear_errors();
	$root = $doc->getElementsByTagName('div')->item(0);
	sanitize_children($root);

	// 最外層沒有包在段落裡的文字（含粗體、連結等行內標籤）補上 <p>，前台才會套到內文樣式
	$run = [];
	$wrap = function () use (&$run, $doc, $root) {
		$blank = fn($n) => $n instanceof DOMText && trim($n->textContent) === '';
		while ($run && $blank($run[0])) {        // 頭尾的換行留在段落外面
			array_shift($run);
		}
		while ($run && $blank(end($run))) {
			array_pop($run);
		}
		if ($run && trim(implode('', array_map(fn($n) => $n->textContent, $run))) !== '') {
			$p = $root->insertBefore($doc->createElement('p'), $run[0]);
			foreach ($run as $n) {
				$p->appendChild($n);
			}
		}
		$run = [];
	};
	foreach (iterator_to_array($root->childNodes) as $node) {
		if ($node instanceof DOMText || in_array($node->nodeName, ['strong', 'b', 'em', 'i', 'u', 's', 'a', 'br'], true)) {
			$run[] = $node;
		} else {
			$wrap();
		}
	}
	$wrap();

	$out = '';
	foreach ($root->childNodes as $node) {
		$out .= $doc->saveHTML($node);
	}
	return trim($out);
}

function sanitize_children(DOMNode $parent): void
{
	foreach (iterator_to_array($parent->childNodes) as $node) {
		if ($node instanceof DOMText) {
			continue;
		}
		if (!$node instanceof DOMElement) { // 註解等
			$parent->removeChild($node);
			continue;
		}
		$tag = strtolower($node->nodeName);
		if (in_array($tag, HTML_DROP, true)
			|| ($tag === 'iframe' && !preg_match(HTML_IFRAME_SRC, $node->getAttribute('src')))) {
			$parent->removeChild($node);
			continue;
		}
		$tag = HTML_RENAME[$tag] ?? $tag;
		if (!isset(HTML_ALLOWED[$tag])) { // 不認識的標籤：保留裡面的內容
			sanitize_children($node);
			while ($node->firstChild) {
				$parent->insertBefore($node->firstChild, $node);
			}
			$parent->removeChild($node);
			continue;
		}
		if ($tag !== strtolower($node->nodeName)) { // 改名：例如 div → p
			$renamed = $node->ownerDocument->createElement($tag);
			while ($node->firstChild) {
				$renamed->appendChild($node->firstChild);
			}
			$parent->replaceChild($renamed, $node);
			$node = $renamed;
		}
		foreach (iterator_to_array($node->attributes) as $attr) {
			$name = strtolower($attr->name);
			$value = trim($attr->value);
			$ok = in_array($name, HTML_ALLOWED[$tag], true) && match ($name) {
				'href'  => (bool) preg_match('#^(https?://|mailto:|/|\#)#i', $value),
				'src'   => $tag === 'iframe' || preg_match('#^(https?://|/uploads/|/assets/)#i', $value),
				default => true,
			};
			if (!$ok) {
				$node->removeAttribute($attr->name);
			}
		}
		if ($tag === 'img' && !$node->hasAttribute('src')) { // 圖片網址不合法（例如 data:）就整張拿掉，免得變破圖
			$parent->removeChild($node);
			continue;
		}
		if ($tag === 'a' && preg_match('#^https?://#i', $node->getAttribute('href'))) {
			$node->setAttribute('target', '_blank'); // 站外連結另開分頁
			$node->setAttribute('rel', 'noopener noreferrer');
		}
		if ($tag === 'iframe' || $tag === 'img') {
			$node->setAttribute('loading', 'lazy');
		}
		sanitize_children($node);
	}
}

// $f：表單欄位，另含 user_id（目前登入的帳號，記為建立者／最後修改者）
function create_article(array $f, string $content, ?string $cover): int
{
	// 先用暫時的唯一 slug 新增，拿到自動編號後改成乾淨的 a-{id}
	$pdo = db();
	$stmt = $pdo->prepare('INSERT INTO articles (slug, title, description, date, location, cover, content, publish_at, unpublish_at, created_by, updated_by)
	                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	$stmt->execute(['tmp-' . bin2hex(random_bytes(8)), $f['title'], $f['description'], $f['date'], $f['location'], $cover, $content, $f['publish_at'], $f['unpublish_at'], $f['user_id'], $f['user_id']]);
	$id = (int) $pdo->lastInsertId();
	$pdo->prepare('UPDATE articles SET slug = ? WHERE id = ?')->execute(["a-$id", $id]);
	return $id;
}

// 更新時不改 slug（原本分享出去的網址繼續有效）；換掉的海報、從內文拿掉的圖片會一併刪檔
function update_article(int $id, array $f, string $content, ?string $cover): void
{
	$old = find_article('id', $id);
	$stmt = db()->prepare('UPDATE articles SET title = ?, description = ?, date = ?, location = ?, cover = ?, content = ?, publish_at = ?, unpublish_at = ?, updated_by = ? WHERE id = ?');
	$stmt->execute([$f['title'], $f['description'], $f['date'], $f['location'], $cover, $content, $f['publish_at'], $f['unpublish_at'], $f['user_id'], $id]);
	if ($old) {
		remove_unused_uploads(array_diff(article_uploads($old), article_uploads(['cover' => $cover, 'content' => $content])));
	}
}

function delete_article(int $id): void
{
	$article = find_article('id', $id);
	if (!$article) {
		return;
	}
	db()->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
	remove_unused_uploads(article_uploads($article));
}

// 文章用到的 uploads/ 檔名：海報＋內文裡的圖片
function article_uploads(array $article): array
{
	preg_match_all('#/uploads/([A-Za-z0-9._-]+)#', $article['content'] ?? '', $m);
	return array_values(array_unique(array_filter([$article['cover'] ?? null, ...$m[1]])));
}

// 刪除 uploads/ 裡已經沒有任何文章用到的檔案（只接受單純檔名，避免刪到其他位置）
function remove_unused_uploads(array $files): void
{
	$check = db()->prepare("SELECT COUNT(*) FROM articles WHERE cover = ? OR content LIKE ?");
	foreach ($files as $file) {
		if (!$file || basename($file) !== $file || !is_file(ROOT_DIR . '/uploads/' . $file)) {
			continue;
		}
		$check->execute([$file, '%/uploads/' . addcslashes($file, '%_\\') . '%']);
		if ((int) $check->fetchColumn() === 0) {
			unlink(ROOT_DIR . '/uploads/' . $file);
		}
	}
}

// ############################################################
//  4. 共用元件（直接輸出 HTML）
//  在頁面裡這樣用：title_block(['titleEN' => 'plan', 'title' => '計畫']);
// ############################################################

// ---- 區塊標題 ----
// layout = 'vertical'（預設，直式、附英文小字）或 'horizontal'（橫式、置中）
function title_block(array $o): void
{
	$horizontal = ($o['layout'] ?? 'vertical') === 'horizontal';
	$container = ($horizontal
		? 'group flex flex-row justify-center space-x-[10px] my-auto'
		: 'group flex flex-col items-center space-y-[10px] [writing-mode:vertical-lr] my-auto lg:space-y-[15px]')
		. ' ' . ($o['class'] ?? '');
	$titleClass = ($horizontal ? 'subtitle-bold lg:subtitle-bold-web' : 'subtitle-bold lg:subtitle-bold-web lg:tracking-[0%]')
		. ' ' . ($o['titleClass'] ?? '');
	?>
	<div class="<?= e(trim($container)) ?>">
		<?php if (!$horizontal): ?>
			<p class="text-[12px] font-serif tracking-[0.4em] lg:tracking-[0.6em] lg:text-[16px]"><?= e($o['titleEN'] ?? '') ?></p>
		<?php endif; ?>
		<?php if (!empty($o['subtitle'])): ?>
			<div class="flex flex-col items-center gap-[10px]">
				<p class="<?= e(trim($titleClass)) ?>"><?= e($o['title']) ?></p>
				<p class="<?= e($o['subtitleClass'] ?? '') ?>"><?= e($o['subtitle']) ?></p>
			</div>
		<?php else: ?>
			<p class="<?= e(trim($titleClass)) ?>"><?= e($o['title']) ?></p>
		<?php endif; ?>
	</div>
	<?php
}

// ---- 麵包屑 ----
// 區塊標籤（英文、全大寫，有列表頁的可以點回去）；不在表裡的字串視為頁面標題
const BREADCRUMB_SECTIONS = [
	'Plan'       => 'Plan/',
	'Exhibition' => 'Plan/ExhibitionList/',
	'Workshop'   => 'Plan/Workshop/List/',
	'Article'    => 'Articles/',
	'Market'     => null,
	'Lecture'    => null,
	'Other'      => null,
];

function breadcrumbs(string ...$labels): void
{
	$labels = array_values(array_filter($labels));
	$last = count($labels) - 1;
	?>
	<nav aria-label="麵包屑導覽">
		<ol class="flex items-center gap-[10px] font-serif text-[12px] tracking-[0.15em] lg:text-[14px]">
			<?php foreach ($labels as $i => $label):
				$isSection = array_key_exists($label, BREADCRUMB_SECTIONS);
				$link = $i === $last ? null : (BREADCRUMB_SECTIONS[$label] ?? null);
				$case = $isSection ? 'uppercase' : '';
				// 頁面標題太長就截斷，完整文字放在 title，滑鼠停留時看得到
				$shown = mb_strlen($label) > 20 ? mb_substr($label, 0, 20) . '…' : $label;
				$full = $shown === $label ? '' : ' title="' . e($label) . '"';
				?>
				<li class="flex items-center gap-[10px]">
					<?php if ($i > 0): ?><span aria-hidden="true" class="text-gray-400">·</span><?php endif; ?>
					<?php if ($link): ?>
						<a href="<?= e(url($link)) ?>"<?= $full ?> class="text-primary hover:text-gray-500 transition-colors duration-[var(--motion-fast)] <?= $case ?>"><?= e($shown) ?></a>
					<?php else: ?>
						<span<?= $i === $last ? ' aria-current="page"' : '' ?><?= $full ?> class="<?= $i === $last ? 'text-gray-400' : 'text-primary' ?> <?= $case ?>"><?= e($shown) ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}

// ---- 「查看更多」按鈕（細框樣式，hover 反白 + 箭頭右滑）----
// align：center（預設）/ end（靠右）/ start（靠左）
function more_link(string $to, string $label, string $class = '', string $align = 'center'): void
{
	$justify = ['end' => 'justify-end', 'start' => 'justify-start'][$align] ?? 'justify-center';
	?>
	<div class="flex <?= $justify ?> <?= e($class) ?>">
		<a href="<?= e(url($to)) ?>" style="font-family: 'Shippori Mincho', serif" class="group inline-flex items-center gap-[16px] border border-primary px-[32px] py-[14px] text-[14px] lg:text-[16px] tracking-[0.1em] text-primary transition-colors duration-[var(--motion-base)] ease-[var(--motion-ease-standard)] hover:bg-primary hover:text-secondary">
			<?= e($label) ?>
			<svg width="46" height="12" viewBox="0 0 46 12" fill="none" aria-hidden="true" class="transition-transform duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] group-hover:translate-x-2">
				<line x1="0" y1="6" x2="42" y2="6" stroke="currentColor" stroke-width="1.2" />
				<path d="M37 1.5 L44.5 6 L37 10.5" stroke="currentColor" stroke-width="1.2" fill="none" stroke-linecap="round" stroke-linejoin="round" />
			</svg>
		</a>
	</div>
	<?php
}

// ---- 列表卡片（文章、展覽、工作坊共用）----
// $href、$image 都是完整網址，圖片通常用 cover_image() 產生
function card(string $href, string $image, string $title, string $date = ''): void
{
	?>
	<a href="<?= e($href) ?>" class="group flex flex-col md:w-full md:max-w-[600px] lg:max-w-[750px]">
		<div class="w-full aspect-video overflow-hidden">
			<img class="w-full h-full object-cover transition duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] group-hover:scale-105" src="<?= e($image) ?>" alt="<?= e($title ?: '封面圖') ?>" loading="lazy" decoding="async">
		</div>
		<h3 class="bodyText lg:bodyText-web mt-[25px]"><?= e($title) ?></h3>
		<p class="bodyText text-[12px] lg:bodyText-web mt-[10px]"><?= e($date) ?></p>
	</a>
	<?php
}

// ---- 講者／電影介紹卡片 ----
// $size：lg 以上的圖片寬度（例如 '250px'）；$movie = true 為電影模式（標題較大、不顯示職稱）
function profile_card(string $size, string $src, string $name, string $job, string $content, bool $movie = false): void
{
	?>
	<div class="space-y-[35px] flex flex-col md:flex-row md:justify-center md:items-center md:space-x-[70px] md:space-y-0">
		<?php /* 圖片寬度：手機滿版、md 固定 300px、lg 用 $size（透過 CSS 變數傳入） */ ?>
		<div class="w-full aspect-square md:w-[300px] lg:w-[var(--card-img-w)]" style="--card-img-w: <?= e($size) ?>">
			<img class="w-full h-full object-cover" src="<?= e($src) ?>" alt="<?= e($name) ?>" loading="lazy" decoding="async">
		</div>
		<?php /* min-[1200px]：圖 250 + 間距 70 + 文字 800 = 1120，加左右 padding 80 → 超過 1200px 才有剩餘空間 */ ?>
		<div class="<?= $movie ? 'space-y-[25px]' : 'space-y-[10px]' ?>  md:w-[450px] lg:w-[800px] min-[1200px]:flex-1">
			<h2 class="<?= $movie ? 'subtitle-bold lg:subtitle-bold-web' : 'bodyText lg:bodyText-web' ?>"><?= e($name) ?></h2>
			<?php if (!$movie): ?><p class="bodyText lg:bodyText-web"><?= e($job) ?></p><?php endif; ?>
			<p class="bodyText lg:bodyText-web"><?= e($content) ?></p>
		</div>
	</div>
	<?php
}

// ---- 計畫頁的活動大卡片 ----
// $descriptionHtml 可含 <br>，由呼叫端提供（不做跳脫）
function exhibition_card(array $o): void
{
	$imageOnRight = $o['imageOnRight'] ?? false;
	?>
	<a href="<?= e(url($o['link'])) ?>" class="group headline flex flex-col max-w-[82.2vw] mx-auto space-y-[30px] lg:space-y-0 lg:flex-row lg:justify-center lg:items-center lg:gap-[100px] p-4">
		<div class="flex flex-col space-y-[10px] <?= $imageOnRight ? 'lg:order-2' : 'lg:order-1' ?>">
			<p class="text-primary"><?= e($o['eyebrow']) ?></p>
			<div class="flex items-center space-x-2 text-primary">
				<h2 class="heading-bold lg:heading-bold-web"><?= e($o['title']) ?></h2>
				<span>|</span>
				<h2 class="heading-bold lg:heading-bold-web"><?= e($o['subtitle']) ?></h2>
				<svg class="w-5 h-5 text-primary transform transition-transform duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] group-hover:translate-x-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
				</svg>
			</div>
			<p class="bodyText lg:bodyText-web text-primary"><?= $o['descriptionHtml'] ?></p>
		</div>
		<div class="lg:w-[40vw] h-[50vh] <?= $imageOnRight ? 'lg:order-1' : 'lg:order-2' ?>">
			<img src="<?= e($o['image']) ?>" alt="<?= e($o['title']) ?>" class="w-full h-full object-cover" loading="lazy" decoding="async">
		</div>
	</a>
	<?php
}

// ---- 報名活動卡片 ----
// status：open 報名中（可點，另開 Google 表單）/ full 已額滿 / closed 已截止（不可點、半透明）
function enroll_card(array $item): void
{
	$statuses = [
		'open'   => ['報名中', 'bg-primary text-secondary'],
		'full'   => ['已額滿', 'bg-gray text-primary'],
		'closed' => ['已截止', 'bg-gray text-primary'],
	];
	$key = isset($statuses[$item['status'] ?? '']) ? $item['status'] : 'open';
	[$label, $badgeClass] = $statuses[$key];
	$clickable = $key === 'open' && !empty($item['formUrl']);
	$base = 'group flex flex-col md:w-full md:max-w-[600px] lg:max-w-[750px]';
	$cover = cover_image($item['cover'] ?? null);

	if ($clickable): ?>
		<a href="<?= e($item['formUrl']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($item['title']) ?>（於新分頁開啟報名表單）" class="<?= $base ?>">
	<?php else: ?>
		<div class="<?= $base ?> opacity-60">
	<?php endif; ?>
			<div class="relative w-full aspect-video overflow-hidden">
				<img class="w-full h-full object-cover transition duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] group-hover:scale-105" src="<?= e($cover) ?>" alt="<?= e($item['title'] ?: '活動封面圖') ?>">
				<span class="absolute left-0 top-0 bodyText text-[12px] px-[12px] py-[6px] <?= $badgeClass ?>"><?= $label ?></span>
			</div>
			<h3 class="bodyText lg:bodyText-web mt-[25px]"><?= e($item['title']) ?></h3>
			<p class="bodyText text-[12px] lg:bodyText-web mt-[10px]"><?= e($item['date']) ?></p>
			<?php if ($clickable): ?>
				<div class="mt-auto pt-[16px] flex justify-end">
					<span class="bodyText text-[12px] flex items-center gap-[4px] whitespace-nowrap">
						前往報名
						<svg aria-hidden="true" width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.2" class="transition-transform duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] group-hover:translate-x-[2px] group-hover:-translate-y-[2px]">
							<path d="M3.5 8.5 8.5 3.5" />
							<path d="M4.5 3.5h4v4" />
						</svg>
					</span>
				</div>
			<?php endif; ?>
	<?php if ($clickable): ?>
		</a>
	<?php else: ?>
		</div>
	<?php endif;
}

// ---- 多張圖輪流淡入（手機版圖庫）----
// 切換由 assets/js/site.js 處理（找 [data-crossfade]）
function crossfade_images(array $images, string $class = 'w-full aspect-video'): void
{
	?>
	<div class="relative overflow-hidden <?= e($class) ?>" data-crossfade>
		<?php foreach ($images as $i => $src): ?>
			<img src="<?= e($src) ?>" alt="" class="absolute top-0 left-0 w-full h-full object-cover transition-opacity duration-[var(--motion-slow)] ease-[var(--motion-ease-standard)] <?= $i === 0 ? 'opacity-100' : 'opacity-0' ?>">
		<?php endforeach; ?>
	</div>
	<?php
}

require __DIR__ . '/layout.php';
