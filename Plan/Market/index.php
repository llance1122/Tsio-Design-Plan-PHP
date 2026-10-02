<?php
// ============================================================
//  市集（/Plan/Market/）— 目前在選單中隱藏
// ============================================================
require __DIR__ . '/../../includes/init.php';

// 市集分區：每區一個標題 + 兩張圖（assets/img/imgs/ 的檔名），要增減分區改這個陣列即可
// ⚠️ 目前三區都是同一份範例資料，上線前請換成真實內容
$zone  = ['title' => '01. 手寫與印刷', 'images' => ['ExhibitionLayout.webp', 'ExhibitionLayout.webp']];
$zones = [$zone, $zone, $zone];

page_start([
	'title'       => '市集',
	'description' => '延伸展覽精神的創意市集，集結手作品牌、獨立出版與插畫小物。',
	'image'       => 'og/exhibition.jpg',
]);
?>
<section class="space-y-[10vh]">
	<main class="space-y-[10vh] lg:space-y-[20vh] mt-[15vh] lg:mt-[24vh]">
		<div class="w-full mx-auto px-[40px] lg:max-w-7xl">
			<?php breadcrumbs('Plan', 'Market'); ?>
		</div>

		<div class="space-y-[30px]">
			<div class="w-full aspect-[1917/796]">
				<img class="w-full h-full object-cover" src="<?= e(asset('img/imgs/ExhibitionBanner.webp')) ?>" alt="">
			</div>
			<div class="w-full mx-auto px-[40px] lg:max-w-7xl">
				<p class="bodyText text-center lg:bodyText-web">
					這裡有一封封未寄出的信、一張張記憶裡的風景、一件件等待被帶走的物品。
					逛市集，不只是購物，而是一場對話——和物件、與人、以及與自己的對話。
					一場延伸展覽精神的創意市集，集結來自不同地方的手作品牌、獨立出版、插畫與設計小物。
				</p>
			</div>
		</div>

		<?php foreach ($zones as $z): ?>
			<div>
				<?php title_block(['class' => 'headline', 'title' => $z['title'], 'layout' => 'horizontal']); ?>
				<div class="w-full mx-auto px-[40px] mt-[var(--title-gap)] lg:max-w-7xl md:flex md:flex-row md:items-center md:justify-center">
					<?php foreach ($z['images'] as $img): ?>
						<div class="w-full md:w-[450px] lg:w-[600px] aspect-square">
							<img class="w-full h-full object-cover" src="<?= e(asset('img/imgs/' . $img)) ?>" alt="" loading="lazy" decoding="async">
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="max-w-[82.2vw] mx-auto">
			<hr class="border-t border-primary my-8 mb-[70px]">
			<?php title_block(['class' => 'headline', 'title' => '報名方式｜RESERVE A SEAT', 'layout' => 'horizontal']); ?>
			<p class="bodyText lg:bodyText-web text-center mt-[var(--title-gap-text)]">
				想成為這場市集的一部分嗎？無論你是手作創作者、插畫家、獨立出版人，還是有獨特故事想分享的品牌，我們都期待你加入。
			</p>
			<a href="<?= e(url('Enroll/')) ?>" class="bodyText lg:bodyText-web bg-gray py-3 px-6 mx-auto block w-fit mt-[30px]">立即報名 Register Now</a>
		</div>
	</main>
</section>
<?php page_end(); ?>
