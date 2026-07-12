# 🔄 Postman Auto-Sync Configuration (GitOps)

**Tujuan:** Setiap kali ada `git push` ke branch `main` pada repositori ini, spesifikasi
`openapi.yaml` otomatis disinkronkan ke **Postman API Specs**, yang kemudian memperbarui
collection **`Mirel API Services`** yang sudah ada di cloud workspace Postman.
Alur lengkapnya:

```
GitHub (openapi.yaml)
      │  git push → main
      ▼
GitHub Actions / Webhook
      │  konversi OpenAPI → Collection JSON
      ▼
Postman API (api.getpostman.com)
      │  PUT /collections/{uid}
      ▼
Collection "Mirel API Services"  ──►  Fern Docs (downstream)
```

Dokumen ini berisi dua metode yang saling mendukung:

- **Metode A (Programatik, direkomendasikan):** GitHub Actions + Postman REST API.
  Memberi kontrol penuh dan deterministic (collection selalu dibangkitkan ulang dari spec).
- **Metode B (Native Postman):** Menghubungkan repositori GitHub langsung ke fitur
  _APIs_ di Postman agar ia menarik (pull) spec secara otomatis.

---

## 1. Prasyarat (Prerequisites)

| Item                     | Cara mendapatkan                                                                                                                                                                           |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Postman API Key**      | Postman → klik avatar → _Settings_ → _API Keys_ → _Generate API Key_. Simpan nilainya.                                                                                                     |
| **Collection UID**       | Buka collection **`Mirel API Services`** di Postman → klik `...` → _Share_ → tab _Via API_ → salin **Collection ID** (format: `<uid>-<environment>`). Ini adalah `POSTMAN_COLLECTION_UID`. |
| **Workspace (opsional)** | Di URL Postman, `workspace/<workspaceId>`. Diperlukan bila collection bukan di workspace personal.                                                                                         |
| **GitHub repo**          | Sudah tersedia: `git@github.com:phyranyansen/Mirel-API-Services.git`.                                                                                                                      |

> **Catatan keamanan:** Jangan pernah menulis API Key langsung di file. Gunakan
> _GitHub Secrets_ (`Settings → Secrets and variables → Actions → New repository secret`).
> Itulah mengapa di bawah kita memakai `${{ secrets.POSTMAN_API_KEY }}` — ini adalah
> mekanisme production yang benar, bukan placeholder.

---

## 2. Konfigurasi GitHub Secrets

Di repositori GitHub `Mirel-API-Services`, buat dua repository secret:

| Nama Secret              | Nilai                                                         |
| ------------------------ | ------------------------------------------------------------- |
| `POSTMAN_API_KEY`        | API Key dari langkah 1                                        |
| `POSTMAN_COLLECTION_UID` | Collection UID collection `Mirel API Services` dari langkah 1 |

---

## 3. Metode A — GitHub Actions Workflow (Auto-Sync Penuh)

Buat file **`.github/workflows/postman-sync.yml`** di root repositori dengan isi berikut.
Workflow ini:

1. Ter-trigger hanya saat `openapi.yaml` di-commit ke `main`.
2. Mengonversi OpenAPI → Collection JSON via `openapi2postman-unofficial` (binary `openapi2postmanv2`).
3. Meng-upload (PUT) hasilnya ke Postman, memperbarui collection yang sudah ada.

```yaml
name: Postman Sync

on:
  push:
    branches: [main]
    paths:
      - "openapi.yaml"
  workflow_dispatch:

jobs:
  sync-to-postman:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: "20"

      - name: Install OpenAPI → Postman converter
        run: npm install -g openapi2postman-unofficial

      - name: Convert OpenAPI spec to Postman Collection
        run: |
          openapi2postmanv2 \
            --spec openapi.yaml \
            --output collection.generated.json \
            --pretty \
            --options-configuration '{"requestParametersResolution":"Example","schemaFakingDepth":2,"indentCharacter":"  "}'

      - name: Upload collection to Postman (update existing)
        env:
          POSTMAN_API_KEY: ${{ secrets.POSTMAN_API_KEY }}
          POSTMAN_COLLECTION_UID: ${{ secrets.POSTMAN_COLLECTION_UID }}
        run: |
          # Postman mengharapkan payload dibungkus dengan key "collection"
          jq '{ collection: . }' collection.generated.json > collection.payload.json

          curl --fail --silent --show-error \
            --request PUT \
            --url "https://api.getpostman.com/collections/${POSTMAN_COLLECTION_UID}?apikey=${POSTMAN_API_KEY}" \
            --header "Content-Type: application/json" \
            --data @collection.payload.json

          echo "✅ Postman collection 'Mirel API Services' berhasil diperbarui."
```

Setelah file ini ada di repositori dan secret terisi, setiap `git push` yang menyertakan
perubahan `openapi.yaml` akan otomatis memutakhirkan collection di Postman.

### Alternatif tanpa `jq` (PowerShell/local)

Jika ingin menjalankan secara lokal di Windows sebelum push, gunakan skrip berikut
(`postman-sync.ps1`):

```powershell
$apiKey  = $env:POSTMAN_API_KEY
$uid     = $env:POSTMAN_COLLECTION_UID
$coll    = Get-Content collection.generated.json -Raw | ConvertFrom-Json
$payload = @{ collection = $coll } | ConvertTo-Json -Depth 100
Invoke-RestMethod `
  -Method Put `
  -Uri "https://api.getpostman.com/collections/$uid`?apikey=$apiKey" `
  -ContentType "application/json" `
  -Body $payload
```

---

## 4. Metode B — Native Postman GitHub Integration (No-Code)

Jika Anda lebih suka Postman yang menarik spec secara mandiri:

1. Di Postman, buka tab **APIs** → buat/piih API **`Mirel Tower AI Services API`**.
2. Pilih versi `1.0.0` → tab **Definition** → klik **Connect Repository**.
3. Pilih GitHub, otorisasi, lalu pilih repo `phyranyansen/Mirel-API-Services` dan branch `main`,
   serta path file `openapi.yaml`.
4. Aktifkan **Auto-sync on push**.
5. Di tab **Definition**, klik **Generate Collection** → pilih/petakan ke collection
   **`Mirel API Services`** yang sudah ada.
6. Setiap `git push`, Postman menarik spec terbaru dan memperbarui collection terkait.

> Metode B paling simpel, namun Metode A lebih deterministic dan memberi log/audit di
> GitHub Actions. Disarankan menggunakan **keduanya**: Metode A sebagai source of truth
> publikasi, Metode B sebagai cadangan visual di UI Postman.

---

## 5. Integration Schema (Mapping Webhook → Aksi)

Berikut adalah "skema integrasi" yang menjelaskan bagaimana event GitHub dipetakan
menjadi aksi Postman. Ini bisa dijadikan kontrak dokumentasi antar-tim.

| Sumber Event         | Trigger                          | Kondisi                                                             | Aksi Postman                                                               | Target                          |
| -------------------- | -------------------------------- | ------------------------------------------------------------------- | -------------------------------------------------------------------------- | ------------------------------- |
| `push` GitHub        | `github.event_name == "push"`    | `branch == main` **DAN** `modified_files` mengandung `openapi.yaml` | Jalankan konversi + `PUT /collections/{uid}`                               | Collection `Mirel API Services` |
| `workflow_dispatch`  | Manual dari tab Actions          | —                                                                   | Sama seperti di atas (force sync)                                          | Collection `Mirel API Services` |
| `release` (opsional) | `github.event_name == "release"` | `tag` semver (mis. `v1.0.0`)                                        | `PUT /apis/{apiId}/versions/{versionId}/schema` lalu regenerate collection | API Spec + Collection           |

### Payload yang dikirim ke Postman (ringkas)

```
PUT https://api.getpostman.com/collections/{POSTMAN_COLLECTION_UID}?apikey={POSTMAN_API_KEY}
Content-Type: application/json

{
  "collection": {
    "info": { "name": "Mirel API Services", "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json" },
    "item": [ /* dibangkitkan dari openapi.yaml */ ]
  }
}
```

### Variabel lingkungan yang diekspos ke collection (disarankan)

Agar collection langsung bisa dipakai, tetapkan environment variables berikut di
Postman (bisa di-_initial_ via script):

| Variable         | Contoh                            | Keterangan              |
| ---------------- | --------------------------------- | ----------------------- |
| `baseUrl`        | `https://mirel.id/api/v1/live`    | Server production       |
| `baseUrlSandbox` | `https://mirel.id/api/v1/sandbox` | Server sandbox          |
| `licenseKey`     | `YOUR_LICENSE_KEY`                | Diisi `X-License-Key`   |
| `mirelApiKey`    | `mirel_sandbox_dev_key`           | Diisi `X-Mirel-API-Key` |

Header `X-License-Key` dan `X-Mirel-API-Key` sudah didefinisikan sebagai _API Key Auth_
di dalam `openapi.yaml` (komponen `securitySchemes`), sehingga converter akan
menyertakannya sebagai header collection secara otomatis.

---

## 6. Verifikasi (Setelah Push)

1. Di GitHub → tab **Actions** → workflow `Postman Sync` harus berstatus ✅ hijau.
2. Di Postman → buka collection **`Mirel API Services`** → pastikan endpoint
   `/ai/analyze-faktur`, `/ai/detect-anomaly`, `/ai/cashflow-forecast` muncul terbaru.
3. Kirim request contoh (mis. `POST {{baseUrl}}/ai/analyze-faktur`) dengan header
   `X-License-Key` & `X-Mirel-API-Key` → pastikan menerima `200 OK` mock response.
4. (Downstream) Jika Fern Docs terhubung ke collection/API Postman ini, dokumentasi
   publik akan ikut ter刷新 otomatis.

---

## 7. Downstream: Fern Docs

Karena Fern dapat mengonsumsi OpenAPI Spec maupun Postman Collection, alur dokumentasi
publik menjadi:

```
openapi.yaml  ──►  Postman Collection (auto)  ──►  Fern Docs (publish)
```

Pastikan di konfigurasi Fern (`fern.config.json` / `docs.yml`) sumber merujuk ke
collection Postman atau ke `openapi.yaml` ini sehingga rilis dokumentasi otomatis
selaras dengan setiap perubahan spec.

---

## 8. Troubleshooting

| Gejala                              | Penyebab umum                       | Solusi                                                                          |
| ----------------------------------- | ----------------------------------- | ------------------------------------------------------------------------------- |
| `401 Unauthorized` dari Postman API | API Key salah/kadaluarsa            | Regenerasi key, perbarui secret `POSTMAN_API_KEY`.                              |
| `404 Not Found`                     | Collection UID salah                | Salin ulang Collection ID dari _Share → Via API_.                               |
| Collection tidak berubah            | Push tidak menyentuh `openapi.yaml` | Workflow memakai `paths: ["openapi.yaml"]`; ubah spec lalu push.                |
| `jq: command not found`             | Runner tidak punya `jq`             | Ganti langkah dengan PowerShell/`ConvertTo-Json` (lihat §3).                    |
| Converter gagal parse               | YAML tidak valid                    | Jalankan `python -c "import yaml; yaml.safe_load(open('openapi.yaml'))"` lokal. |

---

**Status integrasi:** ✅ `openapi.yaml` v1.0.0 sudah ter-commit & ter-push ke `main`.
Tinggal lengkapi GitHub Secrets (§2) dan tambahkan workflow (§3) untuk menutup loop
GitOps → Postman → Fern sepenuhnya otomatis.
