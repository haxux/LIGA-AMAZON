<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Comentar un partido y borrar un comentario.
 *
 * Comenta un técnico con sesión (entra por el guard `club`); lo lee cualquiera.
 * Borra quien lo escribió, o el administrador si lo ve con su sesión: es la
 * única moderación que hace falta con un grupo tan pequeño.
 */
class GameCommentController extends Controller
{
    public function store(Request $request, Game $game): RedirectResponse
    {
        $coach = auth('club')->user();

        abort_unless($coach?->isCoach() === true, 403, 'Sólo los técnicos pueden comentar.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.GameComment::MAX_LENGTH],
        ], [
            'body.required' => 'Escribe algo antes de enviar.',
            'body.max' => 'Un comentario admite como mucho '.GameComment::MAX_LENGTH.' caracteres.',
        ]);

        GameComment::create([
            'game_id' => $game->getKey(),
            'user_id' => $coach->getKey(),
            'body' => trim($data['body']),
        ]);

        return redirect()->to(route('site.games.show', $game).'#comentarios');
    }

    public function destroy(GameComment $comment): RedirectResponse
    {
        $coach = auth('club')->user();
        $admin = auth()->user()?->isAdmin() ? auth()->user() : null;

        $mine = $coach !== null && (int) $comment->user_id === (int) $coach->getKey();

        abort_unless($mine || $admin !== null, 403);

        $game = $comment->game_id;
        $comment->delete();

        return redirect()->to(route('site.games.show', $game).'#comentarios');
    }
}
