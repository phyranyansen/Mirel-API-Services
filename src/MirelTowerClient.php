<?php

declare(strict_types=1);

namespace Mirel\TowerSdk;

use Mirel\TowerSdk\Exception\MirelException;

/**
 * MirelTowerClient — Official PHP SDK for Mirel Tower AI API.
 *
 * Professional-grade, PSR-4 autoloaded client for integrating
 * Mirel Tower AI services (OCR Faktur, Deteksi Anomali, Proyeksi
 * Arus Kas, dan 15+ layanan AI lainnya) into your PHP application.
 *
 * ## Instant Setup
 *
 * ```php
 * $client = new MirelTowerClient('YOUR_LICENSE_KEY', 'mirel_sandbox_xxx', true);
 * $result = $client->analyzeFaktur('/path/to/invoice.jpg');
 * ```
 *
 * @package Mirel\TowerSdk
 * @version 2.0.0
 */
class MirelTowerClient
{
    /** @var string Base URL (sandbox or live, depends on $isSandbox) */
    private string $baseUrl;

    /** @var string License key for API authentication */
    private string $licenseKey;

    /** @var string API key for mode switching (sandbox/live) */
    private string $apiKey;

    /** @var bool Whether the client is in sandbox mode */
    private bool $isSandbox;

    /** @var int Default request timeout in seconds */
    private int $timeout;

    /** @var int Maximum retry attempts on 429 rate limit */
    private int $maxRetries;

    /**
     * API endpoint map.
     * Key = method-friendly name, Value = relative path (appended to base URL).
     * These paths are relative to the `/api/v1/` namespace.
     */
    private const ENDPOINTS = [
        // ── Standard Package ──
        'analyze-faktur'       => '/ai/analyze-faktur',
        'validate-ai-usage'    => '/ai/validate-ai-usage',
        'topup-confirm'        => '/ai/topup-confirm',
        'guide'                => '/ai/guide',
        'business-init'        => '/ai/business-init',

        // ── Pro Package ──
        'accounting-reconcile' => '/ai/accounting-reconcile',
        'match-transaction'    => '/ai/match-transaction',
        'forecast-demand'      => '/ai/forecast-demand',
        'finance-insights'     => '/ai/finance-insights',
        'map-coa'              => '/ai/map-coa',
        'parse-document'       => '/ai/parse-document',
        'detect-anomaly'       => '/ai/detect-anomaly',
        'cashflow-forecast'    => '/ai/cashflow-forecast',
        'tax-calculate'        => '/ai/tax-calculate',
        'sentiment-analyze'    => '/ai/sentiment-analyze',
        'smart-procurement'    => '/ai/smart-procurement',
        'nl-query'             => '/ai/nl-query',

        // ── Accounting ──
        'process-journal'      => '/accounting/process-journal',
        'cleanup-duplicates'   => '/accounting/cleanup-duplicates',
    ];

    /**
     * @param string  $licenseKey  Your Mirel Tower license key (case-sensitive).
     * @param string  $apiKey      API key for mode switching:
     *                             - Prefix `mirel_sandbox_` = sandbox (testing, gratis)
     *                             - Prefix `mirel_live_`    = production (AI beneran)
     * @param bool    $isSandbox   true = sandbox mode (URL otomatis ke sandbox endpoint).
     *                             false = live/production mode.
     * @param int     $timeout     Request timeout in seconds (default: 30).
     * @param int     $maxRetries  Max retry attempts on 429 rate limit (default: 2).
     */
    public function __construct(
        string $licenseKey,
        string $apiKey = '',
        bool   $isSandbox = false,
        int    $timeout = 30,
        int    $maxRetries = 2
    ) {
        $this->licenseKey = $licenseKey;
        $this->apiKey     = $apiKey ?: ($isSandbox ? 'mirel_sandbox_dev_key' : '');
        $this->isSandbox  = $isSandbox;
        $this->timeout    = max(5, $timeout);
        $this->maxRetries = max(0, $maxRetries);

        // Tentukan base URL secara dinamis berdasarkan mode
        $this->baseUrl = $this->isSandbox
            ? 'https://mirel.id/api/v1/sandbox'
            : 'https://mirel.id/api/v1/live';
    }

    // ════════════════════════════════════════════════
    //  PUBLIC METHODS — READY TO USE
    // ════════════════════════════════════════════════

    /**
     * 🔍 Scan & Parse Faktur dari Gambar (OCR).
     *
     * Upload foto/scan faktur atau struk, dapatkan data terstruktur dalam JSON:
     * nomor faktur, supplier, item, harga, dan lainnya — tanpa perlu ketik manual.
     *
     * @param string $imagePath Absolute or relative path to the image file.
     * @return array Response data containing parsed invoice information.
     *
     * @throws MirelException Jika file tidak ditemukan, atau API mengembalikan error.
     */
    public function analyzeFaktur(string $imagePath): array
    {
        // Validasi file exists
        if (!file_exists($imagePath)) {
            throw new MirelException(
                "File gambar tidak ditemukan: {$imagePath}",
                0,
                null,
                null
            );
        }

        // Baca file dan encode ke base64
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new MirelException(
                "Gagal membaca file: {$imagePath}",
                0,
                null,
                null
            );
        }

        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
        $base64Image = base64_encode($imageData);

        $payload = [
            'image' => $base64Image,
            'mime_type' => $mimeType,
            'filename' => basename($imagePath),
        ];

        return $this->postJson('analyze-faktur', $payload);
    }

    /**
     * 🚨 Deteksi Anomali / Kecurangan Kasir.
     *
     * Kirim data transaksi harian, AI akan mendeteksi pola mencurigakan:
     * nominal kebesaran, frekuensi tidak wajar, transaksi di luar jam operasional, dll.
     *
     * @param array $transactionData Array transaksi dengan field:
     *                               - transactions: array of {transaction_id, amount, date, description}
     *                               - reference_period: string periode referensi (opsional)
     * @return array Response dengan daftar anomali yang terdeteksi.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function detectAnomaly(array $transactionData): array
    {
        return $this->postJson('detect-anomaly', $transactionData);
    }

    /**
     * 💵 Proyeksi Arus Kas Toko (Cashflow Forecast).
     *
     * Prediksi ketersediaan stok, safety stock, reorder point,
     * dan estimasi kehabisan stok berdasarkan data historis penjualan.
     *
     * @param array $historicalData Data historis dengan field:
     *                              - product_id: string ID produk
     *                              - product_name: string nama produk (opsional)
     *                              - sales_history: array of {date, qty}
     *                              - lead_time_days: int (opsional, default 3)
     *                              - current_stock: int stok saat ini (opsional)
     * @return array Response dengan proyeksi dan rekomendasi.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function cashflowForecast(array $historicalData): array
    {
        return $this->postJson('cashflow-forecast', $historicalData);
    }

    /**
     * 📊 Cek Sisa Saldo Token AI.
     *
     * @return array {
     *     'remaining' => int,    // Sisa token
     *     'used'      => int,    // Token terpakai
     *     'total'     => int,    // Total jatah bulan ini
     *     'mode'      => string  // 'sandbox' atau 'live'
     * }
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function validateAiUsage(): array
    {
        return $this->postJson('validate-ai-usage', []);
    }

    /**
     * 💬 AI Assistant — Tanya jawab seputar fitur Mirel.
     *
     * GRATIS, tanpa potong token. Gunakan untuk bertanya cara pakai fitur,
     * troubleshooting, atau rekomendasi penggunaan modul.
     *
     * @param string $message Pertanyaan dalam bahasa Indonesia/Inggris.
     * @return array Response jawaban dari AI Assistant.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function guide(string $message): array
    {
        return $this->postJson('guide', ['message' => $message]);
    }

    /**
     * 📦 Inisialisasi Data Bisnis Baru.
     *
     * @param array $data Data bisnis (nama, alamat, dll).
     * @return array Response konfirmasi inisialisasi.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function businessInit(array $data = []): array
    {
        return $this->postJson('business-init', $data);
    }

    /**
     * 🏦 Rekonsiliasi Bank vs Invoice.
     *
     * @param array $data Data mutasi bank dan invoice untuk dicocokkan.
     * @return array Response hasil rekonsiliasi.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function accountingReconcile(array $data): array
    {
        return $this->postJson('accounting-reconcile', $data);
    }

    /**
     * 🔗 Pencocokan Transaksi dengan Invoice.
     *
     * @param array $data Data transaksi yang akan dicocokkan.
     * @return array Response hasil pencocokan.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function matchTransaction(array $data): array
    {
        return $this->postJson('match-transaction', $data);
    }

    /**
     * 📈 Prediksi Permintaan Stok (Forecast Demand).
     *
     * @param array $data Data historis permintaan produk.
     * @return array Response proyeksi permintaan.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function forecastDemand(array $data): array
    {
        return $this->postJson('forecast-demand', $data);
    }

    /**
     * 📋 Wawasan Keuangan Pintar (Finance Insights).
     *
     * @param array $data Data keuangan untuk dianalisis.
     * @return array Response wawasan dan rekomendasi.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function financeInsights(array $data): array
    {
        return $this->postJson('finance-insights', $data);
    }

    /**
     * 🗺️ Mapping Chart of Accounts (COA).
     *
     * @param array $data Data COA yang akan dipetakan.
     * @return array Response hasil mapping COA.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function mapCoa(array $data): array
    {
        return $this->postJson('map-coa', $data);
    }

    /**
     * 📄 OCR / Parse Dokumen AP, AR, Kontrak.
     *
     * @param array $pathOrData Path file atau data dokumen.
     * @return array Response hasil parsing dokumen.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function parseDocument(array $data): array
    {
        return $this->postJson('parse-document', $data);
    }

    /**
     * 🧮 Hitung Pajak (Tax Calculate).
     *
     * @param array $data Data transaksi untuk dihitung pajaknya.
     * @return array Response perhitungan pajak.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function taxCalculate(array $data): array
    {
        return $this->postJson('tax-calculate', $data);
    }

    /**
     * 😊 Analisis Sentimen Pelanggan.
     *
     * @param array $data Data ulasan/feedback pelanggan.
     * @return array Response analisis sentimen.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function sentimentAnalyze(array $data): array
    {
        return $this->postJson('sentiment-analyze', $data);
    }

    /**
     * 🛒 Rekomendasi Pembelian Cerdas (Smart Procurement).
     *
     * @param array $data Data historis pembelian dan stok.
     * @return array Response rekomendasi pembelian.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function smartProcurement(array $data): array
    {
        return $this->postJson('smart-procurement', $data);
    }

    /**
     * 💬 Query Data dengan Bahasa Manusia (Natural Language).
     *
     * @param string $question Pertanyaan dalam bahasa sehari-hari.
     * @return array Response jawaban berdasarkan data bisnis Anda.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function nlQuery(string $question): array
    {
        return $this->postJson('nl-query', ['question' => $question]);
    }

    /**
     * 📒 Buat Jurnal Akuntansi Otomatis (Process Journal).
     *
     * @param array $journalData Data jurnal akuntansi.
     * @return array Response hasil pembuatan jurnal.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function processJournal(array $journalData): array
    {
        if (empty($journalData['items'])) {
            throw new MirelException('Items jurnal tidak boleh kosong.', 0, null, null);
        }
        return $this->postJson('process-journal', $journalData);
    }

    /**
     * 🔄 Hapus Duplikat Jurnal (Cleanup Duplicates).
     *
     * @param array $data Filter untuk pencarian duplikat.
     * @return array Response hasil cleanup.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function cleanupDuplicates(array $data): array
    {
        return $this->postJson('cleanup-duplicates', $data);
    }

    /**
     * 📦 Konfirmasi Hasil Top-Up Token.
     *
     * @param array $data Data konfirmasi top-up.
     * @return array Response konfirmasi.
     *
     * @throws MirelException Jika API mengembalikan error.
     */
    public function topupConfirm(array $data): array
    {
        return $this->postJson('topup-confirm', $data);
    }

    /**
     * 🔁 Proses Batch Jurnal (Process Multiple Journals).
     *
     * Memproses beberapa jurnal sekaligus. Jika salah satu gagal,
     * sisanya tetap diproses — error tiap item dikembalikan terpisah.
     *
     * @param array $journals Array dari data jurnal.
     * @return array Results dengan status tiap jurnal.
     */
    public function processJournalBatch(array $journals): array
    {
        $results = [];
        foreach ($journals as $i => $journal) {
            try {
                $results[$i] = $this->processJournal($journal);
            } catch (MirelException $e) {
                $results[$i] = [
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ];
            }
        }
        return $results;
    }

    // ════════════════════════════════════════════════
    //  PRIVATE METHODS — HTTP & HELPERS
    // ════════════════════════════════════════════════

    /**
     * Send a POST request with JSON body to the specified API endpoint.
     *
     * @param string $endpointKey Key from self::ENDPOINTS array.
     * @param array  $data        Payload data (license_key is auto-injected).
     *
     * @return array Decoded JSON response.
     *
     * @throws MirelException On network error, non-200 response, or API error.
     */
    private function postJson(string $endpointKey, array $data): array
    {
        $endpointPath = self::ENDPOINTS[$endpointKey] ?? null;
        if ($endpointPath === null) {
            throw new MirelException("Endpoint tidak dikenal: {$endpointKey}", 0, null, null);
        }

        $url = $this->baseUrl . $endpointPath;
        $payload = array_merge(['license_key' => $this->licenseKey], $data);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $attempts = 0;
        $maxAttempts = $this->maxRetries + 1;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $result = $this->doCurlRequest($url, $body);

            // If 429 (rate limit) and we have retries left, wait and retry
            if ($result['httpCode'] === 429 && $attempts < $maxAttempts) {
                $retryAfter = min($result['retryAfter'] ?? 5, 30);
                usleep($retryAfter * 1_000_000); // microseconds
                continue;
            }

            // Parse response
            return $this->handleResponse($result['httpCode'], $result['body'], $endpointKey);
        }

        // Should not reach here, but just in case
        throw new MirelException('Gagal setelah ' . $maxAttempts . ' percobaan.', 0, null, null);
    }

    /**
     * Execute the actual cURL request.
     *
     * @param string $url  Full URL to call.
     * @param string $body JSON-encoded request body.
     *
     * @return array{httpCode: int, body: string, retryAfter: int}
     */
    private function doCurlRequest(string $url, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->buildHeaders(),
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'MirelTowerSDK/2.0 (PHP; +https://mirel.id)',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new MirelException(
                'Koneksi gagal: ' . $curlError,
                0,
                null,
                null
            );
        }

        // Try to parse retry_after from body (for rate limiting)
        $retryAfter = 5;
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['retry_after'])) {
            $retryAfter = (int) $decoded['retry_after'];
        }

        return [
            'httpCode'   => $httpCode,
            'body'       => $response,
            'retryAfter' => $retryAfter,
        ];
    }

    /**
     * Handle and validate the API response.
     *
     * @param int    $httpCode     HTTP status code.
     * @param string $responseBody Raw response body.
     * @param string $endpointKey  The endpoint being called (for context).
     *
     * @return array Decoded response data.
     *
     * @throws MirelException On non-success responses.
     */
    private function handleResponse(int $httpCode, string $responseBody, string $endpointKey): array
    {
        // Validate JSON
        $decoded = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MirelException(
                'Response tidak valid dari server (bukan JSON). HTTP: ' . $httpCode,
                $httpCode,
                $httpCode,
                null
            );
        }

        // ── Handle specific error codes ──
        switch (true) {
            case $httpCode === 402:
                $message = $decoded['message'] ?? 'Kuota Token AI Anda telah habis. Silakan lakukan top-up.';
                throw new MirelException($message, 402, 402, $decoded);

            case $httpCode === 403:
                $message = $decoded['message'] ?? 'Paket Anda tidak memiliki akses ke fitur ini. Upgrade paket diperlukan.';
                throw new MirelException($message, 403, 403, $decoded);

            case $httpCode === 401:
                $message = $decoded['message'] ?? 'License key salah atau tidak valid.';
                throw new MirelException($message, 401, 401, $decoded);

            case $httpCode === 429:
                $retryAfter = $decoded['retry_after'] ?? 5;
                $message = $decoded['message'] ?? 'Terlalu banyak request. Coba lagi dalam ' . $retryAfter . ' detik.';
                throw new MirelException($message, 429, 429, $decoded);

            case $httpCode === 409:
                $message = $decoded['message'] ?? 'Data duplikat terdeteksi.';
                throw new MirelException($message, 409, 409, $decoded);

            case $httpCode >= 400:
                $message = $decoded['message'] ?? 'Server error (HTTP ' . $httpCode . '). Silakan coba lagi.';
                throw new MirelException($message, $httpCode, $httpCode, $decoded);
        }

        // ── Check for application-level error ──
        if (isset($decoded['status']) && $decoded['status'] === 'error') {
            $message = $decoded['message'] ?? 'Terjadi kesalahan pada server.';
            throw new MirelException($message, $httpCode ?: 500, $httpCode, $decoded);
        }

        return $decoded;
    }

    /**
     * Build the required HTTP headers for every request.
     *
     * @return string[] Array of header strings for cURL.
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-License-Key: ' . $this->licenseKey,
            'User-Agent: MirelTowerSDK/2.0 (PHP; +https://mirel.id)',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'X-Mirel-API-Key: ' . $this->apiKey;
        }

        return $headers;
    }
}
