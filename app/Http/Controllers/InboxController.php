<?php

namespace App\Http\Controllers;

use App\Services\ActionQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request, ActionQueue $queue): View
    {
        // An intern has nobody waiting on them; their dashboard is the inbox.
        abort_if($request->user()->role === 'intern', 403);

        $inbox = $queue->for($request->user());

        return view('management.inbox', [
            'sections' => $inbox['sections'],
            'total' => $inbox['total'],
            'hasSignature' => (bool) $request->user()->signature_path,
        ]);
    }
}
