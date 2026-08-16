import { ReactNode, useState, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import { Sidebar } from '../components/Sidebar';
import { Dropdown } from '../components/ui/Dropdown';
import { Toast } from '../components/ui/Toast';
import { ThemeToggle } from '../components/ui/ThemeToggle';
import { PageProps } from '@inertiajs/core';

interface AuthProps extends PageProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
        tenant: { id: number; name: string; slug: string } | null;
        tenants: Array<{ id: number; name: string; slug: string }>;
        capabilities: string[];
    };
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}

interface AppLayoutProps {
    children: ReactNode;
}

export function AppLayout({ children }: AppLayoutProps) {
    const { auth, flash } = usePage<AuthProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    // Close sidebar on path change (for mobile)
    useEffect(() => {
        setSidebarOpen(false);
    }, [window.location.pathname]);

    function handleLogout() {
        router.post('/logout');
    }

    function handleTenantSelect(tenantId: number) {
        router.post('/tenant/select', { tenant_id: tenantId });
    }

    const flashMessages = [];
    if (flash?.success) flashMessages.push({ message: flash.success, type: 'success' as const });
    if (flash?.error) flashMessages.push({ message: flash.error, type: 'error' as const });
    if (flash?.warning) flashMessages.push({ message: flash.warning, type: 'warning' as const });
    if (flash?.info) flashMessages.push({ message: flash.info, type: 'info' as const });

    return (
        <div className="flex-1 flex overflow-hidden">
            {/* Flash Messages */}
            <div className="fixed top-4 right-4 z-50 flex flex-col gap-2">
                {flashMessages.map((msg, idx) => (
                    <Toast key={idx} message={msg.message} type={msg.type} />
                ))}
            </div>

            {/* Sidebar */}
            <Sidebar
                isOpen={sidebarOpen}
                setIsOpen={setSidebarOpen}
                capabilities={auth?.capabilities || []}
                currentPath={window.location.pathname}
            />

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col min-w-0">
                {/* Header */}
                <header className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-md"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>

                    <div className="flex items-center space-x-4">
                        {/* Tenant Switcher */}
                        {auth?.user && auth?.tenants?.length > 0 && (
                            <Dropdown
                                trigger={
                                    <div className="flex items-center text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <span className="max-w-[120px] truncate">{auth.tenant ? auth.tenant.name : 'Selecione Organização'}</span>
                                        <svg className="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                }
                            >
                                <div className="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Organizações
                                </div>
                                {auth.tenants.map(t => (
                                    <button
                                        key={t.id}
                                        onClick={() => handleTenantSelect(t.id)}
                                        className={`w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 ${auth.tenant?.id === t.id ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 font-medium' : 'text-gray-700 dark:text-gray-300'}`}
                                    >
                                        <div className="truncate">{t.name}</div>
                                    </button>
                                ))}
                            </Dropdown>
                        )}

                        {/* Theme Toggle */}
                        <ThemeToggle />

                        {/* User Menu */}
                        {auth?.user && (
                            <Dropdown
                                trigger={
                                    <div className="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-semibold text-sm">
                                        {auth.user.name.charAt(0).toUpperCase()}
                                    </div>
                                }
                            >
                                <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{auth.user.name}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{auth.user.email}</p>
                                </div>
                                <button
                                    onClick={handleLogout}
                                    className="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                >
                                    Sair
                                </button>
                            </Dropdown>
                        )}
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
