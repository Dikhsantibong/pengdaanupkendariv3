import { Link, usePage } from '@inertiajs/react';
import {
    Archive,
    BadgeCheck,
    Building2,
    CalendarClock,
    ClipboardCheck,
    ClipboardList,
    FileSignature,
    FileStack,
    FileText,
    Gauge,
    Hash,
    KeyRound,
    LayoutDashboard,
    ListChecks,
    MonitorPlay,
    Play,
    Settings2,
    ShieldCheck,
    UserCog,
    UserSquare2,
    Wallet,
    Workflow,
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import accessRights from '@/routes/access-rights';
import approvals from '@/routes/approvals';
import documents from '@/routes/documents';
import execution from '@/routes/execution';
import masterData from '@/routes/master-data';
import monitoring from '@/routes/monitoring';
import picAssignments from '@/routes/pic-assignments';
import planning from '@/routes/planning';
import procurements from '@/routes/procurements';
import publicMonitoring from '@/routes/public-monitoring';
import reports from '@/routes/reports';
import users from '@/routes/users';
import vendorAssessments from '@/routes/vendor-assessments';
import type { Auth, NavGroup } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { permissions } = auth;

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
                ...(permissions.createProcurement
                    ? [
                          {
                              title: 'Buat Perencanaan Pengadaan',
                              href: procurements.create(),
                              icon: FileSignature,
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
                {
                    title: 'Perencanaan',
                    href: planning.index(),
                    icon: CalendarClock,
                },
                {
                    title: 'Pelaksanaan',
                    href: execution.index(),
                    icon: Play,
                },
                {
                    title: 'Approval',
                    href: approvals.index(),
                    icon: BadgeCheck,
                },
                {
                    title: 'Arsip Dokumen',
                    href: documents.index(),
                    icon: Archive,
                },
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
            title: 'Pengawasan',
            items: [
                {
                    title: 'Monitoring',
                    href: monitoring.index(),
                    icon: Gauge,
                },
                {
                    title: 'Laporan',
                    href: reports.index(),
                    icon: FileText,
                },
                {
                    title: 'Monitoring Publik',
                    href: publicMonitoring.planning(),
                    icon: MonitorPlay,
                },
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
                              title: 'Direksi Pekerjaan',
                              href: masterData.workDirectors.index(),
                              icon: ShieldCheck,
                          },
                          {
                              title: 'Unit Tujuan',
                              href: masterData.targetUnits.index(),
                              icon: Building2,
                          },
                          {
                              title: 'Metode Pengadaan',
                              href: masterData.procurementMethods.index(),
                              icon: Workflow,
                          },
                          {
                              title: 'Sumber Anggaran',
                              href: masterData.budgetSources.index(),
                              icon: Wallet,
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
                              title: 'Jenis Dokumen',
                              href: masterData.documentTypes.index(),
                              icon: FileStack,
                          },
                          {
                              title: 'Template Dokumen',
                              href: masterData.documentTemplates.index(),
                              icon: FileText,
                          },
                          {
                              title: 'Aspek Penilaian',
                              href: masterData.assessmentAspects.index(),
                              icon: ClipboardList,
                          },
                          {
                              title: 'Lembar Penilai',
                              href: masterData.assessmentForms.index(),
                              icon: ClipboardCheck,
                          },
                      ],
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
