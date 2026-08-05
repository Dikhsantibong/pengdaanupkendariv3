import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup, NavItem } from '@/types';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const allItems = groups.flatMap((group) => group.items);

    // An exact match always wins, so a child route such as /procurements/create
    // never lights up the parent "Daftar Pengadaan" entry as well.
    const exactMatch = allItems.find((item) => isCurrentUrl(item.href));

    const isActive = (item: NavItem): boolean => {
        if (exactMatch) {
            return exactMatch === item;
        }

        return item.matchNested === true && isCurrentOrParentUrl(item.href);
    };

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-0">
                    {group.title !== '' && (
                        <SidebarGroupLabel className="text-[0.6875rem] font-semibold tracking-[0.08em] text-sidebar-foreground/50 uppercase">
                            {group.title}
                        </SidebarGroupLabel>
                    )}
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive(item)}
                                    tooltip={{ children: item.title }}
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
