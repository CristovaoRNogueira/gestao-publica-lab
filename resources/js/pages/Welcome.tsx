import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />
            <div className="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50 min-h-screen flex flex-col items-center justify-center">
                <div className="w-full max-w-2xl px-6 lg:max-w-7xl">
                    <main className="mt-6 flex flex-col gap-6 lg:mt-8 items-center">
                        <h1 className="text-4xl font-bold">Welcome to Gestão Pública Lab</h1>
                        <p className="text-lg">Laravel 13, React 19, Inertia 3, and Tailwind 4.</p>
                        <p className="text-lg">Desenvolvido por Cristovão Rodrigues</p>
                    </main>
                </div>
            </div>
        </>
    );
}
