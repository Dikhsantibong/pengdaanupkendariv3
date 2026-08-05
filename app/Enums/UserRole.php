<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case TeamLeader = 'team_leader';
    case PicPerencana = 'pic_perencana';
    case PicPelaksana = 'pic_pelaksana';

    /**
     * Get the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::TeamLeader => 'TL Perencanaan',
            self::PicPerencana => 'PIC Perencana',
            self::PicPelaksana => 'PIC Pelaksana',
        };
    }

    /**
     * Determine whether the role may oversee every procurement in the system.
     */
    public function isSupervisor(): bool
    {
        return in_array($this, [self::Administrator, self::TeamLeader], true);
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
