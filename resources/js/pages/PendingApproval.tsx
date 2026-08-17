import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '../layouts/AppLayout';

interface PendingMembership {
    id: number;
    tenant_name: string;
    unit_name: string | null;
    created_at: string;
}

interface Props {
    memberships: PendingMembership[];
}

export default function PendingApproval({ memberships }: Props) {
    return (
        <>
            <Head title="Aguardando Aprovação" />

            <div className="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto">
                    <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <div className="px-4 py-5 sm:p-6 text-center">
                            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 mb-4">
                                <svg className="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Aguardando Aprovação
                            </h3>
                            <div className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                <p>Sua solicitação de acesso foi registrada com sucesso.</p>
                                <p className="mt-1">
                                    Você precisa aguardar que um administrador revise e aprove sua entrada antes de poder acessar o sistema.
                                </p>
                            </div>

                            <div className="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h4 className="text-md font-medium text-gray-900 dark:text-white mb-4 text-left">
                                    Suas Solicitações Pendentes
                                </h4>
                                <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {memberships.map((membership) => (
                                        <li key={membership.id} className="py-4 flex justify-between items-center text-left">
                                            <div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                    {membership.tenant_name}
                                                </p>
                                                {membership.unit_name && (
                                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                                        Unidade: {membership.unit_name}
                                                    </p>
                                                )}
                                            </div>
                                            <div>
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                                    Pendente
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

PendingApproval.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
