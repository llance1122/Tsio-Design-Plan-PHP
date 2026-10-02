<?php
// ============================================================
//  文章內頁（/Articles/view.php?slug=文章代碼）
//  只顯示上架中的文章。後台的「預覽」（admin/?preview=編號）也借用這支輸出：
//  後台先把文章放進 $previewArticle 再載入，不論上架狀態都會顯示
// ============================================================
require_once __DIR__ . '/../includes/init.php'; // 預覽時後台已經載入過 init.php

$article = $previewArticle ?? find_article('slug', $_GET['slug'] ?? '', true);

if (!$article) {
	// 找不到文章：回傳 404 狀態碼，畫面顯示提示
	http_response_code(404);
	page_start(['title' => '找不到文章', 'noindex' => true]);
	?>
	<section class="w-full mx-auto px-[40px] lg:max-w-7xl mt-[15vh] lg:mt-[24vh]">
		<?php breadcrumbs('Article'); ?>
		<p class="bodyText lg:bodyText-web text-center mt-[60px]">找不到這篇文章。</p>
	</section>
	<?php
	page_end();
	exit;
}

$hero = article_cover($article);

page_start([
	'title'       => $article['title'],
	'description' => $article['description'],
	// 有上傳海報就用海報當分享圖（尺寸不固定），沒有就用網站預設的分享圖
	'image'       => $article['cover'] ? site_origin() . $hero : null,
	'imageSize'   => !$article['cover'],
	'noindex'     => isset($previewArticle),
]);
?>
<?php if (isset($previewArticle)): ?>
	<?php /* 後台預覽才會出現的提示條 */ ?>
	<div class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[60] rounded-full bg-neutral-800 text-white text-sm px-5 py-2 shadow-lg">
		預覽：<?= ['live' => '這篇文章目前上架中', 'scheduled' => '這篇文章還沒到上架時間，前台看不到', 'expired' => '這篇文章已下架，前台看不到'][article_status($article)] ?>
	</div>
<?php endif; ?>
<section class="w-full mx-auto md:px-[40px] lg:max-w-7xl mt-[15vh] lg:mt-[24vh]">
	<?php /* 外層在 md 以上才有 px-[40px]，這層用 md:px-0 互補，否則兩層內距會疊成 80px */ ?>
	<div class="px-[40px] md:px-0 mb-[40px]">
		<?php breadcrumbs('Article', $article['title']); ?>
	</div>
	<div class="w-full space-y-[20px] lg:space-y-[2%]">
		<div class="m-auto w-full px-[40px] pb-10 lg:px-0 md:max-w-3xl xl:max-w-5xl">
			<h2 class="text-left heading lg:heading-web"><?= e($article['title']) ?></h2>
		</div>
		<div class="w-full aspect-[2/1]">
			<img class="w-full h-full object-cover" src="<?= e($hero) ?>" alt="<?= e($article['title']) ?>">
		</div>
		<div class="flex justify-end px-[40px] md:px-0">
			<p class="bodyText lg:bodyText-web"><?= e($article['date']) ?></p>
		</div>
	</div>
	<?php /* 內文 HTML 在存檔時已經過 sanitize_html() 白名單過濾，可以直接輸出；樣式見 tailwind.css 的 .article-body */ ?>
	<div class="article-body w-full mx-auto px-[40px] lg:max-w-3xl"><?= $article['content'] ?></div>
</section>
<?php page_end(); ?>
