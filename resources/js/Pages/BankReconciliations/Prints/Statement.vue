<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { ReconciliationDetail } from '@/Types/treasury';

defineProps<{
    reconciliation: ReconciliationDetail;
    company: { name: string; code: string; email: string | null; phone: string | null; address: string | null };
}>();
const money = (value: string): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(Number(value));
const printPage = (): void => window.print();
</script>

<template>
<Head title="Bank Reconciliation Statement"/>
<div class="page">
    <div class="actions"><button @click="printPage">Print / Save PDF</button></div>
    <header><div><h1>{{company.name}}</h1><p>{{company.address}}</p><p>{{company.phone}}<span v-if="company.phone&&company.email"> · </span>{{company.email}}</p></div><div class="title"><h2>BANK RECONCILIATION STATEMENT</h2><p>{{reconciliation.reconciliation_number??`Session #${reconciliation.id}`}}</p><strong>{{reconciliation.status_label}}</strong></div></header>
    <section class="meta"><div><b>Bank Account</b><p>{{reconciliation.bank_account?.code}} — {{reconciliation.bank_account?.name}}</p><p>{{reconciliation.branch?.name}}</p></div><div><b>Statement Period</b><p>{{reconciliation.statement_start_date}} to {{reconciliation.statement_end_date}}</p><p>{{reconciliation.statement?.reference}}</p></div><div><b>Currency</b><p>{{reconciliation.currency_code}}</p></div></section>
    <section class="recon"><div><span>Balance per bank statement</span><b>{{money(reconciliation.statement_closing_balance)}}</b></div><div><span>Add: Outstanding deposits</span><b>{{money(reconciliation.outstanding_deposits)}}</b></div><div><span>Less: Outstanding payments</span><b>({{money(reconciliation.outstanding_payments)}})</b></div><div class="total"><span>Adjusted bank balance</span><b>{{money(reconciliation.adjusted_bank_balance)}}</b></div><div><span>Balance per General Ledger</span><b>{{money(reconciliation.book_closing_balance)}}</b></div><div class="difference"><span>Difference</span><b>{{money(reconciliation.difference_amount)}}</b></div></section>
    <h3>Statement Lines and Matches</h3>
    <table><thead><tr><th>#</th><th>Date</th><th>Reference / Description</th><th class="right">Statement Amount</th><th class="right">Matched</th><th>Status</th></tr></thead><tbody><tr v-for="line in reconciliation.statement_lines" :key="line.id"><td>{{line.line_number}}</td><td>{{line.transaction_date}}</td><td><b>{{line.bank_reference??'—'}}</b><p>{{line.description}}</p></td><td class="right">{{money(line.signed_amount)}}</td><td class="right">{{money(line.matched_amount)}}</td><td>{{line.status}}</td></tr></tbody></table>
    <section v-if="reconciliation.notes" class="notes"><b>Notes</b><p>{{reconciliation.notes}}</p></section>
    <section v-if="reconciliation.reversal_reason" class="reversed"><b>REVERSED</b><p>{{reconciliation.reversal_reason}}</p></section>
    <footer><div><span>Prepared By</span><b>{{reconciliation.created_by?.name??'—'}}</b></div><div><span>Completed By</span><b>{{reconciliation.completed_by?.name??'—'}}</b></div><div><span>Authorized Signature</span><b>________________________</b></div></footer>
</div>
</template>

<style scoped>
:global(body){margin:0;background:#f3f4f6;color:#111827;font-family:Arial,sans-serif}.page{width:297mm;min-height:210mm;margin:16px auto;padding:12mm;background:#fff;box-sizing:border-box}.actions{text-align:right;margin-bottom:12px}.actions button{border:0;border-radius:7px;background:#465fff;color:#fff;padding:9px 15px}header{display:flex;justify-content:space-between;border-bottom:2px solid #111827;padding-bottom:13px}header h1,header h2,header p{margin:0 0 5px}.title{text-align:right}.title strong{font-size:11px;text-transform:uppercase}.meta{display:grid;grid-template-columns:2fr 2fr 1fr;gap:20px;margin:18px 0;font-size:12px}.meta p{margin:4px 0}.recon{width:430px;margin-left:auto;border:1px solid #d1d5db;padding:12px;font-size:12px}.recon div{display:flex;justify-content:space-between;padding:5px 0}.recon .total,.recon .difference{border-top:1px solid #111827;margin-top:4px;padding-top:8px}.recon .difference{font-size:14px}h3{margin:22px 0 8px;font-size:13px;text-transform:uppercase}table{width:100%;border-collapse:collapse;font-size:10px}th,td{border:1px solid #d1d5db;padding:6px;vertical-align:top}th{background:#f3f4f6;text-align:left}.right{text-align:right}td p{margin:3px 0 0;color:#4b5563}.notes,.reversed{margin-top:18px;border:1px solid #d1d5db;padding:10px;font-size:11px}.notes p,.reversed p{white-space:pre-line}.reversed{border-color:#b91c1c;color:#991b1b}footer{display:grid;grid-template-columns:repeat(3,1fr);gap:30px;margin-top:42px}footer div{border-top:1px solid #111827;padding-top:7px;text-align:center;font-size:11px}footer span,footer b{display:block}@media print{@page{size:A4 landscape;margin:0}:global(body){background:#fff}.page{margin:0}.actions{display:none}}
</style>
