import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '../../layouts/AppLayout';
import { FormEvent } from 'react';

interface Role {
    id: number;
    name: string;
}

interface PageProps {
    roles: Role[];
}

export default function Create({ roles }: PageProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        role_id: ''
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/invitations', {
            onSuccess: () => {
                // Limpar formulário ou redirecionar. O controller atual retorna back()
                setData('email', '');
                setData('role_id', '');
            }
        });
    };

    return (
        <>
            <Head title="Novo Convite" />

            <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="md:grid md:grid-cols-3 md:gap-6">
                    <div className="md:col-span-1">
                        <div className="px-4 sm:px-0">
                            <h3 className="text-lg font-medium leading-6 text-gray-900 dark:text-white">Enviar Convite</h3>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Convide um novo membro para o tenant atribuindo-lhe um papel.
                            </p>
                        </div>
                    </div>
                    <div className="mt-5 md:mt-0 md:col-span-2">
                        <form onSubmit={handleSubmit}>
                            <div className="shadow sm:rounded-md sm:overflow-hidden">
                                <div className="px-4 py-5 bg-white dark:bg-gray-800 space-y-6 sm:p-6">
                                    <div>
                                        <label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            E-mail
                                        </label>
                                        <div className="mt-1">
                                            <input
                                                type="email"
                                                id="email"
                                                value={data.email}
                                                onChange={e => setData('email', e.target.value)}
                                                className="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                placeholder="exemplo@email.com"
                                                required
                                            />
                                        </div>
                                        {errors.email && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.email}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="role_id" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Papel
                                        </label>
                                        <div className="mt-1">
                                            <select
                                                id="role_id"
                                                value={data.role_id}
                                                onChange={e => setData('role_id', e.target.value)}
                                                className="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                required
                                            >
                                                <option value="" disabled>Selecione um papel</option>
                                                {roles.map((role) => (
                                                    <option key={role.id} value={role.id}>
                                                        {role.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        {errors.role_id && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.role_id}</p>}
                                    </div>
                                </div>
                                <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 text-right sm:px-6">
                                    <Link
                                        href="/invitations"
                                        className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-4 dark:bg-gray-600 dark:text-white dark:hover:bg-gray-500"
                                    >
                                        Voltar
                                    </Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                    >
                                        Enviar Convite
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

Create.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
