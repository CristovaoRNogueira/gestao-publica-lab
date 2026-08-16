<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Permission;
use Illuminate\Support\Facades\DB;

class SyncAdminCapabilitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:sync-admin-capabilities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza as capacidades padrão da Role Administrador de todos os Tenants existentes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronização...');

        $expectedSlugs = PermissionSlug::defaultAdminSlugs();
        $permissions = Permission::whereIn('slug', $expectedSlugs)->get();

        if ($permissions->count() !== count($expectedSlugs)) {
            $this->error('Algumas permissões esperadas não foram encontradas no catálogo.');
            return 1;
        }

        $permissionIds = $permissions->pluck('id')->toArray();
        $roles = Role::where('slug', 'admin')->get();

        $processed = 0;
        $added = 0;
        $unchanged = 0;
        $errors = 0;

        foreach ($roles as $role) {
            try {
                DB::transaction(function () use ($role, $permissionIds, &$processed, &$added, &$unchanged) {
                    $processed++;

                    $currentPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();
                    $missingIds = array_diff($permissionIds, $currentPermissionIds);

                    if (count($missingIds) > 0) {
                        $role->permissions()->syncWithoutDetaching($missingIds);
                        $added += count($missingIds);
                    } else {
                        $unchanged++;
                    }
                });
            } catch (\Exception $e) {
                $this->error("Erro processando role ID {$role->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info('Sincronização finalizada.');
        $this->line("Quantidade de Roles processadas: {$processed}");
        $this->line("Quantidade de permissions adicionadas: {$added}");
        $this->line("Quantidade de Roles sem alterações: {$unchanged}");
        if ($errors > 0) {
            $this->error("Erros: {$errors}");
        } else {
            $this->line("Erros: 0");
        }

        return $errors > 0 ? 1 : 0;
    }
}
