import { Head, Link, usePage, router } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
import { useState } from 'react';

interface Permission {
    id: number;
    label: string;
    slug: string;
    description: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Membership {
    id: number;
    user: User;
}

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    permissions: Permission[];
    memberships: Membership[];
}

interface PageProps {
    role: Role;
    allPermissions: Permission[];
    canManagePermissions: boolean;
}

export default function Show({ role, allPermissions, canManagePermissions }: PageProps) {
    const { auth } = usePage<any>().props;
    const [loading, setLoading] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const hasFullCriticalSet = auth.capabilities.includes('memberships.roles.manage') && auth.capabilities.includes('roles.permissions.manage');
    const isCritical = (slug: string) => ['memberships.roles.manage', 'roles.permissions.manage'].includes(slug);

    const rolePermissionIds = new Set(role.permissions.map(p => p.id));

    const handleTogglePermission = async (permission: Permission, isAssigned: boolean) => {
        if (!canManagePermissions) return;
        if (isCritical(permission.slug) && !hasFullCriticalSet) return;

        setLoading(permission.id);
        setError(null);

        try {
            if (isAssigned) {
                router.delete(`/roles/${role.id}/permissions/${permission.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setLoading(null);
                        setError(null);
                    },
                    onError: (errors) => {
                        setError(errors.permission_id || 'Erro ao remover permissão.');
                        setLoading(null);
                    }
                });
            } else {
                router.post(`/roles/${role.id}/permissions`, { permission_id: permission.id }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setLoading(null);
                        setError(null);
                    },
                    onError: (errors) => {
                        setError(errors.permission_id || 'Erro ao conceder permissão.');
                        setLoading(null);
                    }
                });
            }
        } catch (err) {
            setError('Erro inesperado.');
            setLoading(null);
        }
    };

    return (
        <>
            <Head title={`Função: ${role.name}`} />
            <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="px-4 sm:px-0 mb-6 flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">{role.name}</h1>
                        <p className="mt-1 text-sm text-gray-600">{role.description}</p>
                    </div>
                    <Link
                        href="/roles"
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    >
                        &larr; Voltar
                    </Link>
                </div>

                {error && (
                    <div className="mb-4 bg-red-50 p-4 rounded-md">
                        <p className="text-sm text-red-700">{error}</p>
                    </div>
                )}

                <div className="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                    <div className="px-4 py-5 sm:px-6 flex justify-between items-center">
                        <div>
                            <h3 className="text-lg leading-6 font-medium text-gray-900">Permissões da Função</h3>
                            <p className="mt-1 max-w-2xl text-sm text-gray-500">
                                Defina quais ações esta função pode realizar no sistema.
                            </p>
                        </div>
                    </div>
                    <div className="border-t border-gray-200">
                        <ul className="divide-y divide-gray-200">
                            {allPermissions.map(permission => {
                                const isAssigned = rolePermissionIds.has(permission.id);
                                const critical = isCritical(permission.slug);
                                const canManage = canManagePermissions && (!critical || hasFullCriticalSet);

                                return (
                                    <li key={permission.id} className="px-4 py-4 sm:px-6">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className={`text-sm font-medium truncate ${critical ? 'text-red-600' : 'text-gray-900'}`}>
                                                    {permission.label}
                                                    {critical && <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Crítica</span>}
                                                </p>
                                                <p className="text-sm text-gray-500">{permission.description}</p>
                                            </div>
                                            <div className="ml-2 flex-shrink-0 flex items-center">
                                                <button
                                                    onClick={() => handleTogglePermission(permission, isAssigned)}
                                                    disabled={!canManage || loading === permission.id}
                                                    className={`
                                                        relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                                                        ${isAssigned ? 'bg-indigo-600' : 'bg-gray-200'}
                                                        ${(!canManage || loading === permission.id) ? 'opacity-50 cursor-not-allowed' : ''}
                                                    `}
                                                    role="switch"
                                                    aria-checked={isAssigned}
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        className={`
                                                            pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200
                                                            ${isAssigned ? 'translate-x-5' : 'translate-x-0'}
                                                        `}
                                                    />
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </div>

                <div className="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div className="px-4 py-5 sm:px-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900">Membros com esta função</h3>
                    </div>
                    <div className="border-t border-gray-200">
                        <ul className="divide-y divide-gray-200">
                            {role.memberships.map(membership => (
                                <li key={membership.id} className="px-4 py-4 sm:px-6">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium text-gray-900 truncate">{membership.user.name}</p>
                                        <div className="ml-2 flex-shrink-0 flex">
                                            <p className="text-sm text-gray-500">{membership.user.email}</p>
                                        </div>
                                    </div>
                                </li>
                            ))}
                            {role.memberships.length === 0 && (
                                <li className="px-4 py-4 sm:px-6 text-sm text-gray-500">Nenhum membro possui esta função.</li>
                            )}
                        </ul>
                    </div>
                </div>
            </div>
        </>
    );
}

Show.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
