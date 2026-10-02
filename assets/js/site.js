// ============================================================
//  全站互動效果（原生 JavaScript，不需要編譯）
//
//  每個效果都是「頁面上有對應元素才啟動」，所以全站共用這一支：
//    導覽列            [data-nav]              includes/layout.php
//    捲動進場          .headline               任何頁面
//    圖片輪流淡入      [data-crossfade]        includes/init.php 的 crossfade_images()
//    回到頂部          [data-to-top]           includes/layout.php
//    首頁主視覺輪播    [data-banner]           index.php
//    首頁照片牆視差    [data-gallery]          index.php
//    首頁報導輪播      .article-swiper-container index.php（需另外載入 Swiper）
//
//  ⚠️ 這裡寫的 Tailwind class（例如 "translate-y-0"）會被編譯工具掃描，
//     新增 class 後記得執行 tools\build-css.bat 重新編譯樣式。
// ============================================================

// ---- 動畫參數（JS 專用；CSS 的時長與曲線在 assets/css/tailwind.css 最上方）----
const MOTION = {
	fast: 200,
	base: 300,
	slow: 1000,
	easeStandard: "ease-in-out",
	easeSpring: "cubic-bezier(0.34, 1.56, 0.64, 1)",
	crossfadeInterval: 6000, // 圖片輪流淡入的間隔
	reveal: { viewFactor: 0.7 }, // 元素露出 70% 才觸發進場
	banner: {
		interval: 5000, // 每張停留時間
		stagger: 0, // 右欄比左欄晚幾毫秒切換
		duration: 1400, // 擦入動畫時長
		ease: "cubic-bezier(0.77, 0, 0.175, 1)",
		parallaxOffset: "18%", // 內層反向位移量
		initialScale: 1.06, // 進場前縮放
	},
	gallery: {
		maxShift: 130, // 平板以上：速度係數 ±1 時的最大位移 (px)
		maxShiftMobile: 45, // 手機
		lerp: 0.08, // 追隨平滑度（越小越有慣性）
	},
};

const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

// 切換 class：cond 為真時加上 on、拿掉 off；反之亦然
function swap(el, cond, on, off) {
	if (!el) return;
	const list = (s) => s.split(" ").filter(Boolean); // 空字串代表「不加任何 class」
	el.classList.remove(...list(cond ? off : on));
	el.classList.add(...list(cond ? on : off));
}

// ============================================================
//  導覽列
// ============================================================
(function initNav() {
	const nav = document.querySelector("[data-nav]");
	if (!nav) return;

	const shade = nav.querySelector("[data-nav-shade]");
	const logoLink = nav.querySelector("[data-nav-logo]");
	const logoImg = logoLink.querySelector("img");
	const ink = nav.querySelector("[data-nav-ink]");
	const toggle = nav.querySelector("[data-menu-toggle]");
	const burger = {
		top: nav.querySelector('[data-burger="top"]'),
		mid: nav.querySelector('[data-burger="mid"]'),
		bot: nav.querySelector('[data-burger="bot"]'),
	};
	const trigger = nav.querySelector("[data-project-trigger]");
	const panel = document.querySelector("[data-project-panel]");
	const mobile = document.querySelector("[data-mobile-menu]");
	const drawer = mobile.querySelector("[data-menu-drawer]");
	const backdrop = mobile.querySelector("[data-menu-backdrop]");
	const mobilePlanToggle = mobile.querySelector("[data-mobile-plan-toggle]");
	const mobilePlan = mobile.querySelector("[data-mobile-plan]");

	const state = {
		visible: true,
		menuOpen: false,
		projectOpen: false,
		mobilePlanOpen: false,
		white: nav.dataset.color === "white",
	};

	// ---- 字色：偵測導覽列正下方是深色區塊（data-navcolor="white"）還是淺色 ----
	function applyColor() {
		const color = state.white ? "#ffffff" : "#303030";
		shade.style.opacity = state.white ? "1" : "0";
		logoImg.style.filter = `brightness(0) invert(${state.white ? 1 : 0})`;
		ink.style.color = color;
		// 手機選單打開時面板是深色，按鈕維持白色
		toggle.style.color = state.menuOpen || state.white ? "#ffffff" : "#303030";
	}
	function measureColor() {
		const probeY = 45; // logo / 文字大約的垂直位置
		let white = false;
		document.querySelectorAll("[data-navcolor]").forEach((el) => {
			const r = el.getBoundingClientRect();
			if (r.top <= probeY && r.bottom >= probeY) white = el.dataset.navcolor === "white";
		});
		if (white !== state.white) {
			state.white = white;
			applyColor();
		}
	}

	// ---- 往下捲收起、往上捲出現 ----
	let lastY = window.scrollY;
	function onScroll() {
		const y = window.scrollY;
		const visible = state.menuOpen || y <= lastY;
		lastY = y;
		if (visible !== state.visible) {
			state.visible = visible;
			swap(nav, visible, "translate-y-0", "-translate-y-full");
			if (!visible) setProject(false); // 收起時下拉面板一併關閉
		}
		measureColor();
	}

	// ---- Plan 下拉面板（桌機）----
	let closeTimer;
	function setProject(open) {
		clearTimeout(closeTimer);
		if (open) {
			const r = trigger.getBoundingClientRect();
			panel.style.left = `${r.left}px`;
			panel.style.top = `${r.bottom}px`;
		}
		state.projectOpen = open;
		swap(panel, open, "pointer-events-auto translate-y-0 opacity-100", "pointer-events-none -translate-y-2 opacity-0");
		swap(trigger.querySelector("[data-plus]"), open, "rotate-90", "");
		swap(trigger.querySelector("[data-plus-h]"), open, "opacity-0", "opacity-100");
	}
	// 延遲關閉：讓游標從 Plan 移進面板的過程不會誤關
	const scheduleClose = () => {
		clearTimeout(closeTimer);
		closeTimer = setTimeout(() => setProject(false), 150);
	};
	[trigger, panel].forEach((el) => {
		el.addEventListener("mouseenter", () => setProject(true));
		el.addEventListener("mouseleave", scheduleClose);
	});
	panel.querySelectorAll("a").forEach((a) => a.addEventListener("click", () => setProject(false)));

	// ---- 手機側邊選單 ----
	function setMobilePlan(open) {
		state.mobilePlanOpen = open;
		mobilePlanToggle.setAttribute("aria-expanded", String(open));
		mobilePlan.setAttribute("aria-hidden", String(!open));
		swap(mobilePlan, open, "pointer-events-auto mt-2 max-h-48 translate-y-0 opacity-100", "pointer-events-none mt-0 max-h-0 -translate-y-2 opacity-0");
		swap(mobilePlanToggle.querySelector("[data-plus]"), open, "rotate-90", "");
		swap(mobilePlanToggle.querySelector("[data-plus-h]"), open, "opacity-0", "opacity-100");
	}
	function setMenu(open) {
		state.menuOpen = open;
		toggle.setAttribute("aria-expanded", String(open));
		swap(mobile, open, "opacity-100 pointer-events-auto", "opacity-0 pointer-events-none");
		swap(drawer, open, "translate-x-0", "translate-x-full");
		swap(burger.top, open, "rotate-45 translate-y-0.5", "-translate-y-1");
		swap(burger.mid, open, "opacity-0", "opacity-100");
		swap(burger.bot, open, "-rotate-45 -translate-y-0.5", "translate-y-1");
		swap(logoLink, open, "opacity-0", "opacity-100"); // 選單展開時隱藏 logo
		document.body.style.overflow = open ? "hidden" : "auto";
		if (!open) setMobilePlan(false);
		if (open && !state.visible) {
			state.visible = true;
			swap(nav, true, "translate-y-0", "-translate-y-full");
		}
		applyColor();
	}
	toggle.addEventListener("click", () => setMenu(!state.menuOpen));
	backdrop.addEventListener("click", () => setMenu(false));
	mobilePlanToggle.addEventListener("click", () => setMobilePlan(!state.mobilePlanOpen));
	drawer.querySelectorAll("a").forEach((a) => a.addEventListener("click", () => setMenu(false)));

	window.addEventListener("scroll", onScroll, { passive: true });
	window.addEventListener("resize", measureColor);
	measureColor();
})();

// ============================================================
//  捲動進場：.headline 捲進畫面時由下往上淡入（樣式見 tailwind.css 的 .js-reveal）
// ============================================================
(function initReveal() {
	const els = document.querySelectorAll(".headline");
	const showAll = () => els.forEach((el) => el.classList.add("is-revealed"));
	if (reducedMotion || !("IntersectionObserver" in window)) return showAll();

	const io = new IntersectionObserver(
		(entries) => {
			for (const entry of entries) {
				if (!entry.isIntersecting) continue;
				// 比視窗還高的元素永遠到不了 70%，改用「已佔滿半個視窗」判斷
				const tallEnough = entry.intersectionRect.height >= window.innerHeight * 0.5;
				if (entry.intersectionRatio >= MOTION.reveal.viewFactor || tallEnough) {
					entry.target.classList.add("is-revealed");
					io.unobserve(entry.target);
				}
			}
		},
		{ threshold: [0, 0.25, 0.5, MOTION.reveal.viewFactor, 1] },
	);
	els.forEach((el) => io.observe(el));
})();

// ============================================================
//  圖片輪流淡入（手機版圖庫）
// ============================================================
document.querySelectorAll("[data-crossfade]").forEach((box) => {
	const imgs = box.querySelectorAll("img");
	if (imgs.length < 2) return;
	let current = 0;
	setInterval(() => {
		swap(imgs[current], false, "opacity-100", "opacity-0");
		current = (current + 1) % imgs.length;
		swap(imgs[current], true, "opacity-100", "opacity-0");
	}, MOTION.crossfadeInterval);
});

// ============================================================
//  回到頂部
// ============================================================
document.querySelectorAll("[data-to-top]").forEach((btn) =>
	btn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" })),
);

// ============================================================
//  首頁主視覺輪播
//  新圖用 clip-path 從一側「擦入」蓋住舊圖，內層圖反向位移產生視差；
//  左欄由上往下、右欄由下往上。舊圖（before）墊在下層，被蓋住後才重置。
//  初始畫面（第一張）已由 PHP 輸出，這裡只負責之後的切換。
// ============================================================
(function initBanner() {
	const root = document.querySelector("[data-banner]");
	if (!root) return;
	const B = MOTION.banner;

	const columns = [...root.querySelectorAll("[data-banner-col]")].map((el) => {
		const side = el.dataset.side;
		return {
			side,
			stagger: side === "right",
			items: [...el.children],
			active: 0,
			before: null,
			hiddenClip: side === "left" ? "inset(0 0 100% 0)" : "inset(100% 0 0 0)",
			offsetY: side === "left" ? `-${B.parallaxOffset}` : B.parallaxOffset,
		};
	});

	function render(col) {
		col.items.forEach((item, i) => {
			const isActive = i === col.active;
			const revealed = isActive || i === col.before;
			const inner = item.firstElementChild;
			item.style.clipPath = revealed ? "inset(0 0 0 0)" : col.hiddenClip;
			item.style.transition = isActive ? `clip-path ${B.duration}ms ${B.ease}` : "none";
			item.style.zIndex = isActive ? "2" : "1";
			inner.style.transform = revealed ? "translateY(0) scale(1)" : `translateY(${col.offsetY}) scale(${B.initialScale})`;
			inner.style.transition = isActive ? `transform ${B.duration}ms ${B.ease}` : "none";
		});
	}

	function advance(col) {
		if (col.items.length < 2) return;
		col.before = col.active;
		col.active = (col.active + 1) % col.items.length;
		render(col);
		// 動畫結束後清掉 before，讓舊圖回到隱藏初始態
		setTimeout(() => {
			col.before = null;
			render(col);
		}, B.duration + 100);
	}

	setInterval(() => {
		columns.forEach((col) => (col.stagger ? setTimeout(() => advance(col), B.stagger) : advance(col)));
	}, B.interval);
})();

// ============================================================
//  首頁照片牆：捲動視差 + 置中標語進場
//  - 視差：以照片牆中心對視窗中心的偏移當進度（-1 ～ +1），乘上每張圖的 data-speed，
//    再用 lerp 平滑追隨 → 慣性漂浮感
//  - 標語：在「釘住視窗中央的瞬間」才淡入上浮
// ============================================================
(function initGallery() {
	const root = document.querySelector("[data-gallery]");
	if (!root || reducedMotion) return;
	const G = MOTION.gallery;
	const section = root.querySelector("[data-gallery-items]");
	const items = [...root.querySelectorAll("[data-speed]")];
	const speeds = items.map((el) => Number(el.dataset.speed));
	const sticky = root.querySelector("[data-gallery-sticky]");
	const pill = root.querySelector("[data-gallery-pill]");

	// 標語初始狀態：透明 + 從中央下方 20px 準備上浮
	let pillRevealed = false;
	pill.style.transition = `opacity ${MOTION.slow}ms ${MOTION.easeStandard}, translate ${MOTION.slow}ms ${MOTION.easeSpring}`;
	pill.style.opacity = "0";
	pill.style.translate = "0 calc(-50% + 20px)";

	const current = speeds.map(() => 0);
	function tick() {
		const vh = window.innerHeight;
		if (!pillRevealed && sticky.getBoundingClientRect().top <= vh / 2 + 1) {
			pillRevealed = true;
			pill.style.opacity = "1";
			pill.style.translate = "0 -50%";
		}
		const rect = section.getBoundingClientRect();
		const raw = (vh / 2 - (rect.top + rect.height / 2)) / ((vh + rect.height) / 2);
		const progress = Math.max(-1, Math.min(1, raw));
		const maxShift = window.innerWidth >= 768 ? G.maxShift : G.maxShiftMobile;
		items.forEach((el, i) => {
			const target = progress * speeds[i] * maxShift;
			current[i] += (target - current[i]) * G.lerp;
			el.style.transform = `translate3d(0, ${current[i].toFixed(2)}px, 0)`;
		});
		requestAnimationFrame(tick);
	}
	requestAnimationFrame(tick);
})();

// ============================================================
//  首頁「報導」手機輪播（Swiper，只在首頁載入）
// ============================================================
(function initArticleSwiper() {
	const el = document.querySelector(".article-swiper-container");
	if (!el || !window.Swiper) return;
	new window.Swiper(el, {
		spaceBetween: 20,
		slidesPerView: 1,
		pagination: { el: el.querySelector(".swiper-pagination"), clickable: true },
	});
})();
