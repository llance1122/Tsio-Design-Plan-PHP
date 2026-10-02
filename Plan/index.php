<?php
// ============================================================
//  計畫總覽（/Plan/）
// ============================================================
require __DIR__ . '/../includes/init.php';

page_start([
	'title'       => '計劃',
	'description' => '展覽、工作坊、市集、講座與戶外電影 — 設醮的各項活動。',
]);
?>
<section class="space-y-[10vh]">
	<main class="space-y-[10vh] lg:space-y-[20vh] mt-[15vh] lg:mt-[24vh]">
		<div class="lg:pb-[10vh] bg-white">
			<?php title_block(['class' => 'headline', 'titleEN' => 'Plan', 'title' => '計劃']); ?>
			<div class="headline mx-auto w-[82.2vw] mt-[var(--title-gap-text)] lg:w-[900px]">
				<p class="headline bodyText lg:bodyText-web">
					「設醮計畫」不僅呈現作品與經驗，更著重於設計精神與創作信念的傳承。透過展覽、工作坊與互動活動，
					引導學生回望過去、凝視當下、思考未來，將創作視為一種實踐、學習與對話的儀式，逐步形塑完整的設計教育生態。
				</p>
			</div>
		</div>

		<div class="w-full py-[140px] space-y-[100px] lg:py-[280px] lg:space-y-[200px]" style="background-image: url('<?= e(asset('img/bg_gray.webp')) ?>')">
			<?php
			exhibition_card([
				'link'            => 'Plan/ExhibitionList/',
				'imageOnRight'    => true,
				'eyebrow'         => 'Main Exhibition',
				'title'           => '展覽',
				'subtitle'        => '主題策展',
				'descriptionHtml' => '「對話的對話」——青年設計師與創作者的主題展。<br>涵蓋平面、空間、影像與裝置。',
				'image'           => asset('img/imgs/ExhibitionBanner.webp'),
			]);
			exhibition_card([
				'link'            => 'Plan/Workshop/',
				'eyebrow'         => 'Workshop',
				'title'           => '工作坊',
				'subtitle'        => '動手體驗',
				'descriptionHtml' => '來自不同文化的職人，帶著木作、織品、陶藝與書寫走進現場。<br>親手做一件作品，也為自己設下一場微型儀式。',
				'image'           => asset('img/imgs/Exhibition_1.webp'),
			]);			
			exhibition_card([
				'link' => 'Plan/Market/', 'imageOnRight' => true, 'eyebrow' => 'Market', 'title' => '市集', 'subtitle' => '創意市集',
				'descriptionHtml' => '延伸展覽精神的創意市集，集結手作品牌、獨立出版與插畫小物。<br>逛市集不只是購物，而是與物件、與人、與自己的一場對話。',
				'image' => asset('img/imgs/Exhibition_1.webp'),
			]);
			exhibition_card([
				'link' => 'Plan/Lecture/', 'eyebrow' => 'Talks & Sharing', 'title' => '講座', 'subtitle' => '講者分享',
				'descriptionHtml' => '邀請走過這條路的前輩，談創作背後那段無聲的過程。<br>那些掙扎、遲疑與轉彎，說給還在路上的你聽。',
				'image' => asset('img/imgs/Exhibition_1.webp'),
			]);
			exhibition_card([
				'link' => 'Plan/Other/', 'imageOnRight' => true, 'eyebrow' => 'Outdoor Cinema', 'title' => '戶外電影', 'subtitle' => '星空放映',
				'descriptionHtml' => '一塊幕布、一片星空，戶外電影帶你走進被影像喚醒的記憶。<br>精選與展覽主題呼應的作品——關於遠方、離開與回來。',
				'image' => asset('img/imgs/Exhibition_1.webp'),
			]);
			?>
			<div class="max-w-[82.2vw] mx-auto">
				<hr class="border-t border-primary my-8 mb-[70px]">
				<p class="bodyText lg:bodyText-web text-center">
					跨域共創｜集結設計、藝術、手作、聲音與影像等創作形式，拓展展覽的邊界。
					參與式設計｜讓觀眾不只是觀看，而是成為活動的一部分。
					在地連結｜與地方文化、場域歷史對話，將創意落在生活現場。
					青年平台｜支持新生代設計師、創作者、職人，展現多元聲音與觀點。
					創意儀式感｜讓設計不只是作品，而是一種當代的集體行動與微型儀式。
				</p>
			</div>
		</div>
	</main>
</section>
<?php page_end(); ?>
