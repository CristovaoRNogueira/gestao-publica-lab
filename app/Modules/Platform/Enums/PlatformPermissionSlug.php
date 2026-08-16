<?php

namespace App\Modules\Platform\Enums;

enum PlatformPermissionSlug: string
{
    case PLATFORM_ACCESS = 'platform.access';
    case TENANTS_VIEW = 'tenants.view';
    case TENANTS_MANAGE = 'tenants.manage';
}
