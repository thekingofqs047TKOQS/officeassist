// frontend/src/pages/NotificationsPage.jsx

import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { useToast } from '../contexts/ToastContext';
import { useNavigate } from 'react-router-dom';
import { Bell, Check, CheckCheck, Trash2 } from 'lucide-react';

export function NotificationsPage() {
  const toast = useToast();
  const navigate = useNavigate();
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchNotifications();
  }, []);

  const fetchNotifications = async () => {
    try {
      const res = await api.getNotifications(false);
      if (res.data) setNotifications(res.data);
    } catch (e) {
      toast.error('Failed to load notifications');
    } finally {
      setLoading(false);
    }
  };

  const handleMarkRead = async (id) => {
    try {
      await api.markNotificationRead(id);
      fetchNotifications();
    } catch (e) {}
  };

  const handleMarkAllRead = async () => {
    try {
      await api.markAllNotificationsRead();
      toast.success('All notifications marked as read');
      fetchNotifications();
    } catch (e) {}
  };

  const handleDeleteNotif = async (e, id) => {
    e.stopPropagation();
    try {
      await api.deleteNotification(id);
      toast.success('Notification removed');
      fetchNotifications();
    } catch (e) {}
  };

  const handleDeleteAllRead = async () => {
    try {
      await api.deleteAllReadNotifications();
      toast.success('Read notifications cleared');
      fetchNotifications();
    } catch (e) {}
  };

  const unreadExist = notifications.some((n) => !n.is_read);
  const readExist = notifications.some((n) => n.is_read);

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
            <Bell className="w-6 h-6 text-brand-600 dark:text-brand-400" /> Notification Inbox
          </h1>
          <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">Updates on requests routed to you or submitted by you.</p>
        </div>

        <div className="flex items-center gap-2">
          {unreadExist && (
            <button
              onClick={handleMarkAllRead}
              className="bg-brand-50 dark:bg-brand-950/60 hover:bg-brand-100 dark:hover:bg-brand-900/60 text-brand-700 dark:text-brand-300 font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 transition-colors border border-brand-100 dark:border-brand-900/50"
            >
              <CheckCheck className="w-4 h-4" /> Mark all read
            </button>
          )}

          {readExist && (
            <button
              onClick={handleDeleteAllRead}
              className="bg-gray-100 dark:bg-slate-800 hover:bg-red-50 dark:hover:bg-red-950/40 text-gray-600 dark:text-slate-300 hover:text-red-600 dark:hover:text-red-400 font-semibold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition-colors border border-gray-200 dark:border-slate-700"
            >
              <Trash2 className="w-3.5 h-3.5" /> Clear read
            </button>
          )}
        </div>
      </div>

      <div className="bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden divide-y divide-gray-100 dark:divide-slate-800/60 transition-colors duration-200">
        {loading ? (
          <div className="p-8 text-center text-gray-400 dark:text-slate-500 text-sm">Loading notifications...</div>
        ) : notifications.length === 0 ? (
          <div className="p-12 text-center text-gray-400 dark:text-slate-500 text-sm">
            No notifications found.<br />
            <span className="text-xs font-normal text-gray-400 dark:text-slate-600">You're all caught up.</span>
          </div>
        ) : (
          notifications.map((n) => (
            <div
              key={n.id}
              onClick={() => {
                handleMarkRead(n.id);
                if (n.request_id) navigate(`/requests/${n.request_id}`);
              }}
              className={`p-6 hover:bg-gray-50 dark:hover:bg-slate-800/60 cursor-pointer transition-colors flex items-start justify-between gap-4 group ${
                !n.is_read ? 'bg-blue-50/40 dark:bg-blue-950/20 border-l-4 border-l-brand-600 dark:border-l-brand-400' : ''
              }`}
            >
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <span className="font-bold text-sm text-gray-900 dark:text-white">{n.title}</span>
                  {!n.is_read && (
                    <span className="bg-brand-600 dark:bg-brand-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">NEW</span>
                  )}
                </div>
                <p className="text-xs text-gray-600 dark:text-slate-300 leading-relaxed">{n.message}</p>
                <div className="text-[10px] text-gray-400 dark:text-slate-500 pt-1">
                  {new Date(n.created_at).toLocaleString()}
                </div>
              </div>

              <div className="flex items-center gap-1.5 opacity-80 group-hover:opacity-100 transition-opacity">
                {!n.is_read && (
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      handleMarkRead(n.id);
                    }}
                    className="p-1.5 text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 rounded-lg transition-colors"
                    title="Mark read"
                  >
                    <Check className="w-4 h-4" />
                  </button>
                )}

                <button
                  onClick={(e) => handleDeleteNotif(e, n.id)}
                  className="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg transition-colors"
                  title="Delete notification"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
