// frontend/src/pages/EmployeeDashboard.jsx

import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { api } from '../services/api';
import { StatusBadge } from '../components/StatusBadge';
import { Link } from 'react-router-dom';
import { PlusCircle, Clock, CheckCircle2, FileText, Inbox, ArrowRight } from 'lucide-react';

export function EmployeeDashboard() {
  const { user } = useAuth();
  const [myRequests, setMyRequests] = useState([]);
  const [deptQueue, setDeptQueue] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    setLoading(true);
    try {
      const [myReqRes, deptReqRes] = await Promise.all([
        api.getRequests({ limit: 50 }),
        api.getRequests({ department_id: user?.department_id, limit: 50 })
      ]);

      if (myReqRes.data) {
        setMyRequests(myReqRes.data.filter((r) => Number(r.requester_id) === Number(user?.id)));
      }
      if (deptReqRes.data) {
        setDeptQueue(deptReqRes.data);
      }
    } catch (e) {
      // Ignore
    } finally {
      setLoading(false);
    }
  };

  const requestsSent = myRequests.length;
  const requestsReceived = deptQueue.length;
  
  const pendingSent = myRequests.filter((r) => r.status === 'NEW' || r.status === 'ASSIGNED' || r.status === 'IN_PROGRESS').length;
  const pendingReceived = deptQueue.filter((r) => r.status === 'NEW' || r.status === 'ASSIGNED' || r.status === 'IN_PROGRESS').length;
  const totalPending = pendingSent + pendingReceived;

  const completedSent = myRequests.filter((r) => r.status === 'RESOLVED' || r.status === 'CLOSED').length;
  const completedReceived = deptQueue.filter((r) => r.status === 'RESOLVED' || r.status === 'CLOSED').length;
  const totalCompleted = completedSent + completedReceived;

  // Combine recent requests for quick overview
  const recentRequests = [...myRequests].sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0)).slice(0, 5);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
            Employee Dashboard
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Welcome back, {user?.full_name?.split(' ')[0]}. Manage your submitted requests and department tasks.
          </p>
        </div>

        <Link
          to="/requests/new"
          className="ui-btn-primary py-2.5 px-4 text-xs shadow-md shadow-brand-600/20"
        >
          <PlusCircle className="w-4 h-4" />
          <span>New Request</span>
        </Link>
      </div>

      {/* 4 Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Requests Sent</div>
            <div className="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{requestsSent}</div>
          </div>
          <div className="w-10 h-10 bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 rounded-xl flex items-center justify-center border border-blue-100 dark:border-blue-900/50">
            <FileText className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Requests Received</div>
            <div className="text-2xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{requestsReceived}</div>
          </div>
          <div className="w-10 h-10 bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 rounded-xl flex items-center justify-center border border-purple-100 dark:border-purple-900/50">
            <Inbox className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Pending</div>
            <div className="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{totalPending}</div>
          </div>
          <div className="w-10 h-10 bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center border border-amber-100 dark:border-amber-900/50">
            <Clock className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Completed</div>
            <div className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{totalCompleted}</div>
          </div>
          <div className="w-10 h-10 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center border border-emerald-100 dark:border-emerald-900/50">
            <CheckCircle2 className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* Recent Requests Section */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <h2 className="font-bold text-slate-900 dark:text-white text-sm">Recent Requests</h2>
          <Link to="/requests/my" className="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1">
            View All <ArrowRight className="w-3.5 h-3.5" />
          </Link>
        </div>

        {loading ? (
          <div className="p-8 text-center text-slate-400 dark:text-slate-500 text-xs">Loading recent requests...</div>
        ) : recentRequests.length === 0 ? (
          <div className="p-10 text-center text-slate-400 dark:text-slate-500 text-xs italic">
            No requests found. Click 'New Request' above to create one.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th className="px-6 py-3">Request #</th>
                  <th className="px-6 py-3">Problem</th>
                  <th className="px-6 py-3">Department</th>
                  <th className="px-6 py-3">Status</th>
                  <th className="px-6 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                {recentRequests.map((r) => (
                  <tr key={r.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                    <td className="px-6 py-3.5 font-mono font-bold text-slate-900 dark:text-white">{r.request_number}</td>
                    <td className="px-6 py-3.5 font-semibold text-slate-900 dark:text-slate-200 max-w-xs truncate">{r.title}</td>
                    <td className="px-6 py-3.5 text-slate-600 dark:text-slate-400">{r.department_name}</td>
                    <td className="px-6 py-3.5"><StatusBadge status={r.status} /></td>
                    <td className="px-6 py-3.5 text-right">
                      <Link
                        to={`/requests/${r.id}`}
                        className="font-bold text-brand-600 dark:text-brand-400 hover:underline"
                      >
                        Open →
                      </Link>
                    </td>
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

