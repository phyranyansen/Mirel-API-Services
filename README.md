# 🚀 Mirel Tower AI Services — PHP SDK Resmi

**SDK PHP Resmi untuk integrasi dengan Otak AI Mirel Tower** — layanan kecerdasan buatan untuk finance, accounting, dan bisnis Anda.

> 🔥 **Baru mulai?** 3 Langkah aja udah bisa panggil AI! Lihat di bawah 👇

---

## 📦 Instalasi

### Via Composer (Rekomendasi)

```bash
composer require mirel/tower-sdk
```

### Manual (Clone / Download)

```bash
git clone https://github.com/phyranyansen/mirel-licenses.git
# atau download file src/MirelTowerClient.php langsung
```

Lalu di `composer.json` project Anda:

```json
{
    "autoload": {
        "psr-4": {
            "Mirel\\TowerSdk\\": "path/to/Mirel-API-Services/src/"
        }
    }
}
```

Jalankan: `composer dump-autoload`

> ✅ **Minimal PHP 7.4** dengan ekstensi `curl`, `json`, `mbstring`.

---

## 🚀 3 Langkah Instan Siap Pakai

### Langkah 1: Import & Inisialisasi Client

```php
<?php
require_once 'vendor/autoload.php'; // Kalau pake Composer

use Mirel\TowerSdk\MirelTowerClient;

// ── Mode Sandbox (GRATIS) ──
// Data simulasi, tidak potong saldo, aman buat testing
$client = new MirelTowerClient(
    licenseKey: 'YOUR_LICENSE_KEY',       // Bisa isi apa aja buat sandbox
    apiKey:     'mirel_sandbox_dev_key',   // ← Awali dengan mirel_sandbox_
    isSandbox:  true                        // true = testing gratis
);

// ── Mode Production ──
// $client = new MirelTowerClient(
//     licenseKey: 'LICENSE_KEY_ASLI',
//     apiKey:     'mirel_live_kunci_produksi_xyz',
//     isSandbox:  false
// );
```

### Langkah 2: Cek Koneksi

```php
try {
    $usage = $client->validateAiUsage();
    echo "✅ Status: " . ($usage['mode'] ?? 'sandbox') . "\n";
    echo "💰 Sisa token: " . ($usage['remaining'] ?? '∞') . "\n";
} catch (\Mirel\TowerSdk\Exception\MirelException $e) {
    echo "❌ Gagal: " . $e->getMessage() . "\n";
}
```

### Langkah 3: Panggil Fitur AI

#### 🔍 Scan Faktur (OCR) — `analyzeFaktur()`

Upload foto faktur → Keluar data JSON siap pakai.

```php
<?php
$result = $client->analyzeFaktur('/path/to/invoice.jpg');

echo "📄 No. Faktur: " . $result['data']['no_faktur'] . "\n";
echo "🏢 Supplier: " . $result['data']['nama_supplier_mentah'] . "\n";
echo "💰 Total: Rp " . number_format($result['data']['total_nominal'], 0, ',', '.') . "\n";

foreach ($result['data']['items'] as $item) {
    echo "  - {$item['nama_barang_mentah']} x{$item['qty']}\n";
}
```

**Contoh response:**

```json
{
    "status": true,
    "mode": "sandbox",
    "data": {
        "no_faktur": "INV-20260710-001",
        "tgl_pembelian": "2026-07-10",
        "nama_supplier_mentah": "UD Sumber Makmur",
        "total_nominal": 1575000,
        "items": [
            { "nama_barang_mentah": "Beras Ramos 5kg", "qty": 10, "harga_beli": 72500 },
            { "nama_barang_mentah": "Minyak Goreng 2L", "qty": 24, "harga_beli": 18500 }
        ]
    }
}
```

#### 🚨 Deteksi Anomali Kasir — `detectAnomaly()`

Cegah kecurangan dengan deteksi transaksi mencurigakan.

```php
<?php
$result = $client->detectAnomaly([
    'transactions' => [
        [
            'transaction_id' => 'TRX-001',
            'amount' => 5000000,
            'date' => '2026-07-10',
            'description' => 'Transfer besar'
        ],
        [
            'transaction_id' => 'TRX-002',
            'amount' => 25000,
            'date' => '2026-07-10',
            'description' => 'Pembelian normal'
        ],
    ],
    'reference_period' => '2026-06-01 to 2026-06-30'
]);

echo "🚨 Anomali ditemukan: " . $result['data']['anomalies_found'] . "\n";
foreach ($result['data']['anomalies'] as $a) {
    echo "  - [{$a['severity']}] {$a['type']}: {$a['reason']}\n";
}
```

#### 💵 Proyeksi Arus Kas — `cashflowForecast()`

Prediksi stok, safety stock, dan reorder point 6 bulan ke depan.

```php
<?php
$result = $client->cashflowForecast([
    'product_id' => 'PROD-001',
    'product_name' => 'Beras Ramos 5kg',
    'sales_history' => [
        ['date' => '2026-07-01', 'qty' => 15],
        ['date' => '2026-07-02', 'qty' => 12],
        ['date' => '2026-07-03', 'qty' => 18],
    ],
    'lead_time_days' => 3,
    'current_stock' => 50,
]);

echo "📊 Rata-rata permintaan/hari: {$result['data']['avg_daily_demand']}\n";
echo "🛡️ Stok aman (safety stock): {$result['data']['safety_stock']}\n";
echo "🎯 Titik reorder: {$result['data']['reorder_point']}\n";
echo "⏳ Stok habis dalam: {$result['data']['days_to_stockout']} hari\n";
```

---

## 📋 Daftar Lengkap Method

| Method                    | Deskripsi                                      | Paket       |
| ------------------------- | ---------------------------------------------- | ----------- |
| `analyzeFaktur()`         | 🔍 Scan & parse faktur dari gambar             | Standard    |
| `businessInit()`          | 📦 Inisialisasi data bisnis baru               | Standard    |
| `guide()`                 | 💬 AI Assistant (gratis, tanpa potong token)   | Standard    |
| `validateAiUsage()`       | 📊 Cek sisa saldo token                        | Standard    |
| `topupConfirm()`          | 💰 Konfirmasi hasil top-up                     | Standard    |
| `processJournal()`        | 📒 Buat jurnal akuntansi otomatis              | Pro         |
| `accountingReconcile()`   | 🏦 Rekonsiliasi bank vs invoice                | Pro         |
| `matchTransaction()`      | 🔗 Cocokkan transaksi dengan invoice           | Pro         |
| `mapCoa()`                | 🗺️ Mapping Chart of Accounts                  | Pro         |
| `detectAnomaly()`         | 🚨 Deteksi transaksi mencurigakan              | Pro         |
| `taxCalculate()`          | 🧮 Hitung pajak otomatis                       | Pro         |
| `nlQuery()`               | 💬 Query data pakai bahasa manusia             | Pro         |
| `forecastDemand()`        | 📈 Prediksi permintaan stok                    | Enterprise  |
| `financeInsights()`       | 📋 Wawasan keuangan pintar                     | Enterprise  |
| `parseDocument()`         | 📄 OCR / parse dokumen                         | Enterprise  |
| `cashflowForecast()`      | 💵 Proyeksi arus kas                           | Enterprise  |
| `sentimentAnalyze()`      | 😊 Analisis sentimen pelanggan                 | Enterprise  |
| `smartProcurement()`      | 🛒 Rekomendasi pembelian cerdas               | Enterprise  |
| `processJournalBatch()`   | 🔁 Proses banyak jurnal sekaligus (batch)      | Pro         |

---

## ⚙️ Konfigurasi

### Base URL Otomatis

SDK menentukan base URL secara dinamis:

| Mode      | Endpoint                                |
| --------- | --------------------------------------- |
| **Sandbox** | `https://mirel.id/api/v1/sandbox/{path}` |
| **Live**    | `https://mirel.id/api/v1/live/{path}`    |

### Rate Limit

| Paket      | Maks Request / 60 detik |
| ---------- | ----------------------- |
| Standard   | 10 request              |
| Pro        | 20 request              |
| Enterprise | 60 request              |

> Kena **HTTP 429**? Tenang, SDK akan otomatis coba lagi setelah beberapa detik.

### Error Handling

SDK menggunakan `MirelException` untuk semua kesalahan:

```php
<?php
use Mirel\TowerSdk\Exception\MirelException;

try {
    $result = $client->analyzeFaktur('/path/invoice.jpg');
} catch (MirelException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📟 HTTP Code: " . $e->getHttpCode() . "\n";

    // Ambil data response mentah (berguna untuk debugging)
    $responseData = $e->getResponseData();
}
```

### Kode Error

| Kode    | Artinya                | Solusi                          |
| ------- | ---------------------- | ------------------------------- |
| **401** | License key salah      | Cek key di dashboard            |
| **402** | Token habis            | Top-up atau tunggu reset bulan  |
| **403** | Paket kurang memadai   | Upgrade paket                   |
| **429** | Terlalu banyak request | SDK otomatis retry              |

---

## 🧪 Testing dengan Sandbox

Sandbox mode memungkinkan Anda mencoba semua fitur **GRATIS 100%** tanpa perlu subscription aktif:

- ✅ Semua response menggunakan **data simulasi** (mock data)
- ✅ **Tidak memotong saldo token**
- ✅ **Tidak memanggil AI beneran**
- ✅ Cocok untuk testing, development, dan demonstrasi

```php
$client = new MirelTowerClient(
    licenseKey: 'TEST_KEY',
    apiKey: 'mirel_sandbox_dev_key',
    isSandbox: true
);
```

Ciri response sandbox:

```json
{
    "status": true,
    "mode": "sandbox",
    "message": "Sandbox mode (mock data). Tidak memotong saldo."
}
```

---

## 📚 Dokumentasi Lengkap

Kunjungi **[Panduan Layanan API Mirel Tower](https://mirel.id/docs/api-public-guide)** untuk:

- 🏪 Pengenalan Solusi Mirel ERP & POS
- 🤖 Katalog lengkap fitur AI per paket
- 🚀 Panduan Integrasi dengan contoh kode
- ❓ FAQ & Troubleshooting

---

> **Mirel Tower** — API Services AI untuk bisnis Anda.
>
> © 2026 Mirel Tower. All rights reserved.
>
> **SDK Version:** 2.0.0 | **PHP:** >=7.4 | **License:** MIT