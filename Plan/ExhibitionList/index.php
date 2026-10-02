<?php
// ============================================================
//  展覽總覽（/Plan/ExhibitionList/）
//  展覽清單在 data/activities.php
// ============================================================
require __DIR__ . '/../../includes/init.php';
$exhibitions = activities('exhibitions');

page_start([
	'title'       => '展覽總覽',
	'description' => '「對話的對話」— 青年設計師與創作者的主題展，涵蓋平面、空間、影像與裝置。',
	'image'       => 'og/exhibition.jpg',
]);
?>
<section class="w-full mx-auto px-[40px] lg:max-w-7xl mt-[15vh] lg:mt-[24vh]">
	<?php breadcrumbs('Plan', 'Exhibition'); ?>
	<h2 class="text-center heading-bold lg:heading-bold-web mt-[40px]">展覽總覽</h2>
	<div class="w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-[2vw] gap-y-[60px] mt-[60px]">
		<?php foreach ($exhibitions as $ex) {
			card(url('Plan/ExhibitionList/' . $ex['id'] . '/'), cover_image($ex['cover'] ?? null), $ex['title'], $ex['date']);
		} ?>
	</div>
</section>
<?php page_end(); ?>
