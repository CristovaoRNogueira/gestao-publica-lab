import { Head, useForm, Link } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Toast } from '@/Components/ui/Toast';
import { translateAuthError, translateAuthStatus } from '@/utils/translations';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <>
            <Head title="Recuperar Senha" />
            {status && <Toast message={translateAuthStatus(status)} type="success" />}
            <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
                <div className="w-full max-w-md p-8 bg-white dark:bg-gray-800 rounded-lg shadow-md">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-6 text-center">
                        Recuperar Senha
                    </h1>

                    <div className="mb-4 text-sm text-gray-600 dark:text-gray-400 text-center">
                        Esqueceu sua senha? Sem problemas. Apenas nos informe seu endereço de e-mail e enviaremos um link de redefinição de senha que permitirá escolher uma nova.
                    </div>

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
                                className="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                                autoFocus
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-red-600 dark:text-red-400">{translateAuthError(errors.email)}</p>
                            )}
                        </div>

                        <div className="pt-2 flex items-center justify-between">
                            <Link href="/login" className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                Voltar ao Login
                            </Link>

                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex justify-center py-2 px-4 rounded-md bg-blue-600 text-white font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                {processing ? 'Enviando...' : 'Enviar Link'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
