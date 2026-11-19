<?php

namespace App\Http\Controllers;

use App\Services\ActionQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InboxController extends Controller
{
    public function index(Request $request, ActionQueue $queue): View
    {
        // An intern has nobody waiting on them; their dashboard is the inbox.
        Gate::authorize('workspace.inbox');

        $inbox = $queue->for($request->user());

        return view('management.inbox', [
            'sections' => $inbox['sections'],
            'total' => $inbox['total'],
            'hasSignature' => (bool) $request->user()->signature_path,
        ]);
    }
}
