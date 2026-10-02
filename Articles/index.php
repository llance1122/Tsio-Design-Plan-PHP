<?php
// ============================================================
//  文章總覽（/Articles/）
//  文章存在資料庫，從後台 /admin/ 新增、修改、刪除
// ============================================================
require __DIR__ . '/../includes/init.php';
$articles = list_articles();

page_start([
	'title'       => '文章總覽',
	'description' => '設醮的校友特稿與活動報導。',
	'image'       => 'og/articles.jpg',
]);
?>
<section class="w-full mx-auto px-[40px] lg:max-w-7xl mt-[15vh] lg:mt-[24vh]">
	<h2 class="text-center heading-bold lg:heading-bold-web">文章總覽</h2>

	<?php if (!$articles): ?>
		<p class="bodyText lg:bodyText-web text-center mt-[60px]">目前還沒有文章。</p>
	<?php else: ?>
		<div class="w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-[2vw] gap-y-[60px] mt-[60px]">
			<?php foreach ($articles as $a) {
				card(article_url($a), article_cover($a), $a['title'], $a['date']);
			} ?>
		</div>
	<?php endif; ?>
</section>
<?php page_end(); ?>
