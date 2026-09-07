// frontend/src/layouts/AuthLayout.jsx

import React from 'react';
import { Outlet } from 'react-router-dom';

export function AuthLayout() {
  return (
    <div className="min-h-screen bg-gray-50 dark:bg-slate-950 flex items-center justify-center p-4 transition-colors duration-200">
      <div className="w-full max-w-md">
        <Outlet />
      </div>
    </div>
  );
}
