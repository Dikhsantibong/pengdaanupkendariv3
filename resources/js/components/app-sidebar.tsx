import { Link, usePage } from '@inertiajs/react';
import {
    Archive,
    BadgeCheck,
    Building2,
    CalendarClock,
    ClipboardCheck,
    ClipboardList,
    FilePen,
    FileSignature,
    FileStack,
    Gauge,
    Handshake,
    Hash,
    Inbox,
    Landmark,
    PackageCheck,
    KeyRound,
    Layers,
    LayoutDashboard,
    ListChecks,
    MonitorPlay,
    Pin,
    PinOff,
    Play,
    Settings2,
    Tag,
    Truck,
    UserCheck,
    UserCog,
    UserSquare2,
    Wallet,
    Workflow,
    Wrench,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import accessRights from '@/routes/access-rights';
import approvals from '@/routes/approvals';
import budgetPlans from '@/routes/budget-plans';
import coaRequests from '@/routes/coa-requests';
import documents from '@/routes/documents';
import execution from '@/routes/execution';
import finance from '@/routes/finance';
import handover from '@/routes/handover';
import maintenance from '@/routes/maintenance';
import masterData from '@/routes/master-data';
import monitoring from '@/routes/monitoring';
import picAssignments from '@/routes/pic-assignments';
import planning from '@/routes/planning';
import procurements from '@/routes/procurements';
import proposals from '@/routes/proposals';
import publicMonitoring from '@/routes/public-monitoring';
import users from '@/routes/users';
import vendorAssessments from '@/routes/vendor-assessments';
import type { Auth, NavGroup } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { setOpen, isMobile, pinned, unpin } = useSidebar();
    const { permissions } = auth;
    const { menus } = permissions;

    const groups: NavGroup[] = [
        {
            title: '',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutDashboard,
                },
            ],
        },
        {
            title: 'Pengadaan',
            items: [
                // The PRK the proposers pick, kept by Anggaran.
                ...(menus['budget-plans']
                    ? [
                          {
                              title: 'Perencanaan Anggaran',
                              href: budgetPlans.index(),
                              icon: Wallet,
                          },
                      ]
                    : []),
                ...(menus['coa-requests']
                    ? [
                          {
                              title:
                                  permissions.manageCoaRequests &&
                                  !permissions.requestCoa
                                      ? 'Aktivasi COA'
                                      : 'Request COA',
                              href: coaRequests.index(),
                              icon: Tag,
                          },
                      ]
                    : []),
                ...(permissions.createProcurement
                    ? [
                          {
                              title: permissions.proposeOnly
                                  ? 'Usulkan Pekerjaan'
                                  : 'Buat Perencanaan Pengadaan',
                              href: procurements.create(),
                              icon: FileSignature,
                          },
                      ]
                    : []),
                ...(menus.proposals
                    ? [
                          {
                              title: 'Usulan Pekerjaan',
                              href: proposals.index(),
                              icon: Inbox,
                              matchNested: true,
                          },
                      ]
                    : []),
                {
                    title: 'Daftar Pengadaan',
                    href: procurements.index(),
                    icon: ClipboardList,
                    matchNested: true,
                },
                ...(permissions.assignPic
                    ? [
                          {
                              title: 'Penunjukan PIC',
                              href: picAssignments.index(),
                              icon: UserSquare2,
                          },
                      ]
                    : []),
                ...(menus.planning
                    ? [
                          {
                              title: 'Perencanaan',
                              href: planning.index(),
                              icon: CalendarClock,
                          },
                      ]
                    : []),
                ...(menus.execution
                    ? [
                          {
                              title: 'Pelaksanaan',
                              href: execution.index(),
                              icon: Play,
                          },
                      ]
                    : []),
                ...(menus.handover
                    ? [
                          {
                              title: 'Penyerahan',
                              href: handover.index(),
                              icon: PackageCheck,
                          },
                      ]
                    : []),
                ...(menus.finance
                    ? [
                          {
                              title: 'Pembayaran',
                              href: finance.index(),
                              icon: Landmark,
                          },
                      ]
                    : []),
                ...(menus.approvals
                    ? [
                          {
                              title: 'Approval',
                              href: approvals.index(),
                              icon: BadgeCheck,
                          },
                      ]
                    : []),
                ...(menus.documents
                    ? [
                          {
                              title: 'Arsip Dokumen',
                              href: documents.index(),
                              icon: Archive,
                          },
                      ]
                    : []),
                ...(permissions.manageVendorAssessments
                    ? [
                          {
                              title: 'Penilaian Penyedia',
                              href: vendorAssessments.index(),
                              icon: ClipboardCheck,
                          },
                      ]
                    : []),
            ],
        },
        {
            title: 'Monitoring',
            items: [
                ...(menus.monitoring
                    ? [
                          {
                              title: 'Monitoring Pengadaan',
                              href: monitoring.index(),
                              icon: Gauge,
                          },
                          {
                              title: 'Monitoring Amandemen',
                              href: monitoring.amendments(),
                              icon: FilePen,
                          },
                          {
                              title: 'Monitoring Delivery Order',
                              href: monitoring.deliveryOrders(),
                              icon: Truck,
                          },
                          {
                              title: 'Monitoring Kontrak',
                              href: monitoring.contracts(),
                              icon: FileSignature,
                          },
                      ]
                    : []),
                ...(menus['public-monitoring']
                    ? [
                          {
                              title: 'Monitoring Publik',
                              href: publicMonitoring.planning(),
                              icon: MonitorPlay,
                          },
                      ]
                    : []),
            ],
        },
        ...(permissions.manageUsers
            ? [
                  {
                      title: 'Administrasi',
                      items: [
                          {
                              title: 'Pengguna',
                              href: users.index(),
                              icon: UserCog,
                          },
                          {
                              title: 'Hak Akses',
                              href: accessRights.index(),
                              icon: KeyRound,
                          },
                          {
                              title: 'Mode Pemeliharaan',
                              href: maintenance.edit(),
                              icon: Wrench,
                          },
                      ],
                  },
              ]
            : []),
        ...(permissions.manageMasterData
            ? [
                  {
                      title: 'Data Master',
                      items: [
                          {
                              title: 'Pejabat Unit & Direksi',
                              href: masterData.unitManagers.index(),
                              icon: UserCheck,
                          },
                          {
                              title: 'Mitra',
                              href: masterData.partners.index(),
                              icon: Handshake,
                          },
                          {
                              title: 'Unit Tujuan',
                              href: masterData.targetUnits.index(),
                              icon: Building2,
                          },
                          {
                              title: 'Metode Pengadaan',
                              href: masterData.selectionMethods.index(),
                              icon: Workflow,
                          },
                          {
                              title: 'Sumber Anggaran',
                              href: masterData.budgetSources.index(),
                              icon: Wallet,
                          },
                          {
                              title: 'Segmen COA',
                              href: masterData.coaSegments.index(),
                              icon: Layers,
                          },
                          {
                              title: 'Jenis Kontrak',
                              href: masterData.contractTypes.index(),
                              icon: FileSignature,
                          },
                          {
                              title: 'Format No Kontrak',
                              href: masterData.contractNumberFormats.index(),
                              icon: Hash,
                          },
                          {
                              title: 'Status Progres',
                              href: masterData.progressStatuses.index(),
                              icon: Settings2,
                          },
                          {
                              title: 'Item Checklist',
                              href: masterData.checklistItems.index(),
                              icon: ListChecks,
                          },
                          {
                              // Jenis dokumen, templatenya, dan tahapan
                              // yang memintanya, dalam satu layar.
                              title: 'Dokumen & Template',
                              href: masterData.documentTypes.index(),
                              icon: FileStack,
                          },
                          {
                              // Lembar penilai dan aspek yang dinilainya,
                              // dalam satu layar.
                              title: 'Aspek & Lembar Penilai',
                              href: masterData.assessmentForms.index(),
                              icon: ClipboardList,
                          },
                      ],
                  },
              ]
            : []),
    ];

    return (
        // Closed, it waits behind the "Portal Saya" tab.
        <Sidebar collapsible="offcanvas" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center gap-1">
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="min-w-0 flex-1"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        {!isMobile && (
                            // Pinned, it stays; unpinned, it hides behind the
                            // "Portal Saya" tab once the pointer leaves it.
                            <button
                                type="button"
                                onClick={() =>
                                    pinned ? unpin() : setOpen(true)
                                }
                                title={
                                    pinned
                                        ? 'Lepas pin: sidebar tersembunyi dan muncul lewat Portal Saya'
                                        : 'Sematkan sidebar agar selalu tampil'
                                }
                                aria-label={
                                    pinned
                                        ? 'Lepas pin sidebar'
                                        : 'Sematkan sidebar'
                                }
                                aria-pressed={pinned}
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-md hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                    pinned
                                        ? 'text-sidebar-foreground'
                                        : 'text-sidebar-foreground/60',
                                )}
                            >
                                {pinned ? (
                                    <Pin className="size-4 fill-current" />
                                ) : (
                                    <PinOff className="size-4" />
                                )}
                            </button>
                        )}
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                {/* A group whose every menu is switched off is not shown. */}
                <NavMain
                    groups={groups.filter((group) => group.items.length > 0)}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
