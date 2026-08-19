import { Head, Link, usePage } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
import { EmptyState } from '../../components/ui/EmptyState';
import { Button } from '../../components/ui/Button';

interface OrganizationUnit {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    type: string;
    is_active: boolean;
}

interface PageProps {
    units: OrganizationUnit[];
    auth: {
        tenant: { id: number; name: string; slug: string } | null;
        capabilities: string[];
    };
}

export default function Index({ units, auth }: PageProps) {
    const canCreate = auth.capabilities.includes('organization_units.create');
    const canUpdate = auth.capabilities.includes('organization_units.update');
    const canDelete = auth.capabilities.includes('organization_units.delete');

    // Build tree
    const unitMap = new Map<number, OrganizationUnit & { children: any[] }>();
    units.forEach(u => unitMap.set(u.id, { ...u, children: [] }));

    const roots: any[] = [];
    unitMap.forEach(u => {
        if (u.parent_id === null || !unitMap.has(u.parent_id)) {
            roots.push(u);
        } else {
            unitMap.get(u.parent_id)?.children.push(u);
        }
    });

    const renderTree = (nodes: any[], depth = 0) => {
        return nodes.map(node => (
            <div key={node.id} className="border-b border-gray-200 dark:border-gray-700 last:border-0">
                <div
                    className="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                    style={{ paddingLeft: `${(depth * 2) + 1.5}rem` }}
                >
                    <div className="flex items-center space-x-3">
                        {depth > 0 && (
                            <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                            </svg>
                        )}
                        <div>
                            <span className="text-sm font-medium text-gray-900 dark:text-white">{node.name}</span>
                            <span className="ml-2 text-xs text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600 rounded px-1.5 py-0.5">
                                {node.type || 'Unidade'}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center space-x-4">
                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${node.is_active ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'}`}>
                            {node.is_active ? 'Ativo' : 'Inativo'}
                        </span>
                        {canUpdate && (
                            <Link href={`/organization-units/${node.id}/edit`} className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                                Editar
                            </Link>
                        )}
                    </div>
                </div>
                {node.children.length > 0 && (
                    <div className="bg-gray-50/50 dark:bg-gray-800/50">
                        {renderTree(node.children, depth + 1)}
                    </div>
                )}
            </div>
        ));
    };

    return (
        <>
            <Head title="Unidades" />

            <div className="max-w-7xl mx-auto">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Unidades Organizacionais ({auth.tenant?.name})
                    </h1>
                    {canCreate && (
                        <Link href="/organization-units/create">
                            <Button>Nova Unidade</Button>
                        </Link>
                    )}
                </div>

                {units.length > 0 ? (
                    <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div className="flex bg-gray-50 dark:bg-gray-700 px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                            <div className="flex-1 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Estrutura</div>
                            <div className="w-32 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</div>
                        </div>
                        <div className="divide-y divide-gray-200 dark:divide-gray-700">
                            {renderTree(roots)}
                        </div>
                    </div>
                ) : (
                    <EmptyState
                        title="Nenhuma unidade encontrada"
                        description="Comece criando a primeira unidade da organização."
                        icon={
                            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        }
                        action={
                            canCreate ? (
                                <Link href="/organization-units/create">
                                    <Button>Criar Unidade</Button>
                                </Link>
                            ) : undefined
                        }
                    />
                )}
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
