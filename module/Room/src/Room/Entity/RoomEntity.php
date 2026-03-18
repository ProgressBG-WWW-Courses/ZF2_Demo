<?php
namespace Room\Entity;

/**
 * RoomEntity — represents a single hotel room record.
 *
 * In a real ZF2 application Doctrine reads the @ORM annotations below to:
 *   - Map this class to the "rooms" database table
 *   - Generate SQL for SELECT / INSERT / UPDATE / DELETE
 *   - Handle relationships to other entities
 *
 * This demo does NOT have a real database, so Doctrine is not wired in.
 * The annotations are here to show the structure you would use in production.
 *
 * @ORM\Entity
 * @ORM\Table(name="rooms")
 *
 * Lecture 21: Doctrine ORM in ZF2
 * Lecture 22: Hydrators (exchangeArray / getArrayCopy)
 */
class RoomEntity
{
    /**
     * Auto-incremented primary key — Doctrine manages this value.
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="string", length=10) */
    protected $number;

    /** @ORM\Column(type="string", length=50) */
    protected $type;

    /** @ORM\Column(type="decimal", scale=2) */
    protected $price;

    /** @ORM\Column(type="string", length=255) */
    protected $description;

    // ── Hydration (Lecture 22) ────────────────────────────────────────────────

    /**
     * Fill this entity from an array — the "hydrate" direction.
     *
     * Called with form data after isValid():
     *   $room->exchangeArray($form->getData())
     *
     * Or with a raw row from the database (if not using Doctrine):
     *   $room->exchangeArray($row)
     */
    public function exchangeArray(array $data)
    {
        $this->id          = isset($data['id'])          ? (int)   $data['id']          : null;
        $this->number      = isset($data['number'])      ?         $data['number']       : null;
        $this->type        = isset($data['type'])        ?         $data['type']         : null;
        $this->price       = isset($data['price'])       ? (float) $data['price']        : null;
        $this->description = isset($data['description']) ?         $data['description']  : null;
    }

    /**
     * Turn this entity into an array — the "extract" direction.
     *
     * Used to pre-fill a form with existing room data:
     *   $form->setData($room->getArrayCopy())
     */
    public function getArrayCopy()
    {
        return array(
            'id'          => $this->id,
            'number'      => $this->number,
            'type'        => $this->type,
            'price'       => $this->price,
            'description' => $this->description,
        );
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getId()          { return $this->id; }
    public function getNumber()      { return $this->number; }
    public function getType()        { return $this->type; }
    public function getPrice()       { return $this->price; }
    public function getDescription() { return $this->description; }

    // ── Setters ───────────────────────────────────────────────────────────────

    public function setNumber($v)      { $this->number      = $v; }
    public function setType($v)        { $this->type        = $v; }
    public function setPrice($v)       { $this->price       = (float) $v; }
    public function setDescription($v) { $this->description = $v; }
}
