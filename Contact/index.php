<?php
// ============================================================
//  聯絡我們（/Contact/）
// ============================================================
require __DIR__ . '/../includes/init.php';

// ⚠️ 社群連結還沒有實際網址，拿到後填在這裡
$instagramUrl = '您的Instagram連結';
$threadsUrl   = '您的另一個社群連結';

page_start(['title' => '聯絡我們']);
?>
<section class="space-y-[20vh]">
	<main class="w-full mx-auto px-[40px] space-y-[5vh] mt-[15vh] lg:max-w-7xl lg:mt-[24vh] lg:space-y-[20vh]">
		<div class=" space-y-[5vh] bodyText lg:bodyText-web lg:space-y-0 lg:flex lg:justify-between">
			<address>
				<dl class="not-italic space-y-[20px] lg:space-y-[35px]">
					<div class="space-y-[5px]">
						<dt>服務時間:</dt>
						<dd>週一至週五 10:00-19:00</dd>
					</div>
					<div class="space-y-[5px]">
						<dt>電話:</dt>
						<dd><a href="tel:+886-3-211-8800">(03)211-8800</a></dd>
					</div>
					<div class="space-y-[5px]">
						<dt>地點:</dt>
						<dd><a href="https://www.google.com/maps/search/?api=1&amp;query=33302桃園市龜山區文化一路259號" target="_blank" rel="noopener noreferrer">33302桃園市龜山區文化一路259號</a></dd>
					</div>
					<div class="space-y-[5px]">
						<dt>信箱:</dt>
						<dd><a href="mailto:tsio.designplan@gmail.com">tsio.designplan@gmail.com</a></dd>
					</div>
					<div class="space-y-[5px]">
						<dt>社群媒體:</dt>
						<dd class="flex flex-row space-x-[10px]">
							<a class="w-[16px] h-[16px] flex justify-center items-center lg:w-[20px] lg:h-[20px]" href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
								<img class="w-full h-full" src="<?= e(asset('img/icons/ig_black.png')) ?>" alt="">
							</a>
							<a class="w-[14px] h-[16px] flex justify-center items-center lg:w-[18px] lg:h-[20px]" href="<?= e($threadsUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="社群媒體名稱">
								<img class="w-full h-full" src="<?= e(asset('img/icons/thread_black.png')) ?>" alt="">
							</a>
						</dd>
					</div>
				</dl>
			</address>
			<div class="lg:w-[50%]">
				<iframe class="w-full aspect-square" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3615.041137134831!2d121.3873700750584!3d25.032677977817286!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3442a77b20328aab%3A0xae42f4645dfa2c3a!2z6ZW35bqa5aSn5a24!5e0!3m2!1szh-TW!2stw!4v1756102036557!5m2!1szh-TW!2stw" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
			</div>
		</div>
	</main>
</section>
<?php page_end(); ?>
