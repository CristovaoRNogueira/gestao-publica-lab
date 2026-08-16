import { Head, Link, usePage, router } from '@inertiajs/react';
import { PlatformLayout } from '../../../layouts/PlatformLayout';

interface Role {
    id: number;
    name: string;
    slug: string;
}

interface Tenant {
    id: number;
    name: string;
    slug: string;
}

interface Membership {
    id: number;
    tenant_id: number;
    is_active: boolean;
    tenant: Tenant;
    roles: Role[];
}

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    memberships: Membership[];
}

export default function Show() {
    const { user } = usePage<{ user: User }>().props;

    const handleStatusToggle = (membership: Membership) => {
        const action = membership.is_active ? 'desativar' : 'ativar';
        if (window.confirm(`Tem certeza que deseja ${action} o acesso deste usuário à organização ${membership.tenant.name}?`)) {
            router.patch(`/platform/memberships/${membership.id}/status`, {
                is_active: !membership.is_active,
            }, {
                preserveScroll: true,
            });
        }
    };

    return (
        <PlatformLayout>
            <Head title={`Usuário - ${user.name}`} />

            <div className="mb-6">
                <Link href="/platform/users" className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 text-sm font-medium">
                    &larr; Voltar para Usuários
                </Link>
            </div>

            <div className="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight dark:text-white">
                        {user.name}
                    </h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Visualizando identidade global e vínculos organizacionais
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* User Global Profile */}
                <div className="col-span-1">
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                            Perfil Global
                        </h3>
                        <dl className="grid grid-cols-1 gap-x-4 gap-y-6">
                            <div className="sm:col-span-1">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">ID</dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-white">#{user.id}</dd>
                            </div>
                            <div className="sm:col-span-1">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Nome</dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-white">{user.name}</dd>
                            </div>
                            <div className="sm:col-span-1">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">E-mail</dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-white">{user.email}</dd>
                            </div>
                            <div className="sm:col-span-1">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Criado em</dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-white">
                                    {new Date(user.created_at).toLocaleDateString()}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {/* User Memberships */}
                <div className="col-span-1 lg:col-span-2">
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div className="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                            <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Vínculos Organizacionais ({user.memberships.length})
                            </h3>
                            <p className="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                                Organizações às quais o usuário tem acesso.
                            </p>
                        </div>
                        {user.memberships.length > 0 ? (
                            <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                                {user.memberships.map((membership) => (
                                    <li key={membership.id} className="p-4 sm:p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                                        <div className="flex items-center justify-between">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-indigo-600 dark:text-indigo-400 truncate">
                                                    {membership.tenant.name}
                                                </p>
                                                <div className="mt-2 flex">
                                                    <div className="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                        <span className="mr-2">Papéis:</span>
                                                        {membership.roles.length > 0 ? (
                                                            <div className="flex flex-wrap gap-1">
                                                                {membership.roles.map(role => (
                                                                    <span key={role.id} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200">
                                                                        {role.name}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        ) : (
                                                            <span className="italic">Nenhum</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="ml-4 flex-shrink-0 flex items-center space-x-4">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${membership.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                                    {membership.is_active ? 'Ativo' : 'Inativo'}
                                                </span>
                                                <button
                                                    onClick={() => handleStatusToggle(membership)}
                                                    className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline"
                                                >
                                                    {membership.is_active ? 'Suspender Acesso' : 'Restaurar Acesso'}
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <div className="p-6 text-center text-gray-500 dark:text-gray-400">
                                Este usuário não possui vínculos ativos com nenhuma organização.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </PlatformLayout>
    );
}
