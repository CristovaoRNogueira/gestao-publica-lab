import { Head, useForm, Link, router } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
import { Button } from '../../components/ui/Button';
import { Toast } from '../../components/ui/Toast';
import { useState } from 'react';

interface OrganizationUnit {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    type: string;
    is_active: boolean;
}

interface PageProps {
    unit: OrganizationUnit;
    units: OrganizationUnit[];
}

export default function Edit({ unit, units }: PageProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: unit.name,
        type: unit.type,
        parent_id: unit.parent_id || '',
    });

    const [localToast, setLocalToast] = useState<{message: string, type: 'error'|'success'} | null>(null);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/organization-units/${unit.id}`);
    }

    return (
        <>
            <Head title={`Editar Unidade - ${unit.name}`} />

            {localToast && (
                <Toast
                    message={localToast.message}
                    type={localToast.type}
                    onClose={() => setLocalToast(null)}
                />
            )}

            <div className="max-w-2xl mx-auto">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Editar Unidade: {unit.name}
                    </h1>
                </div>

                <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <form onSubmit={submit}>
                        <div className="px-4 py-5 sm:p-6 space-y-6">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Nome da Unidade
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                />
                                {errors.name && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.name}</p>}
                            </div>

                            <div>
                                <label htmlFor="type" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Tipo / Sigla (Opcional)
                                </label>
                                <input
                                    id="type"
                                    type="text"
                                    placeholder="Ex: Secretaria, Departamento, Diretoria..."
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                />
                                {errors.type && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.type}</p>}
                            </div>

                            <div>
                                <label htmlFor="parent_id" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Unidade Pai (Mover na Hierarquia)
                                </label>
                                <select
                                    id="parent_id"
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value)}
                                    className="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="">-- Nenhuma (Mover para a Raiz) --</option>
                                    {units
                                        .filter(u => u.id !== unit.id) // Cannot be its own parent
                                        .map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name} ({u.type})
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Nota: Movimentar esta unidade moverá também todas as sub-unidades filhas. Não é possível mover para dentro de suas próprias filhas.
                                </p>
                                {errors.parent_id && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.parent_id}</p>}
                            </div>

                        </div>

                        <div className="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 flex justify-between items-center">
                            {/* Option to Delete */}
                            <Button
                                type="button"
                                variant="danger"
                                onClick={(e) => {
                                    if(confirm('Tem certeza que deseja excluir esta unidade?')) {
                                        e.preventDefault();
                                        window.axios.delete(`/organization-units/${unit.id}`, {
                                            headers: {
                                                'Accept': 'application/json'
                                            }
                                        }).then(() => {
                                            // Redireciona via Inertia após sucesso
                                            router.visit('/organization-units');
                                        }).catch(err => {
                                            if (err.response?.status === 409) {
                                                setLocalToast({ message: err.response.data.message, type: 'error' });
                                            } else {
                                                // Fallback para outros erros
                                                setLocalToast({ message: 'Ocorreu um erro ao tentar excluir a unidade.', type: 'error' });
                                            }
                                        });
                                    }
                                }}
                            >
                                Excluir Unidade
                            </Button>

                            <div className="flex space-x-3">
                                <Link href="/organization-units">
                                    <Button variant="secondary" type="button">Cancelar</Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    Salvar Alterações
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

Edit.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
