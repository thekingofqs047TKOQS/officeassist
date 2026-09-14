// frontend/src/pages/StaffDashboard.jsx

import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { api } from '../services/api';
import { StatusBadge } from '../components/StatusBadge';
import { Link } from 'react-router-dom';
import { Inbox, UserCheck, UserPlus, Clock, CheckCircle2, AlertCircle } from 'lucide-react';

export function StaffDashboard() {
  const { user } = useAuth();
  const toast = useToast();

  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(null);

  // Department staff assignment state
  const [deptStaff, setDeptStaff] = useState([]);
  const [assigningReqId, setAssigningReqId] = useState(null);
  const [selectedStaffId, setSelectedStaffId] = useState('');

  const isHod = user?.role === 'DEPARTMENT_HEAD' || user?.role === 'DEPARTMENT_MANAGER';

  useEffect(() => {
    fetchDepartmentRequests();
  }, []);

  const fetchDepartmentRequests = async () => {
    setLoading(true);
    try {
      const res = await api.getRequests({ department_id: user?.department_id, limit: 100 });
      if (res.data) {
        setRequests(res.data);
      }

      // Fetch department staff list for assignment
      if (user?.department_id) {
        const staffRes = await api.getDepartmentStaff(user.department_id);
        if (staffRes.data) setDeptStaff(staffRes.data);
      }
    } catch (e) {
      toast.error('Failed to load department requests');
    } finally {
      setLoading(false);
    }
  };

  const handleClaimRequest = async (requestId) => {
    setActionLoading(requestId);
    try {
      await api.claimRequest(requestId);
      toast.success('Request claimed successfully!');
      fetchDepartmentRequests();
    } catch (err) {
      if (err.status === 409) {
        toast.error('This request has already been claimed by another staff member.');
      } else {
        toast.error(err.message || 'Failed to claim request');
      }
    } finally {
      setActionLoading(null);
    }
  };

  const handleAssignStaff = async (requestId) => {
    if (!selectedStaffId) return;
    setActionLoading(requestId);
    try {
      await api.assignRequest(requestId, Number(selectedStaffId));
      toast.success('Request assigned successfully!');
      setAssigningReqId(null);
      setSelectedStaffId('');
      fetchDepartmentRequests();
    } catch (err) {
      toast.error(err.message || 'Failed to assign request');
    } finally {
      setActionLoading(null);
    }
  };

  const incomingCount = requests.length;
  const unassignedCount = requests.filter((r) => !r.assigned_staff_id).length;
  const inProgressCount = requests.filter((r) => r.status === 'IN_PROGRESS' || r.status === 'ASSIGNED').length;
  const completedCount = requests.filter((r) => r.status === 'RESOLVED' || r.status === 'CLOSED').length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
            {isHod ? 'Department Head Dashboard & Operations' : 'Department Office Requests'}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Manage incoming requests for {user?.department_name || 'your department'}.
          </p>
        </div>
      </div>

      {/* HOD Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Incoming Requests</div>
            <div className="text-2xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{incomingCount}</div>
          </div>
          <div className="w-10 h-10 bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 rounded-xl flex items-center justify-center border border-purple-100 dark:border-purple-900/50">
            <Inbox className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Unassigned</div>
            <div className="text-2xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{unassignedCount}</div>
          </div>
          <div className="w-10 h-10 bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 rounded-xl flex items-center justify-center border border-rose-100 dark:border-rose-900/50">
            <AlertCircle className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">In Progress</div>
            <div className="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{inProgressCount}</div>
          </div>
          <div className="w-10 h-10 bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center border border-amber-100 dark:border-amber-900/50">
            <Clock className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Completed</div>
            <div className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{completedCount}</div>
          </div>
          <div className="w-10 h-10 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center border border-emerald-100 dark:border-emerald-900/50">
            <CheckCircle2 className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* Requests Table */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
          <h2 className="font-bold text-slate-900 dark:text-white text-sm">Department Requests Queue</h2>
        </div>

        {loading ? (
          <div className="p-8 text-center text-slate-400 dark:text-slate-500 text-xs">Loading requests...</div>
        ) : requests.length === 0 ? (
          <div className="p-12 text-center text-slate-400 dark:text-slate-500 text-xs italic">
            No incoming requests found for your department.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th className="px-6 py-3.5">Requester</th>
                  <th className="px-6 py-3.5">Problem</th>
                  <th className="px-6 py-3.5">Department</th>
                  <th className="px-6 py-3.5">Assigned To</th>
                  <th className="px-6 py-3.5">Status</th>
                  <th className="px-6 py-3.5">Date</th>
                  <th className="px-6 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                {requests.map((r) => (
                  <tr key={r.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
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
                    <td className="px-6 py-4 text-slate-600 dark:text-slate-300 font-semibold">
                      {r.assigned_staff_name || 'Unassigned'}
                    </td>
                    <td className="px-6 py-4"><StatusBadge status={r.status} /></td>
                    <td className="px-6 py-4 text-slate-500 dark:text-slate-400">
                      {r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}
                    </td>
                    <td className="px-6 py-4 text-right space-x-2">
                      {!r.assigned_staff_id && (
                        <button
                          disabled={actionLoading === r.id}
                          onClick={() => handleClaimRequest(r.id)}
                          className="bg-purple-600 hover:bg-purple-700 text-white font-bold px-3 py-1.5 rounded-lg shadow-sm text-xs transition-all disabled:opacity-50 inline-flex items-center gap-1"
                        >
                          <UserCheck className="w-3.5 h-3.5" /> Claim
                        </button>
                      )}

                      {isHod && (
                        <button
                          onClick={() => {
                            setAssigningReqId(r.id);
                            setSelectedStaffId(r.assigned_staff_id ? String(r.assigned_staff_id) : '');
                          }}
                          className="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold px-2.5 py-1.5 rounded-lg text-xs transition-all inline-flex items-center gap-1 border border-slate-200 dark:border-slate-700"
                        >
                          <UserPlus className="w-3.5 h-3.5" /> {r.assigned_staff_id ? 'Reassign' : 'Assign'}
                        </button>
                      )}

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

      {/* Staff Assignment Modal for HOD */}
      {assigningReqId && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4 animate-in fade-in">
            <h3 className="font-bold text-slate-900 dark:text-white text-base">Assign Request Handler</h3>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Select an employee from your department to assign to request #{assigningReqId}.
            </p>

            <div className="space-y-3">
              <select
                value={selectedStaffId}
                onChange={(e) => setSelectedStaffId(e.target.value)}
                className="w-full p-3 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-brand-500"
              >
                <option value="">-- Choose Department Employee --</option>
                {deptStaff.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.full_name}
                  </option>
                ))}
              </select>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setAssigningReqId(null)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 font-semibold text-xs transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  disabled={!selectedStaffId || actionLoading === assigningReqId}
                  onClick={() => handleAssignStaff(assigningReqId)}
                  className="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow text-xs transition-all disabled:opacity-50"
                >
                  Confirm Assignment
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
