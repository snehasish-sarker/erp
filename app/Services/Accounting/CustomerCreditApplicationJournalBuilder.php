<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CustomerCreditApplication;
use App\Models\CustomerCreditApplicationLine;
use App\Models\CustomerOpenItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CustomerCreditApplicationJournalBuilder
{
    private const SCALE = 6;
    public function __construct(private readonly CustomerCreditControlAccountService $accountService,)
    {
    }
    /** @return array{posting_key: string, description: string, lines: list<array<string, mixed>>} */
    public function buildPosting(CustomerCreditApplication $application): array
    {
        $this->requireTransaction();
        $application->loadMissing('lines.creditOpenItem');
        if (!$application->isApproved() || !$application->hasApplicationNumber()) {
            throw new LogicException('Only a numbered, approved Customer Credit Application can be posted.');
        }
        $ar = $this->accountService->accountsReceivable();
        $gain = $this->accountService->realizedExchangeGain();
        $loss = $this->accountService->realizedExchangeLoss();
        $lines = [];
        $exchange = BigDecimal::zero();
        foreach ($application->lines as $applicationLine) {
            if (!$applicationLine instanceof CustomerCreditApplicationLine) {
                continue;
            }
            $creditItem = $applicationLine->creditOpenItem;
            if (!$creditItem instanceof CustomerOpenItem) {
                throw new LogicException('A Customer Credit Application source credit is unavailable.');
            }
            $control = $this->accountService->forCreditOpenItem($creditItem);
            $amount = BigDecimal::of((string) $applicationLine->amount);
            $receivableBase = BigDecimal::of((string) $applicationLine->receivable_base_amount);
            $creditBase = BigDecimal::of((string) $applicationLine->credit_base_amount);
            $exchange = $exchange->plus($creditBase->minus($receivableBase));
            $reference = (string) $application->application_number;
            $lines[] = $this->line(account: $control, branchId: (int) $application->branch_id, customerId: (int) $application->customer_id, reference: $reference, description: "Apply customer credit {$applicationLine->credit_document_number}", debit: $amount, credit: BigDecimal::zero(), baseDebit: $creditBase, baseCredit: BigDecimal::zero(),);
            $lines[] = $this->line(account: $ar, branchId: (int) $application->branch_id, customerId: (int) $application->customer_id, reference: $reference, description: "Settle customer receivable {$applicationLine->receivable_document_number}", debit: BigDecimal::zero(), credit: $amount, baseDebit: BigDecimal::zero(), baseCredit: $receivableBase,);
        }
        $exchange = $exchange->toScale(self::SCALE, RoundingMode::HalfUp);
        if ($exchange->isPositive()) {
            $lines[] = $this->line($gain, (int) $application->branch_id, null, (string) $application->application_number, 'Realized exchange gain on customer credit application', BigDecimal::zero(), BigDecimal::zero(), BigDecimal::zero(), $exchange);
        } elseif ($exchange->isNegative()) {
            $lines[] = $this->line($loss, (int) $application->branch_id, null, (string) $application->application_number, 'Realized exchange loss on customer credit application', BigDecimal::zero(), BigDecimal::zero(), $exchange->abs(), BigDecimal::zero());
        }
        if ($lines === []) {
            throw new LogicException('The Customer Credit Application does not contain posting lines.');
        }
        return['posting_key' => $this->postingKey($application), 'description' => mb_substr(sprintf('Customer Credit Application %s — %s', (string) $application->application_number, (string) $application->customer_name), 0, 500), 'lines' => $lines,];
    }

    public function postingKey(CustomerCreditApplication $application): string
    {
        return sprintf('customer_credit_application:%d:journal:post', (int) $application->getKey());
    }

    public function reversalPostingKey(CustomerCreditApplication $application): string
    {
        return sprintf('customer_credit_application:%d:journal:reverse', (int) $application->getKey());
    }

    private function line(Account $account, int $branchId, ? int $customerId, string $reference, string $description, BigDecimal $debit, BigDecimal $credit, BigDecimal $baseDebit, BigDecimal $baseCredit): array
    {
        return['account_id' => $account->getKey(), 'branch_id' => $branchId, 'supplier_id' => null, 'customer_id' => $customerId, 'reference' => $reference, 'description' => mb_substr($description, 0, 500), 'due_date' => null, 'debit_amount' => $this->decimal($debit), 'credit_amount' => $this->decimal($credit), 'base_debit_amount' => $this->decimal($baseDebit), 'base_credit_amount' => $this->decimal($baseCredit),];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer Credit Application journal building must run inside a transaction.');
        }
    }
}