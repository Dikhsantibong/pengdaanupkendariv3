<?php

namespace App\Enums;

/**
 * The rights an administrator can hand to a role on the "Hak Akses" screen.
 *
 * Each right replaces what used to be a fixed role check. Its default roles
 * reproduce that fixed behaviour exactly, so nothing changes for anyone until
 * an administrator changes a setting. The administrator always holds every
 * right and cannot lose one, so the screen can never lock everybody out.
 *
 * Managing users and these rights is deliberately absent: it stays with the
 * administrator alone, since anyone holding it could raise their own role.
 *
 * Rights that depend on being the assigned PIC — ticking checklists,
 * generating documents, submitting planning — are not listed: they follow the
 * assignment, not the role.
 */
enum Permission: string
{
    case CreateProcurement = 'procurement.create';
    case UpdateProcurement = 'procurement.update';
    case DeleteProcurement = 'procurement.delete';
    case AssignPic = 'procurement.assign-pic';
    case ReviewPlanning = 'procurement.review-planning';
    case CompleteProcurement = 'procurement.complete';
    case ViewAllProcurements = 'procurement.view-all';
    case ManageVendorAssessments = 'vendor-assessments.manage';
    case ManageMasterData = 'master-data.manage';
    case MenuPlanning = 'menu.planning';
    case MenuExecution = 'menu.execution';
    case MenuApprovals = 'menu.approvals';
    case MenuDocuments = 'menu.documents';
    case MenuMonitoring = 'menu.monitoring';
    case MenuReports = 'menu.reports';
    case MenuPublicMonitoring = 'menu.public-monitoring';

    /**
     * The name shown on the access screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::CreateProcurement => 'Buat perencanaan pengadaan',
            self::UpdateProcurement => 'Ubah data pengadaan',
            self::DeleteProcurement => 'Arsipkan pengadaan',
            self::AssignPic => 'Tunjuk PIC perencana & pelaksana',
            self::ReviewPlanning => 'Setujui / tolak perencanaan',
            self::CompleteProcurement => 'Nyatakan pengadaan selesai',
            self::ViewAllProcurements => 'Lihat seluruh pengadaan',
            self::ManageVendorAssessments => 'Kelola penilaian penyedia',
            self::ManageMasterData => 'Kelola data master',
            self::MenuPlanning => 'Menu Perencanaan',
            self::MenuExecution => 'Menu Pelaksanaan',
            self::MenuApprovals => 'Menu Approval',
            self::MenuDocuments => 'Menu Arsip Dokumen',
            self::MenuMonitoring => 'Menu Monitoring',
            self::MenuReports => 'Menu Laporan',
            self::MenuPublicMonitoring => 'Menu Monitoring Publik',
        };
    }

    /**
     * What the right allows, in one sentence.
     */
    public function description(): string
    {
        return match ($this) {
            self::CreateProcurement => 'Membuka menu Buat Perencanaan Pengadaan dan mendaftarkan pengadaan baru.',
            self::UpdateProcurement => 'Mengubah identitas dan usulan pekerjaan pada pengadaan yang dapat dilihat.',
            self::DeleteProcurement => 'Mengarsipkan pengadaan yang dapat dilihat.',
            self::AssignPic => 'Membuka menu Penunjukan PIC dan mengganti PIC pada pengadaan.',
            self::ReviewPlanning => 'Menyetujui, menolak, atau membuka kembali penolakan perencanaan. Tidak berlaku untuk perencanaan yang diajukan oleh dirinya sendiri.',
            self::CompleteProcurement => 'Menutup pengadaan yang sudah selesai.',
            self::ViewAllProcurements => 'Melihat seluruh pengadaan, bukan hanya yang ditugaskan atau dibuat sendiri.',
            self::ManageVendorAssessments => 'Membuat, mengisi, dan mengirim formulir penilaian kinerja penyedia.',
            self::ManageMasterData => 'Mengubah seluruh data master.',
            self::MenuPlanning => 'Menampilkan dan membuka papan Perencanaan.',
            self::MenuExecution => 'Menampilkan dan membuka papan Pelaksanaan.',
            self::MenuApprovals => 'Menampilkan dan membuka antrean Approval.',
            self::MenuDocuments => 'Menampilkan dan membuka Arsip Dokumen.',
            self::MenuMonitoring => 'Menampilkan dan membuka Monitoring.',
            self::MenuReports => 'Menampilkan dan membuka Laporan, termasuk ekspor.',
            self::MenuPublicMonitoring => 'Menampilkan tautan Monitoring Publik di menu. Halaman publiknya sendiri tetap terbuka untuk umum.',
        };
    }

    /**
     * The heading the right is listed under.
     */
    public function group(): string
    {
        return match ($this) {
            self::CreateProcurement,
            self::UpdateProcurement,
            self::DeleteProcurement,
            self::AssignPic,
            self::ReviewPlanning,
            self::CompleteProcurement,
            self::ViewAllProcurements => 'Pengadaan',
            self::ManageVendorAssessments => 'Penilaian Penyedia',
            self::ManageMasterData => 'Administrasi',
            self::MenuPlanning,
            self::MenuExecution,
            self::MenuApprovals,
            self::MenuDocuments,
            self::MenuMonitoring,
            self::MenuReports,
            self::MenuPublicMonitoring => 'Akses Menu',
        };
    }

    /**
     * The roles holding this right before anybody changes a setting.
     *
     * These mirror the fixed checks the rights replace. The administrator is
     * not listed: it always holds every right.
     *
     * @return array<int, UserRole>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::CreateProcurement,
            self::UpdateProcurement,
            self::DeleteProcurement,
            self::AssignPic,
            self::ReviewPlanning,
            self::CompleteProcurement,
            self::ViewAllProcurements => [UserRole::TeamLeader],
            self::ManageVendorAssessments,
            self::ManageMasterData => [],
            // Every menu was open to everyone before it became a right.
            self::MenuPlanning,
            self::MenuExecution,
            self::MenuApprovals,
            self::MenuDocuments,
            self::MenuMonitoring,
            self::MenuReports,
            self::MenuPublicMonitoring => [UserRole::TeamLeader, UserRole::PicPerencana, UserRole::PicPelaksana],
        };
    }
}
