# Task: PM Status Board — Hanya Mesin OPEN, Kolom Bulan, Load Bulan Depan Tanpa Reload

**Tujuan:** Halaman PM Status (`/pm-status/{area}`) tidak lagi punya filter bulan/tahun. Yang tampil hanya mesin yang statusnya masih OPEN, dari bulan berjalan dan dari bulan-bulan sebelumnya (tanpa batas selama masih OPEN). Ada kolom **Bulan**. Di bawah tabel ada tombol "klik disini" untuk memuat mesin bulan berikutnya (maksimal 2 bulan ke depan), tanpa reload halaman. Status override manual (khusus admin/koordinator) harus ikut aturan baru yang dijelaskan di bagian "Aturan override".

**Level:** Junior developer / AI model murah. Kerjakan berurutan dari Langkah 1 sampai selesai, jangan loncat. Jangan mengubah file di luar daftar "File yang disentuh".

**Status:** ✅ Langkah 1-7 sudah diimplementasikan (2026-11-03). Langkah 0 dan 8 (cek manual di browser) belum dijalankan — lihat "Hasil verifikasi".

---

## Keputusan yang sudah disepakati

| # | Topik | Keputusan |
|---|-------|-----------|
| 1 | Bulan yang dimuat lewat tombol | Bulan **depan**, sampai **2 bulan ke depan** dari bulan berjalan. Satu klik = satu bulan. Setelah bulan ke-2 tombol hilang. |
| 2 | Bulan lampau | Tidak ada batas. Mesin dari bulan lampau tampil selama statusnya masih OPEN. |
| 3 | Cara memuat | **Tanpa reload halaman.** Pakai AJAX (fetch) lalu baris ditambahkan ke tabel dan kartu mobile. |
| 4 | Ringkasan (total / open / closed) | Dihitung dari seluruh baris yang tampil. |
| 5 | Urutan baris | `plan_date` naik, lalu `machine_number` naik. Mesin yang terbawa dari bulan lampau otomatis di atas. |
| 6 | Override manual | Lihat bagian "Aturan override" di bawah. |

**Interpretasi yang perlu dikonfirmasi user (sudah diambil sebagai default):**
- "Hanya mesin yang masih open" berlaku untuk **semua** bulan, termasuk bulan berjalan dan bulan depan. Jadi mesin yang sudah CLOSED tidak tampil sama sekali, di bulan manapun.
- Tombol "klik disini" memuat **satu bulan per klik**, bukan dua bulan sekaligus. Kalau maksudnya sekali klik langsung 2 bulan, bagian Langkah 4 perlu diubah.

---

## Kondisi sekarang (bukti dari kode)

**[app/Http/Controllers/PMStatusBoardController.php](app/Http/Controllers/PMStatusBoardController.php)**
- Baris `$month = (int) ($request->input('plan_month') ?? $now->month);` dan `$year = ...plan_year...` → periode dikunci ke satu bulan lewat query string.
- Query `->whereYear('plan_date', $year)->whereMonth('plan_date', $month)` → hanya satu bulan.
- Semua baris (termasuk CLOSED) dikirim ke view, status dihitung di `->map(...)`.
- `$summary` dihitung dari semua baris; `$years`, `$months` hanya untuk dropdown filter.

**[resources/views/pm-status-board/index.blade.php](resources/views/pm-status-board/index.blade.php)**
- Ada `<form method="GET">` dengan dropdown `plan_month`, `plan_year`, tombol Filter, dan Reset. Harus dihapus.
- Tabel desktop punya 5 kolom: Status, Machine, Plan Date, Gap Day, Actual PM Date. Belum ada kolom Bulan.
- Kartu mobile juga belum punya info bulan.
- Baris di desktop dan mobile dirender dengan markup yang sama dan keduanya punya `data-pm-status-cell` + `data-pm-status-id`.
- `$summary` tidak ditampilkan di view.

**[resources/js/pm-status/override.js](resources/js/pm-status/override.js)**
- Override disimpan di localStorage (`pm_status_board_overrides_v1`) dalam bentuk `{ [id]: 'OPEN' | 'CLOSED' }`.
- `initPmStatusOverride()` hanya jalan kalau ada tombol `[data-pm-status-toggle]` di halaman (artinya hanya admin/koordinator). Guest seharusnya tidak pernah melihat override.
- Init hanya dijalankan sekali saat halaman dimuat. Baris yang ditambahkan nanti lewat AJAX **tidak** otomatis ikut.
- Override yang tersimpan diterapkan ke baris mana pun yang punya id yang sama, tanpa mengecek apakah status asli di server sudah berubah.

**[tests/Feature/PMStatusBoardTest.php](tests/Feature/PMStatusBoardTest.php)**
- Beberapa test memakai `plan_month` / `plan_year` dan mengasumsikan mesin CLOSED tetap tampil. Test ini harus diperbarui (lihat Langkah 7).

---

## Aturan override (keputusan user)

1. **Guest** (tidak login, atau login tapi bukan admin/koordinator): tidak pernah melihat override. Yang tampil selalu status asli dari server.
2. **Admin / koordinator**: melihat status hasil override, lengkap dengan tag "(manual)".
3. **Override hilang kalau data asli sudah CLOSED.** Contoh: admin menandai mesin X jadi CLOSED padahal aslinya OPEN. Lalu PM mesin X benar-benar selesai dan statusnya di server jadi CLOSED. Maka override mesin X harus hilang. Karena baris CLOSED sekarang tidak tampil, cukup hapus entri override-nya saat baris itu muncul lagi dengan status asli yang sudah berubah.

**Cara implementasi yang disarankan:** simpan juga status asli saat override dibuat, jadi bentuk datanya `{ [id]: { status: 'CLOSED', base: 'OPEN' } }`. Saat baris dirender dan diterapkan:
- Kalau `base` sama dengan status asli sekarang → terapkan `status` override.
- Kalau `base` sudah berbeda dengan status asli sekarang → hapus entri override itu dari localStorage, lalu tampilkan status asli.

Catatan: entri override untuk baris yang sudah CLOSED dan tidak tampil lagi akan tetap ada di localStorage (tidak berbahaya karena tidak dirender). Kalau mau dibersihkan total, itu pekerjaan terpisah.

---

## Langkah-langkah

### Langkah 0 — Verifikasi keluhan guest (sebelum mengubah apa pun)

User melaporkan override terlihat di view guest. Dari kode, guest seharusnya tidak melihatnya, jadi cek dulu:
1. Buka `/pm-status/{area}` di jendela incognito **tanpa login**. Pastikan badge sama dengan status asli dari server dan tidak ada tag "(manual)".
2. Login sebagai admin di browser yang sama, buat override, lalu buka lagi halaman sebagai guest di tab/jendela lain yang berbagi localStorage (browser yang sama, bukan incognito).
3. Catat hasilnya di bagian "Hasil verifikasi" di bawah.

Kalau guest ternyata tetap melihat override, cari penyebabnya dulu sebelum lanjut. Jangan asumsikan.

### Langkah 1 — Controller: ubah query menjadi "open saja, bulan berjalan + lampau"

File: [app/Http/Controllers/PMStatusBoardController.php](app/Http/Controllers/PMStatusBoardController.php)

- Hapus pembacaan `plan_month` dan `plan_year`.
- Hitung awal bulan berjalan: `$now->copy()->startOfMonth()` (zona Asia/Jakarta).
- Query utama: mesin area ini, `plan_date` < awal bulan depan **dan** status belum DONE. Gunakan `PMSchedule::DONE_STATUSES` supaya konsisten dengan logika yang sudah ada, dan `whereNotIn('status', PMSchedule::DONE_STATUSES)`.
- Sisanya (perhitungan gap day, plan_date format, actual_date) tetap seperti sekarang.
- Hapus variabel `$months`, `$years`, `$currentMonth`, `$currentYear` yang hanya dipakai dropdown.
- Tambahkan field `month_label` per baris (misalnya `$planDate->translatedFormat('F Y')`, hasilnya "November 2026") untuk kolom Bulan.
- Ringkasan `$summary` tetap dihitung dari koleksi yang dikirim.

**Catatan:** karena hanya OPEN yang dikirim, nilai `closed` di ringkasan akan selalu 0. Ringkasan itu tidak ditampilkan di view, jadi tidak masalah. Kalau nanti ditampilkan, pastikan tidak menyesatkan.

### Langkah 2 — Endpoint untuk memuat satu bulan

Tambahkan route baru di [routes/web.php](routes/web.php), dekat route `pm-status.show` (baris 64-65). Route ini **publik** (tanpa auth), sama seperti halaman utama, karena guest juga harus bisa memuat bulan depan.

Contoh bentuk: `GET /pm-status/{area}/bulan?offset=1` (offset = 1 atau 2, bulan dari sekarang). Nama route: `pm-status.month`.

Di controller, tambahkan method (misalnya `month`):
- Validasi `offset` harus integer 1 atau 2. Di luar itu balas 422 atau 404.
- Ambil mesin area yang sama, filter `plan_date` di bulan `now + offset`, status belum DONE, urut sama seperti Langkah 1.
- Kembalikan **potongan HTML** (partial Blade) untuk baris tabel dan kartu mobile, bukan JSON. Dengan begitu markup hanya ada di satu tempat (Blade), tidak diduplikasi di JavaScript.

Format respons yang disarankan: JSON `{ "desktop": "<tr>...</tr>...", "mobile": "<div>...</div>...", "has_more": true }`. `has_more` = false ketika offset sudah 2 (tombol harus disembunyikan).

### Langkah 3 — Pecah view menjadi partial

File baru:
- `resources/views/pm-status-board/_row-desktop.blade.php` — satu `<tr>` untuk tabel desktop, isinya dipindah dari `@forelse` di [index.blade.php](resources/views/pm-status-board/index.blade.php).
- `resources/views/pm-status-board/_card-mobile.blade.php` — satu kartu mobile.

Di `index.blade.php`, ganti isi `@forelse` dengan `@foreach` yang memanggil partial itu. Kolom Bulan ditambahkan sebagai kolom ke-2 (setelah Status) di desktop, dan sebagai baris di kartu mobile.

Kalau tidak ada baris sama sekali, tetap tampilkan pesan kosong seperti sekarang (`No PM schedules found...`), ubah teksnya jadi "Tidak ada mesin PM yang masih open." — konfirmasi ke user bila ingin teks lain.

### Langkah 4 — View: hapus filter, tambah tombol "klik disini"

File: [resources/views/pm-status-board/index.blade.php](resources/views/pm-status-board/index.blade.php)

- Hapus seluruh blok `<form method="GET">...</form>` (filter bulan, tahun, Filter, Reset).
- Di bawah tabel desktop dan kartu mobile, tambahkan satu tombol/teks, misalnya:
  - Teks: "Klik di sini untuk memuat mesin bulan {nama bulan depan}"
  - Tombol disembunyikan bila offset sudah 2 (sudah 2 bulan ke depan dimuat).
- Tombol ini harus punya atribut data (misalnya `data-pm-month-next`) dan nilai offset berikutnya, supaya JavaScript tahu bulan mana yang diambil.
- Tombol harus tampil untuk guest dan admin.

### Langkah 5 — JavaScript: muat bulan via fetch, tambahkan baris

File baru: `resources/js/pm-status/load-month.js` (atau tambahkan ke `override.js` jika lebih ringan, tapi pisahkan file supaya tanggung jawab jelas).

Alur:
1. Saat tombol diklik, nonaktifkan tombol (cegah klik ganda) dan tampilkan teks "Memuat...".
2. `fetch` ke URL endpoint dengan offset berikutnya. Kirim header `Accept: application/json`.
3. Tambahkan `desktop` ke akhir `<tbody>` tabel, dan `mobile` ke akhir container kartu mobile.
4. Setelah baris baru masuk ke DOM, **panggil ulang logika override untuk baris baru** (lihat Langkah 6). Override.js sekarang hanya inisialisasi sekali, jadi ekspor fungsi untuk menerapkan override ke sebuah elemen/baris, lalu panggil dari sini.
5. Naikkan offset berikutnya di tombol. Kalau `has_more` false, sembunyikan tombol.
6. Kalau gagal (network / 500), tampilkan pesan kecil di bawah tombol dan aktifkan tombol lagi.

Daftarkan script ini di [resources/js/app.js](resources/js/app.js) seperti script PM lainnya. Lihat cara `override.js` di-import di sana.

### Langkah 6 — Override: aturan baru

File: [resources/js/pm-status/override.js](resources/js/pm-status/override.js)

1. **Guest:** tidak ada perubahan perilaku (sudah benar: tanpa tombol toggle, fungsi langsung return). Tapi pastikan **fungsi terapkan override tidak berjalan sama sekali** untuk guest, termasuk untuk baris yang datang dari AJAX. Cek keberadaan `[data-pm-status-toggle]` di halaman, bukan di baris.
2. **Simpan base:** saat admin menekan "Ubah", simpan `{ status: next, base: original }` (lihat "Aturan override").
3. **Terapkan dengan pengecekan base:** untuk setiap sel `[data-pm-status-cell]`, baca entri override. Kalau `base` ≠ `data-pm-status-original` sekarang (artinya server sudah berubah), hapus entri itu dari localStorage dan jangan terapkan. Kalau sama, terapkan.
4. **Fungsi yang bisa dipanggil ulang:** pisahkan bagian "terapkan override ke sel-sel dalam sebuah container" menjadi fungsi yang menerima root element (`document` untuk init, atau container baris baru untuk AJAX). Dengan begitu baris yang baru dimuat juga ikut diproses.
5. **Event listener:** tombol "Ubah" baru (dari AJAX) harus punya listener. Pakai event delegation di `document` / container tabel (satu listener untuk semua tombol), supaya tidak perlu pasang listener per baris.
6. Pertahankan try/catch saat membaca/menulis localStorage seperti sekarang.

### Langkah 7 — Test

File: [tests/Feature/PMStatusBoardTest.php](tests/Feature/PMStatusBoardTest.php)

Update test yang lama:
- Hapus / ganti test yang memakai `plan_month` dan `plan_year`.
- Test yang mengharapkan baris CLOSED tampil sekarang harus diubah: baris CLOSED **tidak** boleh tampil.
- Test "gap day does not affect open/closed status — all four combinations" perlu disesuaikan, karena kombinasi CLOSED tidak tampil lagi.

Tambahkan test baru:
1. Mesin OPEN dari bulan lampau (misalnya 3 bulan lalu) tampil dengan kolom Bulan sesuai.
2. Mesin OPEN bulan berjalan tampil.
3. Mesin CLOSED / FINISHED tidak tampil di bulan manapun.
4. Mesin MISSED tampil sebagai OPEN (sudah ada behavior ini, pertahankan).
5. Endpoint bulan: offset 1 mengembalikan mesin bulan depan yang OPEN, `has_more` true. Offset 2 mengembalikan `has_more` false.
6. Endpoint offset di luar 1-2 ditolak.
7. Endpoint bisa diakses guest tanpa login.

Test JavaScript (override) mengikuti pola yang sudah ada di `resources/js/vme/__tests__/` bila memungkinkan. Kalau belum ada setup test JS untuk folder ini, cukup verifikasi manual (Langkah 8) dan catat di hasil.

Jalankan: `php artisan test --filter=PMStatusBoard`.

### Langkah 8 — Verifikasi manual

Lakukan dan catat hasilnya:
1. Guest (incognito): tabel hanya berisi mesin OPEN bulan berjalan dan bulan lampau. Ada kolom Bulan. Tidak ada filter. Tombol "klik disini" tampil.
2. Klik tombol sekali: baris bulan depan muncul di bawah, tanpa reload (cek tab network, tidak ada navigasi halaman). Tombol tetap ada.
3. Klik sekali lagi: bulan ke-2 muncul, tombol hilang.
4. Admin: buat override pada baris dari bulan depan (yang baru dimuat lewat AJAX). Tag "(manual)" muncul. Reload halaman → baris itu tidak ada lagi karena belum dimuat, dan override tidak merusak tampilan.
5. Admin: ubah data asli mesin override tersebut jadi CLOSED di database, lalu muat ulang halaman dan muat bulan itu → baris tidak tampil dan/atau override dihapus dari localStorage (cek di DevTools → Application → Local Storage).
6. Guest di browser yang sama dengan admin: tidak melihat override sama sekali.
7. Layout mobile (lebar < 768px): kartu muncul dengan kolom Bulan, tombol tetap terlihat, tidak ada scroll horizontal.

---

## File yang disentuh (ringkasan)

**Ubah:**
- [app/Http/Controllers/PMStatusBoardController.php](app/Http/Controllers/PMStatusBoardController.php)
- [routes/web.php](routes/web.php)
- [resources/views/pm-status-board/index.blade.php](resources/views/pm-status-board/index.blade.php)
- [resources/js/pm-status/override.js](resources/js/pm-status/override.js)
- [resources/js/app.js](resources/js/app.js)
- [tests/Feature/PMStatusBoardTest.php](tests/Feature/PMStatusBoardTest.php)

**Baru:**
- `resources/views/pm-status-board/_row-desktop.blade.php`
- `resources/views/pm-status-board/_card-mobile.blade.php`
- `resources/js/pm-status/load-month.js`

**Jangan disentuh:** `PMSchedule` model, `PMChecklistSaveService`, halaman checklist PM, dan halaman dashboard.

---

## Hasil verifikasi

- [ ] Langkah 0: keluhan guest melihat override — **belum dicek di browser.** Dari kode, guest tidak menerapkan override sama sekali (dijaga atribut `data-pm-can-override`, dicover test JS "never applies overrides for guests").
- [x] Test PHP `tests/Feature/PMStatusBoardTest.php` — 31 test lulus (dijalankan dengan `vendor/bin/pest`; `php artisan test` tidak jalan di repo ini karena folder `tests/Unit` tidak ada, sudah begitu sebelum task ini).
- [x] Test JS `resources/js/pm-status/__tests__/override.test.js` — 5 test lulus; seluruh vitest 370 test lulus.
- [x] `npm run build` berhasil; aset di `public/build` ikut berubah.
- [ ] Verifikasi manual 1-7 di browser — belum dijalankan.
- Test lain yang gagal, sudah gagal sebelum task ini (dicek dengan stash): `CostReportTest`, `OilAuditReportTest`, `PMChecklistDraftTest`, `UserAvatarUpdateTest`.

## Risiko / hal yang perlu diperhatikan

- **Data OPEN dari bulan lampau bisa menumpuk** (sesuai keputusan: tanpa batas). Kalau nanti jumlahnya besar, perlu dipikirkan paginasi atau batas tahun. Belum dikerjakan.
- **Entri override yatim di localStorage** untuk baris yang sudah CLOSED tidak dibersihkan, hanya dinonaktifkan saat baris itu muncul lagi. Tidak berdampak visual.
- **Urutan baris setelah AJAX:** baris bulan depan ditambahkan di akhir. Ini sudah sesuai urutan `plan_date` naik, selama bulan depan selalu lebih besar dari bulan sebelumnya.
