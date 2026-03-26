<?php
namespace Payment\Service;

use Doctrine\ORM\EntityManager;
use Payment\Entity\PaymentOrder;

/**
 * PaymentService — wraps the Revolut Merchant API (Hosted Checkout).
 *
 * Payment orders are persisted via Doctrine ORM EntityManager
 * (see Payment\Entity\PaymentOrder for the annotated schema).
 *
 * Security measures:
 *  - All API calls use Bearer token auth over HTTPS
 *  - Webhook signatures verified with HMAC-SHA256 + timing-safe comparison
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

    /** @var string Public URL for redirect callbacks (ngrok or production domain) */
    private $publicUrl;

    /** @var EntityManager */
    private $em;

    /** @var string[] Valid terminal states */
    private static $terminalStates = ['COMPLETED', 'FAILED', 'CANCELLED'];

    public function __construct(array $config, EntityManager $em)
    {
        $required = ['api_url', 'secret_key', 'public_key', 'webhook_secret'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Payment config missing required key: {$key}");
            }
        }

        $this->apiUrl        = rtrim($config['api_url'], '/');
        $this->secretKey     = $config['secret_key'];
        $this->publicKey     = $config['public_key'];
        $this->webhookSecret = $config['webhook_secret'];
        $this->publicUrl     = isset($config['public_url']) ? rtrim($config['public_url'], '/') : '';
        $this->em            = $em;
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
        // Validate input
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Invalid currency code');
        }

        // Prepare payload
        $payload = [
            'amount'       => (int) round($amount * 100),
            'currency'     => $currency,
            'description'  => substr($description, 0, 255),
            'redirect_url' => $redirectUrl,
        ];

        // Call Revolut API
        $response = $this->apiRequest('POST', '/api/orders', $payload);

        // Validate response
        if (empty($response['id']) || empty($response['checkout_url'])) {
            throw new \RuntimeException('Revolut response missing id or checkout_url');
        }

        // Save order to DB
        $now   = new \DateTime();
        $order = new PaymentOrder();
        $order->setOrderId($response['id']);
        $order->setRoomId((int) $roomId);
        $order->setAmount($amount);
        $order->setCurrency($currency);
        $order->setState(strtoupper($response['state'] ?? 'PENDING'));
        $order->setCheckoutUrl($response['checkout_url']);
        $order->setCreatedAt($now);
        $order->setUpdatedAt($now);

        $this->em->persist($order);
        $this->em->flush();

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
     * Update payment state in DB. Only allows valid state transitions.
     *
     * @param string $orderId
     * @param string $state
     */
    public function updatePaymentState($orderId, $state)
    {
        $order = $this->em->getRepository('Payment\Entity\PaymentOrder')
                          ->findOneBy(['orderId' => $orderId]);

        if (!$order) {
            return;
        }

        // Don't update if already in a terminal state
        if (in_array($order->getState(), self::$terminalStates, true)) {
            return;
        }

        $order->setState($state);
        $order->setUpdatedAt(new \DateTime());
        $this->em->flush();
    }

    /**
     * @param  string $orderId
     * @return PaymentOrder|null
     */
    public function getPaymentByOrderId($orderId)
    {
        return $this->em->getRepository('Payment\Entity\PaymentOrder')
                        ->findOneBy(['orderId' => $orderId]);
    }

    /**
     * Return the most recent payment for a room.
     *
     * @param  int $roomId
     * @return PaymentOrder|null
     */
    public function getLatestPaymentForRoom($roomId)
    {
        return $this->em->getRepository('Payment\Entity\PaymentOrder')
                        ->findOneBy(
                            ['roomId' => (int) $roomId],
                            ['createdAt' => 'DESC']
                        );
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
            'Revolut-Api-Version: 2025-12-04',
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
}
