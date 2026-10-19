@echo off
setlocal enableextensions enabledelayedexpansion
chcp 65001 >nul
title AI Cam - Setup Otomatis
cd /d "%~dp0"

set "PROJECT=%~dp0"
if "%PROJECT:~-1%"=="\" set "PROJECT=%PROJECT:~0,-1%"

set "SITE=ai-cam.test"
set "URL=http://%SITE%/ai-cam"
set "LARAGON=C:\laragon"
set "XAMPP=C:\xampp"

rem Argumen (opsional):
rem   setup.bat --laragon    pilih Laragon tanpa menu
rem   setup.bat --xampp      pilih XAMPP tanpa menu
rem   setup.bat --no-server  lewati pengaturan Apache/vhost/hosts
set "SERVER="
set "SKIP_SERVER="

:PARSE_ARGS
if "%~1"=="" goto :ARGS_DONE
if /i "%~1"=="--no-server" set "SKIP_SERVER=1"
if /i "%~1"=="--laragon" set "SERVER=Laragon"
if /i "%~1"=="--xampp" set "SERVER=XAMPP"
shift
goto :PARSE_ARGS
:ARGS_DONE
if defined SKIP_SERVER set "SERVER="

echo ==========================================================
echo  AI Cam - Setup Otomatis
echo ==========================================================
echo  Skrip ini menyiapkan:
echo    [1] Pilihan web server (Laragon / XAMPP)
echo    [2] PHP 8.3+
echo    [3] Laravel: composer install, .env, key, database, migrate
echo    [4] Layanan AI Python: venv + dependensi
echo    [5] Vhost %SITE% + baris hosts
echo.
echo  Jangan tutup jendela ini sampai selesai.
echo ==========================================================
echo.

rem ============ 1. PILIH WEB SERVER ============
if defined SKIP_SERVER (
    echo [1/5] Dilewati: pemilihan web server ^(--no-server^).
    goto :SERVER_DONE
)
if defined SERVER (
    echo [1/5] Web server: %SERVER% ^(dari argumen^)
    goto :SERVER_DONE
)

:MENU_SERVER
echo [1/5] Pilih web server yang SUDAH kamu jalankan:
echo.
echo        1 = Laragon
echo        2 = XAMPP
echo.
choice /c 12 /n /m "Tekan 1 untuk Laragon, 2 untuk XAMPP: "
if errorlevel 255 (
    echo.
    echo [ERROR] Tidak ada input. Jalankan setup.bat dari jendela CMD, atau pakai:
    echo         setup.bat --laragon   atau   setup.bat --xampp
    goto :FAIL
)
if errorlevel 2 if not errorlevel 3 set "SERVER=XAMPP"
if errorlevel 1 if not errorlevel 2 set "SERVER=Laragon"
if not defined SERVER (
    echo.
    echo [PERINGATAN] Pilihan tidak dikenal. Coba lagi.
    echo.
    goto :MENU_SERVER
)
echo.
echo [OK] Web server: %SERVER%

:SERVER_DONE

rem ============ 2. PHP 8.3+ ============
echo.
echo [2/5] Mencari PHP 8.3+ ...

if /i "%SERVER%"=="Laragon" call :LOCATE_LARAGON
if /i "%SERVER%"=="XAMPP" call :LOCATE_XAMPP

set "PHP="

if /i "%SERVER%"=="XAMPP" (
    if exist "%XAMPP%\php\php.exe" (
        "%XAMPP%\php\php.exe" -r "exit(version_compare(PHP_VERSION,'8.3.0','>=')?0:1);" >nul 2>nul
        if not errorlevel 1 set "PHP=%XAMPP%\php\php.exe"
    )
)

if /i "%SERVER%"=="Laragon" (
    for /f "delims=" %%D in ('dir /b /ad /o-n "%LARAGON%\bin\php\php-8.*" 2^>nul') do (
        if not defined PHP (
            if exist "%LARAGON%\bin\php\%%D\php.exe" (
                "%LARAGON%\bin\php\%%D\php.exe" -r "exit(version_compare(PHP_VERSION,'8.3.0','>=')?0:1);" >nul 2>nul
                if not errorlevel 1 set "PHP=%LARAGON%\bin\php\%%D\php.exe"
            )
        )
    )
)

if not defined PHP (
    for /f "delims=" %%P in ('where php 2^>nul') do (
        if not defined PHP (
            "%%P" -r "exit(version_compare(PHP_VERSION,'8.3.0','>=')?0:1);" >nul 2>nul
            if not errorlevel 1 set "PHP=%%P"
        )
    )
)

if not defined PHP (
    echo [ERROR] PHP 8.3+ tidak ditemukan.
    echo         Laragon: pastikan sudah ada PHP 8.3 di %LARAGON%\bin\php.
    echo         XAMPP  : upgrade PHP ke 8.3+ dari https://www.apachefriends.org/
    goto :FAIL
)

echo [OK] PHP: %PHP%

rem ============ 3. LARAVEL ============
echo.
echo [3/5] Menyiapkan Laravel ...

set "COMPOSER_EXE="
for /f "delims=" %%P in ('where composer.bat 2^>nul') do if not defined COMPOSER_EXE set "COMPOSER_EXE=%%P"
if not defined COMPOSER_EXE if exist "%LARAGON%\bin\composer\composer.bat" set "COMPOSER_EXE=%LARAGON%\bin\composer\composer.bat"
if not defined COMPOSER_EXE for /f "delims=" %%P in ('where composer 2^>nul') do if not defined COMPOSER_EXE set "COMPOSER_EXE=%%P"

if not defined COMPOSER_EXE (
    echo [ERROR] Composer tidak ditemukan.
    echo         Install dari https://getcomposer.org/download/ lalu jalankan setup.bat lagi.
    goto :FAIL
)

call "%COMPOSER_EXE%" --version >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Composer tidak bisa dijalankan: %COMPOSER_EXE%
    echo         Install ulang Composer dari https://getcomposer.org/download/
    goto :FAIL
)

echo [..] composer install
call "%COMPOSER_EXE%" install --no-interaction --prefer-dist
if errorlevel 1 (
    echo [ERROR] composer install gagal. Periksa koneksi internet lalu coba lagi.
    goto :FAIL
)
echo [OK] Dependensi PHP terpasang.

if not exist ".env" (
    if not exist ".env.example" (
        echo [ERROR] .env.example tidak ditemukan.
        goto :FAIL
    )
    copy /y ".env.example" ".env" >nul
    echo [OK] .env dibuat dari .env.example
) else (
    echo [OK] .env sudah ada, tidak ditimpa.
)

findstr /b /c:"APP_KEY=base64:" ".env" >nul 2>nul
if errorlevel 1 (
    "%PHP%" artisan key:generate --force
    if errorlevel 1 (
        echo [ERROR] Gagal membuat APP_KEY.
        goto :FAIL
    )
    echo [OK] APP_KEY dibuat.
) else (
    echo [OK] APP_KEY sudah ada.
)

if not exist "database\database.sqlite" type nul > "database\database.sqlite"

echo [..] migrate database
"%PHP%" artisan migrate --force
if errorlevel 1 (
    echo [ERROR] migrate gagal.
    goto :FAIL
)
echo [OK] Database siap.

rem ============ 4. LAYANAN AI PYTHON ============
echo.
echo [4/5] Menyiapkan layanan AI Python ...
echo       Pertama kali bisa 10-30 menit karena mengunduh torch/ultralytics.
echo       Butuh koneksi internet.

if not exist "python-service\requirements.txt" (
    echo [ERROR] python-service\requirements.txt tidak ditemukan.
    goto :FAIL
)

set "PY="
py -3.13 -c "import sys" >nul 2>nul
if not errorlevel 1 set "PY=py -3.13"
if not defined PY (
    py -3 -c "import sys" >nul 2>nul
    if not errorlevel 1 set "PY=py -3"
)
if not defined PY (
    python -c "import sys" >nul 2>nul
    if not errorlevel 1 set "PY=python"
)
if not defined PY (
    echo [ERROR] Python tidak ditemukan.
    echo         Install Python 3.13 dari https://www.python.org/downloads/
    echo         Saat install, centang "Add python.exe to PATH", lalu jalankan setup.bat lagi.
    goto :FAIL
)

%PY% -c "import sys; raise SystemExit(0 if sys.version_info>=(3,10) else 1)" >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Versi Python terlalu lama. Butuh Python 3.10+.
    goto :FAIL
)

if not exist "python-service\.venv\Scripts\python.exe" (
    echo [..] Membuat virtual environment python-service\.venv
    %PY% -m venv "python-service\.venv"
    if errorlevel 1 (
        echo [ERROR] Gagal membuat virtual environment Python.
        goto :FAIL
    )
    echo [OK] Virtual environment dibuat.
) else (
    echo [OK] Virtual environment sudah ada.
)

if not exist "python-service\.env" if exist "python-service\.env.example" (
    copy /y "python-service\.env.example" "python-service\.env" >nul
    echo [OK] python-service\.env dibuat dari .env.example
)

if not exist "python-service\.venv\.deps-installed" (
    echo [..] Memasang dependensi Python, mohon tunggu ...
    "python-service\.venv\Scripts\python.exe" -m pip install --upgrade pip
    "python-service\.venv\Scripts\python.exe" -m pip install -r "python-service\requirements.txt"
    if errorlevel 1 (
        echo [ERROR] Gagal memasang dependensi Python. Periksa koneksi internet.
        goto :FAIL
    )
    type nul > "python-service\.venv\.deps-installed"
    echo [OK] Dependensi Python terpasang.
) else (
    echo [OK] Dependensi Python sudah terpasang.
)

rem ============ 5. WEB SERVER + VHOST ============
echo.
if defined SKIP_SERVER (
    echo [5/5] Dilewati: pengaturan web server ^(--no-server^).
    goto :SUMMARY
)

echo [5/5] Mengatur %SERVER% agar aplikasi terbuka di %URL%

if /i "%SERVER%"=="Laragon" goto :CONF_LARAGON
if /i "%SERVER%"=="XAMPP" goto :CONF_XAMPP
goto :SUMMARY

:CONF_LARAGON
set "SITES=%LARAGON%\etc\apache2\sites-enabled"
if not exist "%SITES%" (
    echo [PERINGATAN] Folder sites-enabled Laragon tidak ditemukan: %SITES%
    echo             Vhost dilewati. Lihat SETUP.md bagian Laragon.
    goto :HOSTS
)

if exist "%SITES%\%SITE%.conf" if not exist "%SITES%\%SITE%.conf.bak" copy /y "%SITES%\%SITE%.conf" "%SITES%\%SITE%.conf.bak" >nul

(
echo # Dibuat otomatis oleh setup.bat
echo define ROOT "%PROJECT:\=/%/public"
echo define SITE "%SITE%"
echo.
echo ^<VirtualHost *:80^>
echo     DocumentRoot "${ROOT}"
echo     ServerName ${SITE}
echo     ServerAlias *.${SITE}
echo     ^<Directory "${ROOT}"^>
echo         AllowOverride All
echo         Require all granted
echo     ^</Directory^>
echo ^</VirtualHost^>
) > "%SITES%\%SITE%.conf"

if errorlevel 1 (
    echo [PERINGATAN] Gagal menulis %SITES%\%SITE%.conf
    echo             Coba jalankan setup.bat sebagai Administrator.
    goto :HOSTS
)

echo [OK] Vhost: %SITES%\%SITE%.conf
echo      DocumentRoot: %PROJECT%\public
echo [INFO] Di Laragon: klik "Stop All" lalu "Start All" agar vhost dibaca.
goto :HOSTS

:CONF_XAMPP
set "HTTPD=%XAMPP%\apache\conf\httpd.conf"
set "VHOSTS=%XAMPP%\apache\conf\extra\httpd-vhosts.conf"
set "PHPINI=%XAMPP%\php\php.ini"

if not exist "%HTTPD%" (
    echo [PERINGATAN] XAMPP tidak lengkap: %HTTPD% tidak ditemukan.
    echo             Vhost dilewati. Lihat SETUP.md bagian XAMPP.
    goto :SUMMARY
)

findstr /i /r /c:"^[ ]*Include[ ]*conf/extra/httpd-vhosts.conf" "%HTTPD%" >nul 2>nul
if errorlevel 1 (
    if not exist "%HTTPD%.ai-cam.bak" copy /y "%HTTPD%" "%HTTPD%.ai-cam.bak" >nul
    powershell -NoProfile -ExecutionPolicy Bypass -Command "$p='%HTTPD%'; $c=Get-Content -LiteralPath $p -Raw; $n=$c -replace '(?m)^\s*#\s*(Include\s+conf/extra/httpd-vhosts\.conf)\s*$','$1'; if($n -ne $c){ Set-Content -LiteralPath $p -Value $n }" >nul 2>nul
    findstr /i /r /c:"^[ ]*Include[ ]*conf/extra/httpd-vhosts.conf" "%HTTPD%" >nul 2>nul
    if errorlevel 1 (
        echo [PERINGATAN] Aktifkan manual di %HTTPD%:
        echo             hapus "#" pada baris: Include conf/extra/httpd-vhosts.conf
    ) else (
        echo [OK] Include vhosts diaktifkan di httpd.conf
    )
) else (
    echo [OK] Include vhosts sudah aktif.
)

findstr /i /c:"ServerName %SITE%" "%VHOSTS%" >nul 2>nul
if errorlevel 1 (
    if exist "%VHOSTS%" if not exist "%VHOSTS%.ai-cam.bak" copy /y "%VHOSTS%" "%VHOSTS%.ai-cam.bak" >nul
    (
        echo.
        echo # ==== AI Cam - dibuat otomatis oleh setup.bat ====
        echo ^<VirtualHost *:80^>
        echo     ServerName %SITE%
        echo     DocumentRoot "%PROJECT%\public"
        echo     ^<Directory "%PROJECT%\public"^>
        echo         AllowOverride All
        echo         Require all granted
        echo     ^</Directory^>
        echo ^</VirtualHost^>
    ) >> "%VHOSTS%"
    echo [OK] Vhost ditambahkan ke %VHOSTS%
) else (
    echo [OK] Vhost %SITE% sudah ada.
)

if exist "%PHPINI%" (
    if not exist "%PHPINI%.ai-cam.bak" copy /y "%PHPINI%" "%PHPINI%.ai-cam.bak" >nul
    powershell -NoProfile -ExecutionPolicy Bypass -Command "$p='%PHPINI%'; $c=Get-Content -LiteralPath $p -Raw; $n=$c -replace '(?m)^\s*;\s*extension=(pdo_sqlite|sqlite3|fileinfo|mbstring|openssl|curl)\s*$','extension=$1'; if($n -ne $c){ Set-Content -LiteralPath $p -Value $n }" >nul 2>nul

    set "MISSING="
    for %%E in (pdo_sqlite sqlite3 fileinfo mbstring openssl curl) do (
        findstr /i /r /c:"^[ ]*extension=%%E" "%PHPINI%" >nul 2>nul
        if errorlevel 1 set "MISSING=!MISSING! %%E"
    )
    if defined MISSING (
        echo [PERINGATAN] Ekstensi PHP belum aktif di %PHPINI%:!MISSING!
        echo             Hapus ";" pada baris extension tersebut.
    ) else (
        echo [OK] Ekstensi PHP (sqlite, mbstring, openssl, curl, fileinfo) aktif.
    )
)

echo [INFO] Di XAMPP Control Panel: Stop Apache, lalu Start Apache.
goto :HOSTS

:HOSTS
findstr /i /r /c:"^[ ]*127\.0\.0\.1[ ]*%SITE%" "%SystemRoot%\System32\drivers\etc\hosts" >nul 2>nul
if not errorlevel 1 (
    echo [OK] Baris hosts %SITE% sudah ada.
    goto :SUMMARY
)

rem Coba tambahkan otomatis (butuh Administrator)
powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=Join-Path $env:SystemRoot 'System32/drivers/etc/hosts'; $c=Get-Content -LiteralPath $p -Raw; if($c -notmatch '(?m)^\s*127\.0\.0\.1\s+%SITE%\s*$'){ Add-Content -LiteralPath $p -Value '127.0.0.1 %SITE%' }" >nul 2>nul

findstr /i /r /c:"^[ ]*127\.0\.0\.1[ ]*%SITE%" "%SystemRoot%\System32\drivers\etc\hosts" >nul 2>nul
if errorlevel 1 (
    echo [PERINGATAN] Tidak bisa mengubah file hosts ^(butuh Administrator^).
    echo             Tambahkan manual: buka Notepad sebagai Administrator,
    echo             buka file: %SystemRoot%\System32\drivers\etc\hosts
    echo             lalu tambahkan baris: 127.0.0.1 %SITE%
) else (
    echo [OK] Baris hosts %SITE% ditambahkan.
)
goto :SUMMARY

:SUMMARY
echo.
echo ==========================================================
echo  Setup selesai
echo ==========================================================
echo.
echo  Cara menjalankan aplikasi:
echo.
echo   1. Buka CMD baru, lalu masuk ke folder project:
echo        cd /d "%PROJECT%"
echo.
echo   2. Jalankan layanan AI, biarkan jendelanya terbuka:
echo        php artisan ai
echo.
if /i "%SERVER%"=="Laragon" echo   3. Laragon: klik "Stop All" lalu "Start All".
if /i "%SERVER%"=="XAMPP" echo   3. XAMPP Control Panel: Stop Apache, lalu Start Apache.
if defined SKIP_SERVER (
    echo.
    echo   3. Web server tidak diatur ^(--no-server^).
    echo      Jalankan: php artisan serve --no-reload
    echo      Lalu buka: http://127.0.0.1:8000/ai-cam
) else (
    echo.
    echo   4. Buka aplikasi: %URL%
)
echo.
echo  Panduan lengkap: SETUP.md
echo ==========================================================
echo.
pause
endlocal
exit /b 0

:LOCATE_LARAGON
if exist "%LARAGON%\bin\php" goto :eof
echo [PERINGATAN] Laragon tidak ditemukan di %LARAGON%.
set "LARAGON="
set /p "LARAGON=Folder instalasi Laragon (kosongkan = C:\laragon): "
if "%LARAGON%"=="" set "LARAGON=C:\laragon"
if not exist "!LARAGON!\bin\php" (
    echo [ERROR] Folder Laragon tidak valid: !LARAGON!
    goto :FAIL
)
goto :eof

:LOCATE_XAMPP
if exist "%XAMPP%\php\php.exe" goto :eof
echo [PERINGATAN] XAMPP tidak ditemukan di %XAMPP%.
set "XAMPP="
set /p "XAMPP=Folder instalasi XAMPP (kosongkan = C:\xampp): "
if "%XAMPP%"=="" set "XAMPP=C:\xampp"
if not exist "!XAMPP!\php\php.exe" (
    echo [ERROR] Folder XAMPP tidak valid: !XAMPP!
    goto :FAIL
)
goto :eof

:FAIL
echo.
echo Setup berhenti. Perbaiki pesan di atas, lalu jalankan setup.bat lagi.
echo Panduan: SETUP.md
echo.
pause
endlocal
exit /b 1
