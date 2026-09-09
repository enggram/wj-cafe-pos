<?php

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Support\Collection;

interface OrderServiceInterface
{
    public function createOrder(int $tableId, array $items): Order;

    public function addItems(int $orderId, array $items): Order;

    public function removeItem(int $orderId, int $orderItemId): ?Order;

    public function syncItems(int $orderId, array $items): ?Order;

    public function getOpenOrderForTable(int $tableId): ?Order;

    public function getTableOverview(): Collection;
}
