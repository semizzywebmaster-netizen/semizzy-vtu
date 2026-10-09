import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

type Transaction = {
  reference:string; status:string; amount_minor:number; currency:string; candidate_identifier:string|null;
  provider_reference:string|null; error:string|null; created_at:string;
  product?:{name:string; service_type:string; institution?:{name:string}|null}|null;
};
export default function SchoolAdmissionTransactionPage({ transaction }: { transaction:Transaction }) {
  const money = new Intl.NumberFormat('en-NG',{style:'currency',currency:transaction.currency}).format(transaction.amount_minor/100);
  return <AppLayout><Head title={`Admission transaction ${transaction.reference}`}/><div className="mx-auto max-w-3xl space-y-5 p-4 md:p-8">
    <div><p className="text-sm text-gray-500">School Admission</p><h1 className="text-2xl font-bold">Transaction details</h1></div>
    <div className="space-y-4 rounded-xl border p-5">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-sm text-gray-500">Reference</p><p className="font-semibold">{transaction.reference}</p></div><span className="rounded-full bg-gray-100 px-3 py-1 text-sm">{transaction.status}</span></div>
      <div className="grid gap-4 sm:grid-cols-2"><div><p className="text-sm text-gray-500">Service</p><p className="font-medium">{transaction.product?.name??'Admission service'}</p></div><div><p className="text-sm text-gray-500">Institution</p><p className="font-medium">{transaction.product?.institution?.name??'Not specified'}</p></div><div><p className="text-sm text-gray-500">Amount</p><p className="font-semibold">{money}</p></div><div><p className="text-sm text-gray-500">Created</p><p>{new Date(transaction.created_at).toLocaleString()}</p></div></div>
      {transaction.candidate_identifier && <div><p className="text-sm text-gray-500">Candidate / application identifier</p><p>{transaction.candidate_identifier}</p></div>}
      {transaction.provider_reference && <div><p className="text-sm text-gray-500">Provider reference</p><p>{transaction.provider_reference}</p></div>}
      {transaction.error && <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{transaction.error}</div>}
      <p className="text-xs text-gray-500">Sensitive customer data is not displayed on this page.</p>
    </div>
  </div></AppLayout>;
}
