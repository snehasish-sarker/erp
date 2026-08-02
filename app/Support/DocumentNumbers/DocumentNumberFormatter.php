<?php

declare(strict_types=1);

namespace App\Support\DocumentNumbers;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

final class DocumentNumberFormatter
{
    public function resetKey(
        string $resetPolicy,
        int $fiscalYearStartMonth,
        DateTimeInterface $date,
    ): string {
        $date = CarbonImmutable::instance($date);

        return match ($resetPolicy) {
            'calendar_year' => $date->format('Y'),

            'fiscal_year' => $this->fiscalYearLabel(
                date: $date,
                fiscalYearStartMonth: $fiscalYearStartMonth,
            ),

            default => 'never',
        };
    }

    public function format(
        string $documentType,
        ?string $prefix,
        ?string $suffix,
        int $sequenceNumber,
        int $numberPadding,
        string $companyCode,
        ?string $branchCode,
        int $fiscalYearStartMonth,
        DateTimeInterface $date,
    ): string {
        $date = CarbonImmutable::instance($date);

        $replacements = [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),

            '{FY}' => $this->fiscalYearLabel(
                date: $date,
                fiscalYearStartMonth: $fiscalYearStartMonth,
            ),

            '{FY_SHORT}' => $this->shortFiscalYearLabel(
                date: $date,
                fiscalYearStartMonth: $fiscalYearStartMonth,
            ),

            '{BRANCH}' => $branchCode ?? $companyCode,

            '{TYPE}' => Str::upper(
                str_replace('_', '-', $documentType),
            ),
        ];

        $resolvedPrefix = strtr(
            $prefix ?? '',
            $replacements,
        );

        $resolvedSuffix = strtr(
            $suffix ?? '',
            $replacements,
        );

        return $resolvedPrefix
            .str_pad(
                (string) $sequenceNumber,
                $numberPadding,
                '0',
                STR_PAD_LEFT,
            )
            .$resolvedSuffix;
    }

    private function fiscalYearLabel(
        CarbonImmutable $date,
        int $fiscalYearStartMonth,
    ): string {
        $startYear = $date->month >= $fiscalYearStartMonth
            ? $date->year
            : $date->year - 1;

        return $startYear.'-'.($startYear + 1);
    }

    private function shortFiscalYearLabel(
        CarbonImmutable $date,
        int $fiscalYearStartMonth,
    ): string {
        $startYear = $date->month >= $fiscalYearStartMonth
            ? $date->year
            : $date->year - 1;

        return substr((string) $startYear, -2)
            .'-'
            .substr((string) ($startYear + 1), -2);
    }
}