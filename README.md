# CBT SMART EXAM PRO - Aplikasi Ujian Berbasis Komputer & Asesmen Pintar

Aplikasi Web CBT (Computer-Based Test) mutakhir yang dirancang khusus untuk lingkungan **XAMPP (Apache, MySQL, PHP 8+)** dengan dukungan fleksibel untuk **semua jenjang pendidikan (SD/MI, SMP/MTs, SMA/SMK/MA)**, **6 Jenis Tipe Soal Asesmen Standar**, manajemen role berjenjang (**Admin, Guru, Operator, Siswa**), pemantauan real-time, dan evaluasi psikometri butir soal.

---

## 🚀 Fitur Utama Berdasarkan Peran (Role)

### 1. 🛡️ Administrator (Full Management Aplikasi)
* **Manajemen Pengguna Petugas**: Tambah, edit, hapus, dan reset password untuk akun Admin, Guru, dan Operator.
* **Manajemen Data Master Akademik**:
  * Kelas & Rombongan Belajar (Tingkat & Jurusan).
  * Mata Pelajaran (Kode & Nama).
  * Data Siswa Peserta Ujian (NISN, Nomor Peserta, Kelas, Reset status login).
  * **Import Siswa Massal dari Excel / CSV**:
    * Unduh template resmi Excel (`.xls`) dan CSV (`.csv`) dengan contoh data siap pakai.
    * Otomatis membaca NISN dengan awalan nol (contoh: `006...`) tanpa terpotong.
    * Otomatis membuat Kelas jika kelas di Excel belum ada di sistem.
    * Opsi perbarui data (update) atau lewati (skip) jika NISN siswa sudah terdaftar.
    * Password otomatis diisi password bawaan (default: `siswa123`) jika kolom di Excel dikosongkan.
* **Jadwal & Sesi Ujian**:
  * Pengaturan tanggal/jam mulai & selesai.
  * Durasi pengerjaan (menit).
  * Alokasi Sesi & Ruang Ujian.
  * Token ujian default & rilis.
  * Pengacakan urutan butir soal & opsi pilihan (A, B, C, D, E).
  * Mode pengamanan Anti-Curang (Lockdown).
* **Pengaturan Aplikasi**: Nama sekolah/lembaga, logo/brand aplikasi, semester, tahun ajaran aktif, dan sensitivitas anti-curang.

### 2. 👨‍🏫 Guru Pengajar (Upload Soal, Analisis Soal & Nilai)
* **Visual Question Authoring Studio (6 Jenis Tipe Soal)**:
  1. **Pilihan Ganda (Pilgan Tunggal)**: Pilihan A, B, C, D, E dengan penetapan radio button kunci jawaban.
  2. **Pilihan Ganda Kompleks**: Multi-pilih pernyataan dengan centang beberapa jawaban benar sekaligus (mendukung penskoran proporsional).
  3. **Menjodohkan (Matching Pairs)**: Pencocokan premis kolom kiri dengan target kolom kanan secara interaktif.
  4. **Pernyataan Benar / Salah**: Matriks pernyataan dengan tombol pilihan Benar / Salah.
  5. **Isian Singkat (Short Answer)**: Jawaban teks dengan pencocokan otomatis fleksibel (alternatif multi-kunci dipisah koma) dan opsi *case-sensitive*.
  6. **Uraian / Essay**: Pertanyaan deskriptif terbuka dilengkapi panduan rubrik penskoran dan contoh jawaban ideal.
* **Upload / Import Soal Massal**:
  * Unggah format `.JSON` terstruktur untuk 6 tipe soal sekaligus.
  * Tombol unduh template contoh soal langsung di aplikasi.
* **Analisis Butir Soal (Item Psychometric Analysis)**:
  * **Tingkat Kesukaran ($P = B/N$)**: Klasifikasi otomatis *Mudah ($P \ge 0.70$)*, *Sedang ($0.30 \le P < 0.70$)*, dan *Sukar ($P < 0.30$)*.
  * **Daya Pembeda ($D$)**: Perbandingan kelompok atas (top performers) vs kelompok bawah (bottom performers) dengan klasifikasi *Sangat Baik*, *Baik*, *Cukup*, dan *Buruk / Perlu Revisi*.
  * **Efektivitas Pengecoh (Distractor Analysis)**: Distribusi frekuensi pemilihan opsi distraktor A, B, C, D, E.
  * Visualisasi grafik interaktif menggunakan **Chart.js** dan laporan siap cetak (Print Friendly).
* **Rekapitulasi Nilai & Koreksi Manual**:
  * Lembar koreksi khusus Guru untuk memberikan skor uraian/isian beserta catatan umpan balik (*teacher feedback*).
  * Perhitungan otomatis nilai akhir berbobot.
  * Status kelulusan KKM (*TUNTAS* vs *REMEDIAL*).
  * Export Rekap Nilai ke **Excel (CSV compatible)** & Cetak.

### 3. 🖥️ Operator / Proktor (Cek Data Siswa, Mantau Tes & Generate Token)
* **Cek Data Siswa Yang Akan Tes**:
  * Verifikasi identitas peserta, sesi, ruang, dan status siap tes.
  * Fitur cetak **Kartu Peserta Ujian** lengkap dengan data kredensial login.
* **Live Monitoring Ujian Real-Time**:
  * Auto-refresh setiap 4 detik via AJAX tanpa me-reload browser.
  * Pemantauan status live tiap siswa: *Belum Mulai*, *Sedang Mengerjakan*, *Selesai*, atau *Terkunci*.
  * Progress pengerjaan (jumlah soal terjawab vs total soal, status ragu-ragu).
  * Sisa waktu live pengerjaan masing-masing siswa.
  * Deteksi jumlah pelanggaran anti-curang (pindah tab browser / keluar jendela).
  * **Kontrol Proktor Cepat**:
    * **Reset Login**: Mengizinkan siswa login kembali saat browser crash atau ganti perangkat.
    * **Tambah Waktu (+Menit)**: Tambahan waktu langsung jika terjadi kendala teknis/listrik.
    * **Paksa Selesai (Force Submit)**: Menyerahkan ujian secara paksa ketika batas sesi usai.
    * **Buka Blokir (Unblock)**: Membuka kunci siswa yang terkena pelanggaran tab.
* **Generate & Rilis Token Ujian**:
  * Penampil token proktor ukuran besar (*Proctor Projection Mode*) yang mudah dilihat siswa dari layar proyektor.
  * Pembuat kode token baru dinamis (6 karakter alfanumerik acak) dengan opsi durasi aktif.
  * Riwayat rilis token per sesi ujian.

### 4. 🎓 Siswa Peserta Ujian (CBT Exam Engine)
* Tampilan responsif & ramah mata (*User Friendly*).
* Komponen interaktif khusus untuk masing-masing 6 jenis tipe soal.
* **Auto-Save Realtime**: Jawaban otomatis tersimpan ke server secara berkala saat siswa mengklik atau mengetik.
* Countdown timer yang tetap sinkron dengan server.
* Pengatur ukuran teks (Kecil, Sedang, Besar).
* Palet nomor soal dengan indikator warna: *Hijau (Sudah)*, *Kuning (Ragu-ragu)*, *Abu-abu (Belum)*.
* **Anti-Curang & Lockdown**: Peringatan otomatis jika siswa membuka tab lain atau meminimalisir jendela.
* Dialog konfirmasi sebelum submit dengan rekapitulasi jumlah soal yang belum dijawab atau masih ragu-ragu.

---

## 🛠️ Panduan Instalasi di XAMPP

1. **Pastikan Folder Berada di htdocs**:
   Aplikasi ini diletakkan di direktori:
   ```
   C:\Users\riana\Videos\xampp\htdocs\CBT
   ```
   *(Atau `C:\xampp\htdocs\CBT` sesuai instalasi XAMPP Anda)*.

2. **Jalankan Apache & MySQL**:
   * Buka **XAMPP Control Panel**.
   * Klik tombol **Start** pada modul **Apache** dan **MySQL** hingga indikator berwarna hijau.

3. **Buka Installer Otomatis di Browser**:
   * Buka browser (Chrome, Edge, Firefox), lalu akses alamat:
     ```
     http://localhost/CBT/database/install.php
     ```
     *(atau cukup buka `http://localhost/CBT/`, Anda akan diarahkan ke installer jika database belum diinisialisasi)*.
   * Klik tombol **"Mulai Inisialisasi Database Sekarang"**.
   * Installer akan otomatis membuat database `cbt_db`, 12 tabel, dan menanam data awal (Admin, Guru, Operator, Siswa, Bank Soal 6 jenis, & Jadwal Ujian Aktif).

---

## 🔑 Akun Bawaan (Default Login)

| Peran (Role) | Username / ID | Password | Akses Halaman |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | `http://localhost/CBT/login.php` |
| **Guru Pengajar** | `guru` | `guru123` | `http://localhost/CBT/login.php` |
| **Operator / Proktor** | `operator` | `operator123` | `http://localhost/CBT/login.php` |
| **Siswa (Peserta)** | `0061234501` | `siswa123` | `http://localhost/CBT/ujian/login.php` |

> **Token Ujian Default untuk Siswa**: `SMART1`

---

## 📁 Struktur Direktori Proyek

```
CBT/
├── config/
│   ├── config.php               # Konfigurasi aplikasi, base URL otomatis, session
│   └── database.php             # Koneksi PDO MySQL dengan error boundary & auto-installer redirect
├── app/
│   ├── helpers.php              # Helper sanitasi XSS, flash message, json response, format tanggal
│   ├── Auth.php                 # Otentikasi & permission guard (Admin, Guru, Operator, Siswa)
│   ├── QuestionService.php      # Algoritma penskoran & kalkulasi 6 jenis tipe soal
│   └── AnalysisService.php      # Rumus psikometri: Tingkat Kesukaran (P), Daya Pembeda (D), Pengecoh
├── database/
│   ├── schema.sql               # Skema lengkap 12 tabel MySQL (InnoDB utf8mb4)
│   ├── seed.sql                 # Data awal: User, Siswa, Bank Soal 6 tipe, Jadwal Ujian
│   └── install.php              # Wizard 1-klik instalasi database interaktif
├── assets/
│   └── js/
│       └── cbt-engine.js        # Engine ujian siswa: timer, autosave, anti-cheat, 6 widget soal
├── admin/
│   ├── index.php                # Dashboard Administrator
│   ├── users.php                # CRUD Pengguna (Admin, Guru, Operator)
│   ├── classes.php              # CRUD Kelas & Rombel
│   ├── subjects.php             # CRUD Mata Pelajaran
│   ├── students.php             # CRUD Siswa Peserta Ujian & Reset Sesi
│   ├── exams.php                # Manajemen Jadwal, Sesi, Ruang & Aturan Ujian
│   └── settings.php             # Pengaturan Sekolah, Logo & Aturan Anti-Curang
├── guru/
│   ├── index.php                # Dashboard Guru
│   ├── bank_soal.php            # Manajemen Bank Soal
│   ├── soal_editor.php          # Visual Studio 6 Tipe Soal (Pilgan, Kompleks, Jodohkan, B/S, Isian, Uraian)
│   ├── soal_import.php          # Upload / Import berkas JSON & Download Template
│   ├── analisis_soal.php        # Laporan Analisis Butir Soal & Grafik Chart.js
│   ├── nilai.php                # Rekapitulasi Nilai Siswa & Export Excel (CSV)
│   └── nilai_koreksi.php        # Lembar Koreksi Uraian Manual & Rubrik Penilaian
├── operator/
│   ├── index.php                # Dashboard Operator
│   ├── token.php                # Generator & Rilis Token Ujian (Proctor View)
│   ├── monitoring.php           # Live Monitoring Ujian Real-Time & Kontrol Siswa
│   ├── siswa_tes.php            # Verifikasi Siswa Siap Tes & Cetak Kartu Peserta
│   └── api_monitor.php          # Endpoint AJAX live data polling & aksi operator
├── ujian/
│   ├── login.php                # Portal Login Siswa (NISN + Password)
│   ├── konfirmasi.php           # Verifikasi Data Siswa & Masukkan Token Ujian
│   ├── ruang.php                # Antarmuka Pengerjaan Soal CBT Modern
│   ├── api_ujian.php            # Endpoint AJAX Autosave, Timer Sync, Anti-Cheat Log, Submit
│   └── selesai.php              # Halaman Selesai Ujian & Ringkasan Nilai
├── includes/
│   ├── header.php               # Header navbar & styling wrapper
│   ├── sidebar.php              # Sidebar dinamis berbasis role
│   └── footer.php               # Footer & script wrapper
├── index.php                    # Gerbang Utama (Portal Siswa vs Portal Petugas)
├── login.php                    # Login Petugas (Admin / Guru / Operator)
├── logout.php                   # Logout handler
└── README.md                    # Dokumentasi panduan lengkap
```
