import { Head, useForm, Link } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
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
}

export default function Create({ units }: PageProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: '',
        parent_id: '' as string | number,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/organization-units');
    }

    return (
        <>
            <Head title="Nova Unidade" />

            <div className="max-w-2xl mx-auto">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Nova Unidade
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
                                    Unidade Pai (Hierarquia)
                                </label>
                                <select
                                    id="parent_id"
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value)}
                                    className="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="">-- Nenhuma (Raiz) --</option>
                                    {units.map((unit) => (
                                        <option key={unit.id} value={unit.id}>
                                            {unit.name} ({unit.type})
                                        </option>
                                    ))}
                                </select>
                                {errors.parent_id && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.parent_id}</p>}
                            </div>

                        </div>

                        <div className="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 flex justify-end space-x-3">
                            <Link href="/organization-units">
                                <Button variant="secondary" type="button">Cancelar</Button>
                            </Link>
                            <Button type="submit" disabled={processing}>
                                Salvar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

Create.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
