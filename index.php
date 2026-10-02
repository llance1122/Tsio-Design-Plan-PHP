<?php
// ============================================================
//  首頁
//  區塊順序：主視覺輪播 → 跑馬燈公告 → 設醮介紹 → 照片牆 → 展覽 → 工作坊 → 計畫 → 最新報導
// ============================================================
require __DIR__ . '/includes/init.php';

// ---- 跑馬燈公告：要換內容改這兩行 ----
$marqueeMessage = '「對話的對話」已開展！';
$marqueeTarget  = 'Plan/ExhibitionList/';

// ---- 主視覺輪播的圖 ----
// 桌機：左右兩欄各一組（同一張圖切成 A 左半、B 右半），放在 assets/img/banner/desktop，順序照陣列
$bannerLeft  = ['desktop/banner1_A.webp', 'desktop/banner2_A.webp', 'desktop/banner3_A.webp', 'desktop/banner4_A.webp'];
$bannerRight = ['desktop/banner1_B.webp', 'desktop/banner2_B.webp', 'desktop/banner3_B.webp', 'desktop/banner4_B.webp'];
// 手機 / 平板：整張圖，直接丟進 assets/img/banner/mobile 或 tablet 資料夾即可，
// 檔名裡的數字決定輪播順序；平板資料夾沒放圖時用手機的圖
$bannerMobile = banner_folder('mobile');
$bannerTablet = banner_folder('tablet') ?: $bannerMobile;

function banner_folder(string $name): array
{
	// 只抓圖檔（資料夾裡的說明檔不會被誤抓）
	$files = array_filter(glob(ROOT_DIR . "/assets/img/banner/$name/*") ?: [], fn($f) => preg_match('/\.(png|jpe?g|webp|avif)$/i', $f));
	usort($files, fn($a, $b) => (int) preg_replace('/\D/', '', basename($a)) <=> (int) preg_replace('/\D/', '', basename($b)));
	return array_map(fn($f) => "$name/" . basename($f), $files);
}

// 輪播的一欄。$side 決定擦入方向（left 由上往下、right 由下往上）；$full = 手機／平板的整張圖模式
// 第一張的初始狀態在這裡輸出，之後的切換由 assets/js/site.js 處理
function banner_column(array $images, string $side, bool $full = false): void
{
	$hiddenClip = $side === 'left' ? 'inset(0 0 100% 0)' : 'inset(100% 0 0 0)';
	$offset = $side === 'left' ? '-18%' : '18%';
	// 桌機左右欄貼齊中縫：左欄以右緣、右欄以左緣對齊，切半的圖拼起來才連續
	$position = $full ? 'center' : ($side === 'left' ? 'right center' : 'left center');
	?>
	<div data-banner-col data-side="<?= $side ?>" class="<?= $full ? 'relative h-full w-full overflow-hidden' : 'relative h-full basis-1/2 overflow-hidden' ?>">
		<?php foreach ($images as $i => $img):
			$shown = $i === 0; ?>
			<div style="position: absolute; inset: 0; overflow: hidden; clip-path: <?= $shown ? 'inset(0 0 0 0)' : $hiddenClip ?>; z-index: <?= $shown ? 2 : 1 ?>; pointer-events: none;">
				<div style="position: absolute; inset: 0; background-image: url('<?= e(asset('img/banner/' . $img)) ?>'); background-size: cover; background-position: <?= $position ?>; background-repeat: no-repeat; transform: <?= $shown ? 'translateY(0) scale(1)' : "translateY($offset) scale(1.06)" ?>; will-change: transform;"></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

// ---- 照片牆：speed = 捲動視差速度（0 = 正常捲動，負值 = 比捲動快往上漂）----
// class 裡「md:」開頭的是平板以上的散排位置，其餘是手機版（直向堆疊）
$tagline = '「在日常裡，設下一場醮。」';
$gallery = [
	['DSCF8777.webp', 0, 'w-[80%] mr-auto aspect-[3/4] md:absolute md:top-0 md:left-0 md:w-[38%]'],
	['DSCF8885.webp', 0, 'w-[70%] ml-auto aspect-[6/5] md:absolute md:top-[27%] md:right-0 md:w-[33%]'],
	['RYK01728.webp', 0, 'w-[85%] mx-auto aspect-[8/5] md:absolute md:top-[66%] md:left-[7.5%] md:w-[48%]'],
	['DSCF8773.webp', -1.5, 'hidden md:block md:absolute md:top-[14%] md:left-[55%] md:w-[16%] aspect-[4/3]'],
	['RYK01732.webp', -2.1, 'hidden md:block md:absolute md:top-[48%] md:left-[13%] md:w-[12%] aspect-[3/4]'],
	['RYK02407.webp', -1.2, 'hidden md:block md:absolute md:top-[80%] md:right-[9%] md:w-[25%] aspect-[5/4]'],
];

// ---- 最新報導：資料庫最新三篇 ----
$latestArticles = list_articles(3);

page_start([
	'navWhite' => true, // 頁面頂端是主視覺照片，導覽列一開始就用白字
	'scripts'  => ['vendor/swiper/swiper-bundle.min.js'],
	'styles'   => ['vendor/swiper/swiper-bundle.min.css'],
]);
?>
<main class="space-y-[80px] lg:space-y-[300px]">

	<?php /* ================= 主視覺輪播 ================= */ ?>
	<section class="relative h-screen w-full" data-navcolor="white" data-banner>
		<?php /* 頂部漸層暗角：讓導覽列的白字在中間調的照片上也清楚 */ ?>
		<div class="pointer-events-none absolute inset-x-0 top-0 z-10 h-[22vh] bg-linear-to-b from-black/10 to-transparent"></div>
		<?php /* 手機（< 768px）：單欄輪播整張圖 */ ?>
		<div class="h-full w-full md:hidden"><?php banner_column($bannerMobile, 'left', true); ?></div>
		<?php /* 平板（768 〜 1023px）：單欄，用 tablet 資料夾的圖 */ ?>
		<div class="hidden h-full w-full md:block lg:hidden"><?php banner_column($bannerTablet, 'left', true); ?></div>
		<?php /* 桌機（lg 以上）：左右兩欄各自輪播，中縫拼接 */ ?>
		<div class="hidden h-full w-full lg:flex flex-row">
			<?php banner_column($bannerLeft, 'left'); ?>
			<?php banner_column($bannerRight, 'right'); ?>
		</div>
	</section>

	<?php /* ================= 跑馬燈公告 =================
	         上下間距刻意比 main 的間距窄（公告貼近主視覺才像通知）：
	         -mt 抵銷主視覺的下方間距，mb 直接指定與「設醮」的距離 */ ?>
	<a href="<?= e(url($marqueeTarget)) ?>" aria-label="<?= e($marqueeMessage) ?>" class="marquee-bar flex h-[var(--marquee-h)] w-full items-center overflow-hidden border-y-[0.5px] border-primary bg-white select-none -mt-[30px] mb-[50px] lg:-mt-[150px] lg:mb-[150px]">
		<span class="sr-only"><?= e($marqueeMessage) ?></span>
		<?php /* 同一句話排兩份、每份重複兩次，位移一半就無縫接回原點 */ ?>
		<div aria-hidden="true" class="marquee-track flex w-max">
			<?php for ($half = 0; $half < 2; $half++): ?>
				<div class="flex min-w-[100vw] shrink-0 justify-around">
					<?php for ($i = 0; $i < 2; $i++): ?>
						<span class="subtitle lg:bodyText-large-web whitespace-nowrap px-[2.5em]"><?= e($marqueeMessage) ?></span>
					<?php endfor; ?>
				</div>
			<?php endfor; ?>
		</div>
	</a>

	<?php /* ================= 設醮介紹 ================= */ ?>
	<section class="w-full h-full lg:mt-[120px]">
		<h2 class="concept-title lg:concept-title-web headline text-center mb-[60px] lg:mb-[120px]">設醮</h2>
		<div class="headline mx-auto max-w-[300px] lg:max-w-none lg:w-fit">
			<div class="flex flex-row items-end gap-[30px] justify-center lg:gap-[280px]">
				<h2 class="subtitle-bold tracking-[0.4em] [writing-mode:vertical-lr] lg:subtitle-bold-web lg:tracking-[0.4em]">為地方設下儀式，為人群打開空間</h2>
				<div class="w-[276px] pt-[80px] lg:w-[550px] lg:pt-[170px]">
					<p class="bodyText lg:bodyText-large-web">
						核心精神｜
						<br><br>
						「設醮」是一種對地方的提問與回應。
						設，是設計的行動；醮，是一種文化的語言。
						在快速流動的時代裡，
						我們選擇用設計設下一場儀式，
						讓空間再次成為人群相遇的可能。
						<br><br>
						透過裝置、文本、影像與體驗，
						我們探索人與地方、人與記憶、人與關係之間
						的可能，也嘗試在其中創造一種新的共同體感。
						這場展覽不是終點，而是一場設醮的開始。
					</p>
					<?php more_link('About/', '了解更多', 'mt-[40px]', 'end'); ?>
				</div>
			</div>
		</div>
	</section>

	<?php /* ================= 照片牆（捲動視差 + 置中標語）=================
	         底部不用 padding 收尾，改用「尾巴」空白 —— sticky 的釘住範圍只算內容 */ ?>
	<section class="w-full pt-[80px] lg:pt-[160px]" style="background-image: url('<?= e(asset('img/bg_gray.webp')) ?>')" data-gallery>
		<?php /* 中央 sticky 標語：釘住範圍 = 照片牆 + 尾巴 */ ?>
		<div data-gallery-sticky class="sticky top-1/2 z-10 h-0 flex items-start justify-center pointer-events-none">
			<p data-gallery-pill style="font-family: 'Shippori Mincho', serif" class="-translate-y-1/2 bg-secondary/95 px-[18px] py-[12px] lg:px-[25px] lg:py-[16px] text-primary text-[15px] md:text-[20px] lg:text-[24px] tracking-[0.25em] shadow-sm"><?= e($tagline) ?></p>
		</div>
		<?php /* 散排照片（視差進度以這層計算）：md 以上絕對定位散排，手機直疊 */ ?>
		<div data-gallery-items class="relative w-full flex flex-col gap-16 md:block md:gap-0 md:aspect-[1588/1936]">
			<?php foreach ($gallery as [$img, $speed, $class]): ?>
				<div data-speed="<?= $speed ?>" class="will-change-transform <?= $class ?>">
					<div class="headline w-full h-full overflow-hidden bg-gray-100">
						<img src="<?= e(asset('img/photos/' . $img)) ?>" alt="" class="w-full h-full object-cover">
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php /* 尾巴：照片走完後標語繼續釘住的距離；下方白色蓋幕層會蓋掉最後 15vh */ ?>
		<div class="h-[55vh]" aria-hidden="true"></div>
	</section>

	<?php /* 白色蓋幕層：往上疊過照片牆尾巴的最後 15vh，把釘在中央的標語「蓋掉」。
	         calc 裡的 +80px/+300px 是抵銷 main 的區塊間距 */ ?>
	<div class="relative z-20 bg-white -mt-[calc(15vh+80px)] lg:-mt-[calc(15vh+300px)] pt-[80px] lg:pt-[300px] space-y-[80px] lg:space-y-[300px]">

		<?php /* ================= 展覽（直接放主視覺海報，海報已含展名、展期等資訊）================= */ ?>
		<section class="flex flex-col items-center">
			<?php title_block(['titleEN' => 'exhibition', 'title' => '展覽']); ?>
			<a href="<?= e(url('Plan/ExhibitionList/')) ?>" class="block w-[83%] aspect-[16/9] mt-[var(--title-gap)]">
				<img class="headline h-full w-full object-cover" src="<?= e(asset('img/imgs/ExhibitionBanner.webp')) ?>" loading="lazy" alt="「對話的對話 — 在彼此的痕跡中流動」展覽主視覺，2026.09.22 至 10.30 於長庚大學">
			</a>
			<?php more_link('Plan/ExhibitionList/', '查看展覽', 'headline mt-[60px] lg:mt-[100px]'); ?>
		</section>

		<?php /* ================= 工作坊 ================= */ ?>
		<section class="flex flex-col items-center">
			<?php title_block(['titleEN' => 'workshop', 'title' => '工作坊']); ?>
			<div class="w-full flex justify-end space-x-[18%] mt-[var(--title-gap)] lg:justify-start">
				<div class="hidden w-[43%] h-[200px] origin-left scale-[1.1] items-end md:h-[400px] lg:flex lg:h-[800px] lg:scale-100">
					<div class="w-full">
						<img class="h-full w-full object-cover headline" src="<?= e(asset('img/photos/workShop_1.webp')) ?>" alt="" loading="lazy" decoding="async">
					</div>
				</div>
				<div class="w-[39%] origin-right scale-[1.1] lg:scale-100">
					<div class="w-full aspect-square">
						<img class="h-full w-full object-cover headline" src="<?= e(asset('img/photos/workShop_2.webp')) ?>" alt="" loading="lazy" decoding="async">
					</div>
				</div>
			</div>
			<div class="w-[300px] bodyText lg:bodyText-large-web lg:w-[800px] headline mt-[60px] lg:mt-[135px]">
				<p>
					來自不同文化的職人，將他們日常中珍貴的技藝與生命哲學帶來現場，與你一同分享。無論是木作、織品、陶藝、書寫，或是任何充滿溫度的創作方式，都是一次與「世界」產生真實連結的機會。
					<br>
					<br>
					我們希望你不只帶回作品，更帶回一種看待生活的方式。
				</p>
			</div>
			<div class="w-[43%] h-[200px] origin-left scale-[1.1] self-start flex items-end md:h-[400px] lg:hidden">
				<div class="w-full">
					<img class="h-full w-full object-cover headline" src="<?= e(asset('img/photos/workShop_1.webp')) ?>" alt="" loading="lazy" decoding="async">
				</div>
			</div>
			<div class="my-auto bodyText-bold [writing-mode:vertical-lr] lg:bodyText-large-bold-web headline mt-[60px] lg:mt-[135px]">
				<p class="tracking-[0.4em]">﹁一起動手，設下自己的微型儀式。﹂</p>
			</div>
			<div class="relative w-[83%] origin-center scale-[1.1] aspect-[11/3.5] mt-[60px] lg:mt-[135px] lg:scale-100">
				<img class="absolute w-full h-full object-cover object-top headline" src="<?= e(asset('img/photos/workShop_3.webp')) ?>" alt="" loading="lazy" decoding="async">
			</div>
			<?php more_link('Plan/Workshop/', '查看工作坊', 'headline mt-[60px] lg:mt-[100px]'); ?>
		</section>

		<?php /* ================= 計畫 ================= */ ?>
		<section>
			<?php title_block(['titleEN' => 'plan', 'title' => '計畫']); ?>
			<div class="space-y-[60px] lg:flex lg:flex-row lg:items-end lg:space-y-0 mt-[var(--title-gap)]">
				<div class="w-full lg:w-[36.5%] space-y-[60px] mt-[60px] lg:mt-0 lg:h-[100vh] lg:flex lg:flex-col lg:justify-between">
					<div class="w-[40%] origin-left scale-[1.1] lg:w-full lg:scale-100 aspect-[28/19]">
						<img class="headline w-full h-full object-cover" src="<?= e(asset('img/photos/plan_1.webp')) ?>" alt="" loading="lazy" decoding="async">
					</div>
					<div class="headline bodyText w-[300px] lg:w-[350px] mx-auto lg:bodyText-large-web">
						<p>
							集結來自不同領域的設計者、創作者與講者， 從設計出發，延伸出生活、
							文化與社會的多元對話。
							活動形式包含靜態展覽、主題講座、職人快閃市集、
							手作工作坊，以及戶外電影、聲音表演等微型活動。
						</p>
					</div>
				</div>
				<div class="w-[53.4%] origin-right scale-[1.1] aspect-[4/3] ml-auto lg:w-[32%] lg:scale-100">
					<img class="headline w-full h-full object-cover" src="<?= e(asset('img/photos/plan_2.webp')) ?>" alt="" loading="lazy" decoding="async">
				</div>
			</div>
			<?php more_link('Plan/', '查看計畫', 'headline mt-[60px] lg:mt-[100px]'); ?>
		</section>

		<?php /* ================= 最新報導（手機用輪播、平板以上三欄）================= */ ?>
		<section class="w-full mx-auto px-[40px] lg:max-w-7xl">
			<?php title_block(['titleEN' => 'article', 'title' => '報導']); ?>
			<div class="headline flex justify-center mt-[var(--title-gap)]">
				<div class="w-full sm:max-w-[500px] p-5 md:hidden">
					<div class="swiper article-swiper-container">
						<div class="swiper-wrapper">
							<?php foreach ($latestArticles as $a): ?>
								<div class="swiper-slide">
									<div class="py-4"><?php card(article_url($a), article_cover($a), $a['title'], $a['date']); ?></div>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="swiper-pagination"></div>
					</div>
				</div>
				<div class="mt-10 hidden md:grid grid-cols-3 gap-x-15 gap-y-16">
					<?php foreach ($latestArticles as $a) {
						card(article_url($a), article_cover($a), $a['title'], $a['date']);
					} ?>
				</div>
			</div>
			<?php more_link('Articles/', '查看報導', 'headline mt-[60px] lg:mt-[100px]'); ?>
		</section>
	</div>
</main>
<?php page_end(); ?>
