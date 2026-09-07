// frontend/src/contexts/AuthContext.jsx

import React, { createContext, useContext, useState, useEffect } from 'react';
import { api } from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Attempt initial session check on mount
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const res = await api.me();
      if (res.data) {
        setUser(res.data);
      } else {
        setUser(null);
      }
    } catch (err) {
      setUser(null);
      localStorage.removeItem('officeassist_token');
    } finally {
      setLoading(false);
    }
  };

  const login = async (username, password) => {
    const res = await api.login({ username, password });
    if (res.data && res.data.token) {
      localStorage.setItem('officeassist_token', res.data.token);
      setUser(res.data.user);
    }
    return res;
  };

  const logout = async () => {
    try {
      await api.logout();
    } catch (e) {
      // Ignore errors on logout
    } finally {
      localStorage.removeItem('officeassist_token');
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider value={{ user, setUser, loading, login, logout, checkAuth }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
