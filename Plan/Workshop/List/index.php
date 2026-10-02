<?php
// ============================================================
//  工作坊總覽與單一工作坊（同一支檔案）
//    /Plan/Workshop/List/          → 總覽（卡片列表）
//    /Plan/Workshop/List/?id=W001  → 單一工作坊
//  工作坊清單在 data/activities.php
// ============================================================
require __DIR__ . '/../../../includes/init.php';
$workshops = activities('workshops');

// ---------------- 總覽 ----------------
if (!isset($_GET['id'])) {
	page_start(['title' => '工作坊總覽', 'image' => 'og/workshop.jpg']);
	?>
	<section class="w-full mx-auto px-[40px] lg:max-w-7xl mt-[15vh] lg:mt-[24vh]">
		<?php breadcrumbs('Plan', 'Workshop'); ?>
		<h2 class="text-center heading-bold lg:heading-bold-web mt-[40px]">工作坊總覽</h2>
		<?php if ($workshops): ?>
			<div class="w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-[2vw] gap-y-[60px] mt-[60px]">
				<?php foreach ($workshops as $w) {
					card(url('Plan/Workshop/List/?id=' . rawurlencode($w['id'])), cover_image($w['cover'] ?? null), $w['title'], $w['date'] ?? '');
				} ?>
			</div>
		<?php else: ?>
			<?php /* 沒有資料時顯示空狀態，而不是一片空白 */ ?>
			<p class="bodyText lg:bodyText-web text-center mt-[60px]">工作坊資訊即將公布，敬請期待。</p>
		<?php endif; ?>
	</section>
	<?php
	page_end();
	exit;
}

// ---------------- 單一工作坊 ----------------
$workshop = null;
foreach ($workshops as $w) {
	if ($w['id'] === $_GET['id']) {
		$workshop = $w;
	}
}

$buttonClass = 'inline-block bodyText lg:bodyText-web border border-primary px-[32px] py-[14px] tracking-[0.1em] text-primary transition-colors duration-[var(--motion-base)] hover:bg-primary hover:text-secondary';

if (!$workshop) {
	http_response_code(404);
	page_start(['title' => '找不到工作坊', 'noindex' => true]);
	?>
	<section class="w-full mx-auto px-[40px] lg:max-w-7xl mt-[25vh] lg:mt-[30vh] mb-[20vh] text-center space-y-[30px]">
		<h2 class="heading-bold lg:heading-bold-web">找不到這個工作坊</h2>
		<a href="<?= e(url('Plan/Workshop/List/')) ?>" class="<?= $buttonClass ?>">回工作坊總覽</a>
	</section>
	<?php
	page_end();
	exit;
}

$cover = cover_image($workshop['cover'] ?? null);

page_start([
	'title'       => $workshop['title'],
	'description' => $workshop['description'] ?? '',
	'image'       => 'og/workshop.jpg',
]);
?>
<section class="space-y-[10vh]">
	<main class="space-y-[10vh] lg:space-y-[15vh] mt-[15vh] lg:mt-[24vh]">
		<div class="w-full mx-auto px-[40px] lg:max-w-7xl">
			<?php breadcrumbs('Plan', 'Workshop', $workshop['title']); ?>
		</div>
		<div class="w-full aspect-video">
			<img class="w-full h-full object-cover" src="<?= e($cover) ?>" alt="<?= e($workshop['title']) ?>">
		</div>
		<div class="w-full mx-auto px-[40px] lg:max-w-3xl space-y-[var(--title-gap-text)]">
			<?php title_block(['title' => $workshop['title'], 'layout' => 'horizontal']); ?>
			<ul class="bodyText lg:bodyText-web space-y-[10px] text-center">
				<li>日期：<?= e($workshop['date'] ?? '') ?></li>
				<li>時間：<?= e($workshop['time'] ?? '') ?></li>
				<li>地點：<?= e($workshop['location'] ?? '') ?></li>
			</ul>
			<p class="bodyText lg:bodyText-web"><?= e($workshop['description'] ?? '') ?></p>
			<div class="flex justify-center pt-[20px]">
				<a href="<?= e(url('Enroll/')) ?>" class="<?= $buttonClass ?>">我要報名</a>
			</div>
		</div>
	</main>
</section>
<?php page_end(); ?>
