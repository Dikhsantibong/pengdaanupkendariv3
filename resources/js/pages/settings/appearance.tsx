import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

/*
 * Theme switching has been disabled. The application is fixed to the PLN
 * light-blue-and-white palette, so this page no longer offers a toggle; it only
 * explains why. The route is kept so an old bookmark does not 404.
 */
export default function Appearance() {
    return (
        <>
            <Head title="Tampilan" />

            <h1 className="sr-only">Tampilan</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Tampilan"
                    description="Tema aplikasi telah ditetapkan dan tidak dapat diubah."
                />

                <p className="rounded-md border border-border bg-muted/40 p-4 text-sm text-muted-foreground">
                    Seluruh halaman menggunakan tema resmi PLN dengan warna biru
                    muda dan putih. Pilihan tema gelap dan pengaturan tampilan
                    lain dinonaktifkan agar tampilan tetap konsisten di semua
                    perangkat.
                </p>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Tampilan',
            href: editAppearance(),
        },
    ],
};
