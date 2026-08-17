import { Head } from '@inertiajs/react';
import { AppLayout } from '../layouts/AppLayout';

export default function AccessDenied() {
    return (
        <>
            <Head title="Acesso Indisponível" />

            <div className="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto">
                    <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <div className="px-4 py-5 sm:p-6 text-center">
                            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 mb-4">
                                <svg className="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Acesso Indisponível
                            </h3>
                            <div className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                <p>Seu acesso está indisponível.</p>
                                <p className="mt-1">
                                    Sua conta existe, mas você não possui atualmente nenhuma organização com acesso ativo.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

AccessDenied.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
