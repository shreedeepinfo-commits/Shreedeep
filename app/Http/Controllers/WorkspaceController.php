<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['workspace_id' => ['required', 'integer']]);
        $workspace = Workspace::findOrFail($data['workspace_id']);
        abort_unless($request->user()->belongsToWorkspace($workspace), 403);

        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('dashboard')->with('status', "Switched to {$workspace->name}.");
    }
}
