<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case TeamLeaderPengadaan = 'team_leader_pengadaan';
    case TeamLeaderIcc = 'team_leader_icc';
    case PicPerencana = 'pic_perencana';
    case PicPelaksana = 'pic_pelaksana';
    case DireksiPekerjaan = 'direksi_pekerjaan';
    case Pengusul = 'pengusul';

    /**
     * Get the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::TeamLeaderPengadaan => 'Team Leader Pengadaan',
            self::TeamLeaderIcc => 'Team Leader ICC',
            self::PicPerencana => 'PIC Perencana',
            self::PicPelaksana => 'PIC Pelaksana',
            self::DireksiPekerjaan => 'Direksi Pekerjaan (Asman/TL)',
            self::Pengusul => 'Pengusul (Staf/TL Bidang)',
        };
    }

    /**
     * Determine whether the role may oversee every procurement in the system.
     */
    public function isSupervisor(): bool
    {
        return in_array($this, [self::Administrator, self::TeamLeaderPengadaan, self::TeamLeaderIcc], true);
    }

    /**
     * Get every role as a selectable option.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role): array => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }
}
