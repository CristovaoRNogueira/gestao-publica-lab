import React, { useState } from 'react';
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
    status: string;
}

interface PageProps {
    memberships: Membership[];
}

export default function Index({ memberships }: PageProps) {
    const { auth } = usePage<any>().props;
    const { patch } = useForm();
    const [activeTab, setActiveTab] = useState('active');

    const handleActivate = (id: number) => {
        if (confirm('Tem certeza que deseja ativar este membro?')) {
            patch(`/memberships/${id}/activate`);
        }
    };

    const handleDeactivate = (id: number) => {
        if (confirm('Tem certeza que deseja desativar este membro? Ele perderá acesso à prefeitura.')) {
            patch(`/memberships/${id}/deactivate`);
        }
    };

    const handleApprove = (id: number) => {
        if (confirm('Tem certeza que deseja aprovar o acesso deste usuário?')) {
            patch(`/memberships/${id}/approve`);
        }
    };

    const handleReject = (id: number) => {
        if (confirm('Tem certeza que deseja recusar o acesso deste usuário?')) {
            patch(`/memberships/${id}/reject`);
        }
    };

    const filteredMemberships = memberships.filter(m => m.status === activeTab);

    return (
        <>
            <Head title="Membros" />

            <div className="max-w-7xl mx-auto">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Membros da Prefeitura
                    </h1>
                    {auth.capabilities.includes('invitations.manage') && (
                        <Link
                            href="/invitations/create"
                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Convidar pessoa
                        </Link>
                    )}
                </div>

                <div className="mb-4 border-b border-gray-200 dark:border-gray-700">
                    <nav className="-mb-px flex space-x-8" aria-label="Tabs">
                        {[
                            { id: 'active', name: 'Ativos' },
                            { id: 'pending', name: 'Aguardando aprovação' },
                            { id: 'inactive', name: 'Inativos' },
                            { id: 'rejected', name: 'Recusados' },
                        ].map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`
                                    whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
                                    ${activeTab === tab.id
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                                    }
                                `}
                            >
                                {tab.name}
                                <span className="ml-2 bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100 py-0.5 px-2 rounded-full text-xs">
                                    {memberships.filter(m => m.status === tab.id).length}
                                </span>
                            </button>
                        ))}
                    </nav>
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
                                        Funções
                                    </th>
                                    <th scope="col" className="relative px-6 py-3">
                                        <span className="sr-only">Ações</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                {filteredMemberships.map((membership) => (
                                    <tr key={membership.id}>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {membership.user.name}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {membership.user.email}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                membership.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' :
                                                membership.status === 'inactive' ? 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' :
                                                membership.status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' :
                                                'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'
                                            }`}>
                                                {membership.status === 'active' ? 'Ativo' :
                                                 membership.status === 'inactive' ? 'Inativo' :
                                                 membership.status === 'pending' ? 'Aguardando aprovação' :
                                                 membership.status === 'rejected' ? 'Acesso recusado' : membership.status}
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
                                                    <span className="text-gray-400 italic">Nenhuma função</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end space-x-3">
                                                {membership.user.id !== auth.user.id ? (
                                                    <>
                                                        {membership.status !== 'pending' && membership.status !== 'rejected' && (
                                                            <Link href={`/memberships/${membership.id}/edit`} className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                                Gerenciar funções
                                                            </Link>
                                                        )}
                                                        {membership.status === 'active' && (
                                                            <button
                                                                onClick={() => handleDeactivate(membership.id)}
                                                                className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                            >
                                                                Desativar acesso
                                                            </button>
                                                        )}
                                                        {membership.status === 'inactive' && (
                                                            <button
                                                                onClick={() => handleActivate(membership.id)}
                                                                className="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                            >
                                                                Ativar acesso
                                                            </button>
                                                        )}
                                                        {membership.status === 'pending' && auth.capabilities?.includes('memberships.manage') && (
                                                            <>
                                                                <button
                                                                    onClick={() => handleApprove(membership.id)}
                                                                    className="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                                >
                                                                    Aprovar
                                                                </button>
                                                                <button
                                                                    onClick={() => handleReject(membership.id)}
                                                                    className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                                >
                                                                    Rejeitar
                                                                </button>
                                                            </>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="text-gray-500 italic">Você</span>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {filteredMemberships.length === 0 && (
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
