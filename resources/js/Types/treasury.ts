export type TreasuryStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'posted'
    | 'reversed'
    | 'cancelled';

export type ReconciliationStatus = 'draft' | 'completed' | 'reversed';
export type TreasuryControlType = 'cash' | 'bank';

export interface IdName {
    id: number;
    name: string;
}

export interface BranchOption {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface TreasuryAccountOption {
    id: number;
    code: string;
    name: string;
    control_type: TreasuryControlType | null;
    account_type?: string;
    account_subtype?: string | null;
    system_key?: string | null;
}

export interface StatusOption {
    value: string;
    label: string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

export interface LifecyclePermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    submit: boolean;
    return_to_draft: boolean;
    approve: boolean;
    post: boolean;
    cancel: boolean;
    reverse: boolean;
}

export interface TreasuryTransferSummary {
    id: number;
    transfer_number: string | null;
    transfer_date: string | null;
    posting_date: string | null;
    currency_code: string;
    amount: string;
    base_amount: string;
    transfer_type: string;
    transfer_type_label: string;
    reference: string | null;
    status: TreasuryStatus;
    status_label: string;
    source_branch: BranchOption | null;
    destination_branch: BranchOption | null;
    source_account: TreasuryAccountOption | null;
    destination_account: TreasuryAccountOption | null;
    created_at: string | null;
    can: LifecyclePermissions;
}

export interface TreasuryTransferDetail extends TreasuryTransferSummary {
    source_branch_id: number;
    destination_branch_id: number;
    source_account_id: number;
    destination_account_id: number;
    exchange_rate: string;
    notes: string | null;
    revision: number;
    accounting_posting_reference: string | null;
    accounting_reversal_reference: string | null;
    reversal_posting_date: string | null;
    reversal_reason: string | null;
    cancellation_reason: string | null;
    created_by: IdName | null;
    submitted_by: IdName | null;
    approved_by: IdName | null;
    posted_by: IdName | null;
    reversed_by: IdName | null;
    cancelled_by: IdName | null;
    submitted_at: string | null;
    approved_at: string | null;
    posted_at: string | null;
    reversed_at: string | null;
    cancelled_at: string | null;
}

export interface TreasuryAdjustmentSummary {
    id: number;
    adjustment_number: string | null;
    adjustment_type: string;
    adjustment_type_label: string;
    adjustment_date: string | null;
    posting_date: string | null;
    currency_code: string;
    amount: string;
    base_amount: string;
    reference: string | null;
    description: string;
    status: TreasuryStatus;
    status_label: string;
    branch: BranchOption | null;
    bank_account: TreasuryAccountOption | null;
    offset_account: TreasuryAccountOption | null;
    created_at: string | null;
    can: LifecyclePermissions;
}

export interface StatementLine {
    id: number;
    line_number: number;
    transaction_date: string | null;
    value_date: string | null;
    bank_reference: string | null;
    description: string | null;
    debit_amount: string;
    credit_amount: string;
    signed_amount: string;
    running_balance: string | null;
    matched_amount: string;
    status: string;
    ignore_reason: string | null;
}

export interface TreasuryAdjustmentDetail extends TreasuryAdjustmentSummary {
    branch_id: number;
    bank_account_id: number;
    offset_account_id: number;
    bank_statement_line_id: number | null;
    exchange_rate: string;
    revision: number;
    accounting_posting_reference: string | null;
    accounting_reversal_reference: string | null;
    reversal_posting_date: string | null;
    reversal_reason: string | null;
    cancellation_reason: string | null;
    statement_line: StatementLine | null;
    created_by: IdName | null;
    submitted_by: IdName | null;
    approved_by: IdName | null;
    posted_by: IdName | null;
    reversed_by: IdName | null;
    cancelled_by: IdName | null;
    submitted_at: string | null;
    approved_at: string | null;
    posted_at: string | null;
    reversed_at: string | null;
    cancelled_at: string | null;
}

export interface BankStatementSummary {
    id: number;
    statement_reference: string;
    source_filename: string;
    period_start: string | null;
    period_end: string | null;
    currency_code: string;
    opening_balance: string;
    closing_balance: string;
    line_count: number;
    status: string;
    branch: BranchOption | null;
    bank_account: TreasuryAccountOption | null;
    imported_by: IdName | null;
    imported_at: string | null;
    can: { view: boolean; delete: boolean };
}

export interface ReconciliationSummary {
    id: number;
    reconciliation_number: string | null;
    statement_start_date: string | null;
    statement_end_date: string | null;
    currency_code: string;
    statement_closing_balance: string;
    book_closing_balance: string;
    outstanding_deposits: string;
    outstanding_payments: string;
    adjusted_bank_balance: string;
    difference_amount: string;
    status: ReconciliationStatus;
    status_label: string;
    branch: BranchOption | null;
    bank_account: TreasuryAccountOption | null;
    statement: { id: number; reference: string; filename: string } | null;
    can: { view: boolean; match: boolean; complete: boolean; reverse: boolean };
}

export interface BankStatementDetail extends BankStatementSummary {
    lines: StatementLine[];
    reconciliations: ReconciliationSummary[];
}

export interface ReconciliationMatch {
    id: number;
    statement_line_id: number;
    journal_entry_line_id: number;
    match_type: string;
    matched_amount: string;
    status: string;
    matched_at: string | null;
    matched_by: IdName | null;
    journal: {
        journal_number: string | null;
        posting_date: string | null;
        source_document_number: string | null;
        reference: string | null;
        description: string | null;
    } | null;
}

export interface AvailableJournalLine {
    id: number;
    posting_date: string;
    journal_number: string | null;
    source_document_number: string | null;
    reference: string | null;
    description: string | null;
    signed_amount: string;
    available_amount: string;
}

export interface ReconciliationDetail extends ReconciliationSummary {
    statement_opening_balance: string;
    notes: string | null;
    created_by: IdName | null;
    completed_by: IdName | null;
    reversed_by: IdName | null;
    completed_at: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    statement_lines: StatementLine[];
    matches: ReconciliationMatch[];
    available_journal_lines: AvailableJournalLine[];
}

export interface TreasuryAccountCard extends TreasuryAccountOption {
    base_balance: string;
    unmatched_statement_lines: number;
}

export interface RegisterRow {
    id: number;
    posting_date: string;
    journal_number: string | null;
    journal_type: string;
    source_document_number: string | null;
    reference: string | null;
    description: string | null;
    account_id: number;
    account_code: string;
    account_name: string;
    control_type: TreasuryControlType;
    branch_id: number;
    branch_code: string;
    branch_name: string;
    currency_code: string;
    debit_amount: string;
    credit_amount: string;
    base_debit_amount: string;
    base_credit_amount: string;
    base_movement: string;
    base_running_balance: string;
}
