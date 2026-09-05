<?php

namespace App\Http\Controllers;

use App\Contracts\BillingServiceInterface;
use App\Contracts\MenuServiceInterface;
use App\Contracts\OrderServiceInterface;
use App\Models\Table;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
        private readonly MenuServiceInterface $menuService,
        private readonly BillingServiceInterface $billingService,
    ) {}

    public function tableOverview(): Response
    {
        $tables = $this->orderService->getTableOverview();

        return Inertia::render('Orders/TableOverview', [
            'tables' => $tables,
        ]);
    }

    public function create(Table $table)
    {
        // If a bill is already generated, go straight to the bill
        $bill = $this->billingService->getBillForTable($table->id);
        if ($bill) {
            return redirect()->route('billing.show', $table->id);
        }

        $menuItems     = $this->menuService->getActiveItems();
        $existingOrder = $this->orderService->getOpenOrderForTable($table->id);

        return Inertia::render('Orders/Create', [
            'table'         => $table,
            'menuItems'     => $menuItems->load('category'),
            'existingOrder' => $existingOrder?->load('orderItems.menuItem'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'table_id'               => 'required|integer|exists:tables,id',
            'items'                  => 'required|array|min:1',
            'items.*.menu_item_id'   => 'required|integer|exists:menu_items,id',
            'items.*.quantity'       => 'required|integer|min:1|max:99',
            'items.*.sub_variety_id' => 'nullable|integer|exists:sub_varieties,id',
        ]);

        $this->orderService->createOrder($validated['table_id'], $validated['items']);

        // Go back to the order screen so staff can review and generate bill
        return redirect()->route('orders.create', $validated['table_id'])
            ->with('success', 'Order created. Review items and generate bill when ready.');
    }

    public function addItems(Request $request, int $order)
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.menu_item_id'   => 'required|integer|exists:menu_items,id',
            'items.*.quantity'       => 'required|integer|min:1|max:99',
            'items.*.sub_variety_id' => 'nullable|integer|exists:sub_varieties,id',
        ]);

        try {
            $updatedOrder = $this->orderService->addItems($order, $validated['items']);
        } catch (ConflictHttpException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Go back to the order screen so staff can review and generate bill
        return redirect()->route('orders.create', $updatedOrder->table_id)
            ->with('success', 'Items added. Generate bill when ready.');
    }
}
