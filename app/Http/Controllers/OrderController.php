<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ServiceListing;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function index(Request $request)
    {
        return Inertia::render('Orders/Index', [
            'orders' => $request->user()
                ->orders()
                ->with('listing')
                ->latest()
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'title' => $order->listing->title,
                    'amount_cc' => (float) $order->amount_cc,
                    'status' => $order->status,
                    'created_at' => $order->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['listing_id' => 'required|uuid|exists:service_listings,id']);
        $listing = ServiceListing::findOrFail($data['listing_id']);

        $this->ledger->holdForOrder($request->user()->wallet, $listing);

        return back()->with('success', 'Order placed — CC held until delivery.');
    }

    public function complete(Request $request, Order $order)
    {
        if ($order->buyer_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->status !== 'PENDING') {
            return back()->withErrors(['order' => 'This order is not in a completable state.']);
        }

        $this->ledger->releaseOnCompletion($order);

        return back()->with('success', 'Order completed — CC released to seller.');
    }
}
