<?php

namespace App\Http\Controllers;

use App\Contracts\BillingServiceInterface;
use App\Models\Bill;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingServiceInterface $billingService
    ) {}

    public function generate(int $table)
    {
        try {
            $bill = $this->billingService->generateBill($table);
        } catch (NotFoundHttpException $e) {
            return redirect()->route('orders.tables')->with('error', $e->getMessage());
        }

        // Redirect to the GET bill page so back/refresh works correctly
        return redirect()->route('billing.show', $table);
    }

    public function show(int $table)
    {
        $bill = $this->billingService->getBillForTable($table);

        if (! $bill) {
            return redirect()->route('orders.tables')->with('error', 'No active bill for this table.');
        }

        $bill->load(['order.orderItems.menuItem', 'table']);

        return Inertia::render('Billing/Show', [
            'bill'  => $this->formatBill($bill),
            'table' => ['id' => $bill->table->id, 'table_number' => $bill->table->table_number],
        ]);
    }

    public function settle(int $bill)
    {
        $billModel = Bill::find($bill);

        try {
            $settled = $this->billingService->settleBill($bill);
        } catch (NotFoundHttpException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Redirect to the bill show page (GET) so the paid state is visible
        $tableId = $settled->table_id;
        return redirect()->route('billing.settled', $tableId)
            ->with('settled', true);
    }

    // Dedicated settled page so the URL is stable after payment
    public function settled(int $table)
    {
        $bill = Bill::where('table_id', $table)
            ->whereHas('order', fn ($q) => $q->where('status', 'completed'))
            ->latest()
            ->first();

        if (! $bill) {
            return redirect()->route('orders.tables')->with('success', 'Bill settled. Table is now free.');
        }

        $bill->load(['order.orderItems.menuItem', 'table']);

        return Inertia::render('Billing/Show', [
            'bill'    => $this->formatBill($bill),
            'table'   => ['id' => $bill->table->id, 'table_number' => $bill->table->table_number],
            'settled' => true,
        ]);
    }

    /**
     * Bill history: a searchable, paginated list of past bills.
     * Filters: date range (from/to on billed_at), table number, status, and bill # search.
     */
    public function history(Request $request)
    {
        $filters = $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'table'    => 'nullable|integer',
            'status'   => 'nullable|in:paid,unpaid',
            'bill'     => 'nullable|integer',
            'per_page' => 'nullable|integer|in:20,50,100',
        ]);

        // Default the date range to today when nothing is provided.
        $from = $filters['from'] ?? now()->toDateString();
        $to   = $filters['to']   ?? now()->toDateString();

        $query = Bill::query()
            ->with('table')
            ->whereDate('billed_at', '>=', $from)
            ->whereDate('billed_at', '<=', $to);

        if (! empty($filters['table'])) {
            $query->whereHas('table', fn ($q) => $q->where('table_number', $filters['table']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['bill'])) {
            $query->where('id', $filters['bill']);
        }

        // Aggregate total for the whole filtered set BEFORE paginating.
        $totalAmount = (float) (clone $query)->sum('grand_total');

        $perPage = (int) ($filters['per_page'] ?? 20);

        $bills = $query->orderByDesc('billed_at')
            ->paginate($perPage)
            ->withQueryString();

        $totalCount = $bills->total();

        $rows = collect($bills->items())->map(fn ($bill) => [
            'id'          => $bill->id,
            'table_number' => $bill->table?->table_number,
            'grand_total' => $bill->grand_total,
            'status'      => $bill->status instanceof \BackedEnum ? $bill->status->value : $bill->status,
            'billed_at'   => optional($bill->billed_at)->toIso8601String(),
        ])->values()->toArray();

        return Inertia::render('Billing/History', [
            'bills'   => $rows,
            'pagination' => [
                'current_page' => $bills->currentPage(),
                'last_page'    => $bills->lastPage(),
                'per_page'     => $bills->perPage(),
                'total'        => $bills->total(),
            ],
            'filters' => [
                'from'     => $from,
                'to'       => $to,
                'table'    => $filters['table']  ?? null,
                'status'   => $filters['status'] ?? null,
                'bill'     => $filters['bill']    ?? null,
                'per_page' => $perPage,
            ],
            'summary' => [
                'total_amount' => round($totalAmount, 2),
                'total_count'  => $totalCount,
            ],
        ]);
    }

    /**
     * View a single past bill (read-only) for reprinting. Reuses the Billing/Show page.
     */
    public function historyShow(int $bill)
    {
        $billModel = Bill::with(['order.orderItems.menuItem', 'table'])->find($bill);

        if (! $billModel) {
            return redirect()->route('billing.history')->with('error', 'Bill not found.');
        }

        return Inertia::render('Billing/Show', [
            'bill'     => $this->formatBill($billModel),
            'table'    => ['id' => $billModel->table->id, 'table_number' => $billModel->table->table_number],
            'readOnly' => true,
        ]);
    }

    private function formatBill($bill): array
    {
        return [
            'id'                   => $bill->id,
            'items_subtotal'       => $bill->items_subtotal,
            'parcel_charges_total' => $bill->parcel_charges_total,
            'grand_total'          => $bill->grand_total,
            'status'               => $bill->status instanceof \BackedEnum ? $bill->status->value : $bill->status,
            'billed_at'            => $bill->billed_at->toIso8601String(),
            'order'                => [
                'id'          => $bill->order->id,
                'order_items' => $bill->order->orderItems->map(fn ($item) => [
                    'id'                => $item->id,
                    'quantity'          => $item->quantity,
                    'unit_price'        => $item->unit_price,
                    'is_parcel'         => (bool) $item->is_parcel,
                    'parcel_rate'       => $item->parcel_rate,
                    'parcel_line_total' => $item->is_parcel ? round((float) $item->parcel_rate * $item->quantity, 2) : 0.0,
                    'menu_item'         => ['name' => $item->menuItem?->name ?? 'Unknown'],
                ])->values()->toArray(),
            ],
        ];
    }
}
