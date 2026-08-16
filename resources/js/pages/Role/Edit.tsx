import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
import { FormEvent } from 'react';

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
}

interface PageProps {
    role: Role;
}

export default function Edit({ role }: PageProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name,
        description: role.description || '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/roles/${role.id}`);
    };

    return (
        <>
            <Head title="Editar Função" />
            <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="md:grid md:grid-cols-3 md:gap-6">
                    <div className="md:col-span-1">
                        <div className="px-4 sm:px-0">
                            <h3 className="text-lg font-medium leading-6 text-gray-900">Editar Função</h3>
                            <p className="mt-1 text-sm text-gray-600">
                                O slug ({role.slug}) é imutável após a criação.
                            </p>
                        </div>
                    </div>
                    <div className="mt-5 md:mt-0 md:col-span-2">
                        <form onSubmit={handleSubmit}>
                            <div className="shadow sm:rounded-md sm:overflow-hidden">
                                <div className="px-4 py-5 bg-white space-y-6 sm:p-6">
                                    <div>
                                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">Nome</label>
                                        <input
                                            type="text"
                                            id="name"
                                            value={data.name}
                                            onChange={e => setData('name', e.target.value)}
                                            className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        />
                                        {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                                    </div>
                                    <div>
                                        <label htmlFor="description" className="block text-sm font-medium text-gray-700">Descrição</label>
                                        <textarea
                                            id="description"
                                            rows={3}
                                            value={data.description}
                                            onChange={e => setData('description', e.target.value)}
                                            className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        />
                                        {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                                    </div>
                                </div>
                                <div className="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                    <Link
                                        href="/roles"
                                        className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none mr-4"
                                    >
                                        Cancelar
                                    </Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none"
                                    >
                                        Salvar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

Edit.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
