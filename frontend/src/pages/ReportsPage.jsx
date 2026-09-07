// frontend/src/pages/ReportsPage.jsx

import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { Download, BarChart2, Users, Building, Calendar, RefreshCw } from 'lucide-react';

export function ReportsPage() {
  const [activeTab, setActiveTab] = useState('volume');
  const [loading, setLoading] = useState(true);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  const [volumeData, setVolumeData] = useState([]);
  const [deptData, setDeptData] = useState([]);
  const [staffData, setStaffData] = useState([]);

  useEffect(() => {
    fetchReportData();
  }, [activeTab, startDate, endDate]);

  const fetchReportData = async () => {
    setLoading(true);
    try {
      if (activeTab === 'volume') {
        const res = await api.getRequestVolumeReport({ start_date: startDate, end_date: endDate });
        if (res.data) setVolumeData(res.data);
      } else if (activeTab === 'departments') {
        const res = await api.getDepartmentPerformanceReport();
        if (res.data) setDeptData(res.data);
      } else if (activeTab === 'staff') {
        const res = await api.getStaffPerformanceReport();
        if (res.data) setStaffData(res.data);
      }
    } catch (e) {
      // Handle error
    } finally {
      setLoading(false);
    }
  };

  const handleExportCSV = async () => {
    try {
      await api.exportReportCSV(activeTab, { start_date: startDate, end_date: endDate });
    } catch (e) {
      alert('Failed to export report CSV');
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">Analytics & Reports</h1>
          <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">Service metrics, department throughput, staff workload, and volume trends.</p>
        </div>

        <button
          onClick={handleExportCSV}
          className="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2.5 rounded-xl shadow transition-colors flex items-center gap-2 text-xs w-fit"
        >
          <Download className="w-4 h-4" /> Export CSV
        </button>
      </div>

      {/* Tabs & Date Filters */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4 transition-colors duration-200">
        {/* Tabs */}
        <div className="flex items-center gap-2 bg-gray-100 dark:bg-slate-800 p-1.5 rounded-xl text-xs w-full md:w-auto overflow-x-auto">
          <button
            onClick={() => setActiveTab('volume')}
            className={`px-4 py-2 rounded-lg font-bold transition-colors whitespace-nowrap flex items-center gap-2 ${
              activeTab === 'volume' ? 'bg-white dark:bg-slate-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            <BarChart2 className="w-3.5 h-3.5" /> Request Volume
          </button>

          <button
            onClick={() => setActiveTab('departments')}
            className={`px-4 py-2 rounded-lg font-bold transition-colors whitespace-nowrap flex items-center gap-2 ${
              activeTab === 'departments' ? 'bg-white dark:bg-slate-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            <Building className="w-3.5 h-3.5" /> Department Performance
          </button>

          <button
            onClick={() => setActiveTab('staff')}
            className={`px-4 py-2 rounded-lg font-bold transition-colors whitespace-nowrap flex items-center gap-2 ${
              activeTab === 'staff' ? 'bg-white dark:bg-slate-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            <Users className="w-3.5 h-3.5" /> Staff Workload
          </button>
        </div>

        {/* Date Filter */}
        {activeTab === 'volume' && (
          <div className="flex items-center gap-3 text-xs w-full md:w-auto">
            <div className="flex items-center gap-2 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-1.5 bg-white dark:bg-slate-800 text-gray-900 dark:text-white">
              <Calendar className="w-3.5 h-3.5 text-gray-400 dark:text-slate-500" />
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="focus:outline-none text-xs bg-transparent text-gray-700 dark:text-slate-200"
              />
              <span className="text-gray-400 dark:text-slate-500">to</span>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="focus:outline-none text-xs bg-transparent text-gray-700 dark:text-slate-200"
              />
            </div>
            {(startDate || endDate) && (
              <button
                onClick={() => { setStartDate(''); setEndDate(''); }}
                className="text-brand-600 dark:text-brand-400 font-semibold hover:underline"
              >
                Reset
              </button>
            )}
          </div>
        )}
      </div>

      {/* Main Report Table Container */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden transition-colors duration-200">
        {loading ? (
          <div className="p-12 text-center text-gray-400 dark:text-slate-500 text-sm flex items-center justify-center gap-2">
            <RefreshCw className="w-4 h-4 animate-spin text-brand-600 dark:text-brand-400" /> Loading report data...
          </div>
        ) : activeTab === 'volume' ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 dark:bg-slate-800/60 text-gray-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider border-b border-gray-100 dark:border-slate-800">
                <tr>
                  <th className="px-6 py-3.5">Date</th>
                  <th className="px-6 py-3.5 text-right">Total Requests Received</th>
                  <th className="px-6 py-3.5 text-right">Resolved Requests</th>
                  <th className="px-6 py-3.5 text-right">Cancelled</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-slate-800/60 font-medium text-xs">
                {volumeData.length === 0 ? (
                  <tr><td colSpan={4} className="px-6 py-8 text-center text-gray-400 dark:text-slate-500">No volume records found for date range</td></tr>
                ) : (
                  volumeData.map((row, i) => (
                    <tr key={i} className="hover:bg-gray-50/80 dark:hover:bg-slate-800/40">
                      <td className="px-6 py-4 font-semibold text-gray-900 dark:text-white">{row.date_key}</td>
                      <td className="px-6 py-4 text-right font-mono font-bold text-gray-900 dark:text-white">{row.request_count}</td>
                      <td className="px-6 py-4 text-right font-mono text-emerald-600 dark:text-emerald-400 font-bold">{row.resolved_count}</td>
                      <td className="px-6 py-4 text-right font-mono text-gray-400 dark:text-slate-500">{row.cancelled_count}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        ) : activeTab === 'departments' ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 dark:bg-slate-800/60 text-gray-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider border-b border-gray-100 dark:border-slate-800">
                <tr>
                  <th className="px-6 py-3.5">Department</th>
                  <th className="px-6 py-3.5 text-right">Total Received</th>
                  <th className="px-6 py-3.5 text-right">Total Resolved</th>
                  <th className="px-6 py-3.5 text-right">Currently Open</th>
                  <th className="px-6 py-3.5 text-right">Avg Response (Mins)</th>
                  <th className="px-6 py-3.5 text-right">Avg Resolution (Mins)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-slate-800/60 font-medium text-xs">
                {deptData.map((row) => (
                  <tr key={row.department_id} className="hover:bg-gray-50/80 dark:hover:bg-slate-800/40">
                    <td className="px-6 py-4 font-bold text-gray-900 dark:text-white">{row.department_name} ({row.code})</td>
                    <td className="px-6 py-4 text-right font-mono font-bold text-gray-900 dark:text-white">{row.total_received}</td>
                    <td className="px-6 py-4 text-right font-mono text-emerald-600 dark:text-emerald-400 font-bold">{row.total_resolved}</td>
                    <td className="px-6 py-4 text-right font-mono text-amber-600 dark:text-amber-400 font-bold">{row.total_open}</td>
                    <td className="px-6 py-4 text-right font-mono text-gray-700 dark:text-slate-300">{row.avg_response_minutes ?? '—'}</td>
                    <td className="px-6 py-4 text-right font-mono text-gray-700 dark:text-slate-300">{row.avg_resolution_minutes ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 dark:bg-slate-800/60 text-gray-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider border-b border-gray-100 dark:border-slate-800">
                <tr>
                  <th className="px-6 py-3.5">Staff Member</th>
                  <th className="px-6 py-3.5">Employee ID</th>
                  <th className="px-6 py-3.5">Department</th>
                  <th className="px-6 py-3.5 text-right">Assigned Total</th>
                  <th className="px-6 py-3.5 text-right">Resolved Total</th>
                  <th className="px-6 py-3.5 text-right">Current Active Workload</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-slate-800/60 font-medium text-xs">
                {staffData.map((row) => (
                  <tr key={row.staff_id} className="hover:bg-gray-50/80 dark:hover:bg-slate-800/40">
                    <td className="px-6 py-4 font-bold text-gray-900 dark:text-white">{row.staff_name}</td>
                    <td className="px-6 py-4 font-mono text-gray-500 dark:text-slate-400">{row.employee_id}</td>
                    <td className="px-6 py-4 text-gray-600 dark:text-slate-300">{row.department_name}</td>
                    <td className="px-6 py-4 text-right font-mono font-bold text-gray-900 dark:text-white">{row.assigned_total}</td>
                    <td className="px-6 py-4 text-right font-mono text-emerald-600 dark:text-emerald-400 font-bold">{row.resolved_total}</td>
                    <td className="px-6 py-4 text-right font-mono text-amber-600 dark:text-amber-400 font-bold">{row.active_workload}</td>
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
