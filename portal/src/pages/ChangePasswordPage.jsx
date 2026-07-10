import React, { useState } from 'react';
import Sidebar from '../components/Sidebar';
import { profileApi } from '../api/endpoints';
import { toastError, toastPromise } from '../utils/toast';

export default function ChangePasswordPage() {
  const [form, setForm] = useState({ current_password: '', new_password: '', confirm_password: '' });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (form.new_password !== form.confirm_password) {
      setError('Passwords do not match.');
      toastError('Passwords do not match.');
      return;
    }
    setError('');
    setSuccess('');
    setBusy(true);
    try {
      await toastPromise(() => profileApi.changePassword({
        current_password: form.current_password,
        new_password: form.new_password,
      }), {
        loading: 'Updating password…',
        success: 'Password changed successfully.',
        error: 'Failed to change password.',
      });
      setSuccess('Password changed.');
      setForm({ current_password: '', new_password: '', confirm_password: '' });
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="oswp-dashboard">
      <Sidebar />
      <div className="oswp-content-area">
        <div className="oswp-card" style={{ maxWidth: 480 }}>
          <div className="oswp-card-header">
            <h2>Change Password</h2>
          </div>

          {error && <div className="oswp-alert oswp-alert-error">{error}</div>}
          {success && <div className="oswp-alert oswp-alert-success">{success}</div>}

          <form onSubmit={handleSubmit}>
            <div className="oswp-form-group">
              <label className="oswp-form-label">Current password</label>
              <input
                type="password"
                className="oswp-input"
                value={form.current_password}
                onChange={(e) => setForm({ ...form, current_password: e.target.value })}
                required
              />
            </div>
            <div className="oswp-form-group">
              <label className="oswp-form-label">New password</label>
              <input
                type="password"
                className="oswp-input"
                value={form.new_password}
                onChange={(e) => setForm({ ...form, new_password: e.target.value })}
                required
                minLength={8}
              />
            </div>
            <div className="oswp-form-group">
              <label className="oswp-form-label">Confirm new password</label>
              <input
                type="password"
                className="oswp-input"
                value={form.confirm_password}
                onChange={(e) => setForm({ ...form, confirm_password: e.target.value })}
                required
              />
            </div>

            <button type="submit" className="oswp-btn oswp-btn-primary" style={{ width: 'auto' }} disabled={busy}>
              {busy ? 'Updating…' : 'Update password'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
