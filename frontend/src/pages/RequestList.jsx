// frontend/src/pages/RequestList.jsx

import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { StatusBadge } from '../components/StatusBadge';
import { Link } from 'react-router-dom';
import { Search, PlusCircle, UserCheck } from 'lucide-react';
import { useToast } from '../contexts/ToastContext';

export function RequestList({ viewMode = 'all' }) {
  const { user } = useAuth();
  const toast = useToast();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [actionLoading, setActionLoading] = useState(null);

  const isHod = user?.role === 'DEPARTMENT_HEAD' || user?.role === 'DEPARTMENT_MANAGER';

  useEffect(() => {
    fetchRequests();
  }, [viewMode]);

  const fetchRequests = async () => {
    setLoading(true);
    try {
      const params = {};
      if (viewMode === 'office' && user?.department_id) {
        params.department_id = user.department_id;
      }
      if (search) params.search = search;

      const res = await api.getRequests(params);
      if (res.data) {
        let filtered = res.data;
        if (viewMode === 'my') {
          filtered = res.data.filter((r) => Number(r.requester_id) === Number(user?.id));
        } else if (viewMode === 'office') {
          filtered = res.data.filter((r) => Number(r.department_id) === Number(user?.department_id));
        }
        setRequests(filtered);
      }
    } catch (e) {
      // Error handling
    } finally {
      setLoading(false);
    }
  };

  const handleClaimRequest = async (requestId) => {
    setActionLoading(requestId);
    try {
      await api.claimRequest(requestId);
      toast.success('Request claimed successfully!');
      fetchRequests();
    } catch (err) {
      if (err.status === 409) {
        toast.error('This request has already been claimed.');
      } else {
        toast.error(err.message || 'Failed to claim request');
      }
    } finally {
      setActionLoading(null);
    }
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    fetchRequests();
  };

  const pageTitle = viewMode === 'my' ? 'My Requests' : viewMode === 'office' ? 'Office Requests' : 'Requests';
  const pageSub = viewMode === 'my' 
    ? 'Requests you personally submitted.' 
    : viewMode === 'office' 
    ? 'Requests sent to your department.' 
    : 'All service requests.';

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">{pageTitle}</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{pageSub}</p>
        </div>

        {user?.role !== 'SYSTEM_ADMIN' && (
          <Link
            to="/requests/new"
            className="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2.5 rounded-xl shadow flex items-center gap-2 text-xs w-fit transition-all"
          >
            <PlusCircle className="w-4 h-4" /> New Request
          </Link>
        )}
      </div>

      {/* Filter / Search Bar */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 shadow-sm">
        <form onSubmit={handleSearchSubmit} className="flex items-center gap-3">
          <div className="relative flex-1 w-full">
            <Search className="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-3" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search request #, problem, or requester..."
              className="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500"
            />
          </div>
          <button
            type="submit"
            className="bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white text-xs font-semibold px-4 py-2 rounded-xl transition-colors"
          >
            Search
          </button>
        </form>
      </div>

      {/* Requests Table */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-slate-400 dark:text-slate-500 text-xs">Loading requests...</div>
        ) : requests.length === 0 ? (
          <div className="p-12 text-center text-slate-400 dark:text-slate-500 text-xs italic">
            No requests found.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800">
                {viewMode === 'my' ? (
                  <tr>
                    <th className="px-6 py-3.5">Problem</th>
                    <th className="px-6 py-3.5">Target Department</th>
                    <th className="px-6 py-3.5">Status</th>
                    <th className="px-6 py-3.5">Assigned To</th>
                    <th className="px-6 py-3.5">Date</th>
                    <th className="px-6 py-3.5 text-right">Action</th>
                  </tr>
                ) : (
                  <tr>
                    <th className="px-6 py-3.5">Requester</th>
                    <th className="px-6 py-3.5">Problem</th>
                    <th className="px-6 py-3.5">Department</th>
                    <th className="px-6 py-3.5">Assigned To</th>
                    <th className="px-6 py-3.5">Status</th>
                    <th className="px-6 py-3.5">Date</th>
                    <th className="px-6 py-3.5 text-right">Action</th>
                  </tr>
                )}
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                {requests.map((r) => (
                  <tr key={r.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                    {viewMode === 'my' ? (
                      <>
                        <td className="px-6 py-4 font-semibold text-slate-900 dark:text-slate-200 max-w-xs truncate">{r.title}</td>
                        <td className="px-6 py-4 text-slate-600 dark:text-slate-400">{r.department_name}</td>
                        <td className="px-6 py-4"><StatusBadge status={r.status} /></td>
                        <td className="px-6 py-4 text-slate-500 dark:text-slate-400 font-semibold">{r.assigned_staff_name || 'Unassigned'}</td>
                        <td className="px-6 py-4 text-slate-500 dark:text-slate-400">{r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}</td>
                        <td className="px-6 py-4 text-right">
                          <Link
                            to={`/requests/${r.id}`}
                            className="font-bold text-brand-600 dark:text-brand-400 hover:underline"
                          >
                            Open →
                          </Link>
                        </td>
                      </>
                    ) : (
                      <>
                        <td className="px-6 py-4 text-slate-900 dark:text-white font-bold">
                          {r.requester_name}
                          {r.requester_department_name && (
                            <span className="block text-[11px] font-normal text-slate-500 dark:text-slate-400">
                              {r.requester_department_name}
                            </span>
                          )}
                        </td>
                        <td className="px-6 py-4 font-semibold text-slate-900 dark:text-slate-200 max-w-xs truncate">{r.title}</td>
                        <td className="px-6 py-4 text-slate-600 dark:text-slate-400">{r.department_name}</td>
                        <td className="px-6 py-4 text-slate-600 dark:text-slate-300 font-semibold">{r.assigned_staff_name || 'Unassigned'}</td>
                        <td className="px-6 py-4"><StatusBadge status={r.status} /></td>
                        <td className="px-6 py-4 text-slate-500 dark:text-slate-400">{r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}</td>
                        <td className="px-6 py-4 text-right space-x-2">
                          {!r.assigned_staff_id && Number(r.department_id) === Number(user?.department_id) && user?.role !== 'SYSTEM_ADMIN' && (
                            <button
                              disabled={actionLoading === r.id}
                              onClick={() => handleClaimRequest(r.id)}
                              className="bg-purple-600 hover:bg-purple-700 text-white font-bold px-3 py-1.5 rounded-lg shadow-sm text-xs transition-all disabled:opacity-50 inline-flex items-center gap-1"
                            >
                              <UserCheck className="w-3.5 h-3.5" /> Claim
                            </button>
                          )}
                          <Link
                            to={`/requests/${r.id}`}
                            className="font-bold text-brand-600 dark:text-brand-400 hover:underline"
                          >
                            Open →
                          </Link>
                        </td>
                      </>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

