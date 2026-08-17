import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';
import { translateAuthError } from '@/utils/translations';

export default function ResetPassword({ token, email }: { token: string, email: string }) {
    const { data, setData, post, processing, errors } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        return () => {
            setData('password', '');
            setData('password_confirmation', '');
        };
    }, []);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post('/reset-password');
    }

    return (
        <>
            <Head title="Redefinir Senha" />
            <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
                <div className="w-full max-w-md p-8 bg-white dark:bg-gray-800 rounded-lg shadow-md">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-6 text-center">
                        Criar Nova Senha
                    </h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 bg-gray-100 dark:bg-gray-600"
                                required
                                readOnly
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-red-600 dark:text-red-400">{translateAuthError(errors.email)}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Nova Senha
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                                autoFocus
                            />
                            {errors.password && (
                                <p className="mt-1 text-sm text-red-600 dark:text-red-400">{translateAuthError(errors.password)}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Confirmar Nova Senha
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>

                        <div className="pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full flex justify-center py-2 px-4 rounded-md bg-blue-600 text-white font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                {processing ? 'Salvando...' : 'Redefinir Senha'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
