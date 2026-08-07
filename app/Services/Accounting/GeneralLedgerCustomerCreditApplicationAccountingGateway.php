<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerCreditApplicationAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\CustomerCreditApplication;
use App\Models\JournalEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerCustomerCreditApplicationAccountingGateway implements CustomerCreditApplicationAccountingGateway
{
    public function __construct(private readonly CustomerCreditApplicationJournalBuilder $journalBuilder, private readonly JournalEntryService $journalEntryService,)
    {
    }

    public function post(CustomerCreditApplication $application, AccountingPeriod $period, User $actor): string
    {
        $this->requireTransaction();
        $payload = $this->journalBuilder->buildPosting($application);
        $journal = $this->journalEntryService->postSystemJournal(journalType: 'customer_credit_application', source: $application, branchId: (int) $application->branch_id, accountingPeriod: $period, documentDate: $application->application_date, postingDate: $application->posting_date, currencyCode: (string) $application->currency_code, exchangeRate: '1.00000000', description: $payload['description'], lines: $payload['lines'], postingKey: $payload['posting_key'], actor: $actor, sourceDocumentNumber: $application->application_number,);
        return $this->journalNumber($journal);
    }

    public function reverse(CustomerCreditApplication $application, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $reason, User $actor): string
    {
        $this->requireTransaction();
        $original = JournalEntry::query()->where('posting_key', $this->journalBuilder->postingKey($application))->lockForUpdate()->first();
        if (!$original instanceof JournalEntry) {
            throw new LogicException('The original Customer Credit Application journal is unavailable.');
        }
        return $this->journalNumber($this->journalEntryService->reverseSystemJournal(journalEntry: $original, source: $application, accountingPeriod: $period, reversalPostingDate: $reversalPostingDate, reason: $reason, postingKey: $this->journalBuilder->reversalPostingKey($application), actor: $actor,));
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim((string) $journal->journal_number);
        if ($number === '') {
            throw new LogicException('The customer settlement journal does not have a journal number.');
        }
        return $number;
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer Credit Application accounting must run inside a transaction.');
        }
    }
}