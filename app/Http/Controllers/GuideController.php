<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reading guides: the page is shared, the writing is Management's.
 */
final class GuideController extends Controller
{
    public function guides(Request $r): View
    {
        $r->validate(['q' => 'nullable|string|max:100']);
        $q = DB::table('intern_guides');
        if ($r->user()->role !== 'superadmin') {
            $q->where(fn ($q) => $q->whereNull('department')->orWhere('department', $r->user()->department));
        }

        $q->when($r->filled('q'), fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$r->query('q').'%')->orWhere('body', 'like', '%'.$r->query('q').'%')));

        return view('management.guides', ['guides' => $q->orderByDesc('id')->get(), 'departments' => DB::table('departments')->orderBy('name')->get()]);
    }
}
