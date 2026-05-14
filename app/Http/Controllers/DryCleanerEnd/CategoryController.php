<?php

namespace App\Http\Controllers\DryCleanerEnd;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * List all categories.
     */
    public function index()
    {
        return response()->json(Category::orderBy('name')->get());
    }

    /**
     * Create a new category.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'price_per_unit' => 'required|numeric|min:0',
            'image_url'      => 'nullable|string',
        ]);

        // Handle base64 image upload
        if ($request->has('image_base64')) {
            $data['image_url'] = $this->saveBase64Image($request->image_base64);
        }

        $category = Category::create($data);

        return response()->json($category, 201);
    }

    /**
     * Update a category.
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name'           => 'sometimes|string|max:100',
            'price_per_unit' => 'sometimes|numeric|min:0',
            'image_url'      => 'nullable|string',
        ]);

        if ($request->has('image_base64')) {
            $data['image_url'] = $this->saveBase64Image($request->image_base64);
        }

        $category->update($data);

        return response()->json($category);
    }

    /**
     * Delete a category.
     */
    public function destroy(string $id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Category deleted.']);
    }

    /**
     * Save a base64-encoded image to public storage and return URL.
     */
    private function saveBase64Image(string $base64): string
    {
        // Strip data URI prefix if present
        $image = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $decoded = base64_decode($image);
        $filename = 'categories/' . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, $decoded);
        return Storage::disk('public')->url($filename);
    }
}
