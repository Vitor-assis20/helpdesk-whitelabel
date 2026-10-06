<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TicketPriority;
use App\Helpdesk\Sla\SlaClock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\BusinessHoursRequest;
use App\Http\Requests\Panel\SlaPoliciesRequest;
use App\Models\BusinessHour;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurações da empresa: marca, expediente e prazos de SLA.
 * Mudanças de expediente e SLA valem para chamados abertos daqui em diante.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly CurrentTenant $current,
        private readonly SlaClock $sla,
    ) {}

    public function edit(): Response
    {
        $tenant = $this->current->get();
        $hours = $tenant->businessHours()->orderBy('weekday')->orderBy('starts_at')->get();

        return Inertia::render('panel/settings', [
            'business' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'primary_color' => $tenant->publicBranding()['primary_color'],
                'portal_url' => route('portal.home', ['tenant' => $tenant->slug]),
                'widget_snippet' => '<script src="'.route('widget.script', ['tenant' => $tenant->slug]).'" async></script>',
            ],
            'hours' => $hours->isEmpty()
                ? array_map(fn (int $weekday) => [
                    'weekday' => $weekday,
                    'starts_at' => SlaClock::DEFAULT_HOURS['starts_at'],
                    'ends_at' => SlaClock::DEFAULT_HOURS['ends_at'],
                ], SlaClock::DEFAULT_HOURS['weekdays'])
                : $hours->map(fn (BusinessHour $hours) => [
                    'weekday' => $hours->weekday,
                    'starts_at' => substr($hours->starts_at, 0, 5),
                    'ends_at' => substr($hours->ends_at, 0, 5),
                ])->all(),
            'policies' => collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $priority) => [
                $priority->value => ['label' => $priority->label(), ...$this->sla->policyFor($tenant, $priority)],
            ]),
        ]);
    }

    public function branding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'name.required' => 'Informe o nome da empresa.',
            'primary_color.*' => 'Escolha uma cor válida (ex.: #7c3aed).',
        ]);

        $tenant = $this->current->get();
        $tenant->update([
            'name' => $validated['name'],
            'branding' => [...($tenant->branding ?? []), 'primary_color' => strtolower($validated['primary_color'])],
        ]);

        return back();
    }

    public function hours(BusinessHoursRequest $request): RedirectResponse
    {
        $tenant = $this->current->get();

        DB::transaction(function () use ($tenant, $request) {
            $tenant->businessHours()->delete();
            $tenant->businessHours()->createMany($request->validated('hours'));
        });

        return back();
    }

    public function sla(SlaPoliciesRequest $request): RedirectResponse
    {
        $tenant = $this->current->get();

        DB::transaction(function () use ($tenant, $request) {
            foreach ($request->validated('policies') as $priority => $minutes) {
                $tenant->slaPolicies()->updateOrCreate(['priority' => $priority], $minutes);
            }
        });

        return back();
    }
}
