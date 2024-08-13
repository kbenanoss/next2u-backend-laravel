<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index()
    {
        return Shop::with('category')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $shop = Shop::create($request->all());

        return response()->json($shop, 201);
    }

    public function show(Shop $shop)
    {
        return $shop->load('category');
    }

    public function update(Request $request, Shop $shop)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'address' => 'sometimes|required|string|max:255',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
        ]);

        $shop->update($request->all());

        return response()->json($shop);
    }

    public function destroy(Shop $shop)
    {
        $shop->delete();

        return response()->json(null, 204);
    }
}
