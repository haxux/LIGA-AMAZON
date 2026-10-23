<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\WallPost;
use App\Models\WallReaction;
use App\Services\WallService;
use App\Support\SiteAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «A Segunda», el foro interno de técnicos y presidente, dentro de Noticias
 * (decisión del propietario, 2026-10-23). Sólo para quien tiene sesión: un
 * visitante no lo ve, y si llega con un enlace se le manda a entrar.
 *
 * Se publica, responde y reacciona con la cuenta ACTIVA del selector de
 * cuentas (ver SiteAccount).
 */
class WallController extends Controller
{
    public function index(SiteAccount $accounts): View|RedirectResponse
    {
        $reader = $accounts->active();

        if ($reader === null) {
            return redirect()->route('filament.club.auth.login');
        }

        $posts = WallPost::query()
            ->whereNull('parent_id')
            ->forReader($reader)
            // Las dos últimas respuestas de cada una, para leer el hilo de un
            // vistazo; el resto, en la publicación.
            ->with(['replies' => fn ($replies) => $replies->forReader($reader)->latest()->limit(2)])
            ->latest()
            ->simplePaginate(15);

        return view('site.wall.index', ['posts' => $posts, 'reader' => $reader]);
    }

    public function show(SiteAccount $accounts, WallPost $post): View|RedirectResponse
    {
        $reader = $accounts->active();

        if ($reader === null) {
            return redirect()->route('filament.club.auth.login');
        }

        // Una respuesta se lee dentro de su publicación.
        if ($post->isReply()) {
            return redirect()->to(route('site.wall.show', $post->parent_id).'#publicacion-'.$post->getKey());
        }

        $post = WallPost::query()->whereKey($post->getKey())->forReader($reader)
            ->with(['replies' => fn ($replies) => $replies->forReader($reader)])
            ->firstOrFail();

        return view('site.wall.show', ['post' => $post, 'reader' => $reader]);
    }

    public function store(Request $request, SiteAccount $accounts, WallService $wall): RedirectResponse
    {
        $author = $accounts->active();
        abort_if($author === null, 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:'.WallPost::MAX_BODY],
            'parent_id' => ['nullable', 'integer', Rule::exists('wall_posts', 'id')],
            'images' => ['nullable', 'array', 'max:'.WallPost::MAX_IMAGES],
            // 12 MB por foto: lo que pesa una foto grande de móvil. El navegador
            // ya las reduce antes de subirlas (ver el formulario).
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:12288'],
        ], [
            'body.max' => 'Como mucho '.WallPost::MAX_BODY.' caracteres.',
            'images.max' => 'Como mucho '.WallPost::MAX_IMAGES.' fotos por publicación.',
            'images.*.image' => 'Sólo se pueden subir fotos.',
            'images.*.mimes' => 'Las fotos tienen que ser JPG, PNG o WEBP.',
            'images.*.max' => 'Cada foto puede pesar como mucho 12 MB.',
        ]);

        $parent = isset($data['parent_id']) ? WallPost::query()->find($data['parent_id']) : null;

        $post = $wall->publish($author, $data['body'] ?? null, $request->file('images', []), $parent);

        return $post->isReply()
            ? redirect()->to(route('site.wall.show', $post->parent_id).'#publicacion-'.$post->getKey())
            : redirect()->to(route('site.wall.index').'#publicacion-'.$post->getKey());
    }

    /** «Me gusta» / «no me gusta», sin recargar la página. */
    public function react(Request $request, SiteAccount $accounts, WallService $wall, WallPost $post): JsonResponse
    {
        $user = $accounts->active();
        abort_if($user === null, 403);

        $data = $request->validate(['value' => ['required', Rule::in([WallReaction::LIKE, WallReaction::DISLIKE])]]);

        $mine = $wall->react($user, $post, (int) $data['value']);

        return response()->json([
            'mine' => $mine,
            'likes' => $post->reactions()->where('value', WallReaction::LIKE)->count(),
            'dislikes' => $post->reactions()->where('value', WallReaction::DISLIKE)->count(),
        ]);
    }

    public function destroy(SiteAccount $accounts, WallService $wall, WallPost $post): RedirectResponse
    {
        $user = $accounts->active();
        abort_unless($user !== null && $wall->mayDelete($user, $post), 403);

        $parent = $post->parent_id;
        $post->delete();

        return $parent
            ? redirect()->route('site.wall.show', $parent)
            : redirect()->route('site.wall.index');
    }
}
