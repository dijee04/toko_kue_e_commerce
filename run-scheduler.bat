@echo off
:: ============================================================
:: Laravel Scheduler Runner untuk toko-kue (development)
:: Jalankan file ini di background saat mengembangkan aplikasi.
:: Akan menjalankan `php artisan schedule:run` setiap 5 menit.
:: ============================================================

set "PHP=C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
set "APP_DIR=C:\Users\dell\toko-kue-main"

echo [Scheduler] Memulai loop schedule:run setiap 5 menit...
echo [Scheduler] Tekan Ctrl+C untuk berhenti.

:loop
echo [%DATE% %TIME%] Menjalankan schedule:run...
cd /d "%APP_DIR%"
"%PHP%" artisan schedule:run
echo [%DATE% %TIME%] Selesai. Tidur 5 menit...
timeout /t 300 /nobreak >nul
goto loop
