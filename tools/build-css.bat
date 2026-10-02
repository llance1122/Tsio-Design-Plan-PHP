@echo off
chcp 65001 >nul
rem ============================================================
rem  編譯網站樣式：assets\css\tailwind.css → assets\css\app.css
rem  有新增或修改 Tailwind class 時才需要執行（只改文字、圖片不用）
rem
rem  第一次使用前，先下載 Tailwind 編譯工具（單一 exe，不需要 Node）：
rem    https://github.com/tailwindlabs/tailwindcss/releases/tag/v4.1.11
rem    下載 tailwindcss-windows-x64.exe，改名成 tailwindcss.exe 放在 tools\ 資料夾
rem ============================================================
cd /d "%~dp0.."
if not exist tools\tailwindcss.exe (
  echo 找不到 tools\tailwindcss.exe，請先依照本檔最上方的說明下載。
  exit /b 1
)
tools\tailwindcss.exe -i assets\css\tailwind.css -o assets\css\app.css --minify
