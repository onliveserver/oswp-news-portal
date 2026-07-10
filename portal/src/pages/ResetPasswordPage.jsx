import React, { useState } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import { authApi } from '../api/endpoints';
import OtpInput from '../components/OtpInput';
import { toastError, toastPromise } from '../utils/toast';

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const email = searchParams.get('email') || '';
  const [step, setStep] = useState('otp'); // 'otp' | 'newpass'
  const [otp, setOtp] = useState('');
  const [token, setToken] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);

  const handleOtpComplete = async (code) => {
    setOtp(code);
    setError('');
    setBusy(true);
    try {
      const data = await toastPromise(() => authApi.verifyResetOtp({ email, code }), {
        loading: 'Verifying reset code…',
        success: 'Code verified. Set your new password.',
        error: 'Invalid or expired reset code.',
      });
      setToken(data.token);
      setStep('newpass');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleReset = async (e) => {
    e.preventDefault();
    if (password !== passwordConfirm) {
      setError('Passwords do not match.');
      toastError('Passwords do not match.');
      return;
    }
    setError('');
    setBusy(true);
    try {
      await toastPromise(() => authApi.resetPassword({ email, token, password }), {
        loading: 'Updating password…',
        success: 'Password updated. Redirecting to sign in.',
        error: 'Failed to update password.',
      });
      setSuccess('Password updated. You can now sign in.');
      setTimeout(() => navigate('/login'), 2000);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="oswp-auth-wrapper">
      <div className="oswp-auth-card">
        <h1>{step === 'otp' ? 'Enter reset code' : 'Set new password'}</h1>
        <p className="oswp-subtitle">
          {step === 'otp'
            ? `We sent a 6-digit code to ${email}`
            : 'Choose a strong new password.'}
        </p>

        {error && <div className="oswp-alert oswp-alert-error">{error}</div>}
        {success && <div className="oswp-alert oswp-alert-success">{success}</div>}

        {step === 'otp' ? (
          <>
            <OtpInput length={6} onComplete={handleOtpComplete} />
            {busy && <p style={{ textAlign: 'center', fontSize: 14, color: 'var(--color-text-secondary)' }}>Verifying…</p>}
          </>
        ) : (
          <form onSubmit={handleReset}>
            <div className="oswp-form-group">
              <label className="oswp-form-label">New password</label>
              <input
                type="password"
                className="oswp-input"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Min 8 characters"
                required
                minLength={8}
              />
            </div>
            <div className="oswp-form-group">
              <label className="oswp-form-label">Confirm password</label>
              <input
                type="password"
                className="oswp-input"
                value={passwordConfirm}
                onChange={(e) => setPasswordConfirm(e.target.value)}
                required
              />
            </div>
            <button type="submit" className="oswp-btn oswp-btn-primary" disabled={busy}>
              {busy ? 'Updating…' : 'Update password'}
            </button>
          </form>
        )}

        <div className="oswp-auth-links">
          <Link to="/login">Back to sign in</Link>
        </div>
      </div>
    </div>
  );
}
