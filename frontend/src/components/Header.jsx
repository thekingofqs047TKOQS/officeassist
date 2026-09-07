// frontend/src/components/Header.jsx

import React, { useState, useEffect, useRef } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import { api } from '../services/api';
import { Bell, LogOut, User, Check, Sun, Moon, Laptop, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export function Header() {
  const { user, logout } = useAuth();
  const { theme, setTheme } = useTheme();
  const navigate = useNavigate();

  const [notifications, setNotifications] = useState([]);
  const [showNotifMenu, setShowNotifMenu] = useState(false);
  const [showThemeMenu, setShowThemeMenu] = useState(false);

  const notifRef = useRef(null);
  const themeRef = useRef(null);

  useEffect(() => {
    if (user) {
      fetchNotifications();
      const interval = setInterval(fetchNotifications, 30000);
      return () => clearInterval(interval);
    }
  }, [user]);

  // Close menus on outside click
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (notifRef.current && !notifRef.current.contains(e.target)) {
        setShowNotifMenu(false);
      }
      if (themeRef.current && !themeRef.current.contains(e.target)) {
        setShowThemeMenu(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const fetchNotifications = async () => {
    try {
      const res = await api.getNotifications(false);
      if (res.data) {
        setNotifications(res.data);
      }
    } catch (e) {}
  };

  const unreadCount = notifications.filter((n) => !n.is_read).length;

  const handleMarkRead = async (id) => {
    try {
      await api.markNotificationRead(id);
      fetchNotifications();
    } catch (e) {}
  };

  const handleMarkAllRead = async () => {
    try {
      await api.markAllNotificationsRead();
      fetchNotifications();
    } catch (e) {}
  };

  const handleDeleteNotif = async (e, id) => {
    e.stopPropagation();
    try {
      await api.deleteNotification(id);
      fetchNotifications();
    } catch (e) {}
  };

  const handleDeleteAllRead = async () => {
    try {
      await api.deleteAllReadNotifications();
      fetchNotifications();
    } catch (e) {}
  };

  return (
    <header className="h-16 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 px-6 flex items-center justify-between sticky top-0 z-30 shadow-sm transition-colors duration-200">
      {/* System Brand Header */}
      <div className="flex items-center gap-3">
        <div className="w-9 h-9 bg-brand-600 rounded-xl flex items-center justify-center text-white font-bold shadow-md shadow-brand-500/20">
          OA
        </div>
        <div>
          <span className="font-bold text-gray-900 dark:text-white tracking-tight">OfficeAssist</span>
          <span className="text-xs text-gray-500 dark:text-slate-400 block font-medium">Service Request Platform</span>
        </div>
      </div>

      {/* Right Controls: Theme Selector, Notification Bell, User Profile */}
      <div className="flex items-center gap-3 sm:gap-4">
        {/* Multi-Option Theme Selector Dropdown */}
        <div className="relative" ref={themeRef}>
          <button
            onClick={() => setShowThemeMenu(!showThemeMenu)}
            aria-label="Theme Selector"
            className="p-2 rounded-xl text-gray-600 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-1.5 text-xs font-semibold"
            title="Switch Theme (Light, Dark, System)"
          >
            {theme === 'dark' ? (
              <Moon className="w-4 h-4 text-amber-400" />
            ) : theme === 'light' ? (
              <Sun className="w-4 h-4 text-amber-500" />
            ) : (
              <Laptop className="w-4 h-4 text-brand-500" />
            )}
            <span className="hidden md:inline capitalize">{theme}</span>
          </button>

          {showThemeMenu && (
            <div className="absolute right-0 mt-2 w-36 bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-800 py-1.5 z-50 animate-in fade-in slide-in-from-top-2">
              <button
                onClick={() => { setTheme('light'); setShowThemeMenu(false); }}
                className={`w-full px-3 py-2 text-xs font-medium flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors ${
                  theme === 'light' ? 'text-brand-600 dark:text-brand-400 font-bold bg-brand-50/50 dark:bg-brand-950/40' : 'text-gray-700 dark:text-slate-300'
                }`}
              >
                <Sun className="w-3.5 h-3.5 text-amber-500" />
                <span>Light</span>
                {theme === 'light' && <Check className="w-3.5 h-3.5 ml-auto" />}
              </button>

              <button
                onClick={() => { setTheme('dark'); setShowThemeMenu(false); }}
                className={`w-full px-3 py-2 text-xs font-medium flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors ${
                  theme === 'dark' ? 'text-brand-600 dark:text-brand-400 font-bold bg-brand-50/50 dark:bg-brand-950/40' : 'text-gray-700 dark:text-slate-300'
                }`}
              >
                <Moon className="w-3.5 h-3.5 text-amber-400" />
                <span>Dark</span>
                {theme === 'dark' && <Check className="w-3.5 h-3.5 ml-auto" />}
              </button>

              <button
                onClick={() => { setTheme('system'); setShowThemeMenu(false); }}
                className={`w-full px-3 py-2 text-xs font-medium flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors ${
                  theme === 'system' ? 'text-brand-600 dark:text-brand-400 font-bold bg-brand-50/50 dark:bg-brand-950/40' : 'text-gray-700 dark:text-slate-300'
                }`}
              >
                <Laptop className="w-3.5 h-3.5 text-brand-500" />
                <span>System</span>
                {theme === 'system' && <Check className="w-3.5 h-3.5 ml-auto" />}
              </button>
            </div>
          )}
        </div>

        {/* Notification Bell Dropdown */}
        <div className="relative" ref={notifRef}>
          <button
            onClick={() => setShowNotifMenu(!showNotifMenu)}
            aria-label="Notifications"
            className="p-2 rounded-xl text-gray-600 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-800 relative transition-colors"
            title="Notifications"
          >
            <Bell className="w-5 h-5" />
            {unreadCount > 0 && (
              <span className="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full ring-2 ring-white dark:ring-slate-900" />
            )}
          </button>

          {/* Notifications Popover Card */}
          {showNotifMenu && (
            <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-800 py-3 z-50 animate-in fade-in slide-in-from-top-2">
              <div className="px-4 py-2 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <h3 className="font-semibold text-gray-900 dark:text-white text-sm">Notifications</h3>
                  {unreadCount > 0 && (
                    <span className="bg-brand-50 dark:bg-brand-950/60 text-brand-700 dark:text-brand-300 text-xs font-bold px-2 py-0.5 rounded-full">
                      {unreadCount} new
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  {unreadCount > 0 && (
                    <button
                      onClick={handleMarkAllRead}
                      className="text-xs text-brand-600 hover:text-brand-700 dark:text-brand-400 font-semibold flex items-center gap-1"
                    >
                      <Check className="w-3.5 h-3.5" /> Read all
                    </button>
                  )}
                  <button
                    onClick={handleDeleteAllRead}
                    className="text-xs text-gray-400 hover:text-red-600 dark:hover:text-red-400 font-medium flex items-center gap-1"
                    title="Clear Read Notifications"
                  >
                    <Trash2 className="w-3.5 h-3.5" /> Clear read
                  </button>
                </div>
              </div>

              <div className="max-h-80 overflow-y-auto divide-y divide-gray-50 dark:divide-slate-800/50">
                {notifications.length === 0 ? (
                  <div className="p-6 text-center text-gray-400 dark:text-slate-500 text-sm">
                    No notifications.<br />
                    <span className="text-xs text-gray-400 dark:text-slate-600 font-normal">You're all caught up.</span>
                  </div>
                ) : (
                  notifications.map((n) => (
                    <div
                      key={n.id}
                      onClick={() => {
                        handleMarkRead(n.id);
                        if (n.request_id) navigate(`/requests/${n.request_id}`);
                        setShowNotifMenu(false);
                      }}
                      className={`p-4 hover:bg-gray-50 dark:hover:bg-slate-800/80 cursor-pointer transition-colors group relative ${
                        !n.is_read ? 'bg-blue-50/50 dark:bg-blue-950/30' : ''
                      }`}
                    >
                      <div className="flex items-start justify-between gap-2 pr-6">
                        <h4 className="font-semibold text-xs text-gray-900 dark:text-slate-100">{n.title}</h4>
                        <span className="text-[10px] text-gray-400 dark:text-slate-500 whitespace-nowrap">
                          {new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                      <p className="text-xs text-gray-600 dark:text-slate-300 mt-1 line-clamp-2">{n.message}</p>
                      
                      <button
                        onClick={(e) => handleDeleteNotif(e, n.id)}
                        className="absolute right-3 top-3 p-1 text-gray-300 hover:text-red-600 dark:text-slate-600 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                        title="Delete notification"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}
        </div>

        <div className="h-6 w-px bg-gray-200 dark:bg-slate-800" />

        {/* User Profile Info & Logout */}
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center text-gray-600 dark:text-slate-300 font-semibold text-sm border border-gray-200 dark:border-slate-700">
            {user?.full_name ? user.full_name.charAt(0) : <User className="w-4 h-4" />}
          </div>
          <div className="hidden sm:block text-left">
            <div className="text-xs font-semibold text-gray-900 dark:text-white">{user?.full_name}</div>
            <div className="text-[11px] text-gray-500 dark:text-slate-400 font-medium flex items-center gap-1.5">
              <span>{user?.role === 'DEPARTMENT_HEAD' ? 'DEPARTMENT HEAD' : user?.role?.replace('_', ' ')}</span>
              {user?.department_name && (
                <span className="bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 px-1.5 py-0.2 rounded text-[10px]">
                  {user.department_name}
                </span>
              )}
            </div>
          </div>

          <button
            onClick={logout}
            aria-label="Log Out"
            className="p-2 rounded-xl text-gray-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors ml-1"
            title="Log Out"
          >
            <LogOut className="w-5 h-5" />
          </button>
        </div>
      </div>
    </header>
  );
}
