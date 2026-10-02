// ============================================================
//  後台：文章內文編輯器
//  - 所見即所得：工具列按鈕用瀏覽器內建的編輯指令（document.execCommand）
//  - 「HTML」按鈕切換成原始碼編輯
//  - 插入圖片：上傳到 admin/index.php（action=upload_image），回傳網址後插進游標位置
//  - 貼上網頁／Word 的內容時先拿掉樣式，存檔時伺服器還會再用白名單過濾一次
//  另外處理「刪除前確認」、離開前提醒與送出時的按鈕文字。
// ============================================================
(function () {
	// ---- 刪除前確認 ----
	document.querySelectorAll("form[data-confirm]").forEach((form) =>
		form.addEventListener("submit", (e) => {
			if (!window.confirm(form.dataset.confirm)) e.preventDefault();
		}),
	);

	const form = document.querySelector("[data-article-form]");
	if (!form) return;

	const content = form.querySelector("[data-content]"); // 實際送出的欄位
	const editor = form.querySelector("[data-editor]");
	const source = form.querySelector("[data-source]");
	const toolbar = form.querySelector("[data-toolbar]");
	const sourceButton = toolbar.querySelector('[data-action="source"]');
	const imageInput = form.querySelector("[data-image-input]");
	const csrf = form.querySelector('input[name="csrf"]').value;
	let sourceMode = false;
	let savedRange = null; // 選圖片時編輯區會失去焦點，先記住游標位置
	let dirty = false;

	editor.innerHTML = content.value;
	document.execCommand("defaultParagraphSeparator", false, "p"); // 按 Enter 產生 <p> 而不是 <div>
	document.execCommand("styleWithCSS", false, false); // 粗體用 <b>，不用 style="font-weight…"

	// 沒有文字、圖片、影片、表格就算空的
	const isEmpty = () => !editor.textContent.trim() && !editor.querySelector("img, iframe, hr, table");

	// 空的編輯區一點進去就放一個段落，第一行打的字才會是 <p>；離開時還是空的就清掉，讓提示文字出現
	editor.addEventListener("focus", () => {
		if (!isEmpty()) return;
		editor.innerHTML = "<p><br></p>";
		placeCaret(editor.firstChild, true);
	});
	editor.addEventListener("blur", () => {
		if (isEmpty()) editor.innerHTML = "";
	});

	// Chrome 的清單等指令有時會把區塊塞進段落裡（<p><ul>…</ul></p>），這是不合法的 HTML。
	// 每次操作後把這種段落拆開：區塊提出來，其餘的文字各自包回段落。游標位置保持不動。
	const BLOCK = /^(P|UL|OL|H2|H3|H4|BLOCKQUOTE|TABLE|HR|FIGURE)$/;
	function normalize() {
		const sel = window.getSelection();
		const caret = sel.rangeCount ? [sel.anchorNode, sel.anchorOffset] : null;
		editor.querySelectorAll("p").forEach((p) => {
			if (![...p.children].some((c) => BLOCK.test(c.nodeName))) return;
			const parts = [];
			let inline = null;
			[...p.childNodes].forEach((n) => {
				if (BLOCK.test(n.nodeName)) {
					inline = null;
					parts.push(n);
				} else {
					if (!inline) parts.push((inline = document.createElement("p")));
					inline.appendChild(n);
				}
			});
			p.replaceWith(...parts.filter((n) => n.nodeName !== "P" || n.textContent.trim() || n.querySelector("img, br")));
		});
		if (caret && caret[0].isConnected) {
			sel.collapse(caret[0], Math.min(caret[1], caret[0].length ?? caret[0].childNodes.length));
		}
	}
	editor.addEventListener("input", normalize);

	function placeCaret(node, atStart) {
		const range = document.createRange();
		range.selectNodeContents(node);
		range.collapse(atStart);
		const sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	}

	// ---- 工具列 ----
	// mousedown 時不讓按鈕搶走焦點，編輯區的選取範圍才不會消失
	toolbar.addEventListener("mousedown", (e) => {
		if (e.target.closest("button")) e.preventDefault();
	});

	toolbar.addEventListener("click", (e) => {
		const btn = e.target.closest("button");
		if (!btn) return;
		const { cmd, block, action } = btn.dataset;
		if (action === "source") return toggleSource();
		if (sourceMode) return;
		editor.focus();
		if (cmd) document.execCommand(cmd, false, null);
		// 再按一次同樣的段落格式 → 變回一般段落
		if (block) document.execCommand("formatBlock", false, currentBlock() === block ? "p" : block);
		if (action === "link") addLink();
		if (action === "image") {
			const sel = window.getSelection();
			savedRange = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
			imageInput.click();
		}
		normalize();
		dirty = true;
		updateToolbar();
	});

	function currentBlock() {
		return String(document.queryCommandValue("formatBlock")).toLowerCase().replace(/[<>]/g, "");
	}

	// 游標所在的格式在工具列上反白（粗體、標題…）
	function updateToolbar() {
		const sel = window.getSelection();
		if (sourceMode || !sel.rangeCount || !editor.contains(sel.anchorNode)) return;
		const block = currentBlock();
		toolbar.querySelectorAll("[data-cmd], [data-block]").forEach((btn) => {
			const on = btn.dataset.block ? btn.dataset.block === block : document.queryCommandState(btn.dataset.cmd);
			btn.setAttribute("aria-pressed", on ? "true" : "false");
		});
	}
	document.addEventListener("selectionchange", updateToolbar);

	function addLink() {
		const sel = window.getSelection();
		if (!sel.rangeCount || sel.isCollapsed) {
			window.alert("請先選取要加上連結的文字");
			return;
		}
		const url = window.prompt("連結網址（例如 https://www.instagram.com/…）", "https://");
		if (url && url.trim() !== "https://") document.execCommand("createLink", false, url.trim());
	}

	// ---- 插入圖片 ----
	imageInput.addEventListener("change", async () => {
		const file = imageInput.files[0];
		imageInput.value = "";
		if (!file) return;
		const btn = toolbar.querySelector('[data-action="image"]');
		const label = btn.textContent;
		btn.disabled = true;
		btn.textContent = "上傳中…";
		try {
			const data = new FormData();
			data.append("csrf", csrf);
			data.append("action", "upload_image");
			data.append("image", file);
			const res = await fetch(window.location.href, { method: "POST", body: data });
			const json = await res.json().catch(() => ({ error: "上傳失敗，可能已經登出，請重新整理後再試" }));
			if (!res.ok || !json.url) throw new Error(json.error || "上傳失敗");
			editor.focus();
			if (savedRange && editor.contains(savedRange.commonAncestorContainer)) {
				const sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(savedRange);
			} else {
				placeCaret(editor, false);
			}
			document.execCommand("insertHTML", false, `<p><img src="${json.url}" alt=""></p>`);
			normalize();
			dirty = true;
		} catch (err) {
			window.alert(err.message);
		} finally {
			btn.disabled = sourceMode;
			btn.textContent = label;
		}
	});

	// ---- 貼上：拿掉樣式、class 等，只留內容與基本格式 ----
	editor.addEventListener("paste", (e) => {
		const html = e.clipboardData.getData("text/html");
		if (!html) return; // 純文字照瀏覽器預設貼上
		e.preventDefault();
		const doc = new DOMParser().parseFromString(html, "text/html");
		doc.querySelectorAll("style, script, meta, link, title").forEach((el) => el.remove());
		doc.body.querySelectorAll("*").forEach((el) =>
			[...el.attributes].forEach((a) => {
				if (!["href", "src", "alt", "colspan", "rowspan"].includes(a.name)) el.removeAttribute(a.name);
			}),
		);
		doc.body.querySelectorAll("span, font").forEach((el) => el.replaceWith(...el.childNodes));
		document.execCommand("insertHTML", false, doc.body.innerHTML);
		normalize();
	});

	// ---- HTML 原始碼模式 ----
	// 每個區塊標籤後面換行，原始碼才不會擠成一長行
	const prettify = (html) =>
		html
			.replace(/(<\/(p|h[1-6]|li|ul|ol|blockquote|figure|table|thead|tbody|tr)>|<hr>)\n?/gi, "$1\n")
			.replace(/(<(ul|ol|table|thead|tbody|tr)>)\n?/gi, "$1\n")
			.trim();

	function toggleSource() {
		sourceMode = !sourceMode;
		if (sourceMode) {
			source.value = isEmpty() ? "" : prettify(editor.innerHTML);
		} else {
			editor.innerHTML = source.value;
		}
		editor.hidden = sourceMode;
		source.hidden = !sourceMode;
		sourceButton.setAttribute("aria-pressed", String(sourceMode));
		toolbar.querySelectorAll("button:not([data-action='source'])").forEach((b) => (b.disabled = sourceMode));
		if (sourceMode) autoGrow();
		(sourceMode ? source : editor).focus();
	}

	// 原始碼框跟著內容長高，不用在小框框裡捲動
	function autoGrow() {
		source.style.height = "auto";
		source.style.height = source.scrollHeight + "px";
	}
	source.addEventListener("input", autoGrow);

	// ---- 上架／下架時間：flatpickr 時間選擇器 ----
	// 送出的值維持 2026-10-05T09:00（伺服器原本就讀這個格式），畫面上顯示 2026-10-05 09:00；也可以直接打字
	if (window.flatpickr) {
		const pickers = [];
		form.querySelectorAll("[data-datetime]").forEach((input) => {
			const picker = window.flatpickr(input, {
				locale: "zh_tw",
				enableTime: true,
				time_24hr: true,
				minuteIncrement: 5,
				dateFormat: "Y-m-d\\TH:i",
				altInput: true,
				altFormat: "Y-m-d H:i",
				allowInput: true,
				disableMobile: true, // 手機也用同一個選擇器，不切換成系統內建的
				onChange: () => (dirty = true),
			});
			input.closest("[data-datetime-field]").querySelector("[data-datetime-clear]").addEventListener("click", () => {
				picker.clear();
				dirty = true;
			});
			pickers.push(picker);
		});
		// 電腦版的側欄是 sticky：捲動時輸入框固定在畫面上，但面板是貼在整個頁面上的，會被捲走而錯位。
		// 所以面板打開時一捲動（滾輪、拖捲軸、側欄自己內部捲動都算）就關掉面板。
		// 只在電腦版：手機版側欄不固定，面板本來就跟著走；而且手機點輸入框時瀏覽器會自動捲動，會一打開就被關掉
		const desktop = window.matchMedia("(min-width: 1024px)");
		document.addEventListener("scroll", () => desktop.matches && pickers.forEach((p) => p.isOpen && p.close()), { capture: true, passive: true });
	}

	// ---- 離開前提醒、送出 ----
	form.addEventListener("input", () => (dirty = true));
	window.addEventListener("beforeunload", (e) => {
		if (dirty) e.preventDefault();
	});

	form.addEventListener("submit", () => {
		content.value = sourceMode ? source.value : isEmpty() ? "" : editor.innerHTML;
		dirty = false;
		const submit = form.querySelector("[data-submit-label]");
		submit.disabled = true;
		submit.textContent = submit.dataset.submitLabel;
	});
})();
