// frontend/src/components/SLABadge.jsx

import React from 'react';
import { Clock, CheckCircle2, ShieldAlert } from 'lucide-react';

export function SLABadge({ sla }) {
  if (!sla) return null;

  const state = sla.overall_sla_state;
  const mins = sla.time_remaining_minutes;

  if (state === 'BREACHED') {
    return (
      <span className="inline-flex items-center gap-1 bg-red-100 dark:bg-red-950/80 text-red-800 dark:text-red-300 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-red-200 dark:border-red-900 uppercase tracking-wider animate-pulse">
        <ShieldAlert className="w-3 h-3 text-red-600 dark:text-red-400" /> SLA BREACHED
      </span>
    );
  }

  if (state === 'DUE_SOON') {
    return (
      <span className="inline-flex items-center gap-1 bg-amber-100 dark:bg-amber-950/80 text-amber-900 dark:text-amber-300 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-amber-300 dark:border-amber-900 uppercase tracking-wider">
        <Clock className="w-3 h-3 text-amber-600 dark:text-amber-400" /> DUE SOON ({mins}m)
      </span>
    );
  }

  if (state === 'COMPLETED') {
    return (
      <span className="inline-flex items-center gap-1 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 text-[10px] font-bold px-2 py-0.5 rounded-full border border-gray-200 dark:border-slate-700 uppercase">
        <CheckCircle2 className="w-3 h-3 text-gray-500 dark:text-slate-400" /> SLA MET
      </span>
    );
  }

  return (
    <span className="inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-900">
      <Clock className="w-3 h-3 text-emerald-500 dark:text-emerald-400" /> ON TRACK ({mins}m left)
    </span>
  );
}
