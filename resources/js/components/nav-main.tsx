import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
            {groups.map((group) => {
                const isGroupActive = group.items.some(item => isActive(item));

                if (group.title === '') {
                    return (
                        <SidebarGroup key={group.title || 'no-title'} className="px-2 py-0">
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
                    );
                }

                return (
                    <Collapsible
                        key={group.title}
                        asChild
                        defaultOpen={isGroupActive || group.title === 'Pengadaan'}
                        className="group/collapsible"
                    >
                        <SidebarGroup className="px-2 py-0">
                            <SidebarGroupLabel asChild className="text-[0.6875rem] font-semibold tracking-[0.08em] text-sidebar-foreground/50 uppercase cursor-pointer hover:bg-sidebar-accent hover:text-sidebar-foreground transition-colors group-data-[collapsible=icon]:opacity-0">
                                <CollapsibleTrigger>
                                    {group.title}
                                    <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
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
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                );
            })}
        </>
    );
}
