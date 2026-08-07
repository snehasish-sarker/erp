<?php

declare(strict_types=1);

namespace App\Support\DocumentNumbers;

final class DocumentTypeRegistry
{
    /**
     * @var array<string, array{
     *     label: string,
     *     default_prefix: string
     * }>
     */
    private const TYPES = [
        'purchase_requisition' => [
            'label' => 'Purchase Requisition',
            'default_prefix' => 'PR-{YYYY}-',
        ],
        'request_for_quotation' => [
            'label' => 'Request for Quotation',
            'default_prefix' => 'RFQ-{YYYY}-',
        ],
        'purchase_order' => [
            'label' => 'Purchase Order',
            'default_prefix' => 'PO-{YYYY}-',
        ],
        'goods_receipt' => [
            'label' => 'Goods Receipt',
            'default_prefix' => 'GRN-{YYYY}-',
        ],
        'supplier_invoice' => [
            'label' => 'Supplier Invoice',
            'default_prefix' => 'SINV-{YYYY}-',
        ],
        'purchase_return' => [
            'label' => 'Purchase Return',
            'default_prefix' => 'PRTN-{YYYY}-',
        ],
        'sales_quotation' => [
            'label' => 'Sales Quotation',
            'default_prefix' => 'SQ-{YYYY}-',
        ],
        'sales_order' => [
            'label' => 'Sales Order',
            'default_prefix' => 'SO-{YYYY}-',
        ],
        'delivery_note' => [
            'label' => 'Delivery Note',
            'default_prefix' => 'DN-{YYYY}-',
        ],
        'sales_invoice' => [
            'label' => 'Sales Invoice',
            'default_prefix' => 'INV-{YYYY}-',
        ],
        'sales_return' => [
            'label' => 'Sales Return',
            'default_prefix' => 'SRTN-{YYYY}-',
        ],
        'stock_transfer' => [
            'label' => 'Stock Transfer',
            'default_prefix' => 'ST-{YYYY}-',
        ],
        'stock_adjustment' => [
            'label' => 'Stock Adjustment',
            'default_prefix' => 'SA-{YYYY}-',
        ],
        'customer_receipt' => [
            'label' => 'Customer Receipt',
            'default_prefix' => 'CR-{YYYY}-',
        ],
        'customer_credit_application' => [
            'label' => 'Customer Credit Application',
            'default_prefix' => 'CCA-{YYYY}-',
        ],
        'customer_refund' => [
            'label' => 'Customer Refund',
            'default_prefix' => 'CRF-{YYYY}-',
        ],
        'customer_ar_adjustment' => [
            'label' => 'Customer AR Adjustment',
            'default_prefix' => 'ARA-{YYYY}-',
        ],
        'supplier_payment' => [
            'label' => 'Supplier Payment',
            'default_prefix' => 'SP-{YYYY}-',
        ],
        'journal_entry' => [
            'label' => 'Journal Entry',
            'default_prefix' => 'JE-{YYYY}-',
        ],
        'debit_note' => [
            'label' => 'Debit Note',
            'default_prefix' => 'DBN-{YYYY}-',
        ],
        'credit_note' => [
            'label' => 'Credit Note',
            'default_prefix' => 'CRN-{YYYY}-',
        ],
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public function exists(string $documentType): bool
    {
        return array_key_exists(
            $documentType,
            self::TYPES,
        );
    }

    public function label(string $documentType): string
    {
        return self::TYPES[$documentType]['label']
            ?? $documentType;
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string,
     *     default_prefix: string
     * }>
     */
    public function options(): array
    {
        $options = [];

        foreach (self::TYPES as $value => $configuration) {
            $options[] = [
                'value' => $value,
                'label' => $configuration['label'],
                'default_prefix' => $configuration['default_prefix'],
            ];
        }

        return $options;
    }
}