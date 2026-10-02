<?php
// ============================================================
//  主站版面：<head>、導覽列、頁尾
//
//  每一頁的寫法：
//    require __DIR__ . '/../includes/init.php';
//    page_start(['title' => '關於設醮', 'description' => '…', 'image' => 'og/default.jpg']);
//    …頁面內容（HTML）…
//    page_end();
//
//  page_start 可用的選項：
//    title        分享時的頁面標題（瀏覽器分頁固定顯示網站名稱）
//    description  頁面描述（搜尋結果、分享預覽）
//    image        分享預覽圖：assets/ 底下的路徑（例 'og/lecture.jpg'），或完整網址
//    imageSize    true = 圖片是 1200×630（assets/og/ 的圖），會一併宣告尺寸
//    noindex      true = 不讓搜尋引擎收錄
//    navWhite     true = 頁面頂端是深色照片，導覽列一開始就用白字（首頁）
//    scripts      額外載入的 JS（assets/ 底下的路徑陣列）
//    styles       額外載入的 CSS（assets/ 底下的路徑陣列）
// ============================================================
defined('TSIO') || exit;

$GLOBALS['PAGE'] = [];

function page_start(array $o = []): void
{
	$GLOBALS['PAGE'] = $o;
	$site = config('site_name');
	$desc = $o['description'] ?? config('description');
	$shareTitle = !empty($o['title']) ? $o['title'] . '｜' . $site : $site;
	$image = $o['image'] ?? 'og/default.jpg';
	$imageUrl = preg_match('#^https?://#', $image) ? $image : site_origin() . url('assets/' . $image);
	$imageSize = $o['imageSize'] ?? !isset($o['image']) || str_starts_with($image, 'og/');
	$pageUrl = site_origin() . ($_SERVER['REQUEST_URI'] ?? '/');
	?>
<!doctype html>
<html lang="zh-Hant">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= e($site) ?></title>
	<meta name="description" content="<?= e($desc) ?>">
	<?php if (!empty($o['noindex'])): ?><meta name="robots" content="noindex"><?php endif; ?>
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="<?= e($site) ?>">
	<meta property="og:title" content="<?= e($shareTitle) ?>">
	<meta property="og:description" content="<?= e($desc) ?>">
	<meta property="og:url" content="<?= e($pageUrl) ?>">
	<meta property="og:image" content="<?= e($imageUrl) ?>">
	<?php if ($imageSize): ?>
	<meta property="og:image:width" content="1200">
	<meta property="og:image:height" content="630">
	<?php endif; ?>
	<meta name="twitter:card" content="summary_large_image">
	<?php /* 不顯示分頁 logo：用 1×1 透明圖，避免瀏覽器改抓 /favicon.ico 而顯示預設圖示 */ ?>
	<link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=">
	<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
	<?php foreach ($o['styles'] ?? [] as $css): ?>
	<link rel="stylesheet" href="<?= e(asset($css)) ?>">
	<?php endforeach; ?>
	<?php /* JS 有執行才啟用「捲動進場」的先隱藏效果（見 app.css 的 .js-reveal）；
	         搜尋引擎與關閉 JS 的瀏覽器會直接看到完整內容 */ ?>
	<script>document.documentElement.classList.add("js-reveal")</script>
</head>
<body>
	<?php /* flow-root 不能省：子頁面第一個區塊的 margin-top 會往上穿透，導致畫面頂端沒有白底 */ ?>
	<div class="relative flow-root w-full space-y-[120px] bg-white">
		<?php render_nav(!empty($o['navWhite'])); ?>
		<?php /* page-enter：頁面載入時整頁淡入上浮（動畫見 app.css） */ ?>
		<div class="page-enter">
	<?php
}

function page_end(): void
{
	$o = $GLOBALS['PAGE'];
	?>
		</div>
		<?php render_footer(); ?>
	</div>
	<?php foreach ($o['scripts'] ?? [] as $js): ?>
	<script src="<?= e(asset($js)) ?>"></script>
	<?php endforeach; ?>
	<script src="<?= e(asset('js/site.js')) ?>"></script>
</body>
</html>
	<?php
}

// ============================================================
//  導覽列
//  互動（捲動收合、依背景切換字色、Plan 下拉選單、手機側邊選單）在 assets/js/site.js
// ============================================================
// 主選單（桌機與手機共用）：[網址, 桌機文字, 所屬區塊, 手機文字（省略則同桌機）]
const NAV_ITEMS = [
	['About/', 'About', '/About'],
	['Plan/', 'Plan', '/Plan'],
	['Enroll/', 'Enroll', '/Enroll'],
	['Contact/', 'Contact', '/Contact'],
	['Articles/', 'Article', '/Articles', 'News'],
];

// Plan 的子頁面：導覽列下拉選單與頁尾「計畫總覽」共用
const PROJECT_ITEMS = [
	['Plan/ExhibitionList/', '展覽'],
	['Plan/Workshop/', '工作坊'],	
	['Plan/Market/', '市集'],
	['Plan/Lecture/', '講座'],
	['Plan/Other/', '其他活動'],
];

function render_nav(bool $white): void
{
	$ink = $white ? '#ffffff' : '#303030';
	$current = fn(string $section) => is_current($section) ? ' aria-current="page"' : '';
	$lastProject = count(PROJECT_ITEMS) - 1;
	?>
	<?php /* select-none：導覽列不是可讀取的內文，關掉文字選取避免誤選 */ ?>
	<nav data-nav data-color="<?= $white ? 'white' : 'black' ?>" class="fixed top-0 left-0 right-0 py-[4.5vh] select-none transition-transform duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] translate-y-0 z-50">
		<?php /* 深色區塊（Banner、Footer）上的淡漸層：白字壓在照片上時拉開對比 */ ?>
		<div data-nav-shade aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-[calc(100%+14vh)] -z-10" style="background: linear-gradient(to bottom, rgba(0,0,0,0.40) 0%, rgba(0,0,0,0.07) 50%, rgba(0,0,0,0) 100%); opacity: <?= $white ? 1 : 0 ?>; transition: opacity var(--motion-base) var(--motion-ease-standard);"></div>
		<div class="w-[92.2vw] mx-auto">
			<div class="w-full flex justify-between items-center lg:justify-between">
				<a href="<?= e(url()) ?>" data-nav-logo class="transition-opacity duration-[var(--motion-base)] opacity-100">
					<img class="w-auto h-[30px] lg:h-[40px]" style="filter: brightness(0) invert(<?= $white ? 1 : 0 ?>); transition: filter var(--motion-base) var(--motion-ease-standard);" src="<?= e(asset('img/icons/logo.svg')) ?>" alt="tsio_design_plan logo">
				</a>

				<button type="button" data-menu-toggle aria-label="導覽列開關" aria-expanded="false" class="lg:hidden z-60 relative" style="color: <?= $ink ?>; transition: color var(--motion-base) var(--motion-ease-standard);">
					<div class="w-6 h-6 flex flex-col justify-center items-center">
						<span data-burger="top" class="w-6 h-0.5 bg-current transition-all duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] -translate-y-1"></span>
						<span data-burger="mid" class="w-6 h-0.5 bg-current transition-all duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] opacity-100"></span>
						<span data-burger="bot" class="w-6 h-0.5 bg-current transition-all duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] translate-y-1"></span>
					</div>
				</button>

				<ul data-nav-ink class="hidden lg:flex space-x-[60px] bodyText-large-bold-web" style="color: <?= $ink ?>; transition: color var(--motion-base) var(--motion-ease-standard);">
					<?php foreach (NAV_ITEMS as [$href, $label, $section]): ?>
						<?php if ($section === '/Plan'): ?>
							<?php /* Plan：hover 展開下拉選單；在 /Plan 任一子頁面時底線常駐 */ ?>
							<li data-project-trigger>
								<a href="<?= e(url($href)) ?>" class="flex items-center gap-[10px]">
									<span class="nav-underline"<?= is_current('/Plan') ? ' data-active="true"' : '' ?>><?= $label ?></span>
									<?php /* 加號 → 減號：整個圖示轉 90 度，橫線同時淡出 */ ?>
									<span data-plus class="relative block h-[11px] w-[11px] transition-transform duration-[var(--motion-base)] ease-[var(--motion-ease-spring)]">
										<span data-plus-h class="absolute left-0 top-1/2 h-[1.5px] w-full -translate-y-1/2 bg-current transition-opacity duration-[var(--motion-base)] opacity-100"></span>
										<span class="absolute left-1/2 top-0 h-full w-[1.5px] -translate-x-1/2 bg-current"></span>
									</span>
								</a>
							</li>
						<?php else: ?>
							<li><a href="<?= e(url($href)) ?>" class="nav-underline"<?= $current($section) ?>><?= $label ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</nav>

	<?php /* Plan 下拉面板：獨立於 nav 之外，開啟時由 JS 量測 Plan 項目的座標來定位。
	         z-60 必須高於 nav 的 z-50，否則 nav 的上下內距會蓋住面板頂端。 */ ?>
	<div data-project-panel style="left: 0px; top: 0px" class="fixed z-60 hidden lg:block transition-[opacity,translate] duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] pointer-events-none -translate-y-2 opacity-0">
		<ul class="mt-[14px] min-w-[140px] overflow-hidden rounded-lg bg-secondary px-[20px] shadow-lg">
			<?php foreach (PROJECT_ITEMS as $i => [$href, $label]): ?>
				<li class="border-b border-primary/15 last:border-b-0">
					<a href="<?= e(url($href)) ?>" class="block -mx-[20px] px-[20px] py-[10px] bodyText transition-colors duration-[var(--motion-fast)] ease-[var(--motion-ease-standard)] hover:bg-gray focus-visible:bg-gray <?= $i === 0 ? 'pt-[16px]' : '' ?> <?= $i === $lastProject ? 'pb-[16px]' : '' ?>"><?= $label ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php /* 手機側邊選單 */ ?>
	<div data-mobile-menu class="fixed inset-0 z-40 lg:hidden transition-opacity duration-[var(--motion-base)] opacity-0 pointer-events-none">
		<div data-menu-backdrop class="absolute inset-0 bg-primary opacity-70 h-screen"></div>
		<div data-menu-drawer class="absolute top-0 right-0 h-screen w-80 max-w-[85vw] bg-primary shadow-xl transform transition-transform duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] translate-x-full">
			<div class="pt-32 px-8">
				<ul class="space-y-8 bodyText-large-bold-web" style="color: #ffffff">
					<?php foreach (NAV_ITEMS as $item):
						[$href, $label, $section] = $item;
						if ($section !== '/Plan'): ?>
					<li><a href="<?= e(url($href)) ?>" class="block py-2 hover:text-gray-300 transition-colors"><?= $item[3] ?? $label ?></a></li>
					<?php else: ?>
					<li>
						<button type="button" data-mobile-plan-toggle aria-expanded="false" class="flex w-full items-center justify-between py-2 text-left bodyText-large-bold-web hover:text-gray-300 transition-colors" style="color: #ffffff">
							<span>Plan</span>
							<span data-plus aria-hidden="true" class="relative block h-[11px] w-[11px] transition-transform duration-[var(--motion-base)] ease-[var(--motion-ease-spring)]">
								<span data-plus-h class="absolute left-0 top-1/2 h-[1.5px] w-full -translate-y-1/2 bg-current transition-opacity duration-[var(--motion-base)] opacity-100"></span>
								<span class="absolute left-1/2 top-0 h-full w-[1.5px] -translate-x-1/2 bg-current"></span>
							</span>
						</button>
						<ul data-mobile-plan aria-hidden="true" class="space-y-2 overflow-hidden border-l border-white/40 pl-4 transition-[max-height,opacity,translate,margin] duration-[var(--motion-base)] ease-[var(--motion-ease-spring)] pointer-events-none mt-0 max-h-0 -translate-y-2 opacity-0">
							<?php foreach (PROJECT_ITEMS as [$href, $label]): ?>
								<li><a href="<?= e(url($href)) ?>" class="block py-1 bodyText hover:text-gray-300 transition-colors" style="color: #ffffff"><?= $label ?></a></li>
							<?php endforeach; ?>
						</ul>
					</li>
					<?php endif; endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php
}

// ============================================================
//  頁尾（含「回到頂部」按鈕）
// ============================================================
function render_footer(): void
{
	// 聯絡資訊：[圖示, 螢幕閱讀器用說明, 顯示文字]
	$contacts = [
		['clock', '服務時間', 'Mon~Fri / 10:00-19:00'],
		['phone', '聯絡電話', '(03)211-8800'],
		['location', '公司地址', '33302桃園市龜山區文化一路259號'],
		['mail', '電子郵件', 'tsio.designplan@gmail.com'],
	];
	?>
	<button type="button" data-to-top class="group cursor-pointer m-auto z-[1000] flex justify-center items-center gap-[10px] mb-[50px]" aria-label="回到頁面頂部">
		<?php /* filter 將黑色 PNG 調成與文字相同的灰階，深淺背景都可見 */ ?>
		<img class="w-auto h-[24px] [filter:brightness(0)_invert(0.65)] group-hover:[filter:brightness(0)_invert(0.45)] transition-[filter] duration-[var(--motion-fast)]" src="<?= e(asset('img/icons/To_Top_Arrow.png')) ?>" alt="" aria-hidden="true">
		<p class="bg-transparent border-none p-0 text-gray-400 group-hover:text-gray-500 font-serif text-base tracking-[2px] uppercase transition-colors duration-[var(--motion-fast)]">TO TOP</p>
	</button>
	<footer class="w-full pt-[40px] pb-[20px] lg:pt-[80px] lg:pb-[40px] bg-black" data-navcolor="white">
		<div class="flex flex-col items-center gap-[70px] md:mx-auto md:max-w-7xl md:px-[40px] md:grid md:grid-cols-4 md:gap-8 md:items-start">
			<div class="w-auto h-[250px] flex flex-col justify-between items-center md:col-span-2 md:h-auto md:items-start md:justify-start md:gap-[50px]">
				<a href="<?= e(url()) ?>" aria-label="返回首頁">
					<img class="w-[140px] h-auto lg:w-[160px]" src="<?= e(asset('img/icons/logo.svg')) ?>" alt="tsio_design_plan logo">
				</a>
				<div class="w-[300px] h-[130px] flex flex-col justify-between md:w-auto md:h-auto md:gap-2 md:mt-4">
					<?php foreach ($contacts as [$icon, $sr, $text]): ?>
						<div class="footer flex flex-row gap-[20px] text-secondary items-center lg:bodyText-web lg:text-secondary">
							<?php footer_icon($icon); ?>
							<?= e($text) ?>
							<span class="sr-only"><?= e($sr) ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="space-y-[70px] md:space-y-0 md:flex md:flex-row md:space-x-[60px] md:col-span-2 md:justify-end lg:space-x-[250px]">
				<div class="space-y-[15px] w-[300px] md:w-auto md:space-y-[50px]">
					<h3 class="subtitle-bold text-center text-secondary md:text-left lg:bodyText-large-bold-web lg:text-secondary">關於設醮</h3>
					<ul class="footer flex flex-row justify-between text-secondary md:flex-col md:justify-start md:space-y-[15px] lg:bodyText-web lg:text-secondary">
						<li><a href="<?= e(url('About/')) ?>">關於我們</a></li>
						<li><a href="<?= e(url('Contact/')) ?>">聯絡我們</a></li>
						<li><a href="<?= e(url('Articles/')) ?>">最新消息</a></li>
					</ul>
				</div>
				<div class="space-y-[15px] w-[300px] md:w-auto md:space-y-[50px]">
					<h3 class="subtitle-bold text-center text-secondary md:text-left lg:bodyText-large-bold-web lg:text-secondary">計畫總覽</h3>
					<?php /* 與導覽列的 Plan 下拉選單共用 PROJECT_ITEMS，開放新頁面只要改一處。
					         手機用 justify-around：項目少時不會被推到兩端 */ ?>
					<ul class="footer flex flex-row justify-around text-secondary md:flex-col md:justify-start md:space-y-[15px] lg:bodyText-web lg:text-secondary">
						<?php foreach (PROJECT_ITEMS as [$href, $label]): ?>
							<li><a href="<?= e(url($href)) ?>"><?= $label ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<div class="max-w-[300px] m-auto mt-[70px] flex flex-row items-center justify-between md:max-w-7xl md:mx-auto md:px-[40px] lg:mt-[80px]">
			<p class="bodyText-web text-secondary">Copyright © 2025–<?= date('Y') ?></p>
			<div class="flex flex-row space-x-[20px]">
				<a href="<?= e(url()) ?>" aria-label="Instagram" class="w-[18px] h-[18px] flex justify-center items-center lg:w-[20px] lg:h-[20px]">
					<img class="w-full h-auto" src="<?= e(asset('img/icons/ig.png')) ?>" alt="Instagram icon">
				</a>
				<a href="<?= e(url()) ?>" aria-label="Threads" class="w-[18px] h-[18px] flex justify-center items-center lg:w-[20px] lg:h-[20px]">
					<img class="w-full h-auto" src="<?= e(asset('img/icons/thread.png')) ?>" alt="Threads icon">
				</a>
			</div>
		</div>
	</footer>
	<?php
}

// 頁尾聯絡資訊的小圖示
function footer_icon(string $name): void
{
	$paths = [
		'clock'    => ['class' => 'size-4.5 lg:size-7.5', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />'],
		'phone'    => ['class' => 'size-4.5 lg:size-7.5', 'svg' => '<path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" />'],
		'mail'     => ['class' => 'w-full', 'svg' => '<path d="M1.5 8.67v8.58a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V8.67l-8.928 5.493a3 3 0 0 1-3.144 0L1.5 8.67Z" /><path d="M22.5 6.908V6.75a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3v.158l9.714 5.978a1.5 1.5 0 0 0 1.572 0L22.5 6.908Z" />'],
		'location' => ['class' => ' w-full', 'svg' => '<path fill-rule="evenodd" d="m9.69 18.933.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 0 0 .281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 1 0 3 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 0 0 2.273 1.765 11.842 11.842 0 0 0 .976.544l.062.029.018.008.006.003ZM10 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" clip-rule="evenodd" />'],
	][$name];
	?>
	<div class="w-[18px] h-[18px] flex justify-center items-center lg:w-[20px] lg:h-[20px]">
		<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="<?= $paths['class'] ?>"><?= $paths['svg'] ?></svg>
	</div>
	<?php
}
