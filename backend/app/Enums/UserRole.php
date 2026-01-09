<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case KADIS = 'KADIS';
    case TIM_PERENCANAAN = 'TIM_PERENCANAAN';
    case TIM_PELAKSANA = 'TIM_PELAKSANA';
    case BENDAHARA = 'BENDAHARA';
    case MONEV = 'MONEV';
    case VIEWER = 'VIEWER';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::KADIS => 'Kepala Dinas',
            self::TIM_PERENCANAAN => 'Tim Perencanaan',
            self::TIM_PELAKSANA => 'Tim Pelaksana',
            self::BENDAHARA => 'Bendahara',
            self::MONEV => 'Monitoring & Evaluasi',
            self::VIEWER => 'Viewer',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
