<?php

namespace App\Http\Controllers\Panel;

use App\Helpdesk\MacroRenderer;
use App\Http\Controllers\Controller;
use App\Models\Macro;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Respostas prontas da empresa. Dono e administradores mantêm; a equipe toda usa.
 */
class MacroController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('panel/macros', [
            'macros' => Macro::query()->orderBy('title')->get()->map->only('id', 'title', 'body', 'uses'),
            'variables' => collect(MacroRenderer::VARIABLES)->map(fn (string $label, string $name) => ['name' => $name, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Macro::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Macro $macro): RedirectResponse
    {
        $macro->update($this->validated($request, $macro));

        return back();
    }

    public function destroy(Macro $macro): RedirectResponse
    {
        $macro->delete();

        return back();
    }

    /**
     * @return array{title: string, body: string}
     */
    private function validated(Request $request, ?Macro $macro = null): array
    {
        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:80',
                // Mesma regra do índice único: na empresa, sem diferenciar maiúsculas.
                function (string $attribute, mixed $value, Closure $fail) use ($macro) {
                    $taken = Macro::query()
                        ->whereRaw('lower(title) = lower(?)', [(string) $value])
                        ->when($macro, fn ($query) => $query->whereKeyNot($macro->id))
                        ->exists();

                    if ($taken) {
                        $fail('Já existe uma resposta com esse título.');
                    }
                },
            ],
            'body' => ['required', 'string', 'max:10000'],
        ], [
            'title.required' => 'Dê um título à resposta.',
            'body.required' => 'Escreva o texto da resposta.',
        ]);
    }
}
