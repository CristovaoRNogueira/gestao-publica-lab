import { ReactNode, useState, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import { PlatformSidebar } from './PlatformSidebar';
import { Dropdown } from '../components/ui/Dropdown';
import { Toast } from '../components/ui/Toast';
import { ThemeToggle } from '../components/ui/ThemeToggle';

interface AuthProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
        platform?: {
            capabilities: string[];
        };
    };
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}

interface PlatformLayoutProps {
    children: ReactNode;
}

export function PlatformLayout({ children }: PlatformLayoutProps) {
    const { auth, flash } = usePage<AuthProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    useEffect(() => {
        setSidebarOpen(false);
    }, [window.location.pathname]);

    function handleLogout() {
        router.post('/logout');
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
            <PlatformSidebar
                isOpen={sidebarOpen}
                setIsOpen={setSidebarOpen}
                capabilities={auth?.platform?.capabilities || []}
                currentPath={window.location.pathname}
            />

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col min-w-0">
                {/* Header */}
                <header className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-sm">
                    <div className="flex items-center">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-md"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <h1 className="ml-4 text-xl font-semibold text-gray-800 dark:text-white hidden sm:block">
                            Platform Administration
                        </h1>
                    </div>

                    <div className="flex items-center space-x-4">
                        {/* Theme Toggle */}
                        <ThemeToggle />

                        {/* User Menu */}
                        {auth?.user && (
                            <Dropdown
                                trigger={
                                    <div className="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-600 text-white font-semibold text-sm shadow-sm cursor-pointer">
                                        {auth.user.name.charAt(0).toUpperCase()}
                                    </div>
                                }
                            >
                                <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{auth.user.name}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{auth.user.email}</p>
                                    <span className="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                        Platform Admin
                                    </span>
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
