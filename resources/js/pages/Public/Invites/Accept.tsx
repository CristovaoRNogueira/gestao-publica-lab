import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { AppLayout } from '@/layouts/AppLayout';

interface Props {
    isValid: boolean;
    token?: string;
    tenantName?: string;
    inviterName?: string;
    expiresAt?: string;
    isAuthenticated?: boolean;
    userExists?: boolean;
}

export default function Accept({ isValid, token, tenantName, inviterName, expiresAt, isAuthenticated, userExists }: Props) {
    const { post, processing } = useForm();

    const acceptInvite = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/invites/${token}`);
    };

    return (
        <AppLayout>
            <Head title="Convite" />
            <div className="flex flex-col items-center justify-center min-h-[70vh]">
                <div className="w-full max-w-md p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md text-center">
                    {!isValid ? (
                        <div>
                            <h2 className="text-xl font-bold text-red-600 dark:text-red-400 mb-4">Convite Inválido</h2>
                            <p className="text-gray-600 dark:text-gray-400">
                                Este convite não é mais válido, pode ter expirado ou já foi aceito.
                            </p>
                        </div>
                    ) : (
                        <div>
                            <h2 className="text-xl font-bold text-gray-900 dark:text-white mb-4">
                                Convite para {tenantName}
                            </h2>
                            <p className="text-gray-600 dark:text-gray-400 mb-6">
                                Você foi convidado por <strong>{inviterName}</strong> para participar de <strong>{tenantName}</strong>.
                            </p>
                            {isAuthenticated ? (
                                <form onSubmit={acceptInvite}>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                    >
                                        {processing ? 'Processando...' : 'Aceitar Convite'}
                                    </button>
                                </form>
                            ) : (
                                <Link
                                    href={userExists ? '/login' : '/register'}
                                    className="w-full flex justify-center px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    {userExists ? 'Entrar para aceitar' : 'Criar minha conta'}
                                </Link>
                            )}
                            {expiresAt && (
                                <p className="mt-4 text-xs text-gray-500 dark:text-gray-500">
                                    Expira em: {new Date(expiresAt).toLocaleString()}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
