import { Head, usePage, router } from '@inertiajs/react';

interface AuthProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
        tenant: { id: number; name: string; slug: string } | null;
        tenants: Array<{ id: number; name: string; slug: string }>;
    };
}

export default function Dashboard() {
    const { auth } = usePage<AuthProps>().props;

    function handleLogout() {
        router.post('/logout');
    }

    return (
        <>
            <Head title="Dashboard" />
            <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
                <nav className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between h-16 items-center">
                            <h1 className="text-lg font-semibold text-gray-900 dark:text-white">
                                Gestão Pública Lab
                            </h1>
                            <div className="flex items-center gap-4">
                                <span className="text-sm text-gray-600 dark:text-gray-400">
                                    {auth.user?.name}
                                </span>
                                <button
                                    onClick={handleLogout}
                                    className="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                >
                                    Sair
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>

                <main className="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                    {auth.tenant ? (
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                                {auth.tenant.name}
                            </h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Tenant ativo: {auth.tenant.slug}
                            </p>
                        </div>
                    ) : auth.tenants.length > 1 ? (
                        <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6">
                            <h2 className="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
                                Seleção de organização necessária
                            </h2>
                            <p className="text-sm text-yellow-700 dark:text-yellow-300">
                                Você pertence a {auth.tenants.length} organizações.
                                A seleção de tenant será implementada em etapa futura.
                            </p>
                        </div>
                    ) : (
                        <div className="bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                            <h2 className="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                Sem organização vinculada
                            </h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Sua conta não está vinculada a nenhuma organização ativa.
                            </p>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
