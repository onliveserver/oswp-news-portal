import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { toastPromise } from '../utils/toast';

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ login: '', password: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      const data = await toastPromise(() => login(form), {
        loading: 'Signing in…',
        success: (result) => result?.needs_verification
          ? 'Signed in. Verification required.'
          : 'Signed in successfully.',
        error: 'Sign in failed.',
      });
      if (data.needs_verification) {
        navigate(`/verify?email=${encodeURIComponent(form.login)}`);
      } else {
        navigate('/dashboard');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="oswp-auth-wrapper">
      <div className="oswp-auth-card">
        <h1>Sign in</h1>
        <p className="oswp-subtitle">Welcome back. Enter your credentials.</p>

        {error && <div className="oswp-alert oswp-alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="oswp-form-group">
            <label className="oswp-form-label">Email</label>
            <input
              type="text"
              className="oswp-input"
              value={form.login}
              onChange={(e) => setForm({ ...form, login: e.target.value })}
              placeholder="you@example.com"
              required
              autoFocus
            />
          </div>

          <div className="oswp-form-group">
            <label className="oswp-form-label">Password</label>
            <input
              type="password"
              className="oswp-input"
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
              placeholder="••••••••"
              required
            />
          </div>

          <div style={{ textAlign: 'right', marginBottom: 16 }}>
            <Link to="/forgot-password" className="oswp-btn-link">
              Forgot password?
            </Link>
          </div>

          <button type="submit" className="oswp-btn oswp-btn-primary" disabled={busy}>
            {busy ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <div className="oswp-auth-links">
          Don't have an account? <Link to="/register">Create one</Link>
        </div>
      </div>
    </div>
  );
}
