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
    case ProposeWork = 'procurement.propose';
    case ApproveProposal = 'procurement.approve-proposal';
    case UpdateProcurement = 'procurement.update';
    case DeleteProcurement = 'procurement.delete';
    case AssignPlanner = 'procurement.assign-planner';
    /**
     * Appointing the PIC Pelaksana. It keeps the value of the single
     * "assign PIC" right it grew out of, so the roles already holding it
     * keep it without anything being rewritten.
     */
    case AssignExecutor = 'procurement.assign-pic';
    case ReviewPlanning = 'procurement.review-planning';
    case ReviewExecution = 'procurement.review-execution';
    case ReviewHandover = 'procurement.review-handover';
    case CompleteProcurement = 'procurement.complete';
    case ProcessPayment = 'procurement.process-payment';
    case ViewAllProcurements = 'procurement.view-all';
    case ManageVendorAssessments = 'vendor-assessments.manage';
    case ManageMasterData = 'master-data.manage';
    case MenuProposals = 'menu.proposals';
    case MenuPlanning = 'menu.planning';
    case MenuExecution = 'menu.execution';
    case MenuHandover = 'menu.handover';
    /** The Pembayaran menu, once called Keuangan: the value is kept. */
    case MenuFinance = 'menu.finance';
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
            self::ProposeWork => 'Usulkan pekerjaan',
            self::ApproveProposal => 'Setujui usulan pekerjaan bidangnya',
            self::UpdateProcurement => 'Ubah data pengadaan',
            self::DeleteProcurement => 'Arsipkan pengadaan',
            self::AssignPlanner => 'Tunjuk PIC perencana',
            self::AssignExecutor => 'Tunjuk PIC pelaksana',
            self::ReviewPlanning => 'Setujui / tolak perencanaan',
            self::ReviewExecution => 'Setujui / tolak pelaksanaan',
            self::ReviewHandover => 'Setujui / kembalikan penyerahan',
            self::CompleteProcurement => 'Nyatakan pengadaan selesai',
            self::ProcessPayment => 'Proses pembayaran (Keuangan)',
            self::ViewAllProcurements => 'Lihat seluruh pengadaan',
            self::ManageVendorAssessments => 'Kelola penilaian penyedia',
            self::ManageMasterData => 'Kelola data master',
            self::MenuProposals => 'Menu Usulan Pekerjaan',
            self::MenuPlanning => 'Menu Perencanaan',
            self::MenuExecution => 'Menu Pelaksanaan',
            self::MenuHandover => 'Menu Penyerahan',
            self::MenuFinance => 'Menu Pembayaran',
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
            self::ProposeWork => 'Mengajukan usulan pekerjaan untuk bidangnya. Usulan disetujui TL atasan langsung, lalu Direksi Pekerjaan (Asman) bidang tersebut, sebelum masuk perencanaan.',
            self::ApproveProposal => 'Meninjau lalu menyetujui atau mengembalikan usulan pekerjaan dari bidang yang dipimpinnya, serta melihat seluruh pengadaan bidang tersebut.',
            self::UpdateProcurement => 'Mengubah identitas dan usulan pekerjaan pada pengadaan yang dapat dilihat.',
            self::DeleteProcurement => 'Mengarsipkan pengadaan yang dapat dilihat.',
            self::AssignPlanner => 'Membuka menu Penunjukan PIC dan menunjuk atau mengganti PIC Perencana pada usulan yang sudah disetujui Asman.',
            self::AssignExecutor => 'Membuka menu Penunjukan PIC dan menunjuk atau mengganti PIC Pelaksana setelah perencanaan disetujui.',
            self::ReviewPlanning => 'Menyetujui, menolak, atau membuka kembali penolakan perencanaan. Tidak berlaku untuk perencanaan yang diajukan oleh dirinya sendiri.',
            self::ReviewExecution => 'Menyetujui atau menolak pelaksanaan yang diajukan PIC Pelaksana. Pelaksanaan yang disetujui diteruskan ke Penyerahan.',
            self::ReviewHandover => 'Menyetujui atau mengembalikan berkas penyerahan yang diajukan PIC Pelaksana. Penyerahan yang disetujui diteruskan ke Pembayaran (Keuangan).',
            self::CompleteProcurement => 'Menutup pengadaan yang sudah selesai.',
            self::ProcessPayment => 'Melengkapi berkas pembayaran pengadaan yang penyerahannya sudah disetujui, lalu mengajukannya ke TL atasan (TL Keuangan) dan Asman bidang (Asman Business Support). Persetujuan Asman mencatat pembayaran lunas dan menutup pengadaan.',
            self::ViewAllProcurements => 'Melihat seluruh pengadaan, bukan hanya yang ditugaskan atau dibuat sendiri.',
            self::ManageVendorAssessments => 'Membuat, mengisi, dan mengirim formulir penilaian kinerja penyedia.',
            self::ManageMasterData => 'Mengubah seluruh data master.',
            self::MenuProposals => 'Menampilkan dan membuka halaman Usulan Pekerjaan (usulan sendiri dan antrean persetujuan).',
            self::MenuPlanning => 'Menampilkan dan membuka papan Perencanaan.',
            self::MenuExecution => 'Menampilkan dan membuka papan Pelaksanaan.',
            self::MenuHandover => 'Menampilkan dan membuka papan Penyerahan (berkas penyerahan yang dikerjakan PIC Pelaksana).',
            self::MenuFinance => 'Menampilkan dan membuka papan Pembayaran (antrean pembayaran Keuangan).',
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
            self::ProposeWork,
            self::ApproveProposal,
            self::UpdateProcurement,
            self::DeleteProcurement,
            self::AssignPlanner,
            self::AssignExecutor,
            self::ReviewPlanning,
            self::ReviewExecution,
            self::ReviewHandover,
            self::CompleteProcurement,
            self::ProcessPayment,
            self::ViewAllProcurements => 'Pengadaan',
            self::ManageVendorAssessments => 'Penilaian Penyedia',
            self::ManageMasterData => 'Administrasi',
            self::MenuProposals,
            self::MenuPlanning,
            self::MenuExecution,
            self::MenuHandover,
            self::MenuFinance,
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
            self::AssignExecutor,
            self::ReviewExecution,
            self::ReviewHandover,
            self::CompleteProcurement => [UserRole::TeamLeaderPengadaan],
            self::AssignPlanner,
            self::ReviewPlanning => [UserRole::TeamLeaderIcc],
            // Keuangan pays; it does not propose work.
            self::ProposeWork => [UserRole::Pengusul, UserRole::DireksiPekerjaan],
            self::ProcessPayment => [UserRole::Keuangan],
            self::MenuFinance => [UserRole::Keuangan, UserRole::TeamLeaderPengadaan, UserRole::TeamLeaderIcc],
            self::ApproveProposal => [UserRole::DireksiPekerjaan],
            self::MenuHandover => [UserRole::TeamLeaderPengadaan, UserRole::TeamLeaderIcc, UserRole::PicPelaksana],
            self::MenuProposals => [
                UserRole::Pengusul,
                UserRole::DireksiPekerjaan,
                UserRole::TeamLeaderPengadaan,
                UserRole::TeamLeaderIcc,
            ],
            // Keuangan follows every job on to the payments it will make.
            self::ViewAllProcurements => [UserRole::TeamLeaderPengadaan, UserRole::TeamLeaderIcc, UserRole::Keuangan],
            self::ManageVendorAssessments,
            self::ManageMasterData => [],
            // Every menu was open to everyone before it became a right.
            self::MenuPlanning,
            self::MenuExecution,
            self::MenuApprovals,
            self::MenuDocuments,
            self::MenuMonitoring,
            self::MenuReports,
            self::MenuPublicMonitoring => [
                UserRole::TeamLeaderPengadaan,
                UserRole::TeamLeaderIcc,
                UserRole::PicPerencana,
                UserRole::PicPelaksana,
            ],
        };
    }
}
