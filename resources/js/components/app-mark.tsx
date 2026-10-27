import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * The SIM-SCM mark (document, shield and swoosh) without its lettering, for
 * small square places such as the sidebar. The source is landscape, so it is
 * fitted with object-contain and sized by width.
 */
export default function AppMark({
    className,
    alt = 'Logo SIM-SCM',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/logo/app-logo-mark.png"
            alt={alt}
            className={cn('h-auto w-full object-contain', className)}
            {...props}
        />
    );
}

/**
 * The full SIM-SCM UP Kendari logo, lettering included. The source is
 * landscape, so it is sized by height.
 */
export function AppLogoFull({
    className,
    alt = 'SIM-SCM UP Kendari',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/logo/app-logo-clean.png"
            alt={alt}
            className={cn('h-full w-auto object-contain', className)}
            {...props}
        />
    );
}
