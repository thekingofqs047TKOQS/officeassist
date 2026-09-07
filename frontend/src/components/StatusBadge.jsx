// frontend/src/components/StatusBadge.jsx

import React from 'react';

const statusConfig = {
  NEW: { label: 'New', bg: 'bg-blue-100 dark:bg-blue-950/70', text: 'text-blue-800 dark:text-blue-300', border: 'dark:border dark:border-blue-800/50', dot: 'bg-blue-500 dark:bg-blue-400' },
  ASSIGNED: { label: 'Assigned', bg: 'bg-purple-100 dark:bg-purple-950/70', text: 'text-purple-800 dark:text-purple-300', border: 'dark:border dark:border-purple-800/50', dot: 'bg-purple-500 dark:bg-purple-400' },
  IN_PROGRESS: { label: 'In Progress', bg: 'bg-amber-100 dark:bg-amber-950/70', text: 'text-amber-800 dark:text-amber-300', border: 'dark:border dark:border-amber-800/50', dot: 'bg-amber-500 dark:bg-amber-400' },
  RESOLVED: { label: 'Resolved', bg: 'bg-emerald-100 dark:bg-emerald-950/70', text: 'text-emerald-800 dark:text-emerald-300', border: 'dark:border dark:border-emerald-800/50', dot: 'bg-emerald-500 dark:bg-emerald-400' },
  CLOSED: { label: 'Closed', bg: 'bg-gray-100 dark:bg-slate-800', text: 'text-gray-700 dark:text-slate-300', border: 'dark:border dark:border-slate-700', dot: 'bg-gray-400 dark:bg-slate-500' },
  CANCELLED: { label: 'Cancelled', bg: 'bg-red-100 dark:bg-red-950/70', text: 'text-red-800 dark:text-red-300', border: 'dark:border dark:border-red-800/50', dot: 'bg-red-500 dark:bg-red-400' }
};

export function StatusBadge({ status }) {
  const config = statusConfig[status] || statusConfig.NEW;

  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${config.bg} ${config.text} ${config.border}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${config.dot}`} />
      {config.label}
    </span>
  );
}
