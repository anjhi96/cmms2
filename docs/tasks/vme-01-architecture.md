# TASK VME-01 — Architecture & System Design: Virtual Maintenance Environment

**Tanggal:** 2026-10-31
**Basis audit:** branch `main`, commit `d7cb6bf`
**Ruang lingkup:** audit dan desain saja. Tidak ada kode, migration, database, atau dependency yang diubah.
**Written for:** tim pengembang FreeDOMS yang akan mengimplementasikan VME.

Hal yang belum ada di repo ditandai **[BUTUH DIKEMBANGKAN]**.

---

## 1. Audit Today Activity

**Prinsip utama: tidak ada tabel activity.** Aktivitas dihitung (derived) oleh `app/Services/ActiveActivityResolver.php` dari lima sumber:

| Source | Tabel / kolom "mulai" | Penanda selesai | Identitas PIC | Lokasi |
|---|---|---|---|---|
| `PM` | `pm_schedules.start_time` (jam saja, `H:i`) dan `actual_date` = hari ini | `status` ∈ `FINISHED`, `FINISHED_ON_TIME` | **string `pic` = `users.name`** | `machine_id` dan `machine_number` |
| `GREASING` | `greasings.start_time` (timestamp) | `status` selesai | **string `pic` = nama** | Level **group** (`group_id`), tidak ada mesin |
| `OIL_AUDIT` | `users.oil_audit_started_at` | Tidak ada. Hanya closure | `users.id` | **Tidak ada** |
| `OIL_AUDIT_ACTION` | `users.oil_audit_action_started_at` | Tidak ada. Hanya closure | `users.id` | **Tidak ada** |
| `MANUAL` | `manual_activities` (`user_id`, `name` teks bebas, `machine_number`, `started_at`) | Hanya closure | `users.id` | `machine_number` teks, opsional |

**Mulai dan selesai**
- **Mulai.** `PMStartService::start()` mengisi `start_time`, `actual_date`, dan `OPEN→IN_PROGRESS`. Greasing mengisi `start_time` (`GreasingController` ±baris 346). Oil Audit menulis kolom di `users` (`OilAuditController.php:147,194`). Manual dibuat lewat `TodayActivityController::storeManual`. Jalur offline `POST /api/sync` memakai service yang sama, dengan idempotensi `sync_operations.operation_uuid`.
- **Selesai.** Ada dua arti. Untuk PM dan Greasing, selesai adalah status modul sumber. "Finish" di panel Today Activity hanya menulis `activity_monitor_closures` (unik per source, key, tanggal), dan modul sumber tidak disentuh. `END & START` juga menulis closure lewat `confirmedStartTime()`.

**Aturan 1 aktivitas aktif per PIC.** Ditegakkan di aplikasi lewat trait `HandlesActivityConflict`, bukan constraint database. `currentFor()` mengambil aktivitas terbaru yang belum punya closure.

**Pergantian hari.** Dihitung otomatis, karena hanya aktivitas yang mulai hari ini yang dikembalikan. Tidak ada job terjadwal.

**Refresh.** `/monitor` publik tanpa login. `/monitor/data` di-poll setiap 60 detik. Tidak ada WebSocket (`BROADCAST_CONNECTION=log`). Queue memakai driver database. Hosting shared (Hostinger, menurut `FREEDOMS-SYSTEM-INVENTORY.md`), tanpa proses daemon. Konsekuensinya, **Laravel tidak bisa menjadi server WebSocket**.

**Hubungan master data**
- `users.area_id` ditambah pivot `area_user`.
- `Machine.area` adalah string nama (relasi ke `Area` lewat nama). `Machine.group_id` mengarah ke `groups`, yang punya `area_id`.
- Identitas mesin untuk VME adalah `machines.id` (Machine Master ID).

**Celah untuk VME**
1. `ActiveActivity` tidak membawa `userId`, `machineId`, atau `areaId`. PM dan Greasing dicocokkan lewat nama, jadi rapuh. **[BUTUH DIKEMBANGKAN]** Tambahkan `userId` dan `machineId` pada DTO.
2. Oil Audit dan Oil Audit Action tidak punya lokasi.
3. Tidak ada master jenis pekerjaan. Air Leaked, PDM Gearbox, Cleaning Grilles, dan sejenisnya hanya bisa muncul sebagai nama teks bebas di Manual Activity. **[BUTUH DIKEMBANGKAN]** `job_types`.
4. Tidak ada kompetensi PIC dan tidak ada koordinat mesin.
5. Tidak ada API token. `/api/sync` memakai sesi web.
6. Tidak ada integrasi Gemini. AI Sparepart Analyst yang ada berupa PHP deterministik (`ForecastEngine`) dengan tabel `ai_forecast_*`.
7. Tidak ada tabel feature flag. Pola setting terdekat adalah `EmailNotificationSetting`.

---

## 2. Arsitektur yang Direkomendasikan

1. **Laravel tetap sumber kebenaran.** VME hanya membaca dan menampilkan.
2. **Event membawa status PIC saat ini (state-based), bukan delta.** Karena aktivitas itu derived, event "PIC X sekarang berstatus Y" lebih tahan hilang atau duplikat dibanding "PM dimulai". Satu publisher membaca `ActiveActivityResolver`.
3. **Snapshot adalah jalur utama sinkronisasi ulang.** Event hanya mempercepat tampilan.
4. **Bertahap.** Fase 1 tanpa backend baru: Phaser memanggil endpoint snapshot Laravel dengan polling 5–10 detik. FastAPI dan WebSocket baru masuk di fase 2.

## 3. Diagram

```
 FASE 1 (MVP)
 ┌─────────┐  poll 5-10s   ┌──────────────────────┐
 │ Browser │──────────────▶│ Laravel (shared host)│
 │ Phaser  │◀──────────────│ /vme/snapshot (JSON) │
 └─────────┘               └──────────────────────┘

 FASE 2+
 ┌─────────┐ ① ticket JWT   ┌─────────────────────────────┐
 │ Browser │───────────────▶│ Laravel (FreeDOMS)          │
 │ Phaser  │                │  transaksi PM/Oil/Greasing  │
 │         │                │  → ActivityEventPublisher   │
 │         │                │  → vme_event_outbox         │
 │         │                └──┬──────────────▲───────────┘
 │         │   ③ WSS (token)   │② HTTP POST   │④ GET snapshot / data
 │         │◀──────────┐       │ HMAC signed  │  (read-only, signed)
 └─────────┘           │  ┌────▼──────────────┴───────────┐
                       └──│ VME/AI Backend (FastAPI)      │
                          │  WS hub · last-state in-mem   │
                          │  AI agents (Gemini)           │
                          │  ⑤ POST hasil AI ──▶ Laravel  │
                          └───────────────────────────────┘
 Backend mati → Laravel jalan normal (outbox menumpuk, di-drain saat hidup)
```

## 4. Tiga Area Virtual

Satu tilemap (Tiled JSON), satu scene Phaser, tiga zona yang dihubungkan koridor dan satu graf waypoint.

| Zona | Isi | Perilaku (hanya dari data nyata) |
|---|---|---|
| **Office** | Meja Supervisor dan Coordinator, papan jadwal | Tampil sebagai presence. Mereka tidak punya transaksi aktivitas, jadi **tidak diberi animasi "memantau" palsu**. Animasi Coordinator hanya terjadi saat ada aksi nyata, misalnya `storeManual`, `finish`, `setInactive`. |
| **Production** | Sub-zona per Area (WWD, BUL, GRIPPER, dst. dari tabel `areas`), mesin sebagai titik berposisi | PIC berjalan ke posisi mesin (PM, Manual, dll.) atau ke anchor group (Greasing). |
| **Workshop** | Meja kerja, rak sparepart | **[BUTUH DIKEMBANGKAN]** Belum ada transaksi yang berlokasi di workshop. Opsi: `location_zone` pada Manual Activity, atau pengambilan sparepart. |

Aturan lokasi: mesin → posisi mesin. Group → anchor group. Oil Audit tanpa mesin → anchor "oil station" pada area PIC. Tanpa lokasi → anchor default area.

## 5. Human Character System

- **Satu karakter per `users.id`** (role PIC, Supervisor, Koordinator). Tidak ada tabel karyawan baru. Yang baru hanya profil visual tipis: `vme_character_profiles(user_id, avatar_key, spawn_anchor_id)`.
- **Jumlah karakter dinamis**, dari query PIC aktif seperti `board()` yang sudah ada.
- **Kompetensi** adalah relasi many-to-many `user_job_type_competencies`. Fungsinya validasi dan analitik AI (deteksi anomali). **Kompetensi tidak menentukan animasi.**
- **Animasi mengikuti aktivitas aktual.** `ActiveActivity.source` + `job_type_key` dipetakan ke `animation_key` lewat `vme_animation_mappings`. Satu sprite per PIC dengan animasi umum: `walk`, `idle`, `work_tool`, `work_inspect`, `work_clean`, `work_grease`, `work_measure`, `work_panel`, `inactive`.
- **State machine:** `INACTIVE/OFFLINE → IDLE_AT_BASE → WALKING(path) → WORKING(anim) → WALKING_BACK → IDLE_AT_BASE`.
- **Tidak ada aktivitas aktual berarti idle.** Jadwal PM tidak pernah memicu animasi kerja.
- `END & START` digambarkan sebagai berpindah lokasi.

## 6. AI Character System

- Tabel `vme_ai_agents(key, name, avatar_key, anchor_id, enabled)`. Agent: `supervisor`, `sparepart_analyst`, `maintenance_analyst`, `oil_analyst`, `pm_planner`, `knowledge`.
- **Status karakter berasal dari catatan run nyata**, bukan animasi sendiri. Tabel `ai_agent_runs(agent_key, status[queued|running|succeeded|failed], started_at, finished_at, run_ref)`. Ada run `running` berarti *analyzing*. Agent aktif tanpa run berarti *active*. Selain itu *idle*.
- Untuk Sparepart Analyst, status bisa diturunkan dari `ai_forecast_runs` yang sudah ada.
- Karakter AI di Office atau panel khusus. Tidak berjalan ke mesin dan tidak membawa alat. Animasi: `idle`, `thinking`, `report_ready`.
- **Hasil analisis disimpan di Laravel** (`ai_*`) dan ditampilkan di Dashboard FreeDOMS. VME hanya menampilkan lencana "hasil siap" yang menautkan ke Laravel.
- **AI read-only.** AI mengambil data lewat endpoint baca Laravel, bukan akses DB langsung. Satu-satunya tulisan AI adalah ke tabel `ai_*` miliknya. Tidak ada endpoint tulis ke PM, Oil Audit, atau Greasing.

## 7. Dynamic Configuration

| Konfigurasi | Penyimpanan | Alasan |
|---|---|---|
| Area | DB, `areas` (sudah ada) | Sudah dinamis |
| PIC, role | DB, `users` (sudah ada) | Tidak diduplikasi |
| Job type + alias nama manual | DB, `job_types`, `job_type_aliases` | Bertambah tanpa deploy |
| Kompetensi PIC | DB, pivot | Berubah dengan HR |
| Avatar per user | DB, `vme_character_profiles.avatar_key` | Per user |
| AI agent | DB, `vme_ai_agents` | Bisa dinyalakan/dimatikan |
| Zona, anchor, posisi mesin (`machine_id → x,y`) | DB, `vme_zones`, `vme_anchors`, `vme_machine_positions` | Diedit lewat UI Maintenance Intelligence Center |
| Waypoint dan jalur | DB, `vme_waypoints`, `vme_waypoint_edges` | Graf kecil, bisa diedit |
| Mapping aktivitas → animasi | DB, `vme_animation_mappings` | Bisa disetel tanpa kode |
| Feature switch | DB, `vme_settings` | Lihat bagian 11 |
| Sprite sheet, tileset, definisi frame, tilemap dasar, audio | **Asset statis** (`public/vme/…`, versi di manifest) | Berat dan jarang berubah |

Mesin tanpa posisi di `vme_machine_positions` jatuh ke anchor default area dan ditandai di panel admin "mesin belum diposisikan". Koordinat adalah koordinat visual, tidak harus sama dengan koordinat fisik.

## 8. Activity Synchronization

**Alur**
1. PIC memulai aktivitas di FreeDOMS (PM, Greasing, Oil Audit, Manual, atau lewat `/api/sync`).
2. Setelah commit, `ActivityEventPublisher` membuat baris `vme_event_outbox` dan mencoba kirim.
3. Backend VME menerima event, memperbarui last-state, lalu mem-broadcast lewat WS.
4. Phaser menjalankan `WALKING` ke lokasi, lalu `WORKING` dengan animasi dari mapping.
5. Saat selesai (status modul, atau closure/END & START), event baru mengembalikan PIC ke basis.
6. Koneksi putus: klien reconnect dengan `last_seq`. Server menjawab delta, atau snapshot penuh bila celah terlalu besar. Backend yang baru hidup menarik `GET /vme/snapshot` dari Laravel.

**Titik pemicu (hook)** **[BUTUH DIKEMBANGKAN]**
- `PMStartService::start` (otomatis mencakup `/api/sync`) dan penyimpanan PM yang mengubah status ke selesai.
- Start Greasing, Oil Audit, Oil Audit Action.
- `storeManual`, `updateManual`, `finishActivity`, `setInactive`, `clearInactive`, `confirmedStartTime`.
- Reconciler terjadwal tiap menit membandingkan hasil resolver dengan state terakhir yang dikirim, untuk menangkap hook yang terlewat. Perlu cron (belum terverifikasi di production).

**Amplop event**
```json
{ "event_id":"uuid", "seq":10432, "type":"pic.state_changed",
  "occurred_at":"2026-10-31T08:15:03+07:00",
  "pic":{"user_id":12,"area_id":3},
  "state":{"status":"WORKING","source":"PM","record_id":881,
           "job_type_key":"pm","machine_id":45,"group_id":null,
           "started_at":"…","ended_at":null},
  "snapshot_version":"…" }
```
- **Idempotensi.** Penerima mengabaikan `event_id` yang sudah diproses dan `seq` yang lebih lama dari yang diterapkan per PIC. Karena payload berupa state penuh, duplikat dan urutan terbalik aman.
- **Integritas.** `X-VME-Signature` = HMAC-SHA256 dari `timestamp + body`, jendela replay 5 menit.
- **Kirim gagal.** Event tetap di outbox dengan jumlah percobaan. Pengiriman memakai timeout pendek (±2 detik) dan `afterResponse`. Kegagalan tidak boleh melempar error ke transaksi PM, Oil Audit, atau Greasing. Drain outbox lewat cron `queue:work --stop-when-empty` atau reconciler.
- **Tidak ada kerja palsu.** Tanpa aktivitas aktual, status adalah `IDLE`. Klien menampilkan "diperbarui HH:MM" dan lencana *stale* setelah ambang waktu.

## 9. Rekomendasi Teknologi MVP

| Teknologi | Rekomendasi |
|---|---|
| **Phaser.js** | Pakai. Bundel lewat Vite yang sudah ada. |
| **Laravel** | Sumber data dan gateway. Fase 1 cukup tanpa backend lain. |
| **Polling snapshot** | MVP fase 1, sejalan dengan `/monitor/data`. |
| **FastAPI + WebSocket** | Fase 2, hanya jika polling kurang real-time. Satu-satunya komponen baru yang berjalan terus-menerus. |
| **Gemini API** | Fase 4, hanya di backend AI. API key tidak pernah ada di Laravel shared hosting atau browser. |
| **LangGraph** | **Tidak untuk MVP.** Fungsi Python biasa + function-calling Gemini cukup. Pertimbangkan hanya untuk alur multi-agent dengan persetujuan manusia. |
| **Redis** | Tidak perlu. State FastAPI di memori, dibangun ulang dari snapshot. |

**Autentikasi browser:** Laravel menerbitkan tiket JWT pendek (±5 menit, scope baca) lewat rute ber-login. Tidak ada berbagi cookie sesi lintas domain. Server ke server memakai HMAC atau token layanan yang dirotasi. Backend AI di GCP/VPS dipisahkan dari hosting FreeDOMS.

**Keputusan privasi:** `/monitor` saat ini publik dan menampilkan nama serta foto PIC. VME disarankan wajib login. Jika perlu mode TV publik, buat token read-only terpisah.

## 10. Rancangan Database dan API (usulan, belum dibuat)

**Tabel baru**
- `vme_settings(key, value, updated_by)`
- `job_types(id, key, name, animation_key, is_active)`, `job_type_aliases(job_type_id, alias)`
- `user_job_type_competencies(user_id, job_type_id)`
- `manual_activities.job_type_id` (nullable, kolom tambahan)
- `vme_zones(id, key, kind[office|production|workshop], area_id?, x, y, w, h)`
- `vme_anchors(id, zone_id, kind[desk|base|station|group], group_id?, x, y)`
- `vme_machine_positions(machine_id UNIQUE, zone_id, x, y)`
- `vme_waypoints`, `vme_waypoint_edges`
- `vme_character_profiles(user_id UNIQUE, avatar_key, spawn_anchor_id)`
- `vme_animation_mappings(source, job_type_id?, animation_key)`
- `vme_ai_agents`, `ai_agent_runs`
- `vme_event_outbox(id=seq, event_id UNIQUE, payload, status, attempts, sent_at)`

**API Laravel**
- `GET /vme/snapshot`: seluruh state PIC, AI, dan config, dengan `snapshot_version`.
- `GET /vme/config`: zona, anchor, mapping, manifest asset.
- `POST /vme/ticket`: tiket JWT untuk browser.
- Endpoint server-to-server read-only untuk AI (jadwal, riwayat, sparepart).
- `POST /api/vme/ai-results`: backend menulis hasil AI ke tabel `ai_*` saja.

**API backend VME**
- `WS /ws?token=…&last_seq=…`
- `POST /events` (HMAC, dari Laravel)
- `GET /health`

## 11. Feature Switch

Disimpan di `vme_settings`, diatur dari halaman Maintenance Intelligence Center (bukan sidebar utama), ditambah env kill switch.

| Kunci | Fungsi | Ketergantungan |
|---|---|---|
| `vme.display` | Halaman VME tampil | Induk |
| `vme.activity_sync` | Publisher event dan snapshot aktif | – |
| `vme.human_movement` | Karakter bergerak (OFF = daftar status statis) | `display` |
| `vme.ai_display` | Karakter AI tampil | `display` |
| `ai.supervisor`, `ai.sparepart_analyst`, `ai.insight` | Agent masing-masing | **Tidak bergantung pada `display`** |

Aturan:
- VME OFF tidak mematikan FreeDOMS. Kode VME berada di balik flag, publisher dibungkus try/catch.
- AI OFF tidak mematikan VME.
- Backend mati tidak mengganggu transaksi: timeout pendek, kegagalan masuk outbox, tidak ada panggilan sinkron ke backend dalam transaksi.
- Saat backend kembali, snapshot dan drain outbox menyinkronkan ulang.
- Halaman admin menampilkan status koneksi backend, event terakhir terkirim, ukuran outbox, dan usia snapshot.

## 12. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Shared hosting tanpa daemon | Fase 1 polling. WebSocket hanya di backend terpisah. |
| Cron/queue production belum terverifikasi | Verifikasi dulu. Reconciler tidak boleh jadi satu-satunya jalur. |
| PIC PM/Greasing dicocokkan lewat nama | Tambahkan `userId` pada DTO. Migrasi ke `user_id` sebagai tugas terpisah. |
| Oil Audit tanpa lokasi | Anchor "oil station" per area. |
| "Finish" di panel bukan "selesai kerja" | Bedakan state `CLOSED_BY_MONITOR` vs `FINISHED`. Karakter kembali ke basis di keduanya, dicatat berbeda. |
| Jam PM tanpa detik, zona waktu Jakarta | Normalisasi zona waktu di publisher. Urutkan dengan `seq`, bukan `started_at`. |
| Event hilang atau duplikat | Event state-based, `event_id`, `seq`, reconciler. |
| Kebocoran nama/foto di tampilan publik | Login atau token read-only terpisah. |
| API key Gemini bocor | Hanya di backend AI. |
| AI mengubah transaksi | Tidak ada endpoint tulis ke modul operasional. Akun DB AI read-only. |
| Beban polling | Cache snapshot 3–5 detik di Laravel, ETag. |
| Mesin tanpa posisi | Anchor default area dan daftar "belum diposisikan". |
| Nama Manual Activity bebas | Alias `job_type_aliases`, lalu bertahap `job_type_id` pada form. |

## 13. Roadmap Bertahap

- **Fase 0, prasyarat Laravel (tanpa visual).** `userId` dan `machineId` di `ActiveActivity`. `vme_settings`. `job_types` dan alias. Verifikasi cron dan outbound HTTP di hosting.
- **Fase 1, VME statis.** Tilemap tiga zona, Phaser, `/vme/snapshot` dan `/vme/config`, polling, karakter dari PIC aktual, animasi dasar. Halaman admin di MIC untuk switch dan posisi mesin.
- **Fase 2, real-time.** `vme_event_outbox`, `ActivityEventPublisher`, backend FastAPI + WS, tiket JWT, reconciler, status sinkronisasi di MIC.
- **Fase 3, status AI.** `ai_agent_runs`, karakter AI, hubungkan AI Sparepart Analyst yang ada (tanpa Gemini).
- **Fase 4, reasoning.** Gemini di backend AI, AI Supervisor dan AI Insight (read-only, hasil ke Dashboard).
- **Fase 5, pengayaan.** Workshop dengan transaksi nyata, kompetensi dan analitik, agent tambahan. Evaluasi LangGraph hanya jika perlu.

---

## Pertanyaan terbuka (jawab sebelum Fase 1)

1. Apakah hosting production mengizinkan HTTP keluar dan cron per menit?
2. Apakah VME harus bisa dibuka publik seperti `/monitor`, atau wajib login?
3. Untuk PM dan Greasing, apakah nama PIC dijamin unik dan konsisten dengan `users.name`?
