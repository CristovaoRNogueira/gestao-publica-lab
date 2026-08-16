import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
import { FormEvent } from 'react';

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    memberships_count: number;
}

interface PageProps {
    roles: {
        data: Role[];
        links: any[];
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function Index({ roles, flash }: PageProps) {
    const { delete: destroy } = useForm();
    const { auth } = usePage<any>().props;

    const handleDelete = (e: FormEvent, id: number) => {
        e.preventDefault();
        if (confirm('Tem certeza que deseja excluir este papel?')) {
            destroy(route('roles.destroy', id));
        }
    };

    return (
        <AppLayout title="Papéis do Tenant">
            <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="px-4 py-6 sm:px-0">
                    <div className="flex justify-between items-center mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">Gerenciar Papéis</h1>

                        {auth.permissions.includes('roles.create') && (
                            <Link
                                href={route('roles.create')}
                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Novo Papel
                            </Link>
                        )}
                    </div>

                    {flash?.success && (
                        <div className="mb-4 bg-green-50 p-4 rounded-md">
                            <p className="text-sm text-green-700">{flash.success}</p>
                        </div>
                    )}

                    {flash?.error && (
                        <div className="mb-4 bg-red-50 p-4 rounded-md">
                            <p className="text-sm text-red-700">{flash.error}</p>
                        </div>
                    )}

                    <div className="flex flex-col">
                        <div className="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div className="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                <div className="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Nome
                                                </th>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Descrição
                                                </th>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Membros
                                                </th>
                                                <th scope="col" className="relative px-6 py-3">
                                                    <span className="sr-only">Ações</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {roles.data.map((role) => (
                                                <tr key={role.id}>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-medium text-gray-900">{role.name}</div>
                                                        <div className="text-sm text-gray-500">{role.slug}</div>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <div className="text-sm text-gray-900">{role.description || '-'}</div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            {role.memberships_count}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        {auth.permissions.includes('roles.view') && (
                                                            <Link href={route('roles.show', role.id)} className="text-indigo-600 hover:text-indigo-900 mr-4">
                                                                Detalhes
                                                            </Link>
                                                        )}
                                                        {auth.permissions.includes('roles.update') && (
                                                            <Link href={route('roles.edit', role.id)} className="text-indigo-600 hover:text-indigo-900 mr-4">
                                                                Editar
                                                            </Link>
                                                        )}
                                                        {auth.permissions.includes('roles.delete') && role.memberships_count === 0 && (
                                                            <button onClick={(e) => handleDelete(e, role.id)} className="text-red-600 hover:text-red-900">
                                                                Excluir
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
