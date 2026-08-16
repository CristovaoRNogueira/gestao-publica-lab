import { Head, useForm } from '@inertiajs/react';
import { Button } from '../../components/ui/Button';

export default function Onboarding() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/tenants');
    };

    return (
        <>
            <Head title="Bem-vindo - Criar Organização" />

            <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
                <div className="sm:mx-auto sm:w-full sm:max-w-md">
                    <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                        Bem-vindo ao Gestão Pública Lab
                    </h2>
                    <p className="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                        Para começar, crie sua primeira organização.
                    </p>
                </div>

                <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                    <div className="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10">
                        <form className="space-y-6" onSubmit={submit}>
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Nome da Organização <span className="text-red-500">*</span>
                                </label>
                                <div className="mt-1">
                                    <input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                        placeholder="Ex: Prefeitura de Exemplo"
                                    />
                                    {errors.name && (
                                        <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.name}</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label htmlFor="slug" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Identificador (Opcional)
                                </label>
                                <div className="mt-1">
                                    <input
                                        id="slug"
                                        name="slug"
                                        type="text"
                                        value={data.slug}
                                        onChange={(e) => setData('slug', e.target.value)}
                                        className="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                        placeholder="Ex: pref-exemplo"
                                    />
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Se deixado em branco, será gerado automaticamente a partir do nome.
                                    </p>
                                    {errors.slug && (
                                        <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errors.slug}</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Button type="submit" className="w-full justify-center" disabled={processing}>
                                    {processing ? 'Criando...' : 'Criar Organização'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
