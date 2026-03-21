<?php
namespace Payment\Entity;

/**
 * PaymentOrder — represents a Revolut payment order.
 *
 * Doctrine ORM annotations map this class to the "payment_orders" table.
 * When DoctrineORMModule is installed, Doctrine can manage persistence
 * automatically. Until then, PaymentService uses PDO with this entity
 * as the schema definition and hydration layer.
 *
 * @ORM\Entity
 * @ORM\Table(name="payment_orders", indexes={
 *     @ORM\Index(name="idx_room_id", columns={"room_id"}),
 *     @ORM\Index(name="idx_state",   columns={"state"})
 * })
 */
class PaymentOrder
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(name="order_id", type="string", length=255, unique=true) */
    protected $orderId;

    /** @ORM\Column(name="room_id", type="integer") */
    protected $roomId;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    protected $amount;

    /** @ORM\Column(type="string", length=3) */
    protected $currency;

    /** @ORM\Column(type="string", length=20) */
    protected $state;

    /** @ORM\Column(name="checkout_url", type="text", nullable=true) */
    protected $checkoutUrl;

    /** @ORM\Column(name="created_at", type="datetime") */
    protected $createdAt;

    /** @ORM\Column(name="updated_at", type="datetime") */
    protected $updatedAt;

    // ── Hydration ─────────────────────────────────────────────────────────────

    /**
     * Fill this entity from an array (the "hydrate" direction).
     */
    public function exchangeArray(array $data)
    {
        $this->id          = isset($data['id'])           ? (int)   $data['id']           : null;
        $this->orderId     = isset($data['order_id'])     ?         $data['order_id']     : null;
        $this->roomId      = isset($data['room_id'])      ? (int)   $data['room_id']      : null;
        $this->amount      = isset($data['amount'])       ? (float) $data['amount']       : null;
        $this->currency    = isset($data['currency'])     ?         $data['currency']     : null;
        $this->state       = isset($data['state'])        ?         $data['state']        : null;
        $this->checkoutUrl = isset($data['checkout_url']) ?         $data['checkout_url'] : null;
        $this->createdAt   = isset($data['created_at'])   ?         $data['created_at']   : null;
        $this->updatedAt   = isset($data['updated_at'])   ?         $data['updated_at']   : null;
    }

    /**
     * Turn this entity into an array (the "extract" direction).
     */
    public function getArrayCopy()
    {
        return array(
            'id'           => $this->id,
            'order_id'     => $this->orderId,
            'room_id'      => $this->roomId,
            'amount'       => $this->amount,
            'currency'     => $this->currency,
            'state'        => $this->state,
            'checkout_url' => $this->checkoutUrl,
            'created_at'   => $this->createdAt,
            'updated_at'   => $this->updatedAt,
        );
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getId()          { return $this->id; }
    public function getOrderId()     { return $this->orderId; }
    public function getRoomId()      { return $this->roomId; }
    public function getAmount()      { return $this->amount; }
    public function getCurrency()    { return $this->currency; }
    public function getState()       { return $this->state; }
    public function getCheckoutUrl() { return $this->checkoutUrl; }
    public function getCreatedAt()   { return $this->createdAt; }
    public function getUpdatedAt()   { return $this->updatedAt; }

    // ── Setters ───────────────────────────────────────────────────────────────

    public function setOrderId($v)     { $this->orderId     = $v; }
    public function setRoomId($v)      { $this->roomId      = (int) $v; }
    public function setAmount($v)      { $this->amount      = (float) $v; }
    public function setCurrency($v)    { $this->currency    = $v; }
    public function setState($v)       { $this->state       = $v; }
    public function setCheckoutUrl($v) { $this->checkoutUrl = $v; }
    public function setCreatedAt($v)   { $this->createdAt   = $v; }
    public function setUpdatedAt($v)   { $this->updatedAt   = $v; }
}
