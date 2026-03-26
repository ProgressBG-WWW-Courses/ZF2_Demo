<?php
namespace Payment\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Payment\Service\PaymentService;
use Room\Service\RoomService;

class PaymentController extends AbstractActionController
{
    /** @var PaymentService */
    private $paymentService;

    /** @var RoomService */
    private $roomService;

    public function __construct(PaymentService $paymentService, RoomService $roomService)
    {
        $this->paymentService = $paymentService;
        $this->roomService    = $roomService;
    }

    /**
     * POST /payment/create
     *
     * Expects: room_id, amount, currency, description
     * Creates a Revolut order and redirects to their hosted checkout page.
     */
    public function createAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('room');
        }

        $post        = $this->getRequest()->getPost();
        $roomId      = (int)   $post->get('room_id', 0);
        $currency    =         $post->get('currency', 'EUR');
        $description =         $post->get('description', 'Hotel room booking');

        // Sanitize description
        $description = strip_tags($description);

        if ($roomId <= 0) {
            return $this->redirect()->toRoute('room');
        }

        // Use authoritative price from DB — never trust the client-submitted amount
        $room = $this->roomService->getById($roomId);
        if (!$room) {
            return $this->redirect()->toRoute('room');
        }
        $amount = (float) $room->getPrice();

        // Revolut redirects the browser here after successful payment
        $baseUrl     = $this->buildBaseUrl();
        $redirectUrl = $baseUrl . $this->url()->fromRoute('room/detail', ['id' => $roomId])
                     . '?payment=success';

        try {
            $order = $this->paymentService->createOrder(
                $roomId, $amount, $currency, $description, $redirectUrl
            );

            // Redirect to Revolut hosted checkout page
            return $this->redirect()->toUrl($order['checkout_url']);

        } catch (\Exception $e) {
            error_log('[Payment] createOrder failed: ' . $e->getMessage());
            return $this->redirect()->toRoute('room/detail', ['id' => $roomId]);
        }
    }

    /**
     * GET /payment/success?order_id=...
     *
     * User lands here after completing payment on Revolut's hosted page.
     * Reads local DB state and redirects to the room detail page.
     * The webhook will have already updated the state; the frontend poller
     * handles any remaining delay.
     */
    public function successAction()
    {
        $orderId = $this->params()->fromQuery('order_id', '');
        $payment = null;
        $roomId  = 0;

        if ($orderId) {
            $payment = $this->paymentService->getPaymentByOrderId($orderId);
            $roomId  = $payment ? $payment->getRoomId() : 0;
        }

        // Redirect to room detail page so the user sees the payment status there
        if ($roomId) {
            return $this->redirect()->toRoute('room/detail', ['id' => $roomId]);
        }

        return new ViewModel([
            'orderId' => $orderId,
            'payment' => $payment,
            'roomId'  => $roomId,
        ]);
    }

    /**
     * GET /payment/cancel?order_id=...
     *
     * User cancelled the checkout on Revolut's hosted page.
     */
    public function cancelAction()
    {
        $orderId = $this->params()->fromQuery('order_id', '');
        $payment = null;
        $roomId  = 0;

        if ($orderId) {
            $payment = $this->paymentService->getPaymentByOrderId($orderId);
            $roomId  = $payment ? $payment->getRoomId() : 0;
            $this->paymentService->updatePaymentState($orderId, 'CANCELLED');
        }

        // Redirect to room detail page so the user sees the cancellation status
        if ($roomId) {
            return $this->redirect()->toRoute('room/detail', ['id' => $roomId]);
        }

        return new ViewModel([
            'orderId' => $orderId,
            'payment' => $payment,
            'roomId'  => $roomId,
        ]);
    }

    /**
     * POST /payment/webhook
     *
     * Revolut sends event notifications here. We verify the HMAC signature,
     * parse the event, and update local payment state.
     *
     * Returns raw HTTP response (no view rendering).
     */
    public function webhookAction()
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->getResponse()->setStatusCode(405);
            return $this->getResponse();
        }

        $body = $request->getContent();

        // Signature verification — mandatory on every webhook request
        $sigHeader = $request->getHeader('Revolut-Signature');
        $tsHeader  = $request->getHeader('Revolut-Request-Timestamp');

        $signature = $sigHeader ? $sigHeader->getFieldValue() : '';
        $timestamp = $tsHeader  ? $tsHeader->getFieldValue()  : '';

        if (empty($signature) || empty($timestamp)) {
            error_log('[Payment] Webhook missing signature or timestamp header');
            $this->getResponse()->setStatusCode(401);
            return $this->getResponse();
        }

        if (!$this->paymentService->verifyWebhookSignature($body, $signature, $timestamp)) {
            error_log('[Payment] Webhook signature verification FAILED');
            $this->getResponse()->setStatusCode(401);
            return $this->getResponse();
        }

        // Parse payload
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->getResponse()->setStatusCode(400);
            return $this->getResponse();
        }

        error_log('[Payment] Webhook received: ' . $body);

        $eventType = $data['event']    ?? ($data['type'] ?? '');
        $orderId   = $data['order_id'] ?? '';

        if ($orderId && $eventType) {
            $stateMap = [
                'ORDER_COMPLETED'         => 'COMPLETED',
                'ORDER_PAYMENT_COMPLETED' => 'COMPLETED',
                'ORDER_PAYMENT_DECLINED'  => 'FAILED',
                'ORDER_PAYMENT_CANCELLED' => 'CANCELLED',
                'ORDER_PAYMENT_FAILED'    => 'FAILED',
                'ORDER_AUTHORISED'        => 'AUTHORISED',
            ];

            $newState = $stateMap[$eventType] ?? null;
            if ($newState) {
                $this->paymentService->updatePaymentState($orderId, $newState);
                error_log("[Payment] Updated order {$orderId} to state {$newState}");
            }
        }

        $this->getResponse()->setStatusCode(200);
        return $this->getResponse();
    }

    /**
     * GET /payment/status/:order_id
     *
     * JSON endpoint for frontend polling.
     * Reads from local DB only. Falls back to Revolut API if the webhook
     * hasn't updated the state within 30 seconds of order creation.
     */
    public function statusAction()
    {
        $orderId = $this->params()->fromRoute('order_id', '');

        if (empty($orderId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $orderId)) {
            return new JsonModel(['success' => false, 'error' => 'Missing or invalid order_id']);
        }

        $payment = $this->paymentService->getPaymentByOrderId($orderId);
        if (!$payment) {
            return new JsonModel(['success' => false, 'error' => 'Order not found']);
        }

        $state = $payment->getState();

        // If still pending and webhook hasn't fired within 30s, poll Revolut API as fallback
        if ($state === 'PENDING' || $state === 'AUTHORISED') {
            $age = time() - $payment->getUpdatedAt()->getTimestamp();
            if ($age >= 30) {
                try {
                    $order = $this->paymentService->getOrderStatus($orderId);
                    $state = $order['state'] ?? $state;
                    $this->paymentService->updatePaymentState($orderId, $state);
                } catch (\Exception $e) {
                    error_log('[Payment] statusAction API fallback failed: ' . $e->getMessage());
                }
            }
        }

        return new JsonModel([
            'success'  => true,
            'state'    => $state,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Build public base URL for Revolut redirect callbacks.
     *
     * Uses APP_PUBLIC_URL from .env (ngrok or production domain).
     * Falls back to the request URL if not configured.
     *
     * @return string e.g. "https://abc123.ngrok-free.dev"
     */
    private function buildBaseUrl()
    {
        // Use configured public URL (required for Revolut to redirect back)
        $publicUrl = $this->paymentService->getPublicUrl();
        if ($publicUrl) {
            return $publicUrl;
        }

        // Fallback to request-based URL
        $uri    = $this->getRequest()->getUri();
        $scheme = $uri->getScheme();
        $host   = $uri->getHost();
        $port   = $uri->getPort();

        $base = $scheme . '://' . $host;
        if ($port && !in_array((int) $port, [80, 443], true)) {
            $base .= ':' . $port;
        }

        return $base;
    }
}
