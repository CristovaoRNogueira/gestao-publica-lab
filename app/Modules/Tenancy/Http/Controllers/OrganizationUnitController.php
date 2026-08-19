<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Services\OrganizationScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Modules\Tenancy\Enums\PermissionSlug;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\Gate;

class OrganizationUnitController extends Controller
{
    protected OrganizationScope $scope;

    public function __construct(OrganizationScope $scope)
    {
        $this->scope = $scope;
    }

    public function index(Request $request)
    {
        $membership = app(TenantContext::class)->getMembership();

        Gate::authorize('viewAny', OrganizationUnit::class);

        $units = OrganizationUnit::where('tenant_id', app(TenantContext::class)->getTenant()->id)->get();

        // Se não tem escopo global, filtra a árvore para mostrar só o escopo dele.
        if (!$this->scope->hasGlobalScope($membership)) {
            $allowedUnitId = $membership->organization_unit_id;
            if (!$allowedUnitId) {
                $units = collect([]); // Não tem acesso a nada.
            } else {
                $units = $units->filter(function ($unit) use ($membership) {
                    return $this->scope->isSameOrDescendant($membership->organization_unit_id, $unit);
                });
            }
        }

        return Inertia::render('OrganizationUnit/Index', [
            'units' => $units->values(),
        ]);
    }

    public function create(Request $request)
    {
        $membership = app(TenantContext::class)->getMembership();
        $units = OrganizationUnit::where('tenant_id', app(TenantContext::class)->getTenant()->id)->get();
        if (!$this->scope->hasGlobalScope($membership)) {
            $allowedUnitId = $membership->organization_unit_id;
            if (!$allowedUnitId) {
                $units = collect([]);
            } else {
                $units = $units->filter(function ($unit) use ($membership) {
                    return $this->scope->isSameOrDescendant($membership->organization_unit_id, $unit);
                });
            }
        }
        return Inertia::render('OrganizationUnit/Create', [
            'units' => $units->values()
        ]);
    }

    public function store(Request $request)
    {
        $membership = app(TenantContext::class)->getMembership();

        Gate::authorize('create', OrganizationUnit::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_units', 'id')->where(function ($query) {
                    return $query->where('tenant_id', app(TenantContext::class)->getTenant()->id);
                }),
            ],
        ]);

        $parentUnit = null;
        if (!empty($validated['parent_id'])) {
            $parentUnit = OrganizationUnit::find($validated['parent_id']);
            if (!$this->scope->canManage($membership, $parentUnit)) {
                abort(403, 'Cannot create under this parent unit.');
            }
        } else {
            // Se tentar criar na raiz (sem parent_id)
            if (!$this->scope->hasGlobalScope($membership)) {
                abort(403, 'Global scope required to create root units.');
            }
        }

        $slug = Str::slug($validated['name']);

        // Verifica unicidade do slug no tenant
        if (OrganizationUnit::where('tenant_id', app(TenantContext::class)->getTenant()->id)->where('slug', $slug)->exists()) {
            $slug = $slug . '-' . uniqid();
        }

        OrganizationUnit::create([
            'tenant_id' => app(TenantContext::class)->getTenant()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'type' => $validated['type'] ?: 'Unidade',
            'name' => $validated['name'],
            'slug' => $slug,
            'is_active' => true,
        ]);

        return redirect()->route('organization-units.index')->with('success', 'Unidade organizacional criada com sucesso.');
    }

    public function edit(OrganizationUnit $organizationUnit, Request $request)
    {
        $membership = app(TenantContext::class)->getMembership();

        Gate::authorize('update', $organizationUnit);

        $units = OrganizationUnit::where('tenant_id', app(TenantContext::class)->getTenant()->id)->get();
        if (!$this->scope->hasGlobalScope($membership)) {
            $allowedUnitId = $membership->organization_unit_id;
            if (!$allowedUnitId) {
                $units = collect([]);
            } else {
                $units = $units->filter(function ($unit) use ($membership) {
                    return $this->scope->isSameOrDescendant($membership->organization_unit_id, $unit);
                });
            }
        }

        return Inertia::render('OrganizationUnit/Edit', [
            'unit' => $organizationUnit,
            'units' => $units->values()
        ]);
    }

    public function update(Request $request, OrganizationUnit $organizationUnit)
    {
        $membership = app(TenantContext::class)->getMembership();

        Gate::authorize('update', $organizationUnit);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_units', 'id')->where(function ($query) {
                    return $query->where('tenant_id', app(TenantContext::class)->getTenant()->id);
                }),
            ],
        ]);

        DB::transaction(function () use ($validated, $organizationUnit, $membership) {
            $newParentId = $validated['parent_id'] ?? null;

            // Se estiver mudando o parent_id (movendo)
            if ($organizationUnit->parent_id !== $newParentId) {
                // Bloqueio de Self Parent
                if ($newParentId === $organizationUnit->id) {
                    abort(422, 'A unit cannot be its own parent.');
                }

                // Validação do novo pai
                if ($newParentId !== null) {
                    $newParent = OrganizationUnit::find($newParentId);
                    if (!$this->scope->canManage($membership, $newParent)) {
                        abort(403, 'You do not have permission to move to this parent unit.');
                    }

                    // Bloqueio de Cyclic Loop (Descendant Parent)
                    if ($this->scope->isSameOrDescendant($organizationUnit->id, $newParent)) {
                        abort(422, 'Cannot move a unit to one of its own descendants.');
                    }
                } else {
                    // Tentar mover para a raiz
                    if (!$this->scope->hasGlobalScope($membership)) {
                        abort(403, 'Global scope required to move unit to root.');
                    }
                }
            }

            $slug = Str::slug($validated['name']);
            if ($slug !== $organizationUnit->slug) {
                if (OrganizationUnit::where('tenant_id', app(TenantContext::class)->getTenant()->id)->where('slug', $slug)->exists()) {
                    $slug = $slug . '-' . uniqid();
                }
            } else {
                $slug = $organizationUnit->slug;
            }

            $organizationUnit->update([
                'name' => $validated['name'],
                'type' => $validated['type'] ?: 'Unidade',
                'parent_id' => $newParentId,
                'slug' => $slug,
            ]);
        });

        return redirect()->route('organization-units.index')->with('success', 'Unidade organizacional atualizada com sucesso.');
    }

    public function destroy(OrganizationUnit $organizationUnit, Request $request)
    {
        $membership = app(TenantContext::class)->getMembership();

        Gate::authorize('delete', $organizationUnit);

        // Validação de exclusão: não pode ter filhos
        if ($organizationUnit->children()->count() > 0) {
            return response()->json(['message' => 'Não é possível excluir esta unidade porque ela possui subunidades.'], 409);
        }

        // Validação de exclusão: não pode ter memberships
        if ($organizationUnit->memberships()->count() > 0) {
            return response()->json(['message' => 'Não é possível excluir esta unidade porque existem membros vinculados a ela.'], 409);
        }

        // Validação de exclusão: não pode ter convites
        if (DB::table('tenant_invitations')->where('organization_unit_id', $organizationUnit->id)->exists()) {
            return response()->json(['message' => 'Não é possível excluir uma unidade que possui convites pendentes.'], 409);
        }

        $organizationUnit->delete();

        return redirect()->route('organization-units.index')->with('success', 'Unidade organizacional removida.');
    }
}
