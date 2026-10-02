<?php
// ============================================================
//  講座（/Plan/Lecture/）— 目前在選單中隱藏
// ============================================================
require __DIR__ . '/../../includes/init.php';

// 講者與場次：要換內容改下面兩個陣列即可
// ⚠️ 目前三筆都是同一份範例資料，上線前請換成真實內容
$speaker = [
	'photo' => 'lectureSpeaker_1.webp', // assets/img/imgs/ 的檔名
	'name'  => '林哲翔',
	'job'   => '產品設計師／自由創作者',
	'intro' => 'Talk 01｜設計是一條彎彎的路，還是可以折返的橋？畢業後進入科技業，曾任職於新創公司與大型 UX 團隊，後選擇離開制度、走向自由接案。他將分享關於「選擇」的故事——在創意與穩定、在理想與現實之間的每一次掙扎。設計不是直線，也不是答案，而是一種反覆折返與自問的方式。「我花了很多年，才學會不要為了成功而設計。」',
];
$speakers = [$speaker, $speaker, $speaker];

$talk = [
	'label'   => '【TALK 01】',
	'topic'   => '設計是一條彎彎的路，還是可以折返的橋？',
	'time'    => '2025.08.17（日）14:00–15:30',
	'place'   => '設醮展場A區講座角落',
	'speaker' => '林哲翔',
];
$talks = [$talk, $talk, $talk];

$photos = [asset('img/imgs/lectureImg_1.webp'), asset('img/imgs/lectureImg_2.webp'), asset('img/imgs/lectureImg_3.webp')];

page_start([
	'title'       => '講座',
	'description' => '邀請走過這條路的前輩，談創作背後那段無聲的過程。',
	'image'       => 'og/lecture.jpg',
]);
?>
<section class="space-y-[10vh]">
	<main class="space-y-[10vh] lg:space-y-[20vh] mt-[15vh] lg:mt-[24vh]">
		<div class="w-full mx-auto px-[40px] lg:max-w-7xl">
			<?php breadcrumbs('Plan', 'Lecture'); ?>
		</div>

		<?php /* 頂部圖庫：手機輪流淡入，平板以上（md）左一大、右兩小 */ ?>
		<?php crossfade_images($photos, 'w-full aspect-video md:hidden'); ?>
		<div class="w-full aspect-video hidden md:flex">
			<div class="w-[50%] h-full"><img class="h-full w-full object-cover" src="<?= e($photos[0]) ?>" alt=""></div>
			<div class="w-[50%] h-full flex flex-col">
				<div class="w-full h-[50%]"><img class="h-full w-full object-cover" src="<?= e($photos[1]) ?>" alt=""></div>
				<div class="w-full h-[50%]"><img class="h-full w-full object-cover" src="<?= e($photos[2]) ?>" alt=""></div>
			</div>
		</div>

		<div>
			<?php title_block(['class' => 'headline', 'title' => '講座活動｜TALKS & SHARING', 'layout' => 'horizontal']); ?>
			<div class="headline mx-auto w-[82.2vw] mt-[var(--title-gap-text)] lg:w-[900px]">
				<p class="headline bodyText lg:bodyText-web">
					我們經常在展覽裡談創作，談設計，談作品的樣貌。但我們更在意的是，這些作品背後那段無聲的過程——那些不被記錄的掙扎、遲疑、離開、或轉彎。《遠方還未說的話》是一場關於時間的展覽，也是一種溫柔的回望。它來自那些曾經走過這條路的人，帶著他們在現實與夢想之間行走的傷痕與光。用作品替代語言，告訴還在路上的我們一件事：你不是孤單的。在這裡，我們邀請你聽見那些未說出口的話。或許來自未來的你，也會留下幾句話，給還沒出發的人。「展覽不只是結果，它是一段曾經沒機會說出來的旅程。」— Lorem Chang, 策展人
				</p>
			</div>
		</div>

		<div>
			<?php title_block(['class' => 'headline', 'title' => '講者介紹｜GUEST SPEAKERS', 'layout' => 'horizontal']); ?>
			<div class="headline w-full mx-auto px-[40px] mt-[var(--title-gap)] space-y-[100px] lg:max-w-7xl">
				<?php foreach ($speakers as $s) {
					profile_card('250px', asset('img/imgs/' . $s['photo']), $s['name'], $s['job'], $s['intro']);
				} ?>
			</div>
		</div>

		<div>
			<?php title_block(['class' => 'headline', 'title' => '講座資訊｜SCHEDULE', 'layout' => 'horizontal']); ?>
			<div class="space-y-[35px] w-full mx-auto px-[40px] mt-[var(--title-gap)] lg:max-w-7xl md:flex md:flex-row md:items-center md:justify-center md:gap-[100px]">
				<div class="w-full md:w-[450px] lg:w-[600px] aspect-square">
					<img class="w-full h-full object-cover" src="<?= e(asset('img/imgs/ExhibitionLayout.webp')) ?>" alt="" loading="lazy" decoding="async">
				</div>
				<ul class="bodyText lg:bodyText-web min-[1180px]:flex-1 space-y-[20px] lg:space-y-[35px]">
					<?php foreach ($talks as $t): ?>
						<li class="space-y-[10px]">
							<h2><?= e($t['label']) ?></h2>
							<p><?= e($t['topic']) ?><br><?= e($t['time']) ?><br><?= e($t['place']) ?></p>
							<p><?= e($t['speaker']) ?></p>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<div class="w-full mx-auto px-[40px] lg:max-w-7xl">
			<hr class="border-t border-primary my-8 mb-[70px]">
			<?php title_block(['class' => 'headline', 'title' => '報名方式｜RESERVE A SEAT', 'layout' => 'horizontal']); ?>
			<p class="bodyText lg:bodyText-web text-center mt-[var(--title-gap-text)]">講座免費參加，部分座位可預約，名額有限。點擊報名連結或現場候補入場。</p>
			<a href="<?= e(url('Enroll/')) ?>" class="bodyText lg:bodyText-web bg-gray py-3 px-6 mx-auto block w-fit mt-[30px]">立即報名 Register Now</a>
		</div>
	</main>
</section>
<?php page_end(); ?>
