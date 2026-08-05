import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center rounded-md bg-white p-1">
                <AppLogoIcon className="h-7" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-semibold">
                    Management Pengadaan
                </span>
                <span className="truncate text-[0.6875rem] leading-tight font-medium tracking-[0.06em] text-sidebar-foreground/60 uppercase">
                    UP Kendari
                </span>
            </div>
        </>
    );
}
