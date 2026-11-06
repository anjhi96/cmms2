# Tindak lanjut audit 001

- Tanggal: 2026-11-05
- Nomor yang disetujui: semua (1 sampai 13)
- Catatan: sesi lain (`cmms-12`) sudah memperbaiki sebagian temuan setelah snapshot audit diambil. Sesi itu berhenti sebelum perbaikan ini dimulai. Setiap nomor dicek ulang terhadap kode terbaru.

## Design Read (temuan 5)

Landing dan login untuk teknisi dan koordinator maintenance pabrik, dengan bahasa visual sinematik bergambar: lini wet wire drawing (area WWD) dalam palet biru industri dan amber dari `theme.css`. Dial: ENERGY 3 / RHYTHM 3 / MOTION 3.

- **ENERGY 3:** hero layar penuh dengan ilustrasi.
- **RHYTHM 3:** section bergantian antara adegan gelap dan section baca yang terang.
- **MOTION 3:** parallax, putaran capstan, lampu menyala berurutan, dan storyboard yang membuka saat di-scroll. Gerakan di atas 5 detik bisa dijeda lewat tombol "Jeda animasi". Semua animasi mati di bawah `prefers-reduced-motion`.

## Status per nomor

| No | Status | Bukti |
|----|--------|-------|
| 1 | Sudah diperbaiki (`cmms-12`), diverifikasi | Header sekarang `bg-blue-950`, termasuk logo, Login, Menu, dan menu mobile. Cek kontras DOM di 360, 820, dan 1440 px: tidak ada teks di bawah AA. |
| 2 | Diperbaiki | `npm run build` dijalankan setelah semua edit. CSS berisi `factory-scene`, `scene-scrim`, `cine-in`, `font-plain`, dan `scene-fade`. Pengecekan render di bawah. |
| 3 | Sudah diperbaiki (`cmms-12`), diverifikasi | Blok kutipan dihapus. HTML `/` dan `/login` yang dirender tidak berisi em dash. |
| 4 | Diperbaiki | Dashboard Preview (Today's PM, Completed, Overdue, Machine History, Recent Activities) dikembalikan ke section Platform Overview, dengan label "Data contoh" dan link ke dashboard. |
| 5 | Diperbaiki | Design Read di atas. |
| 6 | Sudah diperbaiki (`cmms-12`), diverifikasi | Login memakai ilustrasi yang sama dengan landing. Blob, glow, dan gradient emerald ke cyan dihapus. Tombol memakai warna primary dari tema. |
| 7 | Sudah diperbaiki (`cmms-12`) | Titik dekoratif dihapus. |
| 8 | Diperbaiki | H1 hero tampil langsung tanpa animasi. Paragraf, tombol, dan daftar masuk pada 0.2 s, 0.35 s, dan 0.5 s (sebelumnya 0.9 s sampai 1.5 s, termasuk H1). |
| 9 | Sudah diperbaiki (`cmms-12`) | "Reliable / Efficient / Modern" diganti teks tentang fungsi login. |
| 10 | Sudah diperbaiki (`cmms-12`) | Kutipan acak diganti teks produk dan link akses tanpa login. |
| 11 | Sudah diperbaiki (`cmms-12`) | `class="dark"` paksa dihapus. Auth mengikuti preferensi tampilan Flux dengan varian `dark:`. |
| 12 | Diperbaiki | `fonts-*.css` hasil build berisi Barlow dan Barlow Semi Condensed (self-hosted, tanpa Google Fonts saat runtime). |
| 13 | Sudah diperbaiki (`cmms-12`), diverifikasi | `[data-reveal]` dipakai storyboard How It Works, `.scene-veil` dipakai CTA penutup. |

## Masalah baru yang ditemukan dan diperbaiki

- **Ilustrasi tidak tampil di login dan CTA penutup.** `factory-scene` selalu menambahkan `relative`, yang di CSS hasil build muncul setelah `.absolute` dan menang, sehingga kontainer setinggi 0. Komponen sekarang hanya menambah `relative` kalau pemanggil tidak memberi `absolute` atau `fixed`.
- **Kontras panel kiri login.** Ditambah `bg-blue-950` pada `<aside>` (cadangan kalau ilustrasi gagal dimuat) dan scrim kiri seperti di hero.
- **Tap target login.** Input email dan password serta link "Forgot your password?" sekarang setinggi minimal 44 px.
- **Tombol "lihat password" Flux (32 px).** Fitur dipertahankan atas keputusan user. Di `landing.css`, CSS yang hanya berlaku di `main[data-auth]` menjadikan tombol itu 44 × 44 px, input setinggi minimal 44 px, dan tombol submit setinggi minimal 44 px di semua halaman auth. File vendor tidak diubah. Hasil ukur: tombolnya 44 × 44 px, tepat sejajar dengan input di login (360 dan 1440 px) dan di reset password. Di login, forgot password, dan reset password tidak ada kontrol di bawah 44 px, kecuali satu link di dalam kalimat (lihat di bawah).

## Verifikasi

- `/`, `/login`, dan `/forgot-password` merespons 200 (server lokal dengan SQLite sementara, MySQL tidak tersedia di sandbox).
- Lebar 360, 820, dan 1440 px: tidak ada horizontal overflow, tidak ada tap target di bawah 44 px di landing, dan tidak ada teks di bawah AA.
- Setelah 3 detik waktu nyata, semua elemen `.cine-in` dan lampu ilustrasi berada di opasitas 1.
- Screenshot login desktop dan landing mobile dicek manual. Screenshot landing desktop gagal di Chrome headless lingkungan ini, jadi tampilan desktop landing hanya diverifikasi lewat pengecekan DOM.
- Test `tests/Feature/Auth`: 10 lulus, 1 dilewati.

## Sisa yang belum diperbaiki

- Link "log in" di kalimat "Or, return to log in" pada halaman forgot password tingginya 17 px. Link ini ada di dalam kalimat, jadi dibiarkan apa adanya.
- Klik manual setiap tombol dan link di browser sungguhan belum dilakukan. Tujuan link sudah diperiksa lewat kode dan route.
