import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import Sidebar from '../components/Sidebar';
import PageLoader from '../components/PageLoader';
import { useAuth } from '../context/AuthContext';
import { postsApi } from '../api/endpoints';

export default function DashboardPage() {
  const { user } = useAuth();
  const [stats, setStats] = useState({ total: 0, published: 0, pending: 0, draft: 0, limit: 0, used: 0 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    postsApi.list('stats=1').then((data) => {
      setStats(data.stats || stats);
    }).catch(() => {
      setStats({ total: 0, published: 0, pending: 0, draft: 0, limit: 0, used: 0 });
    }).finally(() => {
      setLoading(false);
    });
  }, []);

  if (loading) {
    return (
      <div className="oswp-dashboard">
        <Sidebar />
        <div className="oswp-content-area">
          <PageLoader message="Loading dashboard..." />
        </div>
      </div>
    );
  }

  return (
    <div className="oswp-dashboard">
      <Sidebar />
      <div className="oswp-content-area">
        <h1 style={{ fontSize: 22, fontWeight: 600, marginBottom: 24, letterSpacing: '-0.3px' }}>
          Welcome back, {user?.first_name || 'User'}
        </h1>

        <div className="oswp-stats-grid">
          <div className="oswp-stat-card">
            <div className="oswp-stat-label">Total Posts</div>
            <div className="oswp-stat-value">{stats.total}</div>
          </div>
          <div className="oswp-stat-card">
            <div className="oswp-stat-label">Published</div>
            <div className="oswp-stat-value">{stats.published}</div>
          </div>
          <div className="oswp-stat-card">
            <div className="oswp-stat-label">Pending Review</div>
            <div className="oswp-stat-value">{stats.pending}</div>
          </div>
          <div className="oswp-stat-card">
            <div className="oswp-stat-label">Monthly Limit</div>
            <div className="oswp-stat-value">
              {stats.used} / {stats.limit || '∞'}
            </div>
            {stats.limit > 0 && (
              <div className="oswp-progress">
                <div
                  className="oswp-progress-bar"
                  style={{ width: `${Math.min((stats.used / stats.limit) * 100, 100)}%` }}
                />
              </div>
            )}
          </div>
        </div>

        <div className="oswp-card">
          <div className="oswp-card-header">
            <h2>Quick Actions</h2>
          </div>
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
            <Link to="/posts/new" className="oswp-btn oswp-btn-primary" style={{ width: 'auto' }}>
              Write a new post
            </Link>
            <Link to="/posts" className="oswp-btn oswp-btn-secondary">
              View my posts
            </Link>
            <Link to="/profile" className="oswp-btn oswp-btn-secondary">
              Edit profile
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
