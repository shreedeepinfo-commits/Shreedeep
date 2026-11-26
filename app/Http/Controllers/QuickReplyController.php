<?php

namespace App\Http\Controllers;

use App\Models\QuickReply;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuickReplyController extends Controller
{
    public function index()
    {
        return view('inbox.quick-replies', ['replies' => QuickReply::orderBy('shortcut')->get()]);
    }

    public function store(Request $request)
    {
        QuickReply::create($this->validated($request));

        return back()->with('status', 'Quick reply saved.');
    }

    public function update(Request $request, QuickReply $quickReply)
    {
        $quickReply->update($this->validated($request, $quickReply));

        return back()->with('status', 'Quick reply updated.');
    }

    public function destroy(QuickReply $quickReply)
    {
        $quickReply->delete();

        return back()->with('status', 'Quick reply deleted.');
    }

    private function validated(Request $request, ?QuickReply $reply = null): array
    {
        $request->merge(['shortcut' => strtolower(ltrim(trim((string) $request->input('shortcut')), '/'))]);

        return $request->validate([
            'shortcut' => ['required', 'regex:/^[a-z0-9_-]{1,40}$/',
                Rule::unique('quick_replies')->where('workspace_id', app(CurrentWorkspace::class)->id())->ignore($reply)],
            'body' => ['required', 'string', 'max:4096'],
        ], ['shortcut.regex' => 'Use letters, numbers, - or _ only (e.g. price).']);
    }
}
