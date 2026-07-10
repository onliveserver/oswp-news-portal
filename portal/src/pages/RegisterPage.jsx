import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { settingsApi } from '../api/endpoints';
import DynamicFormField from '../components/DynamicFormField';
import { normalizeFields } from '../utils/formFields';
import { toastPromise } from '../utils/toast';

export default function RegisterPage() {
  const { register: doRegister } = useAuth();
  const navigate = useNavigate();
  const [fields, setFields] = useState([]);
  const [form, setForm] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    settingsApi.getPublic().then((data) => {
      const regFields = normalizeFields(data.registration_fields || []);
      setFields(regFields);
      const init = {};
      regFields.forEach((f) => {
        if (f.type !== 'tab') init[f.id] = '';
      });
      setForm(init);
    });
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      const data = await toastPromise(() => doRegister(form), {
        loading: 'Creating account…',
        success: (result) => result?.needs_verification
          ? 'Account created. Verification code sent.'
          : 'Account created successfully.',
        error: 'Registration failed.',
      });
      if (data.needs_verification) {
        navigate(`/verify?email=${encodeURIComponent(form.email)}&type=registration`);
      } else {
        navigate('/dashboard');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const set = (key, val) => setForm((prev) => ({ ...prev, [key]: val }));

  return (
    <div className="oswp-auth-wrapper">
      <div className="oswp-auth-card" style={{ maxWidth: 520 }}>
        <h1>Create account</h1>
        <p className="oswp-subtitle">Fill in the details to get started.</p>

        {error && <div className="oswp-alert oswp-alert-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="oswp-form-layout">
            {fields.map((field) => (
              <DynamicFormField
                key={field.id}
                field={field}
                value={form[field.id] ?? ''}
                onChange={set}
              />
            ))}
          </div>

          <button type="submit" className="oswp-btn oswp-btn-primary" disabled={busy} style={{ marginTop: 8 }}>
            {busy ? 'Creating account…' : 'Create account'}
          </button>
        </form>

        <div className="oswp-auth-links">
          Already have an account? <Link to="/login">Sign in</Link>
        </div>
      </div>
    </div>
  );
}
