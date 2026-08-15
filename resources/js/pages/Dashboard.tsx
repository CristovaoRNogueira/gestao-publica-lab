import { Head, usePage } from '@inertiajs/react';
import { AppLayout } from '../layouts/AppLayout';
import { EmptyState } from '../components/ui/EmptyState';

interface AuthProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
        tenant: { id: number; name: string; slug: string } | null;
        tenants: Array<{ id: number; name: string; slug: string }>;
        capabilities: string[];
    };
}

export default function Dashboard() {
    const { auth } = usePage<AuthProps>().props;

    return (
        <>
            <Head title="Dashboard" />

            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                </div>

                {auth.tenant ? (
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Bem-vindo, {auth.user?.name}
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Você está logado na organização <span className="font-medium text-gray-900 dark:text-white">{auth.tenant.name}</span>.
                        </p>
                    </div>
                ) : (
                    <EmptyState
                        title="Nenhuma organização selecionada"
                        description="Selecione uma organização no menu superior para começar."
                        icon={
                            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        }
                    />
                )}
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
