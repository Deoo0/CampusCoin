<?php

namespace App\Http\Controllers;

use App\Models\ServiceListing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    public function index()
    {
        return Inertia::render('Marketplace/Index', [
            'listings' => ServiceListing::where('status', 'ACTIVE')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:EDITING,CODING,DESIGN,PRINTING,OTHER',
            'price_cc' => 'required|numeric|min:0.01',
        ]);

        $request->user()->listings()->create($data + ['status' => 'ACTIVE']);

        return back()->with('success', 'Listing created.');
    }
}
