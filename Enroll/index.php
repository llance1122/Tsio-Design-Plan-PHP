<?php
// ============================================================
//  報名參與（/Enroll/）
//  活動清單在 data/activities.php
// ============================================================
require __DIR__ . '/../includes/init.php';
$enrolls = activities('enrolls');

page_start(['title' => '報名參與', 'description' => '設醮各項活動報名資訊。']);
?>
<section class="w-full mx-auto px-[40px] lg:max-w-7xl mt-[15vh] lg:mt-[24vh]">
	<h2 class="text-center heading-bold lg:heading-bold-web">報名參與</h2>

	<?php if ($enrolls): ?>
		<?php /* 格線與文章總覽同一組設定 */ ?>
		<div class="w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-[2vw] gap-y-[60px] mt-[60px]">
			<?php foreach ($enrolls as $item) {
				enroll_card($item);
			} ?>
		</div>
	<?php else: ?>
		<?php /* 空狀態只留一句話，上下 py 撐開，避免頁面過短讓頁尾頂上來 */ ?>
		<p class="text-center heading-bold lg:heading-bold-web mt-[60px] py-[15vh]">目前沒有開放報名的活動</p>
	<?php endif; ?>
</section>
<?php page_end(); ?>
