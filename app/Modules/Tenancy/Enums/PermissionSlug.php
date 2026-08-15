<?php

namespace App\Modules\Tenancy\Enums;

enum PermissionSlug: string
{
    case MEMBERSHIPS_ROLES_MANAGE = 'memberships.roles.manage';
    case SECRETARIAS_VIEW = 'secretarias.view';
    case SECRETARIAS_CREATE = 'secretarias.create';
    case SECRETARIAS_UPDATE = 'secretarias.update';

    public function label(): string
    {
        return match ($this) {
            self::MEMBERSHIPS_ROLES_MANAGE => 'Gerenciar Papéis de Associação',
            self::SECRETARIAS_VIEW => 'Visualizar Secretarias',
            self::SECRETARIAS_CREATE => 'Criar Secretarias',
            self::SECRETARIAS_UPDATE => 'Editar Secretarias',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MEMBERSHIPS_ROLES_MANAGE => 'Permite gerenciar a atribuição e revogação de papéis das associações dentro do tenant.',
            self::SECRETARIAS_VIEW => 'Permite visualizar a lista e os detalhes das secretarias do tenant.',
            self::SECRETARIAS_CREATE => 'Permite criar novas secretarias no tenant.',
            self::SECRETARIAS_UPDATE => 'Permite atualizar os dados das secretarias do tenant.',
        };
    }
}
