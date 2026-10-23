<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:255',
        ]);
        $keyword = trim($validated['keyword'] ?? '');

        $users = User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->whereLike('name', "%{$keyword}%")
                        ->orWhereLike('email', "%{$keyword}%");
                });
            })
            ->orderBy('id')
            ->paginate(50)
            ->appends(['keyword' => $keyword]);

        return view('users.index', compact('users', 'keyword'));
    }
}
