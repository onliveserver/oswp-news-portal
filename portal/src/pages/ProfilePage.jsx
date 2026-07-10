import React, { useState, useEffect } from 'react';
import Sidebar from '../components/Sidebar';
import { useAuth } from '../context/AuthContext';
import { profileApi, settingsApi } from '../api/endpoints';
import DynamicFormField from '../components/DynamicFormField';
import { getProfileFieldValue, normalizeFields } from '../utils/formFields';
import { toastPromise } from '../utils/toast';

export default function ProfilePage() {
  const { fetchUser } = useAuth();
  const [fields, setFields] = useState([]);
  const [form, setForm] = useState({});
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    Promise.all([profileApi.get(), settingsApi.getPublic()]).then(([profile, settings]) => {
      const regFields = normalizeFields((settings.registration_fields || []).filter(
        (f) => f.id !== 'password'
      ));
      setFields(regFields);

      const init = {};
      regFields.forEach((f) => {
        init[f.id] = getProfileFieldValue(profile, f.id);
      });

      setForm(init);
      setLoaded(true);
    });
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setBusy(true);
    try {
      await toastPromise(() => profileApi.update(form), {
        loading: 'Saving profile…',
        success: 'Profile updated successfully.',
        error: 'Failed to update profile.',
      });
      await fetchUser();
      setSuccess('Profile updated.');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const set = (key, val) => setForm((prev) => ({ ...prev, [key]: val }));

  if (!loaded) return null;

  return (
    <div className="oswp-dashboard">
      <Sidebar />
      <div className="oswp-content-area">
        <div className="oswp-card">
          <div className="oswp-card-header">
            <h2>My Profile</h2>
          </div>

          {error && <div className="oswp-alert oswp-alert-error">{error}</div>}
          {success && <div className="oswp-alert oswp-alert-success">{success}</div>}

          <form onSubmit={handleSubmit}>
            <div className="oswp-form-layout">
              {fields.map((field) => (
                <DynamicFormField
                  key={field.id}
                  field={field}
                  value={form[field.id] ?? ''}
                  onChange={set}
                  disabled={field.id === 'email'}
                />
              ))}
            </div>

            <button type="submit" className="oswp-btn oswp-btn-primary" style={{ marginTop: 8, width: 'auto' }} disabled={busy}>
              {busy ? 'Saving…' : 'Save changes'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
