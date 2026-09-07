// frontend/src/pages/AdminDashboard.jsx

import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { useToast } from '../contexts/ToastContext';
import { Users, Building2, Plus, ShieldCheck, UserCheck, Key, Lock, Unlock } from 'lucide-react';

export function AdminDashboard({ initialTab = 'users' }) {
  const toast = useToast();
  const [tab, setTab] = useState(initialTab);

  const [users, setUsers] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [loading, setLoading] = useState(true);

  // Modals / Forms state
  const [showDeptModal, setShowDeptModal] = useState(false);
  const [newDeptName, setNewDeptName] = useState('');
  const [newDeptCode, setNewDeptCode] = useState('');
  const [newDeptDesc, setNewDeptDesc] = useState('');

  const [showUserModal, setShowUserModal] = useState(false);
  const [newEmpId, setNewEmpId] = useState('');
  const [newFullName, setNewFullName] = useState('');
  const [newPhone, setNewPhone] = useState('');
  const [newEmail, setNewEmail] = useState('');
  const [newRole, setNewRole] = useState('EMPLOYEE');
  const [newDeptId, setNewDeptId] = useState('');
  const [newPassword, setNewPassword] = useState('password123');

  // Password reset modal state
  const [resetUserId, setResetUserId] = useState(null);
  const [resetPasswordVal, setResetPasswordVal] = useState('password123');

  useEffect(() => {
    setTab(initialTab);
  }, [initialTab]);

  useEffect(() => {
    fetchAdminData();
  }, []);

  const fetchAdminData = async () => {
    setLoading(true);
    try {
      const [uRes, dRes] = await Promise.all([
        api.getUsers(),
        api.getDepartments(true)
      ]);
      if (uRes.data) setUsers(uRes.data);
      if (dRes.data) setDepartments(dRes.data);
    } catch (e) {
      toast.error('Failed to load system management data');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateDept = async (e) => {
    e.preventDefault();
    if (!newDeptName || !newDeptCode) return;

    try {
      await api.createDepartment({
        name: newDeptName,
        code: newDeptCode,
        description: newDeptDesc
      });
      toast.success(`Department '${newDeptName}' created successfully!`);
      setShowDeptModal(false);
      setNewDeptName('');
      setNewDeptCode('');
      setNewDeptDesc('');
      fetchAdminData();
    } catch (err) {
      toast.error(err.message || 'Failed to create department');
    }
  };

  const handleToggleDeptStatus = async (dept) => {
    const nextStatus = dept.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    try {
      await api.updateDepartment(dept.id, { status: nextStatus });
      toast.success(`Department ${dept.name} status updated to ${nextStatus}`);
      fetchAdminData();
    } catch (err) {
      toast.error('Failed to update department status');
    }
  };

  const handleCreateUser = async (e) => {
    e.preventDefault();
    if (!newFullName || !newRole) return;

    const generatedEmpId = newEmpId || `EMP-${Date.now().toString().slice(-4)}`;
    const emailVal = newEmail || `${newFullName.toLowerCase().replace(/\s+/g, '.')}@officeassist.com`;

    try {
      await api.createUser({
        employee_id: generatedEmpId,
        full_name: newFullName,
        phone: newPhone,
        email: emailVal,
        role: newRole,
        department_id: newDeptId ? Number(newDeptId) : null,
        password: newPassword || 'password123'
      });
      toast.success(`User '${newFullName}' created successfully!`);
      setShowUserModal(false);
      setNewEmpId('');
      setNewFullName('');
      setNewPhone('');
      setNewEmail('');
      setNewRole('EMPLOYEE');
      setNewDeptId('');
      setNewPassword('password123');
      fetchAdminData();
    } catch (err) {
      toast.error(err.message || 'Failed to create user');
    }
  };

  const handleToggleUserStatus = async (userObj) => {
    const nextStatus = userObj.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    try {
      await api.updateUser(userObj.id, { status: nextStatus });
      toast.success(`User '${userObj.full_name}' status set to ${nextStatus}`);
      fetchAdminData();
    } catch (err) {
      toast.error('Failed to update user status');
    }
  };

  const handleResetPassword = async (e) => {
    e.preventDefault();
    if (!resetUserId || !resetPasswordVal) return;
    try {
      await api.resetUserPassword(resetUserId, resetPasswordVal);
      toast.success('Password reset successfully');
      setResetUserId(null);
      setResetPasswordVal('password123');
    } catch (err) {
      toast.error(err.message || 'Password reset failed');
    }
  };

  const totalUsers = users.length;
  const activeUsers = users.filter((u) => u.status === 'ACTIVE').length;
  const totalDepartments = departments.length;
  const departmentHeadsCount = users.filter((u) => u.role === 'DEPARTMENT_HEAD' || u.role === 'DEPARTMENT_MANAGER').length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <ShieldCheck className="w-6 h-6 text-brand-600 dark:text-brand-400" />
            System Administration
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Manage user accounts, roles, and department structures.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={() => setShowDeptModal(true)}
            className="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2 rounded-xl text-xs shadow flex items-center gap-1.5 transition-all"
          >
            <Plus className="w-4 h-4" /> Create Department
          </button>
          <button
            onClick={() => setShowUserModal(true)}
            className="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2 rounded-xl text-xs shadow flex items-center gap-1.5 transition-all"
          >
            <Plus className="w-4 h-4" /> Create User
          </button>
        </div>
      </div>

      {/* Admin Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Users</div>
            <div className="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{totalUsers}</div>
          </div>
          <div className="w-10 h-10 bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 rounded-xl flex items-center justify-center border border-blue-100 dark:border-blue-900/50">
            <Users className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Active Users</div>
            <div className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{activeUsers}</div>
          </div>
          <div className="w-10 h-10 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center border border-emerald-100 dark:border-emerald-900/50">
            <UserCheck className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Departments</div>
            <div className="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{totalDepartments}</div>
          </div>
          <div className="w-10 h-10 bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center border border-amber-100 dark:border-amber-900/50">
            <Building2 className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Department Heads</div>
            <div className="text-2xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{departmentHeadsCount}</div>
          </div>
          <div className="w-10 h-10 bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 rounded-xl flex items-center justify-center border border-purple-100 dark:border-purple-900/50">
            <ShieldCheck className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800">
        <button
          onClick={() => setTab('users')}
          className={`pb-3 px-4 font-bold text-xs border-b-2 transition-all flex items-center gap-2 ${
            tab === 'users' ? 'border-brand-600 dark:border-brand-400 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          }`}
        >
          <Users className="w-4 h-4" /> Users ({users.length})
        </button>

        <button
          onClick={() => setTab('departments')}
          className={`pb-3 px-4 font-bold text-xs border-b-2 transition-all flex items-center gap-2 ${
            tab === 'departments' ? 'border-brand-600 dark:border-brand-400 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          }`}
        >
          <Building2 className="w-4 h-4" /> Departments ({departments.length})
        </button>
      </div>

      {/* Tab 1: Users */}
      {tab === 'users' && (
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
          {loading ? (
            <div className="p-8 text-center text-slate-400 dark:text-slate-500 text-xs">Loading users...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800">
                  <tr>
                    <th className="px-6 py-3.5">Name</th>
                    <th className="px-6 py-3.5">Role</th>
                    <th className="px-6 py-3.5">Department</th>
                    <th className="px-6 py-3.5">Status</th>
                    <th className="px-6 py-3.5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                  {users.map((u) => (
                    <tr key={u.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                      <td className="px-6 py-4 font-bold text-slate-900 dark:text-slate-200">
                        <div>{u.full_name}</div>
                        {u.phone && <div className="text-[10px] text-slate-400 font-normal">{u.phone}</div>}
                      </td>
                      <td className="px-6 py-4">
                        <span className={`px-2.5 py-1 rounded-full font-bold text-[10px] ${
                          u.role === 'SYSTEM_ADMIN' ? 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300' :
                          u.role === 'DEPARTMENT_HEAD' || u.role === 'DEPARTMENT_MANAGER' ? 'bg-amber-100 dark:bg-amber-950/70 text-amber-800 dark:text-amber-300' :
                          'bg-blue-50 dark:bg-blue-950/70 text-blue-800 dark:text-blue-300'
                        }`}>
                          {u.role === 'DEPARTMENT_MANAGER' ? 'DEPARTMENT_HEAD' : u.role === 'DEPARTMENT_STAFF' ? 'EMPLOYEE' : u.role}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-slate-600 dark:text-slate-400">{u.department_name || '—'}</td>
                      <td className="px-6 py-4">
                        <span className={`px-2 py-0.5 rounded-full font-bold text-[10px] ${u.status === 'ACTIVE' ? 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'}`}>
                          {u.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right space-x-2">
                        <button
                          onClick={() => handleToggleUserStatus(u)}
                          className={`text-xs font-bold px-2.5 py-1 rounded-lg border transition-colors ${
                            u.status === 'ACTIVE' 
                              ? 'border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40' 
                              : 'border-emerald-200 dark:border-emerald-900/50 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                          }`}
                        >
                          {u.status === 'ACTIVE' ? 'Deactivate' : 'Activate'}
                        </button>
                        <button
                          onClick={() => setResetUserId(u.id)}
                          className="text-xs font-semibold px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                          Reset Password
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Tab 2: Departments */}
      {tab === 'departments' && (
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
          {loading ? (
            <div className="p-8 text-center text-slate-400 dark:text-slate-500 text-xs">Loading departments...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800">
                  <tr>
                    <th className="px-6 py-3.5">Code</th>
                    <th className="px-6 py-3.5">Department Name</th>
                    <th className="px-6 py-3.5">HOD</th>
                    <th className="px-6 py-3.5">Status</th>
                    <th className="px-6 py-3.5 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                  {departments.map((d) => {
                    const hod = users.find((u) => Number(u.department_id) === Number(d.id) && (u.role === 'DEPARTMENT_HEAD' || u.role === 'DEPARTMENT_MANAGER'));
                    return (
                      <tr key={d.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                        <td className="px-6 py-4 font-mono font-bold text-brand-700 dark:text-brand-300">{d.code}</td>
                        <td className="px-6 py-4 font-bold text-slate-900 dark:text-slate-200">{d.name}</td>
                        <td className="px-6 py-4 font-semibold text-purple-700 dark:text-purple-300">
                          {hod ? hod.full_name : '—'}
                        </td>
                        <td className="px-6 py-4">
                          <span className={`px-2 py-0.5 rounded-full font-bold text-[10px] ${d.status === 'ACTIVE' ? 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'}`}>
                            {d.status}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-right">
                          <button
                            onClick={() => handleToggleDeptStatus(d)}
                            className={`text-xs font-bold px-3 py-1 rounded-lg border transition-colors ${
                              d.status === 'ACTIVE' 
                                ? 'border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40' 
                                : 'border-emerald-200 dark:border-emerald-900/50 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                            }`}
                          >
                            {d.status === 'ACTIVE' ? 'Deactivate' : 'Activate'}
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Modal: Create Department */}
      {showDeptModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4 animate-in fade-in">
            <h3 className="font-bold text-slate-900 dark:text-white text-lg">Create Department</h3>
            <form onSubmit={handleCreateDept} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Department Name *</label>
                <input
                  type="text"
                  value={newDeptName}
                  onChange={(e) => setNewDeptName(e.target.value)}
                  placeholder="e.g. Information Technology"
                  className="w-full p-3 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                  required
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Department Code *</label>
                <input
                  type="text"
                  value={newDeptCode}
                  onChange={(e) => setNewDeptCode(e.target.value)}
                  placeholder="e.g. IT"
                  className="w-full p-3 border border-slate-200 dark:border-slate-700 rounded-xl font-mono uppercase bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                  required
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowDeptModal(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl shadow"
                >
                  Create Department
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Create User */}
      {showUserModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4 animate-in fade-in">
            <h3 className="font-bold text-slate-900 dark:text-white text-lg">Create User</h3>
            <form onSubmit={handleCreateUser} className="space-y-3 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Name *</label>
                <input
                  type="text"
                  value={newFullName}
                  onChange={(e) => setNewFullName(e.target.value)}
                  placeholder="e.g. Jane Doe"
                  className="w-full p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                  required
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone</label>
                <input
                  type="text"
                  value={newPhone}
                  onChange={(e) => setNewPhone(e.target.value)}
                  placeholder="e.g. +1 555-0199"
                  className="w-full p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Role *</label>
                <select
                  value={newRole}
                  onChange={(e) => setNewRole(e.target.value)}
                  className="w-full p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium"
                >
                  <option value="EMPLOYEE">EMPLOYEE</option>
                  <option value="DEPARTMENT_HEAD">DEPARTMENT_HEAD</option>
                  <option value="SYSTEM_ADMIN">SYSTEM_ADMIN</option>
                </select>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Department</label>
                <select
                  value={newDeptId}
                  onChange={(e) => setNewDeptId(e.target.value)}
                  className="w-full p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium"
                >
                  <option value="">-- Select Department --</option>
                  {departments.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name} ({d.code})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Password *</label>
                <input
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  placeholder="Password"
                  className="w-full p-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                  required
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowUserModal(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow"
                >
                  Create User
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Reset Password */}
      {resetUserId && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4 animate-in fade-in">
            <h3 className="font-bold text-slate-900 dark:text-white text-base">Reset User Password</h3>
            <form onSubmit={handleResetPassword} className="space-y-3 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
                <input
                  type="text"
                  value={resetPasswordVal}
                  onChange={(e) => setResetPasswordVal(e.target.value)}
                  className="w-full p-3 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                  required
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setResetUserId(null)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow"
                >
                  Save Password
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

