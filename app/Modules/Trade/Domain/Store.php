<?php

namespace App\Modules\Trade\Domain;

use App\Modules\Character\Domain\CharacterId;
use App\Modules\Equipment\Domain\Item;
use App\Modules\Equipment\Domain\ItemId;
use App\Modules\Equipment\Domain\ItemType;
use App\Modules\Equipment\Domain\Money;
use App\Modules\Generic\Domain\Container\ContainerIsFullException;
use App\Modules\Generic\Domain\Container\ContainerSlotIsTakenException;
use App\Modules\Generic\Domain\Container\ContainerSlotOutOfRangeException;
use App\Modules\Generic\Domain\Container\ItemNotInContainer;
use App\Modules\Generic\Domain\Container\NotEnoughSpaceInContainerException;
use App\Modules\Trade\Domain\Store\StoreDoesNotBuyItems;
use Illuminate\Support\Collection;

class Store
{
    public const NUMBER_OF_SLOTS = 24;

    /**
     * @var StoreId
     */
    private $id;

    /**
     * @var CharacterId
     */
    private $characterId;

    /**
     * @var Collection
     */
    private $items;

    /**
     * @var StoreType
     */
    private $type;

    /**
     * @var Money
     */
    private $money;

    public function __construct(StoreId $id, CharacterId $characterId, StoreType $type, Collection $items, Money $money)
    {
        if ($items->count() >= self::NUMBER_OF_SLOTS) {
            throw new NotEnoughSpaceInContainerException(
                "Not enough space in the Store for {$items->count()} new items"
            );
        }

        $this->id = $id;
        $this->characterId = $characterId;
        $this->type = $type;
        $this->items = $items;
        $this->money = $money;
    }

    public function getId(): StoreId
    {
        return $this->id;
    }

    public function addItemToSlot(int $slot, StoreItem $item): void
    {
        if ($slot >= self::NUMBER_OF_SLOTS) {
            throw new ContainerSlotOutOfRangeException("Store slot $slot is out of range.");
        }

        if ($this->items->has($slot)) {
            throw new ContainerSlotIsTakenException("Store slot $slot is already take");
        }

        $this->items->put($slot, $item);
    }

    public function add(Item $item): void
    {
        $item = new StoreItem($item, $item->getPrice());

        $slot = $this->findFreeSlot();

        $this->items->put($slot, $item);
    }

    private function findFreeSlot(): int
    {
        for ($slot = 0; $slot < self::NUMBER_OF_SLOTS; $slot++) {
            if (!$this->items->has($slot)) {
                return $slot;
            }
        }

        throw new ContainerIsFullException('Cannot add to full store');
    }

    public function findItem(ItemId $itemId): ?StoreItem
    {
        return $this->items->first(static function (StoreItem $item) use ($itemId) {
            return $item->getId()->equals($itemId);
        });
    }

    public function findItemsOfType(ItemType $type): Collection
    {
        return $this->items->filter(static function (StoreItem $item) use ($type) {
            return $item->isOfType($type);
        });
    }

    public function getCharacterId(): CharacterId
    {
        return $this->characterId;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function takeOut(ItemId $itemId): Item
    {
        $slot = $this->items->search(static function (StoreItem $item) use ($itemId) {
            return $item->getId()->equals($itemId);
        });

        if ($slot === false) {
            throw new ItemNotInContainer('Cannot take out item from empty slot');
        }

        /** @var StoreItem $item */
        $item = $this->items->get($slot);

        $this->items->forget($slot);

        return $item->toBaseItem();
    }

    public function putMoneyIn(Money $money): void
    {
        $this->money = $this->money->combine($money);
    }

    public function takeMoneyOut(Money $money): Money
    {
        if ($this->type->isSellOnly()) {
            throw new StoreDoesNotBuyItems('Store does not buy items');
        }

        $this->money = $this->money->remove($money);

        return $money;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }
}
