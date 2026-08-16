import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
import { Button } from '../../components/ui/Button';
import { Toast } from '../../components/ui/Toast';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
}

interface Membership {
    id: number;
    user: User;
    roles: Role[];
    is_active: boolean;
}

interface PageProps {
    membership: Membership;
    availableRoles: Role[];
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    [key: string]: any;
}

export default function Edit({ membership, availableRoles }: PageProps) {
    const { flash } = usePage<PageProps>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        role_id: ''
    });

    const availableToAssign = availableRoles.filter(
        role => !membership.roles.some(mr => mr.id === role.id)
    );

    function handleAssign(e: React.FormEvent) {
        e.preventDefault();
        post(`/memberships/${membership.id}/roles`, {
            preserveScroll: true,
            onSuccess: () => reset('role_id')
        });
    }

    function handleRevoke(id: number) {
        if (window.confirm('Tem certeza que deseja remover este papel do usuário?')) {
            router.delete(`/memberships/${membership.id}/roles/${id}`, {
                preserveScroll: true
            });
        }
    }

    // Identificar toast ativo a partir da flash message
    let activeToast = null;
    if (flash?.success) activeToast = { message: flash.success, type: 'success' as const };
    else if (flash?.warning) activeToast = { message: flash.warning, type: 'warning' as const };
    else if (flash?.error) activeToast = { message: flash.error, type: 'error' as const };

    return (
        <>
            <Head title={`Gerenciar Papéis - ${membership.user.name}`} />

            {activeToast && (
                <Toast
                    message={activeToast.message}
                    type={activeToast.type}
                    duration={5000}
                />
            )}

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Gerenciar Papéis: {membership.user.name}
                    </h1>
                    <Link href="/memberships" className="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                        &larr; Voltar para Membros
                    </Link>
                </div>

                <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                            Atribuir Novo Papel
                        </h3>
                        <form onSubmit={handleAssign} className="flex gap-4 items-start">
                            <div className="flex-1 max-w-sm">
                                <label htmlFor="role" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Selecione um papel
                                </label>
                                <select
                                    id="role"
                                    value={data.role_id}
                                    onChange={(e) => setData('role_id', e.target.value)}
                                    className={`mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md ${errors.role_id ? 'border-red-500' : ''}`}
                                >
                                    <option value="">Selecione...</option>
                                    {availableToAssign.map(role => (
                                        <option key={role.id} value={role.id}>
                                            {role.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.role_id && (
                                    <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.role_id}</p>
                                )}
                            </div>
                            <Button type="submit" disabled={processing || !data.role_id} className="mt-6">
                                Atribuir
                            </Button>
                        </form>
                    </div>
                </div>

                <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                            Papéis Atuais
                        </h3>
                        {membership.roles.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Nome
                                            </th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Descrição
                                            </th>
                                            <th scope="col" className="relative px-6 py-3">
                                                <span className="sr-only">Remover</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        {membership.roles.map(role => (
                                            <tr key={role.id}>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    {role.name}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    {role.description || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button
                                                        onClick={() => handleRevoke(role.id)}
                                                        className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                    >
                                                        Remover
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Este usuário não possui nenhum papel atribuído.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Edit.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
