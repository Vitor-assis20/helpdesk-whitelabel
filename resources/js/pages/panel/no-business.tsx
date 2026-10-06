import { Head } from '@inertiajs/react';
import { Headset } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';

export default function NoBusiness() {
    return (
        <AppLayout>
            <Head title="Nenhuma equipe" />

            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                <Headset className="text-muted-foreground size-10" />
                <h1 className="text-xl font-semibold">Você ainda não faz parte de nenhuma equipe</h1>
                <p className="text-muted-foreground max-w-md">Peça ao responsável pela empresa para adicionar sua conta à equipe de atendimento.</p>
            </div>
        </AppLayout>
    );
}
