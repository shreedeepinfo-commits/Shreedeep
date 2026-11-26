<?php

namespace App\Http\Controllers;

use App\Models\ContactGroup;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactGroupController extends Controller
{
    public function store(Request $request, CurrentWorkspace $current)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255',
            Rule::unique('contact_groups')->where('workspace_id', $current->id())]]);
        ContactGroup::create($data);

        return back()->with('status', 'Group created.');
    }

    public function destroy(ContactGroup $group)
    {
        $group->delete();

        return redirect()->route('contacts.index')->with('status', 'Group deleted. Its contacts were kept.');
    }
}
