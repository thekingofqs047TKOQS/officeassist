// frontend/src/pages/Login.jsx

import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { useNavigate } from 'react-router-dom';
import { LogIn, UserCheck } from 'lucide-react';

export function Login() {
  const { login } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!username || !password) {
      toast.error('Please enter both email/username and password');
      return;
    }

    setSubmitting(true);
    try {
      const res = await login(username, password);
      toast.success('Successfully logged in!');
      if (res?.data?.user?.must_change_password) {
        navigate('/change-password', { replace: true });
      } else {
        navigate('/dashboard', { replace: true });
      }
    } catch (err) {
      toast.error(err.message || 'Login failed. Please check credentials.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleQuickLogin = (email) => {
    setUsername(email);
    setPassword('password123');
  };

  return (
    <div className="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl p-8 border border-gray-100 dark:border-slate-800 transition-colors duration-200">
      {/* Brand Header */}
      <div className="text-center mb-8">
        <div className="w-12 h-12 bg-brand-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl mx-auto shadow-lg shadow-brand-600/30 mb-3">
          OA
        </div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">OfficeAssist</h1>
        <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Sign in to your account</p>
      </div>

      {/* Login Form */}
      <form onSubmit={handleSubmit} className="space-y-5">
        <div>
          <label className="block text-xs font-semibold text-gray-700 dark:text-slate-300 uppercase tracking-wider mb-2">
            Email or Employee ID
          </label>
          <input
            type="text"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            placeholder="sarah.jenkins@officeassist.com"
            className="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent text-sm transition-all"
            required
          />
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-700 dark:text-slate-300 uppercase tracking-wider mb-2">
            Password
          </label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
            className="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent text-sm transition-all"
            required
          />
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 px-4 rounded-xl shadow-md shadow-brand-500/20 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2 disabled:opacity-50"
        >
          {submitting ? (
            <span className="animate-spin rounded-full h-5 w-5 border-b-2 border-white" />
          ) : (
            <>
              <LogIn className="w-5 h-5" />
              <span>Sign In</span>
            </>
          )}
        </button>
      </form>

      {/* Quick Demo Test Accounts */}
      <div className="mt-8 pt-6 border-t border-gray-100 dark:border-slate-800">
        <div className="text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-1.5">
          <UserCheck className="w-4 h-4 text-brand-600 dark:text-brand-400" />
          Quick Test Accounts (Password: password123)
        </div>
        <div className="grid grid-cols-2 gap-2 text-xs">
          <button
            type="button"
            onClick={() => handleQuickLogin('sarah.jenkins@officeassist.com')}
            className="p-2.5 rounded-xl border border-gray-200 dark:border-slate-700 dark:bg-slate-800/60 hover:border-brand-500 dark:hover:border-brand-500 hover:bg-brand-50/50 dark:hover:bg-brand-950/40 text-left transition-all"
          >
            <div className="font-semibold text-gray-800 dark:text-slate-200">Sarah Jenkins</div>
            <div className="text-[10px] text-gray-500 dark:text-slate-400">Regular Employee</div>
          </button>

          <button
            type="button"
            onClick={() => handleQuickLogin('john.doe@officeassist.com')}
            className="p-2.5 rounded-xl border border-gray-200 dark:border-slate-700 dark:bg-slate-800/60 hover:border-purple-500 dark:hover:border-purple-500 hover:bg-purple-50/50 dark:hover:bg-purple-950/40 text-left transition-all"
          >
            <div className="font-semibold text-gray-800 dark:text-slate-200">John Doe</div>
            <div className="text-[10px] text-purple-600 dark:text-purple-400 font-medium">IT Staff Handler</div>
          </button>

          <button
            type="button"
            onClick={() => handleQuickLogin('alex.mercer@officeassist.com')}
            className="p-2.5 rounded-xl border border-gray-200 dark:border-slate-700 dark:bg-slate-800/60 hover:border-amber-500 dark:hover:border-amber-500 hover:bg-amber-50/50 dark:hover:bg-amber-950/40 text-left transition-all"
          >
            <div className="font-semibold text-gray-800 dark:text-slate-200">Alex Mercer</div>
            <div className="text-[10px] text-amber-600 dark:text-amber-400 font-medium">IT Department Head</div>
          </button>

          <button
            type="button"
            onClick={() => handleQuickLogin('admin@officeassist.com')}
            className="p-2.5 rounded-xl border border-gray-200 dark:border-slate-700 dark:bg-slate-800/60 hover:border-emerald-500 dark:hover:border-emerald-500 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/40 text-left transition-all"
          >
            <div className="font-semibold text-gray-800 dark:text-slate-200">System Admin</div>
            <div className="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">System Admin</div>
          </button>
        </div>
      </div>
    </div>
  );
}
