import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * The official PLN mark. The source asset is portrait, so it is always fitted
 * with object-contain and sized by height.
 */
export default function AppLogoIcon({
    className,
    alt = 'Logo PLN',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/logo/icon.png"
            alt={alt}
            className={cn('h-full w-auto object-contain', className)}
            {...props}
        />
    );
}
