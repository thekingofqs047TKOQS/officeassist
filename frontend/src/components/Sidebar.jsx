// frontend/src/components/Sidebar.jsx

import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { 
  LayoutDashboard, 
  PlusCircle, 
  ListFilter, 
  Inbox, 
  Users, 
  Building2, 
  FileText
} from 'lucide-react';

export function Sidebar() {
  const { user } = useAuth();

  const isAdmin = user?.role === 'SYSTEM_ADMIN';
  const isHod = user?.role === 'DEPARTMENT_HEAD' || user?.role === 'DEPARTMENT_MANAGER';

  return (
    <aside className="w-64 bg-white dark:bg-slate-900 border-r border-slate-200/80 dark:border-slate-800/80 min-h-[calc(100vh-4rem)] p-4 flex flex-col justify-between transition-colors duration-150">
      <div className="space-y-6">
        {/* Navigation Links based on Role */}
        <nav className="space-y-1">
          {/* SYSTEM_ADMIN Navigation */}
          {isAdmin && (
            <>
              <NavLink
                to="/dashboard"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <LayoutDashboard className="w-4 h-4 text-brand-600 dark:text-brand-400" />
                <span>Dashboard</span>
              </NavLink>

              <NavLink
                to="/admin/users"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <Users className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span>Users</span>
              </NavLink>

              <NavLink
                to="/admin/departments"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <Building2 className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                <span>Departments</span>
              </NavLink>
            </>
          )}

          {/* DEPARTMENT_HEAD Navigation */}
          {isHod && !isAdmin && (
            <>
              <NavLink
                to="/dashboard"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <LayoutDashboard className="w-4 h-4 text-brand-600 dark:text-brand-400" />
                <span>Dashboard</span>
              </NavLink>

              <NavLink
                to="/requests/office"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <Inbox className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                <span>Requests</span>
              </NavLink>

              <NavLink
                to="/requests/new"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <PlusCircle className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                <span>New Request</span>
              </NavLink>

              <NavLink
                to="/requests/my"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <FileText className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span>My Requests</span>
              </NavLink>
            </>
          )}

          {/* EMPLOYEE Navigation */}
          {!isAdmin && !isHod && (
            <>
              <NavLink
                to="/dashboard"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <LayoutDashboard className="w-4 h-4 text-brand-600 dark:text-brand-400" />
                <span>Dashboard</span>
              </NavLink>

              <NavLink
                to="/requests/my"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <FileText className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span>My Requests</span>
              </NavLink>

              <NavLink
                to="/requests/office"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <Inbox className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                <span>Office Requests</span>
              </NavLink>

              <NavLink
                to="/requests/new"
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-xs transition-colors ${
                    isActive 
                      ? 'bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 font-semibold' 
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }`
                }
              >
                <PlusCircle className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                <span>New Request</span>
              </NavLink>
            </>
          )}
        </nav>
      </div>

      {/* Footer Info */}
      <div className="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400 space-y-1">
        <div className="font-semibold text-slate-700 dark:text-slate-200 text-[11px]">
          OfficeAssist
        </div>
        <div className="text-[10px] text-slate-400 dark:text-slate-500">Internal Service System</div>
      </div>
    </aside>
  );
}

