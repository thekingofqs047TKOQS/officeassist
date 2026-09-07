// frontend/src/components/PriorityBadge.jsx

import React from 'react';

const priorityConfig = {
  LOW: { label: 'Low', bg: 'bg-gray-100 dark:bg-slate-800', text: 'text-gray-700 dark:text-slate-300', border: 'dark:border dark:border-slate-700' },
  NORMAL: { label: 'Normal', bg: 'bg-blue-50 dark:bg-blue-950/60', text: 'text-blue-700 dark:text-blue-300', border: 'dark:border dark:border-blue-800/50' },
  HIGH: { label: 'High', bg: 'bg-orange-100 dark:bg-orange-950/70', text: 'text-orange-800 dark:text-orange-300', border: 'dark:border dark:border-orange-800/50' },
  URGENT: { label: 'Urgent', bg: 'bg-red-100 dark:bg-red-950/70', text: 'text-red-800 dark:text-red-300', border: 'dark:border dark:border-red-800/50' }
};

export function PriorityBadge({ priority }) {
  const config = priorityConfig[priority] || priorityConfig.NORMAL;

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold ${config.bg} ${config.text} ${config.border}`}>
      {config.label}
    </span>
  );
}
