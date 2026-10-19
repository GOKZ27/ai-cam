# Cara Menjalankan AI Cam (Laragon / XAMPP, tanpa port)

Aplikasi ini punya **2 bagian** yang harus jalan bersamaan:

1. **Laravel** — halaman + API. Yang kamu buka di browser.
2. **Layanan Python** — AI (YOLO + deskripsi). Dipanggil Laravel di
   `127.0.0.1:8001` (internal; **kamu tidak pernah mengetik port ini**).

Karena frontend memakai URL relatif, aplikasi bisa dibuka di alamat apa saja
(mis. `http://ai-cam.test/ai-cam`) **tanpa mengubah kode** dan **tanpa port**.

---

## Yang perlu dipasang

| Kebutuhan | Laragon | XAMPP |
| --- | --- | --- |
| PHP **8.3+** | sudah ada | XAMPP biasanya 8.2 → **tambah/upgrade PHP 8.3** |
| Composer | sudah ada | **perlu install** (getcomposer.org) |
| Apache (web server) | sudah ada | sudah ada |
| Git | sudah ada | perlu (atau unduh ZIP) |
| **Python 3.13** | ❌ perlu install | ❌ perlu install |
| Node.js | tidak perlu | tidak perlu |
| MySQL | tidak perlu (pakai SQLite) | tidak perlu |

> **Jujur:** tidak bisa benar-benar "nol install", karena AI-nya dijalankan di
> Python. Kalau Laragon/XAMPP sudah ada, tambahan yang wajib hanyalah **Python**.
> Untuk XAMPP tambah **Composer** (dan **PHP 8.3** bila versinya masih 8.2).
> Node.js tidak diperlukan sama sekali.

---

## Sekali saja (setup)

### 1. Unduh project
Clone (atau ekstrak ZIP) ke web root, lalu: **`cd` ke folder project**.

Kalau di-clone ke `C:\laragon\www\ai-cam` atau `C:\xampp\htdocs\ai-cam`,
sesuaikan `define ROOT` / `DocumentRoot` otomatis sudah diurus `setup.bat`.

### 2. Jalankan `setup.bat` (satu kali)
**Klik dua kali `setup.bat`**, lalu pilih web server yang kamu pakai
(`1` = Laragon, `2` = XAMPP). Skrip akan melakukan semuanya:

| Langkah | Yang dilakukan |
| --- | --- |
| 1 | Menanyakan web server: Laragon atau XAMPP |
| 2 | Mencari **PHP 8.3+** (PATH → folder Laragon → folder XAMPP) |
| 3 | `composer install`, buat `.env`, `key:generate`, `database/database.sqlite`, `migrate` |
| 4 | Python: cek Python 3.10+, buat `python-service\.venv`, `pip install -r requirements.txt` |
| 5 | Bikin vhost `http://ai-cam.test/ai-cam` + tambah baris `hosts` |

Selesai setup, jangan lupa **restart web server** (Laragon: Stop All → Start All,
XAMPP: Stop Apache → Start Apache). Kalau kamu tidak memakai Apache sama sekali:
`setup.bat --no-server`.

Catatan:
- Tanpa Administrator, baris `hosts` tidak bisa ditambahkan otomatis; skrip akan
  memberi tahu cara menambahkannya manual.
- File konfigurasi di luar project dicadangkan lebih dulu (`.ai-cam.bak`).
- Argumen opsional: `setup.bat --laragon`, `setup.bat --xampp`, `setup.bat --no-server`.

Manual (bila tidak mau pakai skrip):
```cmd
composer install
copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite
php artisan migrate --force
```

### 3. Siapkan & jalankan layanan Python
`setup.bat` sudah membuat `python-service\.venv` dan memasang dependensinya.
Kalau perlu diulang manual (sekali saja, agak lama, ~beberapa menit):

```cmd
cd python-service
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
```

**Setiap kali** mau memakai aplikasi — jalankan dari **root project**:

```cmd
php artisan ai
```

Sama seperti `php artisan serve`, hanya beda nama. Bisa juga `php artisan ai:serve`
(nama panjangnya) dan ganti port: `php artisan ai --port=8002`.

**Jendela ini harus tetap terbuka** (menutupnya = mematikan layanan AI).
Perintah ini otomatis memakai interpreter `.venv` di dalam `python-service`,
jadi kamu tidak perlu `cd` atau menyebut path Python.

Bila `python` tidak dikenali saat membuat venv, pakai `py -3.13`, mis. `py -3.13 -m venv .venv`.

Penting: isi `GEMINI_API_KEY` pada `python-service\.env` bila mau pakai Gemini
(tidak wajib; bisa pakai Ollama lokal). Kunci Gemini **hanya** di file ini.

---

## Menjalankan aplikasi (tanpa port)

> Bagian ini **sudah otomatis** oleh `setup.bat`. Teks di bawah hanya untuk
> pemasangan/pengecekan manual atau bila kamu memindahkan project ke folder lain.
>
> Penting: DocumentRoot harus menunjuk ke folder **`public`** (bukan root project),
> dan `http://ai-cam.test/ai-cam` adalah halaman aplikasinya.

### A. Laragon (paling mudah)
1. Letakkan project di dalam `C:\laragon\www\ai-cam` (langsung di dalam `www`).
2. Klik **Reload** di Laragon. Laragon otomatis membuat `http://ai-cam.test`.
3. Buka **http://ai-cam.test** (otomatis diarahkan ke halaman aplikasi).

Jika project tidak berada langsung di `www` (mis. bercabang seperti
`www\Aicam\ai-cam`), buat vhost manual: salin `deploy/apache/ai-cam.test.conf` ke
`C:\laragon\etc\apache2\sites-enabled\`, sesuaikan `ROOT` ke
`.../ai-cam/public`, tambahkan baris `hosts` (lihat di bawah), lalu Reload.
Itulah yang dilakukan `setup.bat` secara otomatis (file `ai-cam.test.conf`).

### B. XAMPP
> `setup.bat` mengurus 4 langkah di bawah (backup `.ai-cam.bak` dibuat dulu).
1. Letakkan project di `C:\xampp\htdocs\ai-cam`.
2. Buka `C:\xampp\apache\conf\httpd.conf`, pastikan baris ini aktif
   (tanpa `#`): `Include conf/extra/httpd-vhosts.conf`.
3. Tambahkan blok berikut ke `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
   ```apache
   <VirtualHost *:80>
       ServerName ai-cam.test
       DocumentRoot "C:/xampp/htdocs/ai-cam/public"
       <Directory "C:/xampp/htdocs/ai-cam/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
4. Buka `C:\xampp\php\php.ini`, pastikan aktif (tanpa `;`):
   `pdo_sqlite`, `sqlite3`, `fileinfo`, `mbstring`, `openssl`, `curl`.
5. Restart Apache, buka **http://ai-cam.test**.

### Daftar `hosts` (wajib untuk A & B)
Buka Notepad **sebagai Administrator** → `C:\Windows\System32\drivers\etc\hosts`,
tambahkan baris:
```
127.0.0.1 ai-cam.test
```
(Laragon biasanya menambahkannya otomatis saat Reload.)

### C. Uji cepat tanpa Apache (pakai port)
```cmd
set PHP_CLI_SERVER_WORKERS=4
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```
→ `http://127.0.0.1:8000/ai-cam`. Ini memakai port, hanya untuk uji cepat.
Saat mode realtime, `artisan serve` perlu worker (di atas) atau lebih baik pakai
Apache.

---

## Urutan menjalankan (setelah setup)
1. Jalankan layanan AI: **`php artisan ai`** (biarkan jendelanya terbuka).
2. Jalankan Laragon/XAMPP (Apache).
3. Buka **http://ai-cam.test**.

---

## Troubleshooting

| Gejala | Solusi |
| --- | --- |
| Halaman 403/404 | DocumentRoot harus ke folder **`public`**, bukan root project |
| `composer` error soal versi PHP | PHP harus **8.3+** |
| "Tidak dapat menghubungi server" | Layanan Python belum jalan → jalankan `php artisan ai` |
| YOLO "Gagal, mencoba lagi" | Pakai Apache, atau `artisan serve` dengan `--no-reload` + `PHP_CLI_SERVER_WORKERS=4` |
| Vision kosong / "Kuota habis" | Isi `GEMINI_API_KEY`, atau jalankan Ollama (`qwen2.5vl:3b`) |
| Perubahan kode Python tidak berefek | Layanan Python tidak `--reload` → hentikan (Ctrl+C) & jalankan ulang `php artisan ai` |
