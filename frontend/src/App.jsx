// frontend/src/App.jsx

import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { ToastProvider } from './contexts/ToastContext';
import { ThemeProvider } from './contexts/ThemeContext';

import { MainLayout } from './layouts/MainLayout';
import { AuthLayout } from './layouts/AuthLayout';

import { Login } from './pages/Login';
import { EmployeeDashboard } from './pages/EmployeeDashboard';
import { StaffDashboard } from './pages/StaffDashboard';
import { AdminDashboard } from './pages/AdminDashboard';
import { RequestList } from './pages/RequestList';
import { NewRequest } from './pages/NewRequest';
import { RequestDetail } from './pages/RequestDetail';

function ProtectedRoute({ children, allowedRoles }) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-slate-900 text-gray-400 dark:text-slate-500 text-sm font-medium">
        Loading OfficeAssist session...
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles) {
    const isHod = user.role === 'DEPARTMENT_HEAD' || user.role === 'DEPARTMENT_MANAGER';
    const isEmp = user.role === 'EMPLOYEE' || user.role === 'DEPARTMENT_STAFF';
    const isAdmin = user.role === 'SYSTEM_ADMIN';

    let hasAccess = false;
    if (allowedRoles.includes('SYSTEM_ADMIN') && isAdmin) hasAccess = true;
    if (allowedRoles.includes('DEPARTMENT_HEAD') && isHod) hasAccess = true;
    if (allowedRoles.includes('EMPLOYEE') && isEmp) hasAccess = true;

    if (!hasAccess) {
      return <Navigate to="/dashboard" replace />;
    }
  }

  return children;
}

function DashboardSwitch() {
  const { user } = useAuth();

  if (user?.role === 'SYSTEM_ADMIN') {
    return <AdminDashboard />;
  }

  if (user?.role === 'DEPARTMENT_HEAD' || user?.role === 'DEPARTMENT_MANAGER') {
    return <StaffDashboard />;
  }

  return <EmployeeDashboard />;
}

export function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <ToastProvider>
          <BrowserRouter>
            <Routes>
              {/* Public Auth Routes */}
              <Route element={<AuthLayout />}>
                <Route path="/login" element={<Login />} />
              </Route>

              {/* Protected App Routes */}
              <Route
                element={
                  <ProtectedRoute>
                    <MainLayout />
                  </ProtectedRoute>
                }
              >
                <Route path="/" element={<Navigate to="/dashboard" replace />} />
                <Route path="/dashboard" element={<DashboardSwitch />} />
                
                {/* Request Routes */}
                <Route path="/requests" element={<RequestList viewMode="all" />} />
                <Route path="/requests/my" element={<RequestList viewMode="my" />} />
                <Route path="/requests/office" element={<RequestList viewMode="office" />} />
                <Route
                  path="/requests/new"
                  element={
                    <ProtectedRoute allowedRoles={['EMPLOYEE', 'DEPARTMENT_HEAD']}>
                      <NewRequest />
                    </ProtectedRoute>
                  }
                />
                <Route path="/requests/:id" element={<RequestDetail />} />

                {/* Admin Routes */}
                <Route
                  path="/admin/users"
                  element={
                    <ProtectedRoute allowedRoles={['SYSTEM_ADMIN']}>
                      <AdminDashboard initialTab="users" />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="/admin/departments"
                  element={
                    <ProtectedRoute allowedRoles={['SYSTEM_ADMIN']}>
                      <AdminDashboard initialTab="departments" />
                    </ProtectedRoute>
                  }
                />
              </Route>

              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
          </BrowserRouter>
        </ToastProvider>
      </AuthProvider>
    </ThemeProvider>
  );
}

export default App;

