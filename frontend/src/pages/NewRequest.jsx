// frontend/src/pages/NewRequest.jsx

import React, { useState, useEffect } from 'react';
import { useToast } from '../contexts/ToastContext';
import { api } from '../services/api';
import { useNavigate, Link } from 'react-router-dom';
import { Paperclip, Send, CheckCircle2, ArrowLeft, Trash2 } from 'lucide-react';

export function NewRequest() {
  const toast = useToast();
  const navigate = useNavigate();

  const [departments, setDepartments] = useState([]);
  const [departmentId, setDepartmentId] = useState('');
  const [problemType, setProblemType] = useState('');
  const [description, setDescription] = useState('');
  const [attachment, setAttachment] = useState(null);

  const [submitting, setSubmitting] = useState(false);
  const [submittedRequest, setSubmittedRequest] = useState(null);

  useEffect(() => {
    fetchDepartments();
  }, []);

  const fetchDepartments = async () => {
    try {
      const res = await api.getDepartments();
      if (res.data) setDepartments(res.data);
    } catch (e) {
      toast.error('Failed to load departments');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (submitting) return;
    if (!departmentId || !problemType || !description) {
      toast.error('Please fill in Target Department, Problem Type, and Description.');
      return;
    }

    setSubmitting(true);

    try {
      const formData = new FormData();
      formData.append('department_id', departmentId);
      formData.append('title', problemType);
      formData.append('description', description);
      formData.append('priority', 'NORMAL');

      if (attachment) {
        formData.append('attachment', attachment);
      }

      const res = await api.createRequest(formData);
      setSubmittedRequest(res.data);
      toast.success('Request sent successfully!');
    } catch (err) {
      toast.error(err.message || 'Failed to send request');
    } finally {
      setSubmitting(false);
    }
  };

  if (submittedRequest) {
    return (
      <div className="max-w-xl mx-auto py-12 animate-in fade-in">
        <div className="bg-white dark:bg-slate-900 rounded-3xl p-8 border border-slate-200/80 dark:border-slate-800 shadow-xl text-center space-y-6">
          <div className="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto border border-emerald-100 dark:border-emerald-900/50">
            <CheckCircle2 className="w-8 h-8" />
          </div>

          <div className="space-y-1">
            <h1 className="text-xl font-bold text-slate-900 dark:text-white">Request Sent Successfully!</h1>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Request <strong className="text-slate-900 dark:text-white">#{submittedRequest.request_number}</strong> has been sent to {submittedRequest.department_name}.
            </p>
          </div>

          <div className="flex items-center justify-center gap-3 pt-2">
            <Link
              to={`/requests/${submittedRequest.id}`}
              className="bg-brand-600 hover:bg-brand-700 text-white font-bold px-6 py-2.5 rounded-xl shadow text-xs"
            >
              View Request →
            </Link>
            <Link
              to="/dashboard"
              className="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-semibold px-5 py-2.5 rounded-xl text-xs"
            >
              Back to Dashboard
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <button
        onClick={() => navigate(-1)}
        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors"
      >
        <ArrowLeft className="w-4 h-4" /> Back
      </button>

      <div className="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xl overflow-hidden">
        <div className="bg-slate-900 text-white p-6">
          <h1 className="text-xl font-bold tracking-tight">New Service Request</h1>
          <p className="text-slate-400 text-xs mt-1">Send a request to another department.</p>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-5 text-xs">
          {/* Target Department */}
          <div>
            <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wider text-[11px]">
              Target Department <span className="text-red-500">*</span>
            </label>
            <select
              value={departmentId}
              onChange={(e) => setDepartmentId(e.target.value)}
              className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium text-xs focus:ring-2 focus:ring-brand-500"
              required
            >
              <option value="">-- Select Target Department --</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name} ({d.code})
                </option>
              ))}
            </select>
          </div>

          {/* Problem Type */}
          <div>
            <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wider text-[11px]">
              Problem Type / Subject <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={problemType}
              onChange={(e) => setProblemType(e.target.value)}
              placeholder="e.g. Printer paper jammed or Wi-Fi offline"
              className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium text-xs focus:ring-2 focus:ring-brand-500"
              required
            />
          </div>

          {/* Description */}
          <div>
            <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wider text-[11px]">
              Description <span className="text-red-500">*</span>
            </label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={4}
              placeholder="Describe the issue and how the department can help..."
              className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium text-xs focus:ring-2 focus:ring-brand-500"
              required
            />
          </div>

          {/* Attachment (optional) */}
          <div>
            <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wider text-[11px]">
              Attachment <span className="text-slate-400 font-normal">(Optional)</span>
            </label>
            <div className="flex items-center gap-3">
              <label className="cursor-pointer bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-semibold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition-colors">
                <Paperclip className="w-4 h-4 text-slate-500" />
                <span>Choose File</span>
                <input
                  type="file"
                  onChange={(e) => setAttachment(e.target.files[0] || null)}
                  className="hidden"
                />
              </label>

              {attachment && (
                <div className="flex items-center gap-2 bg-brand-50 dark:bg-brand-950/60 border border-brand-100 dark:border-brand-900/50 text-brand-700 dark:text-brand-300 px-3 py-1.5 rounded-xl text-xs font-semibold">
                  <span>{attachment.name}</span>
                  <button
                    type="button"
                    onClick={() => setAttachment(null)}
                    className="text-brand-500 hover:text-red-600 p-0.5"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Submit Action */}
          <div className="pt-3 flex justify-end">
            <button
              type="submit"
              disabled={submitting || !departmentId || !problemType || !description}
              className="bg-brand-600 hover:bg-brand-700 text-white font-bold px-6 py-3 rounded-xl shadow-md transition-all flex items-center gap-2 text-xs disabled:opacity-50"
            >
              <Send className="w-4 h-4" />
              <span>Send Request</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

