<?php
// ============================================================
//  關於設醮（/About/）
// ============================================================
require __DIR__ . '/../includes/init.php';

$photos = [
	asset('img/photos/AboutPage_1.webp'),
	asset('img/photos/AboutPage_2.webp'),
	asset('img/photos/AboutPage_3.webp'),
];

page_start([
	'title'       => '關於設醮',
	'description' => '「設」是設計，「醮」是面對創作時的虔誠與反省。設醮是一場屬於設計者的精神儀式。',
]);
?>
<section class="space-y-[20vh]">
	<main class="space-y-[10vh] mt-[15vh] lg:space-y-[20vh] lg:mt-[24vh]">
		<?php /* 頂部圖庫：手機與平板輪流淡入，桌機（lg 以上）三張並排 */ ?>
		<div class="space-y-[20px] w-full lg:space-y-[2%]">
			<?php crossfade_images($photos, 'w-full aspect-video lg:hidden'); ?>
			<div class="hidden lg:flex gap-[5px] lg:h-[62vh] mx-auto ">
				<div class="grow-1 basis-0 h-full"><img class="w-full h-full object-cover" src="<?= e($photos[0]) ?>" alt=""></div>
				<div class="grow-1 basis-0 h-full"><img class="w-full h-full object-cover" src="<?= e($photos[1]) ?>" alt=""></div>
				<div class="grow-1 basis-0 h-full"><img class="w-full h-full object-cover object-bottom" src="<?= e($photos[2]) ?>" alt=""></div>
			</div>
		</div>

		<div class="">
			<?php title_block(['class' => 'headline', 'titleEN' => 'Name Origin', 'title' => '計劃的由來']); ?>
			<div class="headline w-full mx-auto px-[40px] lg:max-w-4xl mt-[var(--title-gap-text)]">
				<p class="bodyText lg:bodyText-web">
					「設醮」shè jiào一詞源自傳統信仰中的儀式行為 ——
					僧道設壇、祈神求福、感謝與超渡的過程。在民間信仰裡，設醮往往被視為一種連結天地人三界的行動，透過齋戒、供品與誦經，祈求平安、祝福新生、感念過往。
					我們借用這個意象，將「設醮」轉化為屬於設計系的象徵語彙。
					<br>
					<br>
					「設」不只是「設壇」，更代表「設計」—
					是創造、規劃與實踐的起點「醮」則從宗教的儀式轉化為設計的精神—
					是面對創作時的虔誠、思考與反省
					<br>
					<br>
					因此在這裡「設醮」在我們的語境中，不再是宗教儀式，而是一場屬於設計者的精神。其中「重生」的理念，象徵我們以作品為供品、以創作為祈願，在設計的壇上，與自己、與前人、與世界進行一場深層對話。
					<br>
					<br>
					「設醮」的誕生，是我們對設計這件事的再定義
					我們在此回望學系的歷程，感念前輩的經驗與指引，也為後輩祈願新的啟程與祝福。每一件作品，都是一次獻禮；每一位創作者，以最真誠的方式，
					讓創作成為對未來的祝福、也讓設醮成為設計者之間共享的信仰與記憶。
				</p>
			</div>
		</div>

		<div class="">
			<?php title_block(['titleEN' => 'What We Do', 'title' => '我們主要在做什麼']); ?>
			<div class="headline w-full mx-auto px-[40px] lg:max-w-4xl mt-[var(--title-gap-text)]">
				<p class="bodyText lg:bodyText-web">
					我們是一群喜歡動手、愛觀察、敢玩創意的人，來自不同科系、背景與生活經驗，卻因為對設計與生活的熱情走在一起。
					<br>
					<br>
					我們透過展覽、工作坊、市集等多元形式的實體活動，讓設計不再只是展場裡的名詞，而是可以被觸摸、被參與、被共同完成的日常經驗。
					<br>
					<br>
					在這裡，每一個點子都有被實踐的可能，每一種聲音都有被看見的機會。你不需要是設計師，也不需要有什麼專業背景，因為我們相信「設計」其實離每個人都很近——
					它可能是一場對話、一張圖、一段走過的路，甚至是一次你願意參與的行動。
					<br>
					<br>
					歡迎你一起走進來，不只是參觀，而是參與。
					<br>
					<br>
					在這裡，我們一起設，一起醮；一起創造，也一起感受。
				</p>
			</div>
		</div>
	</main>
</section>
<?php page_end(); ?>
