<?php

namespace App\Modules\Secretaria\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Secretaria\Http\Requests\SaveSecretariaRequest;
use App\Modules\Secretaria\Models\Secretaria;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SecretariaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Secretaria::class);

        $tenant = $this->context->getTenant();

        $secretarias = Secretaria::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'is_active']);

        return Inertia::render('Secretaria/Index', [
            'secretarias' => $secretarias,
        ]);
    }
    public function create(): Response
    {
        $this->authorize('create', Secretaria::class);
        return Inertia::render('Secretaria/Create');
    }

    public function store(SaveSecretariaRequest $request): RedirectResponse
    {
        $this->authorize('create', Secretaria::class);

        $tenant = $this->context->getTenant();
        $validated = $request->validated();

        // Gerar o slug no backend
        $slug = Str::slug($validated['name']);

        // Tratar colisão de slug adicionando um sufixo numérico (implementação super básica de uniqueness scope)
        $originalSlug = $slug;
        $counter = 1;
        while (Secretaria::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        Secretaria::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('secretarias.index')->with('success', 'Secretaria criada com sucesso.');
    }
    public function edit(Secretaria $secretaria): Response
    {
        $this->authorize('update', $secretaria);
        return Inertia::render('Secretaria/Edit', [
            'secretaria' => [
                'id' => $secretaria->id,
                'name' => $secretaria->name,
                'slug' => $secretaria->slug,
                'description' => $secretaria->description,
                'is_active' => $secretaria->is_active,
            ]
        ]);
    }

    public function update(SaveSecretariaRequest $request, Secretaria $secretaria): RedirectResponse
    {
        $this->authorize('update', $secretaria);

        $validated = $request->validated();

        $slug = Str::slug($validated['name']);

        if ($slug !== $secretaria->slug) {
            $originalSlug = $slug;
            $counter = 1;
            while (Secretaria::where('tenant_id', $this->context->getTenant()->id)->where('slug', $slug)->where('id', '!=', $secretaria->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $secretaria->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? $validated['is_active'] : $secretaria->is_active,
        ]);

        return redirect()->route('secretarias.index')->with('success', 'Secretaria atualizada com sucesso.');
    }
}
