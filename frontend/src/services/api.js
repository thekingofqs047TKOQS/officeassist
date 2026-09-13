// frontend/src/services/api.js

const API_BASE = import.meta.env.VITE_API_BASE || 'https://officeassist-backend.onrender.com';

export async function apiFetch(endpoint, options = {}) {
  const token = localStorage.getItem('officeassist_token');
  
  const headers = {
    'Accept': 'application/json',
    ...(options.headers || {})
  };

  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const config = {
    ...options,
    headers,
    credentials: 'include'
  };

  const response = await fetch(`${API_BASE}${endpoint}`, config);

  // Handle CSV/Binary Blob downloads
  if (options.responseType === 'blob') {
    if (!response.ok) throw new Error('Export download failed');
    return response.blob();
  }

  const data = await response.json().catch(() => ({ success: false, message: 'Invalid server response' }));

  if (!response.ok || data.success === false) {
    const error = new Error(data.message || 'API Request Failed');
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}

export const api = {
  // Auth
  login: (credentials) => apiFetch('/auth/login', { method: 'POST', body: JSON.stringify(credentials) }),
  logout: () => apiFetch('/auth/logout', { method: 'POST' }),
  me: () => apiFetch('/auth/me'),
  changePassword: (data) => apiFetch('/auth/change-password', { method: 'POST', body: JSON.stringify(data) }),

  // SLA
  getSLASettings: (deptId) => apiFetch(`/sla${deptId ? `?department_id=${deptId}` : ''}`),
  saveSLASetting: (slaData) => apiFetch('/sla', { method: 'POST', body: JSON.stringify(slaData) }),

  // Dashboards
  getAdminDashboardStats: () => apiFetch('/dashboard/admin'),
  getDepartmentDashboardStats: (deptId) => apiFetch(`/dashboard/department${deptId ? `?department_id=${deptId}` : ''}`),

  // Reports
  getRequestVolumeReport: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return apiFetch(`/reports/requests${q ? `?${q}` : ''}`);
  },
  getDepartmentPerformanceReport: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return apiFetch(`/reports/departments${q ? `?${q}` : ''}`);
  },
  getStaffPerformanceReport: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return apiFetch(`/reports/staff${q ? `?${q}` : ''}`);
  },
  exportReportCSV: async (type, params = {}) => {
    const q = new URLSearchParams({ ...params, export: 'csv' }).toString();
    const blob = await apiFetch(`/reports/${type}?${q}`, { responseType: 'blob' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `officeassist_${type}_report.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
  },

  // Departments & Categories
  getDepartments: (includeInactive = false) => apiFetch(`/departments${includeInactive ? '?include_inactive=1' : ''}`),
  createDepartment: (deptData) => apiFetch('/departments', { method: 'POST', body: JSON.stringify(deptData) }),
  updateDepartment: (id, deptData) => apiFetch(`/departments/${id}`, { method: 'PUT', body: JSON.stringify(deptData) }),
  assignDepartmentHod: (deptId, userId) => apiFetch(`/departments/${deptId}/assign-hod`, { method: 'POST', body: JSON.stringify({ user_id: userId }) }),

  getCategories: (deptId, includeInactive = false) => apiFetch(`/departments/${deptId}/categories${includeInactive ? '?include_inactive=1' : ''}`),
  createCategory: (catData) => apiFetch('/categories', { method: 'POST', body: JSON.stringify(catData) }),
  updateCategory: (id, catData) => apiFetch(`/categories/${id}`, { method: 'PUT', body: JSON.stringify(catData) }),

  getDepartmentStaff: (deptId) => apiFetch(`/departments/${deptId}/staff`),

  // Locations
  getLocations: (includeInactive = false) => apiFetch(`/locations${includeInactive ? '?include_inactive=1' : ''}`),
  createLocation: (locData) => apiFetch('/locations', { method: 'POST', body: JSON.stringify(locData) }),
  updateLocation: (id, locData) => apiFetch(`/locations/${id}`, { method: 'PUT', body: JSON.stringify(locData) }),

  // Requests
  getRequests: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return apiFetch(`/requests${query ? `?${query}` : ''}`);
  },
  getRequest: (id) => apiFetch(`/requests/${id}`),
  createRequest: (body) => {
    const isForm = body instanceof FormData;
    return apiFetch('/requests', {
      method: 'POST',
      body: isForm ? body : JSON.stringify(body)
    });
  },
  claimRequest: (id) => apiFetch(`/requests/${id}/claim`, { method: 'POST' }),
  assignRequest: (id, staffUserId, comment = '') => apiFetch(`/requests/${id}/assign`, {
    method: 'PATCH',
    body: JSON.stringify({ assigned_staff_id: staffUserId, comment })
  }),
  transferRequest: (id, targetStaffId, reason = '') => apiFetch(`/requests/${id}/transfer`, {
    method: 'POST',
    body: JSON.stringify({ target_staff_id: targetStaffId, reason })
  }),
  updateStatus: (id, statusData) => apiFetch(`/requests/${id}/status`, {
    method: 'PATCH',
    body: JSON.stringify(statusData)
  }),
  addComment: (id, commentData) => apiFetch(`/requests/${id}/comments`, {
    method: 'POST',
    body: JSON.stringify(commentData)
  }),
  uploadAttachment: (id, formData) => apiFetch(`/requests/${id}/attachments`, {
    method: 'POST',
    body: formData
  }),

  // Notifications
  getNotifications: (unreadOnly = false) => apiFetch(`/notifications${unreadOnly ? '?unread_only=1' : ''}`),
  markNotificationRead: (id) => apiFetch(`/notifications/${id}/read`, { method: 'PATCH' }),
  markAllNotificationsRead: () => apiFetch('/notifications/read-all', { method: 'POST' }),
  deleteNotification: (id) => apiFetch(`/notifications/${id}`, { method: 'DELETE' }),
  deleteAllReadNotifications: () => apiFetch('/notifications/read', { method: 'DELETE' }),

  // Users
  getUsers: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return apiFetch(`/users${q ? `?${q}` : ''}`);
  },
  createUser: (userData) => apiFetch('/users', { method: 'POST', body: JSON.stringify(userData) }),
  updateUser: (id, userData) => apiFetch(`/users/${id}`, { method: 'PUT', body: JSON.stringify(userData) }),
  resetUserPassword: (id, newPassword) => apiFetch(`/users/${id}/reset-password`, {
    method: 'POST',
    body: JSON.stringify({ new_password: newPassword })
  })
};