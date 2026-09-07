// frontend/src/pages/RequestDetail.jsx

import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { api } from '../services/api';
import { StatusBadge } from '../components/StatusBadge';
import { 
  ArrowLeft, 
  User, 
  Building, 
  MessageSquare, 
  Paperclip, 
  Download, 
  Send, 
  CheckCircle2, 
  UserCheck,
  UserPlus
} from 'lucide-react';

export function RequestDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();

  const [request, setRequest] = useState(null);
  const [loading, setLoading] = useState(true);
  
  // Comment State
  const [newComment, setNewComment] = useState('');
  const [submittingComment, setSubmittingComment] = useState(false);

  // Assign Modal for HOD
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [deptStaff, setDeptStaff] = useState([]);
  const [selectedStaffId, setSelectedStaffId] = useState('');

  const [updatingStatus, setUpdatingStatus] = useState(false);

  useEffect(() => {
    fetchRequestDetail();
  }, [id]);

  const fetchRequestDetail = async () => {
    try {
      const res = await api.getRequest(id);
      if (res.data) {
        setRequest(res.data);

        // Fetch department staff if HOD or target department member
        const isTargetDept = Number(res.data.department_id) === Number(user?.department_id);
        const isHod = user?.role === 'DEPARTMENT_HEAD' || user?.role === 'DEPARTMENT_MANAGER';
        if (isTargetDept && isHod) {
          const staffRes = await api.getDepartmentStaff(res.data.department_id);
          if (staffRes.data) setDeptStaff(staffRes.data);
        }
      }
    } catch (err) {
      toast.error(err.message || 'Failed to load request detail');
      if (err.status === 403 || err.status === 404) {
        navigate('/dashboard');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleStatusChange = async (targetStatus, commentText = '') => {
    setUpdatingStatus(true);
    try {
      const res = await api.updateStatus(id, {
        status: targetStatus,
        comment: commentText || `Status updated to ${targetStatus}`
      });
      toast.success(`Request marked as ${targetStatus.toLowerCase()}`);
      setRequest(res.data);
    } catch (err) {
      toast.error(err.message || 'Status update failed');
    } finally {
      setUpdatingStatus(false);
    }
  };

  const handleClaim = async () => {
    setUpdatingStatus(true);
    try {
      const res = await api.claimRequest(id);
      toast.success('Request claimed successfully!');
      setRequest(res.data);
    } catch (err) {
      if (err.status === 409) {
        toast.error('This request has already been claimed.');
      } else {
        toast.error(err.message || 'Failed to claim request');
      }
    } finally {
      setUpdatingStatus(false);
    }
  };

  const handleAssignStaff = async () => {
    if (!selectedStaffId) return;
    setUpdatingStatus(true);
    try {
      const res = await api.assignRequest(id, Number(selectedStaffId));
      toast.success('Staff assigned to request');
      setRequest(res.data);
      setShowAssignModal(false);
    } catch (err) {
      toast.error(err.message || 'Failed to assign staff');
    } finally {
      setUpdatingStatus(false);
    }
  };

  const handleAddComment = async (e) => {
    e.preventDefault();
    if (!newComment.trim()) return;

    setSubmittingComment(true);
    try {
      const res = await api.addComment(id, {
        comment: newComment,
        is_internal: false
      });
      toast.success('Comment added');
      setRequest(res.data);
      setNewComment('');
    } catch (err) {
      toast.error(err.message || 'Failed to add comment');
    } finally {
      setSubmittingComment(false);
    }
  };

  if (loading) {
    return <div className="p-12 text-center text-slate-400 dark:text-slate-500 text-xs">Loading request details...</div>;
  }

  if (!request) return null;

  const isAdmin = user?.role === 'SYSTEM_ADMIN';
  const isHod = (user?.role === 'DEPARTMENT_HEAD' || user?.role === 'DEPARTMENT_MANAGER') && Number(user?.department_id) === Number(request.department_id);
  const isTargetDeptEmployee = Number(user?.department_id) === Number(request.department_id) && !isAdmin;
  const isAssignedHandler = Number(user?.id) === Number(request.assigned_staff_id);
  const isCompleted = request.status === 'RESOLVED' || request.status === 'CLOSED';

  return (
    <div className="space-y-6 max-w-4xl mx-auto">
      {/* Back Header */}
      <div className="flex items-center justify-between">
        <button
          onClick={() => navigate(-1)}
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors"
        >
          <ArrowLeft className="w-4 h-4" /> Back
        </button>
        <span className="font-mono text-xs font-bold text-slate-400">Request #{request.request_number}</span>
      </div>

      {/* Main Request Card */}
      <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
          <div>
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Problem Subject</span>
            <h1 className="text-xl font-bold text-slate-900 dark:text-white">{request.title}</h1>
          </div>
          <div>
            <StatusBadge status={request.status} />
          </div>
        </div>

        {/* Detailed Grid: Problem, Requester, Departments, Handler, Status, Date */}
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs">
          <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Requester</span>
            <span className="font-bold text-slate-900 dark:text-white block">{request.requester_name}</span>
          </div>

          <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Requester Department</span>
            <span className="font-semibold text-slate-800 dark:text-slate-200 block">{request.requester_department_name || '—'}</span>
          </div>

          <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Target Department</span>
            <span className="font-bold text-purple-600 dark:text-purple-400 block">{request.department_name}</span>
          </div>

          <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Assigned To</span>
            <span className="font-semibold text-slate-800 dark:text-slate-200 block">{request.assigned_staff_name || 'Unassigned'}</span>
          </div>

          <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Status</span>
            <span className="font-bold text-slate-900 dark:text-white block">{request.status}</span>
          </div>

          <div className="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider block">Created Date</span>
            <span className="font-semibold text-slate-700 dark:text-slate-300 block">
              {request.created_at ? new Date(request.created_at).toLocaleString() : '—'}
            </span>
          </div>
        </div>

        {/* Description Body */}
        <div className="space-y-2 pt-2">
          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Description</span>
          <p className="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/40 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 whitespace-pre-wrap leading-relaxed">
            {request.description}
          </p>
        </div>

        {/* Attachments Section */}
        {request.attachments && request.attachments.length > 0 && (
          <div className="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Attachments</span>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              {request.attachments.map((att) => (
                <div key={att.id} className="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                  <span className="truncate font-semibold text-slate-800 dark:text-slate-200 mr-2">{att.original_name}</span>
                  <a
                    href={`/api/requests/${request.id}/attachments/${att.id}/download`}
                    target="_blank"
                    rel="noreferrer"
                    className="p-1.5 bg-white dark:bg-slate-700 text-brand-600 dark:text-brand-300 rounded-lg border border-slate-200 dark:border-slate-600 transition-colors flex items-center gap-1 text-[11px] font-bold"
                  >
                    <Download className="w-3.5 h-3.5" /> Download
                  </a>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Relevant Role-Based Actions (System Admin strictly disabled) */}
        {!isAdmin && !isCompleted && (
          <div className="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-3">
            {/* Unassigned Request Actions */}
            {!request.assigned_staff_id && isTargetDeptEmployee && (
              <button
                disabled={updatingStatus}
                onClick={handleClaim}
                className="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow transition-all flex items-center gap-1.5"
              >
                <UserCheck className="w-4 h-4" /> Claim Request
              </button>
            )}

            {/* HOD Actions */}
            {isHod && (
              <>
                <button
                  onClick={() => setShowAssignModal(true)}
                  className="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-800 dark:text-slate-200 font-bold text-xs px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 transition-all flex items-center gap-1.5"
                >
                  <UserPlus className="w-4 h-4" /> {request.assigned_staff_id ? 'Reassign' : 'Assign Employee'}
                </button>
                {!request.assigned_staff_id && (
                  <button
                    disabled={updatingStatus}
                    onClick={handleClaim}
                    className="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow transition-all flex items-center gap-1.5"
                  >
                    <UserCheck className="w-4 h-4" /> Claim Personally
                  </button>
                )}
              </>
            )}

            {/* Handling Worker Actions */}
            {(isAssignedHandler || (isHod && request.assigned_staff_id)) && (
              <button
                disabled={updatingStatus}
                onClick={() => handleStatusChange('RESOLVED', `${user.full_name} completed request.`)}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow transition-all flex items-center gap-1.5"
              >
                <CheckCircle2 className="w-4 h-4" /> Mark as Completed
              </button>
            )}
          </div>
        )}
      </div>

      {/* Simple Comments Section */}
      <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <h3 className="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-2">
          <MessageSquare className="w-4 h-4 text-brand-600 dark:text-brand-400" /> Comments & Activity
        </h3>

        <div className="space-y-3">
          {request.comments && request.comments.length > 0 ? (
            request.comments.map((c) => (
              <div
                key={c.id}
                className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-xs space-y-1"
              >
                <div className="flex items-center justify-between">
                  <span className="font-bold text-slate-900 dark:text-white">{c.user_name}</span>
                  <span className="text-[10px] text-slate-400">
                    {new Date(c.created_at).toLocaleString()}
                  </span>
                </div>
                <p className="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{c.comment}</p>
              </div>
            ))
          ) : (
            <div className="text-center p-4 text-slate-400 dark:text-slate-500 text-xs italic">No comments yet.</div>
          )}
        </div>

        {/* Add Comment Form (Disabled for System Admin) */}
        {!isAdmin && (
          <form onSubmit={handleAddComment} className="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3">
            <textarea
              value={newComment}
              onChange={(e) => setNewComment(e.target.value)}
              placeholder="Write a simple comment..."
              rows={2}
              className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-brand-500"
            />
            <div className="flex justify-end">
              <button
                type="submit"
                disabled={submittingComment || !newComment.trim()}
                className="bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs px-4 py-2 rounded-xl shadow transition-all flex items-center gap-1.5 disabled:opacity-50"
              >
                <Send className="w-3.5 h-3.5" /> Send Comment
              </button>
            </div>
          </form>
        )}
      </div>

      {/* Assign Modal for HOD */}
      {showAssignModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4 animate-in fade-in">
            <h3 className="font-bold text-slate-900 dark:text-white text-base">Assign Department Employee</h3>
            <div className="space-y-3">
              <select
                value={selectedStaffId}
                onChange={(e) => setSelectedStaffId(e.target.value)}
                className="w-full p-3 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium"
              >
                <option value="">-- Choose Employee --</option>
                {deptStaff.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.full_name}
                  </option>
                ))}
              </select>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowAssignModal(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300 font-semibold text-xs"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  disabled={!selectedStaffId || updatingStatus}
                  onClick={handleAssignStaff}
                  className="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow text-xs disabled:opacity-50"
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

