import { ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: ReactNode;
    action?: ReactNode;
}

export function EmptyState({ title, description, icon, action }: EmptyStateProps) {
    return (
        <div className="text-center py-12 px-4 sm:px-6 lg:px-8 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
            {icon && (
                <div className="mx-auto flex h-12 w-12 items-center justify-center text-gray-400 mb-4">
                    {icon}
                </div>
            )}
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">{title}</h3>
            {description && (
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    {description}
                </p>
            )}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
