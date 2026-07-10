import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { authApi } from '../api/endpoints';
import { toastPromise } from '../utils/toast';

export default function ForgotPasswordPage() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      await toastPromise(() => authApi.forgotPassword({ email }), {
        loading: 'Sending reset code…',
        success: 'Reset code sent.',
        error: 'Failed to send reset code.',
      });
      navigate(`/reset-password?email=${encodeURIComponent(email)}`);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="oswp-auth-wrapper">
      <div className="oswp-auth-card">
        <h1>Forgot password</h1>
        <p className="oswp-subtitle">Enter your email and we'll send a reset code.</p>

        {error && <div className="oswp-alert oswp-alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="oswp-form-group">
            <label className="oswp-form-label">Email</label>
            <input
              type="email"
              className="oswp-input"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              required
              autoFocus
            />
          </div>

          <button type="submit" className="oswp-btn oswp-btn-primary" disabled={busy}>
            {busy ? 'Sending…' : 'Send reset code'}
          </button>
        </form>

        <div className="oswp-auth-links">
          <Link to="/login">Back to sign in</Link>
        </div>
      </div>
    </div>
  );
}
