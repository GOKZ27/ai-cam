# DOKUMENTASI PROYEK — AI Cam (Asisten Penglihatan)

> Dokumen ini menjelaskan keseluruhan proyek **AI Cam**: tujuan, fitur, arsitektur,
> bahasa pemrograman, komponen AI/agen yang dipakai, status pengerjaan (progres),
> cara menjalankan, serta tanya‑jawab singkat untuk presentasi ke dosen.
>
> **Status ringkas: proyek berjalan end‑to‑end dan diperkirakan di tahap ± 50 %.**

---

## 1. Ringkasan Proyek

| Item | Keterangan |
| --- | --- |
| **Nama aplikasi** | AI Cam — Asisten Penglihatan |
| **Jenis** | Aplikasi web *single page* (kamera + suara) |
| **Tujuan** | Membantu tunanetra / low‑vision mengenali benda di sekitarnya secara otomatis |
| **Cara kerja singkat** | Kamera → deteksi objek (YOLO) → deskripsi (Gemini/Ollama) → dibacakan (text‑to‑speech Bahasa Indonesia) |
| **Bahasa keluaran** | Bahasa Indonesia (teks di layar dan suara) |
| **Target platform** | Browser modern (Chrome/Edge/Firefox) via `localhost` atau HTTPS |

### Latar belakang / masalah
Tunanetra sulit mengetahui benda di sekitarnya tanpa menyentuh atau bertanya.
Aplikasi ini memanfaatkan kamera ponsel/laptop untuk "melihat" lalu **membacakan**
apa yang ada di depan kamera, tanpa perlu menekan tombol berulang kali.

### Manfaat
- Memberi informasi objek secara *realtime* dan otomatis.
- Bisa berjalan **lokal** (tanpa internet) maupun **cloud** (lebih akurat).
- Antarmuka aksesibel: kontras tinggi, target sentuh besar, ramah pembaca layar.

---

## 2. Fitur Utama

1. **Mode Realtime otomatis** — cukup arahkan kamera; sistem menganalisis sendiri
   (deteksi tiap ±1,2 detik, deskripsi tiap 10 detik / dapat diubah). Hasil deteksi
   langsung diucapkan tanpa menunggu analisis vision selesai.
2. **Deteksi objek lokal (YOLO)** — mengenali 80 kategori benda umum (orang, botol,
   kursi, ponsel, dsb.) dan menggambar kotak di atas objek.
3. **Deskripsi lingkungan (Vision AI)** — kalimat deskripsi dalam Bahasa Indonesia,
   mis. "Di tengah terlihat botol air dengan tutup hijau."
4. **Multi‑provider vision** — Gemini (cloud, lebih akurat) dengan **fallback otomatis**
   ke Ollama (lokal) bila kuota/internet bermasalah.
5. **Text‑to‑Speech Indonesia** — hasil dibacakan otomatis; ada anti‑pengulangan suara.
6. **Kontrol aksesibel** — Mulai, Jeda, Suara On/Off, Analisis Sekarang, Berhenti;
   semuanya bisa lewat keyboard.
7. **Pengaturan interval** — pengguna memilih jeda analisis (5–60 detik).
8. **Indikator status** — status kamera, YOLO, Vision AI, dan suara ditampilkan jelas.
9. **Perlindungan privasi** — pemberitahuan bila gambar dikirim ke cloud (Gemini).
10. **Pilih sumber kamera** — daftar perangkat kamera (mis. kamera HP via **Iriun Webcam**,
    atau webcam bawaan) dapat diganti langsung tanpa menutup aplikasi.
11. **Diagnostik Sistem (self‑test)** — tombol "Jalankan Uji" mengukur kecepatan server,
    kamera, YOLO, Vision AI, dan suara, menandai tiap bagian **Optimal / Bisa lebih baik /
    Bermasalah**, lalu merangkum bagian mana yang **belum maksimal**.
12. **Mode "Ajar Objek"** — bekukan gambar, tarik kotak pada benda baru, lalu ketik
    namanya. Contoh tersimpan sebagai **data latih** agar model bisa mengenali benda
    di luar 80 kategori standar (COCO) setelah dilatih ulang.

---

## 3. Arsitektur Sistem

Aplikasi terdiri dari **dua layanan (2 services)**:

```
┌─────────────────────────────┐
│  Browser (Pengguna)         │
│  - Kamera (MediaDevices)    │
│  - Overlay kotak + UI       │
│  - Text‑to‑Speech (id-ID)   │
└──────────────┬──────────────┘
               │ HTTP (multipart gambar JPEG)
               ▼
┌─────────────────────────────┐
│  Laravel (PHP) — port 8000  │
│  - Halaman UI (Blade)       │
│  - Validasi upload gambar    │
│  - Proxy ke layanan Python  │
└──────────────┬──────────────┘
               │ HTTP (Http::attach)
               ▼
┌─────────────────────────────┐
│  FastAPI (Python) port 8001 │
│  - /detect   → YOLO         │
│  - /describe → Gemini/Ollama│
│  - /health   → status       │
└──────────────┬──────────────┘
        ┌──────┴───────┐
        ▼              ▼
  Gemini (cloud)   Ollama (lokal)
```

**Poin penting:** Laravel **tidak** menjalankan AI. Ia hanya menerima gambar,
memvalidasi, lalu meneruskannya (proxy) ke layanan Python. Ini memisahkan
"aplikasi web" dan "mesin AI" sehingga lebih rapi dan mudah dikembangkan.

### Alur data satu kali analysis
1. Browser mengambil frame kamera ke `<canvas>` → JPEG.
2. Gambar dikirim ke Laravel (`POST /api/ai-cam/detect` atau `/describe`).
3. Laravel memvalidasi (jenis file, ukuran maksimal 8 MB) lalu meneruskan ke Python.
4. Python menjalankan YOLO (deteksi) dan/atau model vision (deskripsi).
5. Hasil dikembalikan sebagai JSON (label, confidence, deskripsi, provider, waktu).
6. Browser menggambar kotak, menampilkan teks, dan **membacakannya**.

---

## 4. Bahasa Pemrograman & Teknologi

### 4.1 Bahasa pemrograman yang dipakai
| Bahasa | Dipakai untuk |
| --- | --- |
| **PHP 8.3** | Backend web (Laravel): routing, controller, validasi, proxy |
| **Python 3.13** | Layanan AI (FastAPI): YOLO, Gemini, Ollama |
| **JavaScript (ES6+)** | Logika frontend: kamera, fetch API, overlay, TTS |
| **HTML5** | Struktur halaman & aksesibilitas |
| **CSS3** | Tampilan (tema gelap/glass, responsif) |
| **Blade** | Templating HTML Laravel (`.blade.php`) |
| **SQL (SQLite)** | Database bawaan (migrasi siap; proyek belum menyimpan data user) |

### 4.2 Framework & library utama
| Bagian | Teknologi |
| --- | --- |
| Backend web | **Laravel 13.17** (Composer) |
| Layanan AI | **FastAPI** + **Uvicorn** (Python) |
| Deteksi objek | **Ultralytics YOLO11** (PyTorch) |
| Vision cloud | **google-genai** (SDK resmi Gemini) |
| Vision lokal | **Ollama** (`qwen2.5vl:3b`) |
| Gambar/foto | **Pillow (PIL)** |
| Suara | **Web Speech API** (bawaan browser) |
| Pengujian | **PHPUnit** (PHP), **unittest** (Python), **Node.js** (JS) |
| Linter | **Laravel Pint** |

> Frontend sengaja memakai **CSS/JS inline** (tanpa build Vite/Tailwind) agar
> aplikasi bisa langsung dijalankan tanpa proses kompilasi aset.

### 4.3 Untuk apa masing‑masing & kenapa dipilih
| Teknologi | Untuk apa | Kenapa dipilih |
| --- | --- | --- |
| **Laravel (PHP)** | Halaman web, routing, validasi upload, proxy ke Python, keamanan kunci API | Framework matang & cepat dikembangkan, aman, praktis untuk aplikasi web |
| **Python + FastAPI** | Menjalankan model AI: YOLO (deteksi), Gemini & Ollama (deskripsi) | Ekosistem AI/ML terbaik (PyTorch, Ultralytics, google‑genai); FastAPI ringan dan cepat |
| **JavaScript** | Akses kamera, kirim gambar, menggambar kotak, memanggil suara | Satu‑satunya bahasa yang berjalan di browser untuk kamera & text‑to‑speech |
| **HTML/CSS (Blade)** | Tampilan dan aksesibilitas antarmuka | Standar web; Blade menyatukan template dengan PHP |
| **SQLite** | Database (disiapkan untuk riwayat & pengguna) | Ringan, tanpa server, cukup untuk pengembangan |

**Kenapa dua bahasa (PHP + Python)?**
- **Kenapa pakai Python?** Karena AI/ML (YOLO, model vision) hidup di ekosistem Python;
  PHP tidak punya alat setara untuk menjalankan model‑model ini.
- **Kenapa tetap pakai Laravel/PHP?** Karena membangun web yang lengkap
  (routing, validasi, keamanan, session) jauh lebih praktis di Laravel daripada di Python.
- **Analoginya:** **Laravel = "rumah" aplikasi, Python = "otak" AI** — dihubungkan lewat HTTP.
  Pemisahan ini membuat kode rapi, aman (kunci API tetap di Python), dan mudah dikembangkan.

---

## 5. Komponen AI — "AI apa saja yang dipakai?"

Sistem ini adalah **pipeline 3 tahap**: *persepsi → pemahaman → keluaran suara*.

### 5.1 YOLO11 — Deteksi Objek (lokal, cepat)
- **Jenis:** model deteksi objek (Computer Vision klasik).
- **Library:** Ultralytics.
- **Model:** `yolo11n.pt` (default agar *realtime*; dapat dinaikkan: `yolo11n/s/m/l/x.pt` — makin besar makin akurat tapi lebih lambat di CPU).
- **Peran:** menemukan *di mana* dan *benda apa* (80 kelas COCO), lalu digambar sebagai kotak.
- **Tuning:** `YOLO_IMGSZ` (resolusi inferensi, default 640 — naikkan ke 960 untuk objek kecil/jauh) dan `YOLO_CONF` (ambang keyakinan 0.35) untuk mengurangi salah deteksi.
- **Catatan:** angka "confidence" YOLO adalah skor deteksi, **bukan** tingkat kepastian deskripsi.

### 5.2 Gemini Vision — Deskripsi (cloud, paling akurat)
- **Jenis:** Vision‑Language Model (VLM) / multimodal.
- **SDK:** `google-genai` resmi.
- **Model:** `gemini-3.8-flash` (dapat diubah), dengan model cadangan `gemini-3.5-flash-lite`.
- **Peran:** memahami isi gambar dan menulis deskripsi Bahasa Indonesia (JSON terstruktur).
- **Keunggulan:** paling akurat, menggabungkan petunjuk YOLO + gambar.
- **Keterbatasan:** memerlukan internet & API key; **kuota free tier sangat kecil**
  (contoh: ± 20 permintaan/hari per model) sehingga cocok untuk uji coba, bukan produksi.

### 5.3 Ollama + qwen2.5vl:3b — Deskripsi (lokal, cadangan)
- **Jenis:** VLM yang dijalankan di komputer sendiri.
- **Peran:** cadangan bila Gemini gagal, atau mode tanpa internet.
- **Keterbatasan:** lebih lambat di CPU dan akurasi di bawah Gemini.
- **Prinsip:** hasil Ollama **tidak pernah** ditampilkan seolah‑olah dari Gemini
  (dibedakan lewat field `provider` & `fallback_used`).

### 5.4 Web Speech API — Suara (text‑to‑speech)
- **Jenis:** API suara bawaan browser (bukan model AI yang kita latih).
- **Peran:** membacakan hasil dalam Bahasa Indonesia (`lang = id-ID`).

---

## 6. "Agen" — Agen AI & Alat Pengembangan

> Kata "agent" bisa berarti dua hal; keduanya dijelaskan di sini.

### 6.1 Agen/komponen cerdas **di dalam sistem** (runtime)
Sistem dapat dilihat sebagai **kumpulan agen** yang saling bekerja sama:
1. **Agen Persepsi (YOLO)** — "mata": mendeteksi objek.
2. **Agen Pemahaman (Gemini/Ollama)** — "otak": memahami & menjelaskan situasi.
3. **Agen Orkestrasi (FastAPI)** — "koordinator": memilih provider, menangani error & fallback.
4. **Agen Suara (Web Speech API)** — "mulut": membacakan hasil.

### 6.2 Agen AI untuk **pengembangan** (coding agent)
Pengembangan aplikasi ini dibantu **AI coding agent**:
- **opencode** (CLI agent) dengan model **big‑pickle**.
- Digunakan untuk: menulis/memperbaiki kode, refactor, menulis tes, dan dokumentasi ini.

> Jika dosen bertanya, jawaban jujur: *"Pengembangan dibantu AI coding agent,
> tetapi arsitektur, keputusan desain, dan verifikasi tetap saya lakukan."*

---

## 7. Struktur Folder & File Penting

```
ai-cam/
├─ app/Http/Controllers/AiCamController.php   # Backend Laravel (proxy + validasi)
├─ routes/web.php                              # 4 rute utama
├─ config/services.php                         # URL layanan Python (AI_CAM_PYTHON_URL)
├─ resources/views/ai-cam.blade.php            # SELURUH UI (HTML+CSS+JS inline)
├─ python-service/
│  ├─ main.py                                  # Layanan FastAPI + logika AI
│  ├─ requirements.txt                         # Dependensi Python
│  ├─ .env.example                             # Contoh konfigurasi (tanpa kunci rahasia)
│  ├─ list_gemini_models.py                    # Alat memilih model Gemini yang valid
│  ├─ test_gemini_mock.py                      # 16 tes unit Python (tanpa jaringan)
│  └─ test_describe.py                         # Uji manual deskripsi dengan gambar
├─ tests/js/speech-dedupe.test.mjs             # 8 tes logika suara (Node.js)
├─ tests/ (PHPUnit)                            # 3 tes PHP
├─ AGENTS.md                                   # Catatan teknis untuk agen/AI
└─ DOKUMENTASI.md                              # Dokumen ini
```

---

## 8. Endpoint API

| Metode | Endpoint | Fungsi |
| --- | --- | --- |
| GET | `/ai-cam` | Halaman utama aplikasi |
| GET | `/api/ai-cam/health` | Status layanan (provider & model aktif) |
| POST | `/api/ai-cam/detect` | Deteksi objek (YOLO) → JSON kotak + label |
| POST | `/api/ai-cam/describe` | Deskripsi lingkungan (Gemini/Ollama) → JSON teks |
| POST | `/api/ai-cam/annotate` | Simpan contoh ajar (gambar + kotak + label) → JSON jumlah data |
| GET | `/api/ai-cam/dataset` | Ringkasan data ajar terkumpul (jumlah per kelas) |

Contoh respons `/describe` (disederhanakan):
```json
{
  "provider": "gemini",
  "model": "gemini-3.8-flash",
  "fallback_used": false,
  "description": "Di tengah terlihat botol air dengan tutup hijau.",
  "main_objects": ["botol"],
  "elapsed_ms": 8800
}
```

---

## 9. Konfigurasi (Environment)

Semua pengaturan AI ada di `python-service/.env` (disalin dari `.env.example`):

| Variabel | Fungsi |
| --- | --- |
| `AI_VISION_PROVIDER` | `auto` / `gemini` / `ollama` |
| `AI_VISION_FALLBACK` | Aktifkan cadangan ke Ollama |
| `GEMINI_API_KEY` | Kunci Gemini (RAHASIA) |
| `GEMINI_MODEL` | Model utama (mis. `gemini-3.8-flash`) |
| `GEMINI_FALLBACK_MODELS` | Model cadangan (mis. `gemini-3.5-flash-lite`) |
| `GEMINI_THINKING_LEVEL` | Tingkat "berpikir" Gemini (`LOW` = lebih cepat) |
| `YOLO_MODEL`, `YOLO_IMGSZ`, `YOLO_CONF` | Tuning deteksi lokal (`yolo11n.pt` @ 640 = realtime) |
| `YOLO_CUSTOM_MODEL` | Model hasil latihan sendiri (mis. `custom.pt`); kosong = nonaktif |
| `YOLO_CUSTOM_CONF` | Ambang keyakinan khusus model hasil latihan |
| `DATASET_DIR` | Lokasi data ajar (mis. `python-service/datasets/objek`) |
| `OLLAMA_MODEL`, `OLLAMA_TIMEOUT` | Model & batas waktu Ollama |
| `VISION_MAX_SIDE`, `VISION_JPEG_QUALITY` | Kualitas gambar yang dikirim |

### Keamanan
- **Kunci API Gemini hanya berada di backend Python** (`python-service/.env`, di‑*gitignore*).
- Kunci **tidak pernah** muncul di HTML/JavaScript/URL/response/log.
- `.env.example` hanya berisi placeholder kosong.

---

## 10. Status Pengerjaan (Progres ± 50 %)

### Legenda: ✅ selesai · 🟡 sebagian · ⛔ belum

| # | Modul | Status | Keterangan |
| --- | --- | --- | --- |
| 1 | Halaman UI + desain (gelap/glass) | ✅ | Sudah dirombak, responsif & aksesibel |
| 2 | Akses kamera & kirim gambar | ✅ | Berjalan di browser |
| 3 | Deteksi objek YOLO | ✅ | Berjalan; masih disetel akurasinya |
| 4 | Deskripsi Gemini (cloud) | ✅ | Berjalan saat kuota tersedia |
| 5 | Deskripsi Ollama (lokal) | ✅ | Berjalan sebagai cadangan |
| 6 | Fallback otomatis Gemini→Ollama | ✅ | Ada, dengan penanda provider |
| 7 | Text‑to‑speech Indonesia | ✅ | Berjalan + anti‑pengulangan |
| 8 | Proxy & validasi Laravel | ✅ | Berjalan + server konkuren (worker) |
| 9 | Diagnostik Sistem (self‑test UI) | ✅ | Mengukur latensi & menandai bagian belum maksimal |
| 10 | Pengujian (PHP/Python/JS) | 🟡 | 3 + 16 + 8 tes lulus; cakupan belum luas |
| 11 | Dokumentasi | 🟡 | Dokumen ini dibuat |
| 12 | Validasi akurasi dengan foto nyata | ⛔ | Belum ada dataset foto asli |
| 13 | Autentikasi / multi‑pengguna | ⛔ | Belum |
| 14 | Penyimpanan riwayat ke database | ⛔ | Belum (SQLite belum dipakai) |
| 15 | Deploy ke server publik | ⛔ | Masih lokal (localhost) |
| 16 | Mode offline penuh / optimasi mobile | ⛔ | Belum |
| 17 | Mode "Ajar Objek" (kumpul data latih) | ✅ | Berjalan; pelatihan model khusus menyusul |

### Mengapa disebut ± 50 %?
- **Sudah jalan (inti produk):** alur lengkap kamera → deteksi → deskripsi → suara,
  dua penyedia AI + fallback, UI, dan pengujian dasar. Ini bagian **fungsional** terbesar.
- **Belum selesai (menuju 100 %):** validasi akurasi dengan data nyata, penyimpanan
  riwayat, autentikasi, optimasi performa, dan **deploy**. Ini bagian **pematangan & produksi**.

> **Kalimat siap pakai untuk dosen:**
> *"Progres sekitar 50 %: fitur inti sudah berfungsi end‑to‑end (kamera, deteksi,
> deskripsi AI, dan suara). Setengah berikutnya fokus ke akurasi dengan data nyata,
> penyimpanan riwayat, autentikasi, dan deployment."*

---

## 11. Cara Menjalankan

### Prasyarat
- PHP 8.3 + Composer, Node.js (untuk tes JS), Python 3.13 (di `python-service/.venv`).
- (Opsional) Ollama + model `qwen2.5vl:3b` untuk mode lokal.

### Langkah
```powershell
# 1) Laravel (WAJIB mode konkuren agar deteksi tidak tersendat saat vision berjalan)
composer setup                                  # sekali saja
$env:PHP_CLI_SERVER_WORKERS = 4
php artisan serve --no-reload                    # http://127.0.0.1:8000/ai-cam

# 2) Layanan AI (terminal terpisah)
cd python-service
.\.venv\Scripts\python.exe -m uvicorn main:app --port 8001
```
Lalu buka: **http://127.0.0.1:8000/ai-cam** → tekan **Mulai Realtime**.

> **Kenapa `--no-reload` + `PHP_CLI_SERVER_WORKERS`?** Server bawaan `php artisan serve`
> bersifat **satu‑thread**: satu panggilan `/describe` yang lambat akan memblokir semua
> `/detect` sehingga kamera terasa "diam". Menjalankan dengan worker (dan `--no-reload`)
> membuat deteksi & vision berjalan bersamaan → terasa realtime.

> **Memakai kamera HP (Iriun Webcam):** pasang aplikasi Iriun Webcam di HP **dan** di PC,
> hubungkan keduanya (WiFi/USB) hingga muncul sebagai kamera. Di halaman aplikasi,
> pilih sumber kamera pada menu **"Sumber kamera"** → pilih **Iriun Webcam**.
> Daftar kamera muncul setelah kamera pernah dinyalakan sekali (izin akses).

> **Cek performa:** buka panel **"Diagnostik Sistem"** di halaman → **Jalankan Uji** untuk
> melihat latensi dan status tiap bagian, serta ringkasan bagian yang belum maksimal.

### Menjalankan pengujian
```powershell
php artisan test                                            # 3 tes PHP
cd python-service
.\.venv\Scripts\python.exe -m unittest test_gemini_mock     # 16 tes Python
cd ..
node tests/js/speech-dedupe.test.mjs                        # 8 tes JS
```

---

## 12. Batasan & Etika (PENTING)

- Aplikasi ini **alat bantu**, **bukan pengganti** tongkat, anjing pemandu,
  atau alat navigasi khusus.
- Sistem **tidak mengukur jarak** dan **tidak menjamin jalur aman**.
- Model dapat **keliru**, terutama untuk benda kecil, gambar gelap, atau objek jauh.
- Tidak boleh menampilkan **persentase akurasi palsu**; angka YOLO = skor deteksi.
- Gunakan selalu bersama alat bantu mobilitas yang biasa dipakai.

---

## 13. Rencana Pengembangan (Roadmap)

1. **Data & akurasi:** kumpulkan foto nyata lewat mode **"Ajar Objek"** (botol, gelas,
   remote, benda kecil/jauh, kondisi gelap) → latih model khusus (YOLO) lalu ukur akurasinya.
2. **Performa:** sesuaikan model YOLO & resolusi agar halus di perangkat lemah.
3. **Riwayat & database:** simpan log analisis memakai SQLite/MySQL.
4. **Autentikasi:** login pengguna dan preferensi tersimpan.
5. **Deployment:** hosting/HTTPS + mundurkan (proxy) agar kunci tetap aman.
6. **Aksesibilitas lanjutan:** perintah suara (speech‑to‑text) & mode fokus besar.
7. **Multi‑bahasa** dan pengaturan suara (kecepatan, pilihan suara).

---

## 14. Tanya‑Jawab Singkat untuk Presentasi (Cheat Sheet)

**Q: Ini proyek apa?**
A: Asisten penglihatan untuk tunanetra. Kamera "melihat" benda lalu dibacakan
otomatis dalam Bahasa Indonesia.

**Q: Pakai bahasa pemrograman apa saja?**
A: PHP (Laravel) untuk web, Python (FastAPI) untuk AI, JavaScript/HTML/CSS untuk
antarmuka, plus Blade, dan SQL/SQLite untuk database.

**Q: AI apa saja yang dipakai?**
A: Tiga: **YOLO11** (deteksi objek lokal), **Gemini Vision** (deskripsi cloud, akurat),
dan **Ollama + qwen2.5vl** (deskripsi lokal sebagai cadangan). Suara memakai
**Web Speech API** browser.

**Q: Laravel untuk apa dan kenapa pakai Python?**
A: **Laravel (PHP)** = "rumah" aplikasi: halaman web, routing, validasi upload, dan
keamanan kunci API. **Python** = "otak" AI: menjalankan YOLO, Gemini, dan Ollama.
Kami pakai Python karena AI/ML hanya tersedia lengkap di ekosistem Python, dan tetap
pakai Laravel karena membangun web lengkap jauh lebih praktis di PHP. Keduanya
dihubungkan lewat HTTP agar rapi dan aman.

**Q: Bagaimana kalau internet mati / kuota habis?**
A: Sistem otomatis beralih (fallback) ke Ollama lokal, dan menandai provider-nya
agar tidak menyesatkan.

**Q: Sudah sampai mana?**
A: Sekitar **50 %**. Fitur inti sudah jalan end‑to‑end; sisanya akurasi dengan data
nyata, riwayat/database, autentikasi, dan deployment.

**Q: Apakah akuratnya dijamin 100 %?**
A: Tidak. Ini alat bantu. Model bisa salah; kami tidak menampilkan klaim akurasi palsu.

**Q: Bagaimana keamanan kunci API-nya?**
A: Kunci hanya di backend Python (`.env`, di‑gitignore), tidak pernah muncul di
halaman web, URL, atau log.

**Q: Ada pengujiannya?**
A: Ya: 3 tes PHP, 16 tes Python, dan 8 tes JS—semuanya lulus.

---

## 15. Kesimpulan

AI Cam adalah aplikasi asisten penglihatan berbasis **pipeline AI tiga tahap**
(YOLO → Gemini/Ollama → Text‑to‑Speech) dengan arsitektur dua layanan
(Laravel + FastAPI). Fitur inti telah berfungsi end‑to‑end dan diperkirakan berada
di tahap **± 50 %**; setengah berikutnya berfokus pada akurasi dengan data nyata,
penyimpanan data, keamanan, dan deployment. Proyek menjunjung etika penggunaan:
sebagai **alat bantu**, bukan pengganti alat bantu mobilitas, dan tanpa klaim
akurasi yang berlebihan.

---

*Dokumen ini dapat diperbarui seiring bertambahnya fitur. Rahasia (API key) tidak pernah
ditulis dalam dokumen ini.*
