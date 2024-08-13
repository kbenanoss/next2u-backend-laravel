<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $path = $request->file('image')->store('category_images', 'public');

        $category = Category::create([
            'name' => $request->name,
            'image' => $path,
        ]);

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return $category;
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'image' => 'sometimes|required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($category->image);
            $path = $request->file('image')->store('category_images', 'public');
            $category->image = $path;
        }

        if ($request->has('name')) {
            $category->name = $request->name;
        }

        $category->save();

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        Storage::disk('public')->delete($category->image);
        $category->delete();

        return response()->json(null, 204);
    }
}
