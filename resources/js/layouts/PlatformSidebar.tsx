import { Link } from '@inertiajs/react';

interface SidebarProps {
    isOpen: boolean;
    setIsOpen: (isOpen: boolean) => void;
    capabilities: string[];
    currentPath: string;
}

export function PlatformSidebar({ isOpen, setIsOpen, capabilities, currentPath }: SidebarProps) {
    const navItems = [
        {
            name: 'Tenants',
            href: '/platform/tenants',
            icon: (
                <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            ),
            show: capabilities.includes('tenants.view'),
        },
        {
            name: 'Usuários',
            href: '/platform/users',
            icon: (
                <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            ),
            show: capabilities.includes('users.view'),
        },
        {
            name: 'Voltar ao App',
            href: '/dashboard',
            icon: (
                <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            ),
            show: true,
        },
    ];

    return (
        <>
            {/* Mobile overlay */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-20 bg-black bg-opacity-50 lg:hidden"
                    onClick={() => setIsOpen(false)}
                ></div>
            )}

            {/* Sidebar container */}
            <div className={`
                fixed inset-y-0 left-0 z-30 w-64 bg-gray-900 border-r border-gray-800
                transform transition-transform duration-300 ease-in-out lg:static lg:translate-x-0
                ${isOpen ? 'translate-x-0' : '-translate-x-full'}
            `}>
                <div className="flex items-center justify-center h-16 border-b border-gray-800">
                    <span className="text-lg font-bold text-white tracking-wide">PLATFORM</span>
                </div>

                <div className="overflow-y-auto overflow-x-hidden flex-grow">
                    <nav className="px-4 py-4 space-y-2">
                        {navItems.map((item) => {
                            if (!item.show) return null;

                            const isActive = currentPath.startsWith(item.href) && item.href !== '/dashboard';

                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`
                                        flex items-center px-3 py-2.5 text-sm font-medium rounded-md group transition-colors
                                        ${isActive
                                            ? 'bg-indigo-600 text-white shadow-sm'
                                            : 'text-gray-300 hover:bg-gray-800 hover:text-white'}
                                    `}
                                >
                                    {item.icon}
                                    {item.name}
                                </Link>
                            );
                        })}
                    </nav>
                </div>
            </div>
        </>
    );
}
