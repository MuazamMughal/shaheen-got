<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesMasterRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use ManagesMasterRecords;

    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::ordered()->withCount('sectors')->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = new Category($request->safe()->only('name', 'country', 'description'));
        $category->is_active = $request->boolean('is_active', true);
        $category->sort_order = Category::nextSortOrder();
        $category->image = $this->handleUpload($request, 'image', 'categories', null);
        $category->flag_icon = $this->handleUpload($request, 'flag_icon', 'flags', null);
        $category->save();

        return back()->with('success', 'Category created.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->fill($request->safe()->only('name', 'country', 'description'));
        $category->is_active = $request->boolean('is_active');
        $category->image = $this->handleUpload($request, 'image', 'categories', $category->image);
        $category->flag_icon = $this->handleUpload($request, 'flag_icon', 'flags', $category->flag_icon);
        $category->save();

        return back()->with('success', 'Category updated.');
    }

    public function toggle(Category $category): RedirectResponse
    {
        return $this->toggleActive($category, 'Category');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->applyOrder(Category::class, $this->validateOrder($request, 'categories'));

        return back()->with('success', 'Order saved.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        return $this->destroyRecord($category, 'Category', ['image', 'flag_icon']);
    }
}
