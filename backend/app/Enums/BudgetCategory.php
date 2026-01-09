<?php

namespace App\Enums;

enum BudgetCategory: string
{
    case ANALISIS = 'ANALISIS';
    case TATA_KELOLA = 'TATA_KELOLA';
    case OPERASIONALISASI = 'OPERASIONALISASI';
    case LAYANAN = 'LAYANAN';
    case ELEK_NON_ELEK = 'ELEK_NON_ELEK';

    public function label(): string
    {
        return match ($this) {
            self::ANALISIS => 'Analisis',
            self::TATA_KELOLA => 'Tata Kelola',
            self::OPERASIONALISASI => 'Operasionalisasi',
            self::LAYANAN => 'Layanan',
            self::ELEK_NON_ELEK => 'Elektronik & Non Elektronik',
        };
    }

    public function budget(): int
    {
        return match ($this) {
            self::ANALISIS => 4_700_000,
            self::TATA_KELOLA => 13_350_000,
            self::OPERASIONALISASI => 152_987_875,
            self::LAYANAN => 639_400_000,
            self::ELEK_NON_ELEK => 575_000_000,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function totalBudget(): int
    {
        return array_sum(array_map(fn($case) => $case->budget(), self::cases()));
    }
}
