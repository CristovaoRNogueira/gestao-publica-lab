import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Secretaria {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
}

interface PageProps {
    secretarias: Secretaria[];
    auth: {
        tenant: { id: number; name: string; slug: string } | null;
    };
}

export default function Index({ secretarias, auth }: PageProps) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingSecretaria, setEditingSecretaria] = useState<Secretaria | null>(null);

    const [form, setForm] = useState({
        name: '',
        description: '',
        is_active: true,
    });

    const [errors, setErrors] = useState<Record<string, string>>({});

    function openCreateModal() {
        setEditingSecretaria(null);
        setForm({ name: '', description: '', is_active: true });
        setErrors({});
        setIsModalOpen(true);
    }

    function openEditModal(secretaria: Secretaria) {
        setEditingSecretaria(secretaria);
        setForm({
            name: secretaria.name,
            description: secretaria.description || '',
            is_active: secretaria.is_active,
        });
        setErrors({});
        setIsModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        const payload = {
            name: form.name,
            description: form.description,
            is_active: form.is_active,
        };

        if (editingSecretaria) {
            router.put(`/secretarias/${editingSecretaria.id}`, payload, {
                onError: (err) => setErrors(err),
                onSuccess: () => setIsModalOpen(false),
            });
        } else {
            router.post('/secretarias', payload, {
                onError: (err) => setErrors(err),
                onSuccess: () => setIsModalOpen(false),
            });
        }
    }

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 p-8">
            <Head title="Secretarias" />

            <div className="max-w-7xl mx-auto">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Secretarias ({auth.tenant?.name})
                    </h1>
                    <button
                        onClick={openCreateModal}
                        className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        Nova Secretaria
                    </button>
                </div>

                <div className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Slug</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            {secretarias.map((sec) => (
                                <tr key={sec.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{sec.name}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{sec.slug}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${sec.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {sec.is_active ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onClick={() => openEditModal(sec)} className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {secretarias.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Nenhuma secretaria encontrada.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {isModalOpen && (
                <div className="fixed inset-0 z-10 overflow-y-auto">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 transition-opacity" aria-hidden="true" onClick={() => setIsModalOpen(false)}>
                            <div className="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>

                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div className="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form onSubmit={submit}>
                                <div className="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                        {editingSecretaria ? 'Editar Secretaria' : 'Nova Secretaria'}
                                    </h3>

                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                                        <input
                                            type="text"
                                            value={form.name}
                                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                                            className="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                        />
                                        {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                                    </div>

                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Descrição</label>
                                        <textarea
                                            value={form.description}
                                            onChange={(e) => setForm({ ...form, description: e.target.value })}
                                            className="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                        ></textarea>
                                        {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                                    </div>

                                    <div className="mb-4 flex items-center">
                                        <input
                                            id="is_active"
                                            type="checkbox"
                                            checked={form.is_active}
                                            onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                            Ativo
                                        </label>
                                    </div>
                                </div>
                                <div className="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <button
                                        type="submit"
                                        className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                                    >
                                        Salvar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setIsModalOpen(false)}
                                        className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
