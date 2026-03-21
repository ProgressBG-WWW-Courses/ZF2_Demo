<?php
namespace Payment\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Payment\Service\PaymentService;

class PaymentController extends AbstractActionController
{
    /** @var PaymentService */
    private $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
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
        $amount      = (float) $post->get('amount', 0);
        $currency    =         $post->get('currency', 'GBP');
        $description =         $post->get('description', 'Hotel room booking');

        // Sanitize description
        $description = strip_tags($description);

        if ($roomId <= 0 || $amount <= 0) {
            return $this->redirect()->toRoute('room');
        }

        $baseUrl    = $this->buildBaseUrl();
        $successUrl = $baseUrl . '/payment/success';
        $cancelUrl  = $baseUrl . '/payment/cancel';

        try {
            $order       = $this->paymentService->createOrder(
                $roomId, $amount, $currency, $description, $successUrl, $cancelUrl
            );
            $checkoutUrl = $order['checkout_url'];

            return $this->redirect()->toUrl($checkoutUrl);

        } catch (\Exception $e) {
            error_log('[Payment] createOrder failed: ' . $e->getMessage());
            return $this->redirect()->toRoute('room/detail', ['id' => $roomId]);
        }
    }

    /**
     * GET /payment/success?order_id=...
     *
     * User lands here after completing payment on Revolut's hosted page.
     */
    public function successAction()
    {
        $orderId = $this->params()->fromQuery('order_id', '');
        $payment = null;
        $order   = null;
        $roomId  = 0;

        if ($orderId) {
            $payment = $this->paymentService->getPaymentByOrderId($orderId);
            $roomId  = $payment ? (int) $payment['room_id'] : 0;

            try {
                $order = $this->paymentService->getOrderStatus($orderId);
                if ($order) {
                    $this->paymentService->updatePaymentState($orderId, $order['state']);
                }
            } catch (\Exception $e) {
                error_log('[Payment] successAction getOrderStatus failed: ' . $e->getMessage());
            }
        }

        return new ViewModel([
            'orderId' => $orderId,
            'payment' => $payment,
            'order'   => $order,
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
            $roomId  = $payment ? (int) $payment['room_id'] : 0;
            $this->paymentService->updatePaymentState($orderId, 'CANCELLED');
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

        // Signature verification
        $sigHeader = $request->getHeader('Revolut-Signature');
        $tsHeader  = $request->getHeader('Revolut-Request-Timestamp');

        $signature = $sigHeader ? $sigHeader->getFieldValue() : '';
        $timestamp = $tsHeader  ? $tsHeader->getFieldValue()  : '';

        if ($signature) {
            if (!$this->paymentService->verifyWebhookSignature($body, $signature, $timestamp)) {
                error_log('[Payment] Webhook signature verification FAILED');
                $this->getResponse()->setStatusCode(401);
                return $this->getResponse();
            }
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
     */
    public function statusAction()
    {
        $orderId = $this->params()->fromRoute('order_id', '');

        if (empty($orderId)) {
            return new JsonModel(['success' => false, 'error' => 'Missing order_id']);
        }

        try {
            $order = $this->paymentService->getOrderStatus($orderId);
            $state = $order['state'] ?? 'UNKNOWN';
            $this->paymentService->updatePaymentState($orderId, $state);

            return new JsonModel([
                'success'  => true,
                'state'    => $state,
                'order_id' => $orderId,
            ]);
        } catch (\Exception $e) {
            return new JsonModel([
                'success' => false,
                'error'   => 'Unable to retrieve order status',
            ]);
        }
    }

    /**
     * Build base URL from the current request.
     *
     * @return string e.g. "http://localhost:8088"
     */
    private function buildBaseUrl()
    {
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
