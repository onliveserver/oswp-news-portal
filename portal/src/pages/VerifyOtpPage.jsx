import React, { useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { authApi } from '../api/endpoints';
import { useAuth } from '../context/AuthContext';
import OtpInput from '../components/OtpInput';
import { toastPromise } from '../utils/toast';

export default function VerifyOtpPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { fetchUser } = useAuth();
  const email = searchParams.get('email') || '';
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);
  const [resending, setResending] = useState(false);

  const handleComplete = async (code) => {
    setError('');
    setBusy(true);
    try {
      await toastPromise(() => authApi.verifyOtp({ email, code }), {
        loading: 'Verifying code…',
        success: 'Email verified! Redirecting…',
        error: 'Verification failed.',
      });
      setSuccess('Email verified! Redirecting…');
      await fetchUser();
      setTimeout(() => navigate('/dashboard'), 1500);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleResend = async () => {
    setResending(true);
    setError('');
    try {
      const data = await toastPromise(() => authApi.resendOtp({ email }), {
        loading: 'Resending code…',
        success: (response) => response?.message || 'Code resent.',
        error: 'Failed to resend code.',
      });
      setSuccess(data.message || 'Code resent.');
    } catch (err) {
      setError(err.message);
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="oswp-auth-wrapper">
      <div className="oswp-auth-card">
        <h1>Verify your email</h1>
        <p className="oswp-subtitle">Enter the 6-digit code sent to {email}</p>

        {error && <div className="oswp-alert oswp-alert-error">{error}</div>}
        {success && <div className="oswp-alert oswp-alert-success">{success}</div>}

        <OtpInput length={6} onComplete={handleComplete} />

        {busy && (
          <p style={{ textAlign: 'center', fontSize: 14, color: 'var(--color-text-secondary)' }}>
            Verifying…
          </p>
        )}

        <div style={{ textAlign: 'center', marginTop: 16 }}>
          <button onClick={handleResend} className="oswp-btn-link" disabled={resending}>
            {resending ? 'Resending…' : "Didn't receive a code? Resend"}
          </button>
        </div>
      </div>
    </div>
  );
}
