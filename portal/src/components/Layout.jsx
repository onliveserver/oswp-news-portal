import React from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { toastPromise } from '../utils/toast';

export default function Layout({ children }) {
  const { user, logout } = useAuth();
  const location = useLocation();

  const isAuthPage = ['/login', '/register', '/forgot-password', '/reset-password', '/verify'].some(
    (p) => location.pathname.startsWith(p)
  );

  const handleLogout = async (e) => {
    e.preventDefault();
    await toastPromise(() => logout(), {
      loading: 'Signing out…',
      success: 'Logged out successfully.',
      error: 'Logout failed.',
    });
  };

  const initials = user
    ? (user.first_name?.[0] || '') + (user.last_name?.[0] || user.email?.[0] || '')
    : '';

  return (
    <div className="oswp-layout">
      {/* <header className="oswp-header">
        <Link to={user ? '/dashboard' : '/login'} className="oswp-header-brand">
          {window.oswpPortal?.siteName || 'Portal'}
        </Link>

        {user && !isAuthPage ? (
          <div className="oswp-header-user">
            <nav className="oswp-header-nav">
              <NavLink to="/dashboard">Dashboard</NavLink>
              <NavLink to="/posts">Posts</NavLink>
              <NavLink to="/profile">Profile</NavLink>
            </nav>
            <div className="oswp-header-avatar">{initials.toUpperCase()}</div>
            <button onClick={handleLogout} className="oswp-btn-link">
              Logout
            </button>
          </div>
        ) : null}
      </header> */}

      <main className={isAuthPage ? '' : 'oswp-main'}>{children}</main>
    </div>
  );
}
