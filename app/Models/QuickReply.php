<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class QuickReply extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'shortcut', 'body'];
}
