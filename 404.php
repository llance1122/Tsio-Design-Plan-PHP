<?php
// ============================================================
//  找不到頁面（404）
//  其他頁面找不到資料時會呼叫 not_found() 載入這支；
//  直接開網址或設定成伺服器的 404 頁面也可以
// ============================================================
if (!defined('TSIO')) {
	require __DIR__ . '/includes/init.php';
	http_response_code(404);
}
page_start(['title' => '找不到頁面', 'noindex' => true]);
?>
<section class="w-full mx-auto px-[40px] lg:max-w-7xl mt-[25vh] lg:mt-[30vh] mb-[20vh] text-center space-y-[30px]">
	<p class="concept-title lg:concept-title-web">404</p>
	<h2 class="heading-bold lg:heading-bold-web">找不到這個頁面</h2>
	<p class="bodyText lg:bodyText-web">您要找的頁面可能已被移除、更名，或暫時無法使用。</p>
	<a href="<?= e(url()) ?>" class="inline-block bodyText lg:bodyText-web border border-primary px-[32px] py-[14px] tracking-[0.1em] text-primary transition-colors duration-[var(--motion-base)] hover:bg-primary hover:text-secondary">回到首頁</a>
</section>
<?php page_end(); ?>
