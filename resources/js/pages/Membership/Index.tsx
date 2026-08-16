import { Head, Link, usePage, useForm } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Role {
    id: number;
    name: string;
    slug: string;
}

interface Membership {
    id: number;
    user: User;
    roles: Role[];
    is_active: boolean;
}

interface PageProps {
    memberships: Membership[];
}

export default function Index({ memberships }: PageProps) {
    const { auth } = usePage<any>().props;
    const { patch } = useForm();

    const handleActivate = (id: number) => {
        if (confirm('Tem certeza que deseja ativar este membro?')) {
            patch(`/memberships/${id}/activate`);
        }
    };

    const handleDeactivate = (id: number) => {
        if (confirm('Tem certeza que deseja desativar este membro? Ele perderá acesso ao tenant.')) {
            patch(`/memberships/${id}/deactivate`);
        }
    };

    return (
        <>
            <Head title="Membros" />

            <div className="max-w-7xl mx-auto">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Membros do Tenant
                    </h1>
                </div>

                <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Usuário
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        E-mail
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Papéis
                                    </th>
                                    <th scope="col" className="relative px-6 py-3">
                                        <span className="sr-only">Ações</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                {memberships.map((membership) => (
                                    <tr key={membership.id}>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {membership.user.name}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {membership.user.email}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${membership.is_active ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'}`}>
                                                {membership.is_active ? 'Ativo' : 'Inativo'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <div className="flex flex-wrap gap-1">
                                                {membership.roles.length > 0 ? (
                                                    membership.roles.map(role => (
                                                        <span key={role.id} className="px-2 inline-flex text-xs leading-5 font-medium rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                            {role.name}
                                                        </span>
                                                    ))
                                                ) : (
                                                    <span className="text-gray-400 italic">Nenhum papel</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end space-x-3">
                                                {membership.user.id !== auth.user.id && (
                                                    <>
                                                        <Link href={`/memberships/${membership.id}/edit`} className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                            Papéis
                                                        </Link>
                                                        {membership.is_active ? (
                                                            <button
                                                                onClick={() => handleDeactivate(membership.id)}
                                                                className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                            >
                                                                Desativar
                                                            </button>
                                                        ) : (
                                                            <button
                                                                onClick={() => handleActivate(membership.id)}
                                                                className="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                            >
                                                                Ativar
                                                            </button>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {memberships.length === 0 && (
                            <div className="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum membro encontrado.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
