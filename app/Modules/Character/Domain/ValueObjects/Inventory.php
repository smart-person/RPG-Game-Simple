<?php

namespace App\Modules\Character\Domain\ValueObjects;

use App\Modules\Character\Domain\ValueObjects\Inventory\InventorySlotIsTakenException;
use App\Modules\Character\Domain\ValueObjects\Inventory\InventorySlotOutOfRangeException;
use App\Modules\Character\Domain\ValueObjects\Inventory\InventoryIsFullException;
use App\Modules\Character\Domain\ValueObjects\Inventory\NotEnoughSpaceException;
use App\Modules\Equipment\Domain\Entities\Item;
use App\Modules\Equipment\Domain\ValueObjects\InventorySlot;
use App\Modules\Equipment\Domain\ValueObjects\ItemType;
use Doctrine\Common\Collections\Collection;

class Inventory
{
    const NUMBER_OF_SLOTS = 24;

    /**
     * @var Collection
     */
    private $items;

    private function __construct(Collection $items)
    {
        if ($items->count() >= self::NUMBER_OF_SLOTS) {
            throw new NotEnoughSpaceException("Not enough space in the Inventory for {$items->count()} new items");
        }

        $this->items = $items;
    }

    public static function withItems(Collection $items): self
    {
        return new self($items);
    }

    public function withAddedItem(int $slot, Item $item): self
    {
        if ($slot >= self::NUMBER_OF_SLOTS) {
            throw new InventorySlotOutOfRangeException("Inventory slot $slot is out of range.");
        }

        if ($this->items->contains($slot)) {
            throw new InventorySlotIsTakenException("Inventory slot $slot is already take");
        }

        return $this->addItem($slot, $item);
    }

    public function withAddedItemToFreeSlot(Item $item): self
    {
        $slot = $this->findFreeSlot();

        return $this->addItem($slot, $item);
    }

    public function findFreeSlot(): int
    {
        for ($slot = 0; $slot < self::NUMBER_OF_SLOTS; $slot++) {
            if (!$this->items->contains($slot)) {
                return $slot;
            }
        }

        throw new InventoryIsFullException('Cannot add to full inventory');
    }

    public function findEquippedItemOfType(ItemType $type)
    {
        return $this->items->filter(function (Item $item) use ($type) {
            return $item->getType()->equals($type) && $item->isEquipped();
        })->first();
    }

    public function hasItem(Item $itemToFind): bool
    {
        $contains = $this->items->contains($itemToFind);

        return $contains;
    }

    public function getEquippedItemsEffect(string $itemEffectType): int
    {
        return (int)array_reduce($this->getEquippedItems()->toArray(), function ($carry, Item $item) use ($itemEffectType) {

            $itemEffect = $item->getItemEffect($itemEffectType);

            return $carry + $itemEffect;
        });
    }

    public function getItemForSlot(int $slot): ?Item
    {
        return $this->items->get($slot);
    }

    private function getEquippedItems(): Collection
    {
        return $this->items->filter(function (Item $item) {
            return $item->isEquipped();
        });
    }

    private function addItem(int $slot, Item $item): Inventory
    {
        $item->setInventorySlot(InventorySlot::defined($slot));

        $items = $this->items;
        $items->set($slot, $item);

        return new self($this->items);
    }

    public function getItems(): Collection
    {
        return $this->items;
    }
}
