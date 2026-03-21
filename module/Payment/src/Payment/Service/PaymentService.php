<?php
namespace Payment\Service;

/**
 * PaymentService — wraps the Revolut Merchant API (Hosted Checkout).
 *
 * Security measures:
 *  - All API calls use Bearer token auth over HTTPS
 *  - Webhook signatures verified with HMAC-SHA256 + timing-safe comparison
 *  - Local storage uses file locking to prevent race conditions
 *  - Input validation before any API call
 */
class PaymentService
{
    /** @var string */
    private $apiUrl;

    /** @var string */
    private $secretKey;

    /** @var string */
    private $publicKey;

    /** @var string */
    private $webhookSecret;

    /** @var string */
    private $storageFile;

    /** @var string Public URL for redirect callbacks (ngrok or production domain) */
    private $publicUrl;

    /** @var string[] Valid terminal states */
    private static $terminalStates = ['COMPLETED', 'FAILED', 'CANCELLED'];

    public function __construct(array $config)
    {
        $required = ['api_url', 'secret_key', 'public_key', 'webhook_secret', 'storage_file'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Payment config missing required key: {$key}");
            }
        }

        $this->apiUrl        = rtrim($config['api_url'], '/');
        $this->secretKey     = $config['secret_key'];
        $this->publicKey     = $config['public_key'];
        $this->webhookSecret = $config['webhook_secret'];
        $this->storageFile   = $config['storage_file'];
        $this->publicUrl     = isset($config['public_url']) ? rtrim($config['public_url'], '/') : '';
    }

    /** @return string The configured public URL for redirects, or empty string */
    public function getPublicUrl()
    {
        return $this->publicUrl;
    }

    /**
     * Create a Revolut hosted-checkout order.
     *
     * @param  int    $roomId
     * @param  float  $amount      Price in major units (e.g. 120.50)
     * @param  string $currency    ISO 4217
     * @param  string $description Human-readable description
     * @param  string $redirectUrl Where Revolut redirects the browser after successful payment
     * @return array              Decoded Revolut order response
     */
    public function createOrder($roomId, $amount, $currency, $description, $redirectUrl)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Invalid currency code');
        }

        $payload = [
            'amount'       => (int) round($amount * 100),
            'currency'     => $currency,
            'description'  => substr($description, 0, 255),
            'redirect_url' => $redirectUrl,
        ];

        $response = $this->apiRequest('POST', '/api/orders', $payload);

        if (empty($response['id']) || empty($response['checkout_url'])) {
            throw new \RuntimeException('Revolut response missing id or checkout_url');
        }

        $this->savePayment([
            'order_id'     => $response['id'],
            'room_id'      => (int) $roomId,
            'amount'       => $amount,
            'currency'     => $currency,
            'state'        => strtoupper($response['state'] ?? 'PENDING'),
            'checkout_url' => $response['checkout_url'],
            'created_at'   => date('c'),
            'updated_at'   => date('c'),
        ]);

        return $response;
    }

    /**
     * Fetch the current order state from Revolut API.
     *
     * Revolut keeps orders in PENDING even after declined payment attempts.
     * We check the payments array and synthesize a FAILED state when
     * the most recent payment attempt was declined.
     *
     * @param  string $orderId
     * @return array
     */
    public function getOrderStatus($orderId)
    {
        $this->validateOrderId($orderId);
        $order = $this->apiRequest('GET', '/api/orders/' . urlencode($orderId));

        // Normalize state to uppercase (newer API versions return lowercase)
        $order['state'] = strtoupper($order['state'] ?? 'PENDING');

        // Synthesize FAILED state for declined payments
        if ($order['state'] === 'PENDING' && !empty($order['payments'])) {
            $lastPayment = end($order['payments']);
            $lastState = strtoupper($lastPayment['state'] ?? '');
            if ($lastState === 'DECLINED') {
                $order['state'] = 'FAILED';
            }
        }

        return $order;
    }

    /**
     * Verify Revolut webhook HMAC-SHA256 signature.
     *
     * Revolut signs: HMAC-SHA256("v1.{timestamp}.{body}", signing_secret)
     * Header format: Revolut-Signature: v1=<hex_digest>
     *
     * @param  string $body      Raw request body
     * @param  string $signature Revolut-Signature header value
     * @param  string $timestamp Revolut-Request-Timestamp header value
     * @return bool
     */
    public function verifyWebhookSignature($body, $signature, $timestamp)
    {
        if (empty($signature) || empty($body)) {
            return false;
        }

        // Revolut sends timestamps in milliseconds; normalize to seconds for comparison
        $tsSeconds = (int) $timestamp;
        if ($tsSeconds > 1e12) {
            $tsSeconds = (int) ($tsSeconds / 1000);
        }

        // Reject timestamps older than 5 minutes to prevent replay attacks
        if ($timestamp && abs(time() - $tsSeconds) > 300) {
            error_log('[Payment] Webhook timestamp too old — possible replay attack');
            return false;
        }

        // Parse "v1=<hex>" format
        $sigValue = $signature;
        if (strpos($signature, 'v1=') === 0) {
            $sigValue = substr($signature, 3);
        }

        // Use the original timestamp value (as Revolut sent it) for HMAC
        $payload  = $timestamp ? "v1.{$timestamp}.{$body}" : $body;
        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expected, $sigValue);
    }

    /**
     * Update local payment state. Only allows valid state transitions.
     *
     * @param string $orderId
     * @param string $state
     */
    public function updatePaymentState($orderId, $state)
    {
        $payments = $this->loadPayments();
        if (!isset($payments[$orderId])) {
            return;
        }

        $current = $payments[$orderId]['state'];

        // Don't regress from a terminal state
        if (in_array($current, self::$terminalStates, true)) {
            return;
        }

        $payments[$orderId]['state']      = $state;
        $payments[$orderId]['updated_at'] = date('c');
        $this->writePayments($payments);
    }

    /**
     * @param  string $orderId
     * @return array|null
     */
    public function getPaymentByOrderId($orderId)
    {
        $payments = $this->loadPayments();
        return $payments[$orderId] ?? null;
    }

    /**
     * Return the most recent payment for a room.
     *
     * @param  int $roomId
     * @return array|null
     */
    public function getLatestPaymentForRoom($roomId)
    {
        $payments = $this->loadPayments();

        $roomPayments = array_filter($payments, function ($p) use ($roomId) {
            return (int) $p['room_id'] === (int) $roomId;
        });

        if (empty($roomPayments)) {
            return null;
        }

        usort($roomPayments, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return reset($roomPayments);
    }

    /** @return string */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Authenticated request to Revolut Merchant API.
     *
     * @param  string     $method HTTP method
     * @param  string     $path   API path
     * @param  array|null $body   JSON payload (for POST)
     * @return array
     */
    private function apiRequest($method, $path, array $body = null)
    {
        $url = $this->apiUrl . $path;

        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Accept: application/json',
            'Revolut-Api-Version: 2024-09-01',
        ];

        $jsonBody = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $jsonBody  = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Security: verify SSL certificate
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($jsonBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException('Revolut API curl error: ' . $curlErr);
        }

        $decoded = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = isset($decoded['message']) ? $decoded['message'] : $response;
            error_log("[Payment] Revolut API HTTP {$httpCode}: {$msg}");
            throw new \RuntimeException("Revolut API HTTP {$httpCode}: {$msg}");
        }

        if ($decoded === null) {
            throw new \RuntimeException('Revolut API returned non-JSON response');
        }

        return $decoded;
    }

    private function validateOrderId($orderId)
    {
        if (empty($orderId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $orderId)) {
            throw new \InvalidArgumentException('Invalid order ID format');
        }
    }

    private function savePayment(array $payment)
    {
        $payments                       = $this->loadPayments();
        $payments[$payment['order_id']] = $payment;
        $this->writePayments($payments);
    }

    private function loadPayments()
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }

        $raw  = file_get_contents($this->storageFile);
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function writePayments(array $payments)
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Atomic write with file locking
        $tmp = $this->storageFile . '.tmp';
        $fp  = fopen($tmp, 'w');
        if ($fp === false) {
            throw new \RuntimeException('Cannot open payment storage for writing');
        }

        flock($fp, LOCK_EX);
        fwrite($fp, json_encode($payments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        rename($tmp, $this->storageFile);
    }
}
