# VME-07 — AI Character System

Tanggal: 31 Oktober 2026

**Tujuan:** Menambahkan karakter AI ke VME sebagai pemantau aktivitas maintenance. Karakter membaca data nyata dari fitur yang sudah ada, menampilkan statusnya, dan menyimpan hasil analisisnya. Karakter AI tidak bekerja dan tidak mengubah data apa pun di fitur lain.

---

## Tujuan dan keputusan

Karakter AI menjadi pemantau maintenance di VME. Mereka membaca data nyata dari fitur yang sudah ada, menampilkan statusnya, dan menyimpan hasil analisisnya. Karakter AI tidak bekerja dan tidak mengubah data apa pun.

| Topik | Keputusan |
| --- | --- |
| Karakter awal | AI Supervisor, AI Koordinator, AI Sparepart Analyst |
| Desain | Fleksibel. Karakter baru ditambah lewat registry tanpa mengubah renderer |
| Data | Data nyata dari database. Simulasi hanya untuk fixture test |
| Gemini | Belum dihubungkan. Dipakai nanti untuk data training dan testing |
| Peran | Hanya memantau. Tidak mengambil alih pekerjaan PIC |
| Penyimpanan | Setiap output AI disimpan, dan ada halaman kumpulan datanya |
| Batasan data | Read-only terhadap fitur lain. Tabel AI hanya ditulis oleh fitur AI |

---

## Konteks yang sudah ada

Jangan dibangun ulang. Komponen berikut sudah tersedia.

| Komponen | Lokasi | Catatan |
| --- | --- | --- |
| Snapshot VME | `app/Services/Vme/VmeSnapshotService.php` | Setiap PIC punya aktivitas, target, dan id mesin atau grup |
| Sumber aktivitas | `app/Services/ActiveActivityResolver.php` | Aktivitas aktual, bukan dari jadwal PM |
| Feature switch | `app/Services/Vme/VmeFeature.php`, tabel `vme_settings` | Key baru untuk karakter AI ditambahkan di sini |
| Kill switch | `VME_KILL_SWITCH` di `config/vme.php` | Harus mematikan karakter AI juga |
| Panel detail | `resources/views/vme/index.blade.php` (`#vme-detail`) | Dari VME-06, dipakai ulang |
| Layout Office | `config/vme.php`, `VmeLayout::basePoint()` | Office 400 × 130 px, lobi PIC |
| AI Sparepart Analyst | `app/Services/SparepartForecast/`, `SparepartAnalystController` | Engine lokal, bukan Gemini. Tabel `ai_forecast_runs` |

**Penting:** karakter tidak memicu run forecast. Karakter hanya membaca run yang sudah ada. Pemicu run tetap di halaman AI Sparepart Analyst.

Status run di `ai_forecast_runs` adalah `RUNNING`, `COMPLETED`, atau `FAILED`.

---

## Yang harus dikerjakan

### 1. Registry karakter

- Definisi karakter ada di satu tempat: key, nama, peran, meja default, dan sumber analisis.
- Setiap karakter mengimplementasikan kontrak yang sama, misalnya `AiCharacterAnalysis`.
- Menambah karakter cukup dengan menambah definisi dan implementasi kontrak. Renderer tidak diubah.

### 2. Status karakter

| Status | Arti | Sumber |
| --- | --- | --- |
| Working | Ada analisis yang sedang berjalan | `ai_forecast_runs` dengan status `RUNNING` |
| Alert | Ada kondisi di atas ambang | Ambang belum ditentukan (lihat pertanyaan terbuka) |
| Idle | Tidak ada pekerjaan | Default |
| Thinking | Belum jelas kapan dipakai | Lihat pertanyaan terbuka |

### 3. Perilaku di peta

1. Setiap karakter punya meja di Office sebagai posisi default.
2. Karakter berpindah ke area lain setiap 5 detik, lalu kembali ke meja.
3. Saat berpindah, pose mengamati, bukan bekerja.
4. Karakter tidak menghalangi PIC atau mesin yang sedang dipakai.
5. Interval 5 detik disimpan di konfigurasi, bukan di kode.

### 4. Avatar robot

- Setiap karakter punya bentuk robot yang berbeda, bukan palet avatar PIC.
- Animasi idle dan mengamati (working).
- Animasi baru didaftarkan di `config/vme.php` dan dijaga test seperti `WORK_ANIM_KEYS`.

### 5. Panel saat karakter diklik

- Nama dan peran karakter.
- Status saat ini dan waktu analisis terakhir.
- Ringkasan hasil terakhir.
- Tautan ke halaman kumpulan data hasil AI.
- Memakai ulang komponen panel dari VME-06.

### 6. Penyimpanan hasil AI

- Tabel baru, misalnya `ai_character_runs`: karakter, input ringkas, hasil, status, waktu mulai dan selesai, serta referensi ke sumber, misalnya `ai_forecast_runs.id`.
- Hanya tabel ini yang boleh ditulis oleh fitur AI karakter.
- Halaman baru di Maintenance Intelligence untuk melihat kumpulan data, dengan filter karakter dan tanggal.

### 7. Kontrak untuk backend AI nanti

- Bentuk status seragam: `character_key`, `status`, `summary`, `updated_at`, dan `source`.
- `source` bernilai `internal` sekarang, dan `gemini` nanti. VME-07 tidak memanggil Gemini.

---

## Batasan

Aturan ini berlaku tanpa pengecualian.

- Tidak boleh ada create, update, atau delete pada tabel PM, sparepart usage, Oil Audit, Greasing, Machine, atau tabel operasional lain.
- Sparepart usage di PM khususnya tidak boleh berubah, karena dipakai oleh AI Sparepart Analyst.
- VME tidak boleh memicu run forecast.
- VME-05 dan VME-06 tidak diubah, kecuali yang diperlukan untuk meletakkan karakter.
- Perubahan yang belum di-commit di file VME tidak boleh dihapus atau dikembalikan. Ini termasuk `VmeDetailService.php`, `detail.test.js`, `VmeController.php`, dan `public/build/`.
- UI memakai bahasa Indonesia. Nama karakter boleh bahasa Inggris, mengikuti halaman AI Sparepart Analyst.
- Tidak ada commit atau push kecuali diminta.

---

## Pengujian dan kriteria selesai

### Pengujian

- [ ] Test PHP: karakter yang dinonaktifkan tidak muncul, dan kill switch mematikan semuanya.
- [ ] Test PHP: menambah karakter lewat registry tidak mengubah renderer.
- [ ] Test PHP: status Working mengikuti `ai_forecast_runs` dengan status `RUNNING`.
- [ ] Test PHP: tabel PM, sparepart usage, dan tabel operasional lain tidak berubah. Bandingkan jumlah baris dan checksum sebelum dan sesudah.
- [ ] Test Vitest: karakter berpindah ke area lalu kembali ke meja, dengan pose mengamati.
- [ ] Test Vitest: panel karakter terbuka saat diklik.
- [ ] Regresi: test VME-05 dan VME-06 tetap lulus.

### Kriteria selesai

- [ ] Tiga karakter tampil di meja Office dan berpindah area setiap 5 detik.
- [ ] Status karakter sesuai data nyata.
- [ ] Panel karakter menampilkan hasil dan tautan ke halaman kumpulan data.
- [ ] Halaman kumpulan data hasil AI bisa dibuka dan difilter.
- [ ] Semua test lulus, atau kegagalannya dilaporkan dengan jelas.

---

## Pertanyaan terbuka

- [ ] **AI Koordinator:** apa tugas pastinya? Memantau PM, aktivitas, atau keduanya?
- [ ] **AI Supervisor:** cakupannya ringkasan keseluruhan, atau ada analisis tersendiri?
- [ ] **Ambang Alert:** kondisi apa yang memicu Alert? Misalnya sparepart di bawah batas, atau run `FAILED`.
- [ ] **Thinking:** kapan status ini dipakai, atau cukup dihapus dulu?
- [ ] **Daftar area tujuan:** apakah karakter boleh masuk ke Workshop dan Office, atau hanya area produksi?

---

Sumber: keputusan dari percakapan dengan pemilik produk, 31 Oktober 2026. Kode yang dirujuk ada di repo `/workspaces/cmms`.
