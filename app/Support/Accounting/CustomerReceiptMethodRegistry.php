<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use LogicException;

final class CustomerReceiptMethodRegistry
{
    /**
     * @var array<string, array{
     *     label: string,
     *     account_control_type: string,
     *     requires_cheque_details: bool
     * }>
     */
    private const METHODS = [
        'cash' => [
            'label' => 'Cash',
            'account_control_type' => 'cash',
            'requires_cheque_details' => false,
        ],
        'bank_transfer' => [
            'label' => 'Bank Transfer',
            'account_control_type' => 'bank',
            'requires_cheque_details' => false,
        ],
        'cheque' => [
            'label' => 'Cheque',
            'account_control_type' => 'bank',
            'requires_cheque_details' => true,
        ],
        'mobile_financial_service' => [
            'label' => 'Mobile Financial Service',
            'account_control_type' => 'bank',
            'requires_cheque_details' => false,
        ],
        'other' => [
            'label' => 'Other',
            'account_control_type' => 'bank',
            'requires_cheque_details' => false,
        ],
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(
            self::METHODS,
        );
    }

    public function exists(
        string $method,
    ): bool {
        return array_key_exists(
            $method,
            self::METHODS,
        );
    }

    public function label(
        string $method,
    ): string {
        $configuration = self::METHODS[$method]
            ?? null;

        if (!is_array($configuration)) {
            throw new LogicException(
                "Unsupported Customer Receipt method [{$method}].",
            );
        }

        return $configuration['label'];
    }

    public function accountControlType(
        string $method,
    ): string {
        $configuration = self::METHODS[$method]
            ?? null;

        if (!is_array($configuration)) {
            throw new LogicException(
                "Unsupported Customer Receipt method [{$method}].",
            );
        }

        return $configuration[
            'account_control_type'
        ];
    }

    public function requiresChequeDetails(
        string $method,
    ): bool {
        $configuration = self::METHODS[$method]
            ?? null;

        if (!is_array($configuration)) {
            throw new LogicException(
                "Unsupported Customer Receipt method [{$method}].",
            );
        }

        return $configuration[
            'requires_cheque_details'
        ];
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string,
     *     account_control_type: string,
     *     requires_cheque_details: bool
     * }>
     */
    public function options(): array
    {
        $options = [];

        foreach (
            self::METHODS
            as $value => $configuration
        ) {
            $options[] = [
                'value' => $value,
                'label' => $configuration['label'],
                'account_control_type' =>
                    $configuration[
                        'account_control_type'
                    ],
                'requires_cheque_details' =>
                    $configuration[
                        'requires_cheque_details'
                    ],
            ];
        }

        return $options;
    }
}