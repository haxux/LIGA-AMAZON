<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** «Me gusta» (+1) o «no me gusta» (-1), una por persona y publicación. */
#[Fillable(['wall_post_id', 'user_id', 'value'])]
class WallReaction extends Model
{
    public const LIKE = 1;

    public const DISLIKE = -1;
}
