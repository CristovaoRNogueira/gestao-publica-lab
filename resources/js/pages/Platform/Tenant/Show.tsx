import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { PlatformLayout } from '../../../Layouts/PlatformLayout';

import { Button } from '../../../components/ui/Button';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    active_members_count: number;
}

interface Props {
    tenant: Tenant;
}

export default function Show({ tenant }: Props) {
    const { auth } = usePage<any>().props;
    const canManageStatus = auth.platform?.capabilities?.includes('tenants.manage');

    const { patch, processing } = useForm({
        is_active: !tenant.is_active
    });

    function toggleStatus() {
        if (confirm(`Tem certeza que deseja ${tenant.is_active ? 'desativar' : 'ativar'} este Tenant?`)) {
            patch(`/platform/tenants/${tenant.id}/status`);
        }
    }

    return (
        <PlatformLayout>
            <Head title={`Detalhes do Tenant: ${tenant.name}`} />

            <div className="mb-6">
                <Link href="/platform/tenants" className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 text-sm font-medium">
                    &larr; Voltar para Tenants
                </Link>
            </div>

            <div className="mb-6 flex items-center justify-between">
                <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight dark:text-white">
                    {tenant.name}
                </h2>
                <div>
                    {canManageStatus && (
                        <Button
                            onClick={toggleStatus}
                            disabled={processing}
                            variant={tenant.is_active ? 'danger' : 'primary'}
                        >
                            {tenant.is_active ? 'Desativar Tenant' : 'Ativar Tenant'}
                        </Button>
                    )}
                </div>
            </div>

            <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Informações do Tenant
                </h3>
                <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div className="sm:col-span-1">
                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Slug</dt>
                        <dd className="mt-1 text-sm text-gray-900 dark:text-white">{tenant.slug}</dd>
                    </div>
                    <div className="sm:col-span-1">
                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd className="mt-1 text-sm">
                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                tenant.is_active
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                            }`}>
                                {tenant.is_active ? 'Ativo' : 'Inativo'}
                            </span>
                        </dd>
                    </div>
                    <div className="sm:col-span-1">
                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Membros Ativos</dt>
                        <dd className="mt-1 text-sm text-gray-900 dark:text-white">{tenant.active_members_count}</dd>
                    </div>
                </dl>
            </div>
        </PlatformLayout>
    );
}
