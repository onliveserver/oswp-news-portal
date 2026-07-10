import React, { useEffect, useMemo, useState } from 'react';
import { adminApi } from './api/endpoints';
import { toastError, toastPromise } from './utils/toast';

const FALLBACK_SECTIONS = [
  { id: 'dashboard', label: 'Dashboard', status: 'live' },
  { id: 'settings', label: 'Settings', status: 'live' },
  { id: 'menu_visibility', label: 'Menu Visibility', status: 'live' },
  { id: 'pages', label: 'Portal Routes', status: 'live' },
  { id: 'forms', label: 'Forms', status: 'live' },
  { id: 'emails', label: 'Emails', status: 'live' },
  { id: 'posts', label: 'Posts', status: 'live' },
  { id: 'keywords', label: 'Keywords', status: 'live' },
  { id: 'ai', label: 'AI', status: 'live' },
  { id: 'help', label: 'Help', status: 'live' },
];

const DASHBOARD_SECTION_OPTIONS = [
  { value: 'overview', label: 'Overview' },
  { value: 'profile', label: 'Profile' },
  { value: 'posts', label: 'Posts' },
  { value: 'new_post', label: 'New Post' },
  { value: 'password', label: 'Password' },
];

function getInitialSection() {
  const config = window.oswpAdmin || {};
  const params = new URLSearchParams(window.location.search);
  return params.get('section') || config.initialSection || 'dashboard';
}

function setSectionInUrl(section) {
  const url = new URL(window.location.href);
  url.searchParams.set('section', section);
  window.history.replaceState({}, '', url.toString());
}

function optionsToRaw(options = {}) {
  return Object.entries(options)
    .map(([value, label]) => `${value}:${label}`)
    .join('\n');
}

function hydrateFields(fields = []) {
  return fields.map((field) => ({
    ...field,
    required: !!field.required,
    is_builtin: !!field.is_builtin,
    width: String(field.width || '100'),
    description: field.description ?? '',
    min_limit: field.min_limit ?? '',
    max_limit: field.max_limit ?? '',
    options_raw: field.options_raw || optionsToRaw(field.options || {}),
  }));
}

function hydrateMimeTypes(value = []) {
  return Array.isArray(value) ? value.join('\n') : String(value || '');
}

function hydrateKeywords(items = []) {
  return items.map((item) => item.keyword || '').filter(Boolean).join('\n');
}

function createField(type = 'text') {
  const suffix = `${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
  const id = `field_${suffix}`;
  return {
    id,
    label: 'New field',
    type,
    required: false,
    is_builtin: false,
    meta_key: type === 'tab' ? '' : `oswp_${id}`,
    width: '100',
    description: '',
    min_limit: '',
    max_limit: '',
    options_raw: '',
  };
}

function SidebarSkeleton() {
  return (
    <aside className="oswp-admin-sidebar">
      {Array.from({ length: 8 }).map((_, index) => (
        <div className="oswp-skeleton" key={index} style={{ height: 42, marginBottom: 10, borderRadius: 12 }} />
      ))}
    </aside>
  );
}

function ContentSkeleton() {
  return (
    <div className="oswp-admin-content">
      <div className="oswp-skeleton" style={{ height: 42, width: '30%', marginBottom: 20, borderRadius: 12 }} />
      <div className="oswp-admin-stats-grid">
        {Array.from({ length: 4 }).map((_, index) => (
          <div className="oswp-admin-stat-card" key={index}>
            <div className="oswp-skeleton" style={{ height: 18, width: '45%', marginBottom: 12 }} />
            <div className="oswp-skeleton" style={{ height: 32, width: '60%' }} />
          </div>
        ))}
      </div>
      <div className="oswp-admin-panel-card">
        {Array.from({ length: 5 }).map((_, index) => (
          <div className="oswp-skeleton" key={index} style={{ height: 18, marginBottom: 12 }} />
        ))}
      </div>
    </div>
  );
}

function SectionBadge({ status }) {
  const isLive = status === 'live';
  return <span className={`oswp-admin-pill ${isLive ? 'oswp-admin-pill-live' : 'oswp-admin-pill-placeholder'}`}>{isLive ? 'Live' : 'Soon'}</span>;
}

function SectionHeader({ title, description, actions = null }) {
  return (
    <div className="oswp-admin-section-head">
      <div>
        <h1>{title}</h1>
        <p>{description}</p>
      </div>
      {actions ? <div className="oswp-admin-hero-actions">{actions}</div> : null}
    </div>
  );
}

function ToggleCard({ label, help, checked, onChange }) {
  return (
    <label className="oswp-admin-toggle-card">
      <span>
        <strong>{label}</strong>
        {help ? <small>{help}</small> : null}
      </span>
      <input type="checkbox" checked={!!checked} onChange={(event) => onChange(event.target.checked)} />
    </label>
  );
}

function CheckboxGroup({ options, value, onChange }) {
  const selected = Array.isArray(value) ? value : [];

  function toggle(optionValue) {
    if (selected.includes(optionValue)) {
      onChange(selected.filter((item) => item !== optionValue));
      return;
    }

    onChange([...selected, optionValue]);
  }

  return (
    <div className="oswp-admin-chip-grid">
      {options.map((option) => (
        <label className={`oswp-admin-chip ${selected.includes(option.value) ? 'active' : ''}`} key={option.value}>
          <input
            type="checkbox"
            checked={selected.includes(option.value)}
            onChange={() => toggle(option.value)}
          />
          <span>{option.label}</span>
        </label>
      ))}
    </div>
  );
}

function FieldEditorForm({ field, index, fieldTypes, widths, onChange, onClose }) {
  const locked = !!field.is_builtin;

  return (
    <div className="oswp-admin-field-editor-inline">
      <div className="oswp-admin-form-grid oswp-admin-form-grid-full">
        <label>
          Label
          <input className="oswp-input" value={field.label || ''} onChange={(event) => onChange(index, 'label', event.target.value)} />
        </label>
        <label>
          ID
          <input className="oswp-input" value={field.id || ''} disabled={locked} onChange={(event) => onChange(index, 'id', event.target.value)} />
        </label>
        <label>
          Type
          <select className="oswp-input oswp-select" value={field.type || 'text'} disabled={locked} onChange={(event) => onChange(index, 'type', event.target.value)}>
            {fieldTypes.map((type) => (
              <option key={type.value} value={type.value}>{type.label}</option>
            ))}
          </select>
        </label>
        <label>
          Width
          <select className="oswp-input oswp-select" value={String(field.width || '100')} onChange={(event) => onChange(index, 'width', event.target.value)}>
            {widths.map((width) => (
              <option key={width} value={width}>{width}%</option>
            ))}
          </select>
        </label>
        {field.type !== 'tab' ? (
          <label className="oswp-admin-form-grid__full">
            Description
            <textarea className="oswp-input oswp-admin-textarea-sm" value={field.description || ''} onChange={(event) => onChange(index, 'description', event.target.value)} placeholder="Helper text shown below the field label" />
          </label>
        ) : null}
        {field.type !== 'tab' ? (
          <label>
            Meta key
            <input className="oswp-input" value={field.meta_key || ''} disabled={locked} onChange={(event) => onChange(index, 'meta_key', event.target.value)} />
          </label>
        ) : null}
        {field.type !== 'tab' ? (
          <label>
            Minimum length
            <input className="oswp-input" type="number" min="0" value={field.min_limit ?? ''} onChange={(event) => onChange(index, 'min_limit', event.target.value)} />
          </label>
        ) : null}
        {field.type !== 'tab' ? (
          <label>
            Maximum length
            <input className="oswp-input" type="number" min="0" value={field.max_limit ?? ''} onChange={(event) => onChange(index, 'max_limit', event.target.value)} />
          </label>
        ) : null}
        {field.type === 'select' ? (
          <label className="oswp-admin-form-grid__full">
            Options
            <textarea
              className="oswp-input oswp-admin-textarea-sm"
              value={field.options_raw || ''}
              onChange={(event) => onChange(index, 'options_raw', event.target.value)}
              placeholder={'value:Label\nsecond:Second option'}
            />
          </label>
        ) : null}
        {field.type !== 'tab' ? (
          <label className="oswp-admin-checkbox oswp-admin-form-grid__full">
            <input type="checkbox" checked={!!field.required} onChange={(event) => onChange(index, 'required', event.target.checked)} />
            <span>Required field</span>
          </label>
        ) : null}
      </div>
      <div className="oswp-admin-field-editor-actions">
        <button type="button" className="oswp-btn oswp-btn-secondary" onClick={onClose}>Done editing</button>
      </div>
    </div>
  );
}

function FieldBuilder({ title, help, fields, setFields, fieldTypes, widths }) {
  const [filter, setFilter] = useState('');
  const [editingIndex, setEditingIndex] = useState(null);

  const filteredFields = useMemo(() => {
    const query = filter.trim().toLowerCase();
    return fields.reduce((items, field, index) => {
      const haystack = [field.label, field.id, field.type].join(' ').toLowerCase();
      if (!query || haystack.includes(query)) {
        items.push({ field, index });
      }
      return items;
    }, []);
  }, [fields, filter]);

  function updateField(index, key, value) {
    setFields((current) => current.map((item, itemIndex) => (itemIndex === index ? { ...item, [key]: value } : item)));
  }

  function addField(type) {
    const nextField = createField(type);
    setFields((current) => {
      const next = [...current, nextField];
      setEditingIndex(next.length - 1);
      return next;
    });
  }

  function moveField(index, direction) {
    setFields((current) => {
      const next = [...current];
      const target = index + direction;
      if (target < 0 || target >= current.length) return current;
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
  }

  function removeField(index) {
    setFields((current) => current.filter((_, itemIndex) => itemIndex !== index));
    setEditingIndex((currentIndex) => {
      if (currentIndex === null) return null;
      if (currentIndex === index) return null;
      if (currentIndex > index) return currentIndex - 1;
      return currentIndex;
    });
  }

  function toggleEditor(index) {
    setEditingIndex((currentIndex) => (currentIndex === index ? null : index));
  }

  return (
    <div className="oswp-admin-form-card oswp-admin-builder-card">
      <div className="oswp-admin-builder-head">
        <div>
          <h2>{title}</h2>
          <p>{help}</p>
        </div>
        <div className="oswp-admin-inline-actions">
          <input className="oswp-input oswp-admin-search" value={filter} onChange={(event) => setFilter(event.target.value)} placeholder="Filter fields" />
          <button type="button" className="oswp-btn oswp-btn-secondary" onClick={() => addField('text')}>+ Field</button>
          <button type="button" className="oswp-btn oswp-btn-secondary" onClick={() => addField('tab')}>+ Section</button>
        </div>
      </div>

      <div className="oswp-admin-builder-list oswp-admin-builder-list--accordion">
        {filteredFields.length ? filteredFields.map(({ field, index }) => {
          const isEditing = editingIndex === index;
          const isLocked = !!field.is_builtin;
          const isRequired = !!field.required && field.type !== 'tab';

          return (
            <div key={`${field.id}-${index}`} className={`oswp-admin-field-item ${isEditing ? 'is-open' : ''}`}>
              <div className="oswp-admin-field-item__header">
                <div className="oswp-admin-field-item__meta">
                  <strong>{field.label || field.id || `Field ${index + 1}`}</strong>
                  <div className="oswp-admin-field-item__info">
                    <span>{field.id || '—'}</span>
                    <span>{field.type || 'text'}</span>
                    {field.width && field.width !== '100' ? <span>{field.width}%</span> : null}
                  </div>
                </div>
                <div className="oswp-admin-field-item__badges">
                  {isLocked ? <span className="oswp-admin-pill oswp-admin-pill-neutral">Built-in</span> : null}
                  {isRequired ? <span className="oswp-admin-pill oswp-admin-pill-accent">Required</span> : null}
                  {field.type === 'tab' ? <span className="oswp-admin-pill oswp-admin-pill-subtle">Section</span> : null}
                </div>
                <div className="oswp-admin-field-item__actions">
                  <button type="button" className="oswp-btn oswp-btn-sm oswp-btn-secondary" disabled={index === 0} onClick={() => moveField(index, -1)} title="Move up">↑</button>
                  <button type="button" className="oswp-btn oswp-btn-sm oswp-btn-secondary" disabled={index === fields.length - 1} onClick={() => moveField(index, 1)} title="Move down">↓</button>
                  <button type="button" className={`oswp-btn oswp-btn-sm ${isEditing ? 'oswp-btn-primary' : 'oswp-btn-secondary'}`} onClick={() => toggleEditor(index)}>{isEditing ? '✕ Close' : '✎ Edit'}</button>
                  {!isLocked ? <button type="button" className="oswp-btn oswp-btn-sm oswp-btn-secondary" onClick={() => removeField(index)}>Remove</button> : null}
                </div>
              </div>

              {isEditing ? (
                <FieldEditorForm
                  field={field}
                  index={index}
                  fieldTypes={fieldTypes}
                  widths={widths}
                  onChange={updateField}
                  onClose={() => toggleEditor(index)}
                />
              ) : null}
            </div>
          );
        }) : <div className="oswp-admin-empty">No matching fields found.</div>}
      </div>
    </div>
  );
}

export default function AdminApp() {
  const [loading, setLoading] = useState(true);
  const [section, setSection] = useState(getInitialSection());
  const [overview, setOverview] = useState(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [savingSection, setSavingSection] = useState('');

  const [settingsForm, setSettingsForm] = useState({
    email_verification_method: 'otp',
    enable_email_verification: true,
    verification_required: true,
    otp_max_resends: 3,
    otp_resend_cooldown: 2,
    otp_expiry_hours: 24,
    default_registration_role: 'subscriber',
    default_user_verified: false,
    default_user_account_active: true,
    default_user_approved_to_post: false,
    notify_admin_email: '',
    menu_visibility_enabled: true,
    menu_visibility_default: 'logged_out',
    dashboard_sections: ['overview', 'profile', 'posts', 'new_post', 'password'],
    available_roles: [],
    available_dashboard_sections: DASHBOARD_SECTION_OPTIONS,
  });

  const [formsForm, setFormsForm] = useState({
    registration_fields: [],
    post_fields: [],
    post_form_description: '',
    field_types: [],
    widths: ['25', '33', '50', '66', '75', '100'],
  });

  const [postsForm, setPostsForm] = useState({
    post_monthly_limit: 5,
    post_limit_message: '',
    post_auto_approve: true,
    post_status_default: 'pending',
    allowed_mime_types: 'image/jpeg\nimage/png\nimage/gif',
    seo_title_min_length: 50,
    seo_meta_desc_min_length: 150,
    max_tags_per_post: 5,
    auto_focus_keyword_from_first_tag: true,
    allowed_post_statuses: ['pending', 'draft', 'publish'],
  });

  const [emailsForm, setEmailsForm] = useState({
    email_templates: {},
    placeholders: [],
  });
  const [emailFilter, setEmailFilter] = useState('');

  const [keywordsForm, setKeywordsForm] = useState({
    items: [],
    total: 0,
    keywordsText: '',
    filter: '',
  });

  const [menus, setMenus] = useState([]);

  useEffect(() => {
    let active = true;

    async function loadOverview() {
      setLoading(true);
      setError('');

      try {
        const data = await adminApi.getOverview();
        if (!active) return;
        setOverview(data);
        setSettingsForm((prev) => ({
          ...prev,
          ...(data.settings || {}),
          dashboard_sections: data.settings?.dashboard_sections || prev.dashboard_sections,
        }));
        setFormsForm({
          registration_fields: hydrateFields(data.forms?.registration_fields || []),
          post_fields: hydrateFields(data.forms?.post_fields || []),
          post_form_description: data.forms?.post_form_description || '',
          field_types: data.forms?.field_types || [],
          widths: data.forms?.widths || ['25', '33', '50', '66', '75', '100'],
        });
        setPostsForm({
          ...(data.posts || {}),
          allowed_mime_types: hydrateMimeTypes(data.posts?.allowed_mime_types || []),
        });
        setEmailsForm(data.emails || { email_templates: {}, placeholders: [] });
        setKeywordsForm({
          items: data.keywords?.items || [],
          total: data.keywords?.total || 0,
          keywordsText: hydrateKeywords(data.keywords?.items || []),
          filter: '',
        });
      } catch (err) {
        if (!active) return;
        setError(err.message || 'Failed to load admin workspace.');
        toastError(err, 'Failed to load admin workspace.');
      } finally {
        if (active) setLoading(false);
      }
    }

    loadOverview();
    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    setSectionInUrl(section);
  }, [section]);

  useEffect(() => {
    if (section === 'menu_visibility') {
      fetch('/wp-json/wp/v2/menus')
        .then((res) => res.json())
        .then((data) => {
          if (Array.isArray(data)) {
            setMenus(data);
          }
        })
        .catch((err) => console.error('Failed to load menus:', err));
    }
  }, [section]);

  const sections = overview?.sections?.length ? overview.sections : FALLBACK_SECTIONS;
  const activeSection = useMemo(() => (sections.some((item) => item.id === section) ? section : 'dashboard'), [section, sections]);

  const filteredEmailEntries = useMemo(() => {
    const entries = Object.entries(emailsForm.email_templates || {});
    const query = emailFilter.trim().toLowerCase();
    if (!query) return entries;
    return entries.filter(([key, template]) => [key, template.subject, template.body].join(' ').toLowerCase().includes(query));
  }, [emailFilter, emailsForm]);

  const filteredKeywordItems = useMemo(() => {
    const query = keywordsForm.filter.trim().toLowerCase();
    if (!query) return keywordsForm.items;
    return (keywordsForm.items || []).filter((item) => item.keyword.toLowerCase().includes(query));
  }, [keywordsForm]);

  function syncSection(sectionId, payload) {
    setOverview((current) => (current ? { ...current, [sectionId]: payload } : current));

    if (sectionId === 'ai') {
      setAiForm((prev) => ({
        ...prev,
        ...(payload || {}),
        ai_allowed_models: Array.isArray(payload?.ai_allowed_models) && payload.ai_allowed_models.length ? payload.ai_allowed_models : prev.ai_allowed_models,
      }));
    } else if (sectionId === 'settings') {
      setSettingsForm((prev) => ({ ...prev, ...(payload || {}) }));
    } else if (sectionId === 'forms') {
      setFormsForm((prev) => ({
        registration_fields: hydrateFields(payload?.registration_fields || []),
        post_fields: hydrateFields(payload?.post_fields || []),
        post_form_description: payload?.post_form_description || '',
        field_types: payload?.field_types || prev.field_types,
        widths: payload?.widths || prev.widths,
      }));
    } else if (sectionId === 'posts') {
      setPostsForm({
        ...(payload || {}),
        allowed_mime_types: hydrateMimeTypes(payload?.allowed_mime_types || []),
      });
    } else if (sectionId === 'emails') {
      setEmailsForm(payload || { email_templates: {}, placeholders: [] });
    } else if (sectionId === 'keywords') {
      setKeywordsForm({
        items: payload?.items || [],
        total: payload?.total || 0,
        keywordsText: hydrateKeywords(payload?.items || []),
        filter: '',
      });
    }
  }

  async function saveSection(sectionId, values) {
    setSavingSection(sectionId);
    setError('');
    setNotice('');

    try {
      const response = await toastPromise(() => adminApi.saveSettings(sectionId, values), {
        loading: `Saving ${sectionId}…`,
        success: (result) => result?.message || 'Settings saved.',
        error: 'Failed to save settings.',
      });
      if (response?.[sectionId]) {
        syncSection(sectionId, response[sectionId]);
      }
      setNotice(response.message || 'Settings saved.');
    } catch (err) {
      setError(err.message || 'Failed to save settings.');
    } finally {
      setSavingSection('');
    }
  }

  function renderDashboard() {
    const stats = overview?.stats || {};
    return (
      <>
        <div className="oswp-admin-hero">
          <div>
            <p className="oswp-admin-kicker">React workspace</p>
            <h1>OSWP Portal Admin</h1>
            <p>The legacy tab maze has been replaced with API-backed sections for settings, forms, keywords, posts, emails, and AI. Much less spelunking, more shipping.</p>
          </div>
          <div className="oswp-admin-hero-actions">
            <a className="oswp-btn oswp-btn-secondary" href={overview?.portal?.base || '/portal/'}>Open portal</a>
            <button className="oswp-btn oswp-btn-primary" type="button" onClick={() => setSection('forms')}>Open form builder</button>
          </div>
        </div>

        <div className="oswp-admin-stats-grid">
          <div className="oswp-admin-stat-card"><span>Users</span><strong>{stats.total_users || 0}</strong></div>
          <div className="oswp-admin-stat-card"><span>Published posts</span><strong>{stats.published_posts || 0}</strong></div>
          <div className="oswp-admin-stat-card"><span>Registration fields</span><strong>{stats.registration_fields || 0}</strong></div>
          <div className="oswp-admin-stat-card"><span>Post fields</span><strong>{stats.post_fields || 0}</strong></div>
        </div>

        <div className="oswp-admin-panel-grid">
          {sections.map((item) => (
            <div key={item.id} className="oswp-admin-panel-card clickable" onClick={() => setSection(item.id)} role="button" tabIndex={0} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') setSection(item.id); }}>
              <div>
                <h2>{item.label}</h2>
                <p>{item.description}</p>
              </div>
              <SectionBadge status={item.status} />
            </div>
          ))}
        </div>
      </>
    );
  }

  function renderSettings() {
    return (
      <>
        <SectionHeader
          title="Settings"
          description="Control verification, default user state, admin email, and which dashboard panels show up in the frontend portal."
          actions={<button className="oswp-btn oswp-btn-primary" type="button" onClick={() => saveSection('settings', settingsForm)} disabled={savingSection === 'settings'}>{savingSection === 'settings' ? 'Saving…' : 'Save settings'}</button>}
        />

        <div className="oswp-admin-panel-grid oswp-admin-panel-grid-wide">
          <div className="oswp-admin-form-card">
            <h2>Verification and OTP</h2>
            <div className="oswp-admin-form-grid">
              <label>
                Verification method
                <select className="oswp-input oswp-select" value={settingsForm.email_verification_method} onChange={(event) => setSettingsForm((prev) => ({ ...prev, email_verification_method: event.target.value }))}>
                  <option value="none">None</option>
                  <option value="link">Email link</option>
                  <option value="otp">OTP code</option>
                </select>
              </label>
              <label>
                Max OTP resends
                <input className="oswp-input" type="number" min="0" value={settingsForm.otp_max_resends ?? 3} onChange={(event) => setSettingsForm((prev) => ({ ...prev, otp_max_resends: Number(event.target.value) }))} />
              </label>
              <label>
                Resend cooldown (minutes)
                <input className="oswp-input" type="number" min="0" value={settingsForm.otp_resend_cooldown ?? 2} onChange={(event) => setSettingsForm((prev) => ({ ...prev, otp_resend_cooldown: Number(event.target.value) }))} />
              </label>
              <label>
                OTP expiry (hours)
                <input className="oswp-input" type="number" min="1" value={settingsForm.otp_expiry_hours ?? 24} onChange={(event) => setSettingsForm((prev) => ({ ...prev, otp_expiry_hours: Number(event.target.value) }))} />
              </label>
              <label>
                Default registration role
                <select className="oswp-input oswp-select" value={settingsForm.default_registration_role || 'subscriber'} onChange={(event) => setSettingsForm((prev) => ({ ...prev, default_registration_role: event.target.value }))}>
                  {(settingsForm.available_roles || []).map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
                </select>
              </label>
              <label>
                Admin notification email
                <input className="oswp-input" type="email" value={settingsForm.notify_admin_email || ''} onChange={(event) => setSettingsForm((prev) => ({ ...prev, notify_admin_email: event.target.value }))} />
              </label>
            </div>
          </div>

          <div className="oswp-admin-form-card">
            <h2>Default new-user flags</h2>
            <div className="oswp-admin-toggle-stack">
              <ToggleCard label="Verification required before posting" help="When enabled, unverified users are blocked from submitting posts." checked={settingsForm.verification_required} onChange={(value) => setSettingsForm((prev) => ({ ...prev, verification_required: value }))} />
              <ToggleCard label="Mark new users as verified" help="This now affects actual user meta instead of being a decorative checkbox in exile." checked={settingsForm.default_user_verified} onChange={(value) => setSettingsForm((prev) => ({ ...prev, default_user_verified: value }))} />
              <ToggleCard label="Mark new accounts active" help="Inactive accounts cannot sign in until an admin re-enables them." checked={settingsForm.default_user_account_active} onChange={(value) => setSettingsForm((prev) => ({ ...prev, default_user_account_active: value }))} />
              <ToggleCard label="Approve new users to post" help="If enabled, new users can submit posts immediately after verification rules are satisfied." checked={settingsForm.default_user_approved_to_post} onChange={(value) => setSettingsForm((prev) => ({ ...prev, default_user_approved_to_post: value }))} />
              <ToggleCard label="Enable menu visibility rules" help="Keeps the plugin’s menu-visibility feature enabled for legacy integrations." checked={settingsForm.menu_visibility_enabled} onChange={(value) => setSettingsForm((prev) => ({ ...prev, menu_visibility_enabled: value }))} />
              <label style={{ marginTop: '15px', display: 'block' }}>
                Default menu visibility
                <select className="oswp-input oswp-select" value={settingsForm.menu_visibility_default || 'logged_out'} onChange={(event) => setSettingsForm((prev) => ({ ...prev, menu_visibility_default: event.target.value }))}>
                  <option value="everyone">Everyone</option>
                  <option value="logged_in">Logged-in users</option>
                  <option value="logged_out">Logged-out users</option>
                  <option value="hidden">Hidden (none)</option>
                </select>
              </label>
            </div>
          </div>
        </div>

        <div className="oswp-admin-form-card">
          <h2>Dashboard panels</h2>
          <p className="oswp-admin-muted">Choose which panels appear inside the portal dashboard.</p>
          <CheckboxGroup options={settingsForm.available_dashboard_sections || DASHBOARD_SECTION_OPTIONS} value={settingsForm.dashboard_sections || []} onChange={(value) => setSettingsForm((prev) => ({ ...prev, dashboard_sections: value }))} />
        </div>
      </>
    );
  }

  function renderPages() {
    const pages = overview?.pages || [];
    return (
      <>
        <SectionHeader title="Portal routes" description="Shortcode pages are now compatibility shims. The React portal routes below are the real front door." />
        <div className="oswp-admin-table-card">
          <table className="oswp-table">
            <thead>
              <tr>
                <th>Area</th>
                <th>React route</th>
              </tr>
            </thead>
            <tbody>
              {pages.map((page) => (
                <tr key={page.key}>
                  <td>{page.label}</td>
                  <td><a href={page.portal_url} target="_blank" rel="noreferrer">{page.portal_url}</a></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </>
    );
  }

  function renderForms() {
    return (
      <>
        <SectionHeader
          title="Form builders"
          description="Build registration and post submission forms by creating or modifying fields. Click Edit to expand a field's settings inline."
          actions={<button className="oswp-btn oswp-btn-primary" type="button" onClick={() => saveSection('forms', formsForm)} disabled={savingSection === 'forms'}>{savingSection === 'forms' ? 'Saving…' : 'Save forms'}</button>}
        />

        <div className="oswp-admin-form-card">
          <label>
            Post form intro text
            <textarea className="oswp-input oswp-admin-textarea" value={formsForm.post_form_description || ''} onChange={(event) => setFormsForm((prev) => ({ ...prev, post_form_description: event.target.value }))} />
          </label>
        </div>

        <div className="oswp-admin-builder-section">
          <FieldBuilder title="Registration fields" help="These fields are used by the registration API and saved to user meta for custom entries." fields={formsForm.registration_fields} setFields={(value) => setFormsForm((prev) => ({ ...prev, registration_fields: typeof value === 'function' ? value(prev.registration_fields) : value }))} fieldTypes={formsForm.field_types} widths={formsForm.widths} />
        </div>

        <div className="oswp-admin-builder-section">
          <FieldBuilder title="Post fields" help="These fields drive post submission validation, post meta saving, and frontend form rendering." fields={formsForm.post_fields} setFields={(value) => setFormsForm((prev) => ({ ...prev, post_fields: typeof value === 'function' ? value(prev.post_fields) : value }))} fieldTypes={formsForm.field_types} widths={formsForm.widths} />
        </div>
      </>
    );
  }

  function renderPosts() {
    return (
      <>
        <SectionHeader
          title="Post settings"
          description="Control monthly limits, moderation defaults, allowed uploads, and the SEO thresholds enforced during submission."
          actions={<button className="oswp-btn oswp-btn-primary" type="button" onClick={() => saveSection('posts', postsForm)} disabled={savingSection === 'posts'}>{savingSection === 'posts' ? 'Saving…' : 'Save post settings'}</button>}
        />

        <div className="oswp-admin-panel-grid oswp-admin-panel-grid-wide">
          <div className="oswp-admin-form-card">
            <h2>Publishing rules</h2>
            <div className="oswp-admin-form-grid">
              <label>
                Monthly post limit
                <input className="oswp-input" type="number" min="0" value={postsForm.post_monthly_limit ?? 5} onChange={(event) => setPostsForm((prev) => ({ ...prev, post_monthly_limit: Number(event.target.value) }))} />
              </label>
              <label>
                Default post status
                <select className="oswp-input" value={postsForm.post_status_default || 'pending'} onChange={(event) => setPostsForm((prev) => ({ ...prev, post_status_default: event.target.value }))}>
                  {(postsForm.allowed_post_statuses || []).map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
              </label>
              <label>
                Maximum tags per post
                <input className="oswp-input" type="number" min="1" value={postsForm.max_tags_per_post ?? 5} onChange={(event) => setPostsForm((prev) => ({ ...prev, max_tags_per_post: Number(event.target.value) }))} />
              </label>
              <label className="oswp-admin-checkbox oswp-admin-checkbox-inline">
                <input type="checkbox" checked={!!postsForm.post_auto_approve} onChange={(event) => setPostsForm((prev) => ({ ...prev, post_auto_approve: event.target.checked }))} />
                <span>Auto-publish approved posts</span>
              </label>
              <label className="oswp-admin-checkbox oswp-admin-checkbox-inline">
                <input type="checkbox" checked={!!postsForm.auto_focus_keyword_from_first_tag} onChange={(event) => setPostsForm((prev) => ({ ...prev, auto_focus_keyword_from_first_tag: event.target.checked }))} />
                <span>Use first tag as focus keyword</span>
              </label>
            </div>
            <label>
              Limit reached message
              <textarea className="oswp-input oswp-admin-textarea-sm" value={postsForm.post_limit_message || ''} onChange={(event) => setPostsForm((prev) => ({ ...prev, post_limit_message: event.target.value }))} />
            </label>
          </div>

          <div className="oswp-admin-form-card">
            <h2>SEO thresholds</h2>
            <div className="oswp-admin-form-grid">
              <label>
                Minimum title length
                <input className="oswp-input" type="number" min="10" value={postsForm.seo_title_min_length ?? 50} onChange={(event) => setPostsForm((prev) => ({ ...prev, seo_title_min_length: Number(event.target.value) }))} />
              </label>
              <label>
                Minimum meta description length
                <input className="oswp-input" type="number" min="50" value={postsForm.seo_meta_desc_min_length ?? 150} onChange={(event) => setPostsForm((prev) => ({ ...prev, seo_meta_desc_min_length: Number(event.target.value) }))} />
              </label>
            </div>

            <label>
              Allowed upload mime types
              <textarea className="oswp-input oswp-admin-textarea" value={postsForm.allowed_mime_types || ''} onChange={(event) => setPostsForm((prev) => ({ ...prev, allowed_mime_types: event.target.value }))} placeholder={'image/jpeg\nimage/png\nimage/gif'} />
            </label>
          </div>
        </div>
      </>
    );
  }

  function renderEmails() {
    return (
      <>
        <SectionHeader
          title="Email templates"
          description="Edit the transactional templates used for registration, verification, password reset, and admin notifications."
          actions={<button className="oswp-btn oswp-btn-primary" type="button" onClick={() => saveSection('emails', emailsForm)} disabled={savingSection === 'emails'}>{savingSection === 'emails' ? 'Saving…' : 'Save templates'}</button>}
        />

        <div className="oswp-admin-form-card">
          <div className="oswp-admin-builder-head">
            <div>
              <h2>Available placeholders</h2>
              <p>These tokens are replaced automatically when emails are sent.</p>
            </div>
            <input className="oswp-input oswp-admin-search" value={emailFilter} onChange={(event) => setEmailFilter(event.target.value)} placeholder="Filter templates" />
          </div>
          <div className="oswp-admin-token-list">
            {(emailsForm.placeholders || []).map((token) => <code key={token}>{token}</code>)}
          </div>
        </div>

        <div className="oswp-admin-builder-list">
          {filteredEmailEntries.map(([key, template]) => (
            <div className="oswp-admin-form-card" key={key}>
              <div className="oswp-admin-builder-head">
                <div>
                  <h2>{key}</h2>
                  <p>Template key used by the plugin email service.</p>
                </div>
                <label className="oswp-admin-checkbox oswp-admin-checkbox-inline">
                  <input
                    type="checkbox"
                    checked={template.enabled !== false}
                    onChange={(event) => setEmailsForm((prev) => ({
                      ...prev,
                      email_templates: {
                        ...prev.email_templates,
                        [key]: {
                          ...prev.email_templates[key],
                          enabled: event.target.checked,
                        },
                      },
                    }))}
                  />
                  <span>{template.enabled !== false ? 'Enabled' : 'Disabled'}</span>
                </label>
              </div>
              <div className="oswp-admin-form-grid">
                <label className="oswp-admin-form-grid__full">
                  Subject
                  <input className="oswp-input" value={template.subject || ''} onChange={(event) => setEmailsForm((prev) => ({
                    ...prev,
                    email_templates: {
                      ...prev.email_templates,
                      [key]: { ...prev.email_templates[key], subject: event.target.value },
                    },
                  }))} />
                </label>
                <label className="oswp-admin-form-grid__full">
                  Body
                  <textarea className="oswp-input oswp-admin-textarea" value={template.body || ''} onChange={(event) => setEmailsForm((prev) => ({
                    ...prev,
                    email_templates: {
                      ...prev.email_templates,
                      [key]: { ...prev.email_templates[key], body: event.target.value },
                    },
                  }))} />
                </label>
              </div>
            </div>
          ))}
          {!filteredEmailEntries.length ? <div className="oswp-admin-empty">No email templates matched the current filter.</div> : null}
        </div>
      </>
    );
  }

  function renderKeywords() {
    return (
      <>
        <SectionHeader
          title="Blocked keywords"
          description="Filter the current moderation list, then bulk edit it as plain text. One keyword per line keeps things pleasantly boring and reliable."
          actions={<button className="oswp-btn oswp-btn-primary" type="button" onClick={() => saveSection('keywords', { keywords: keywordsForm.keywordsText })} disabled={savingSection === 'keywords'}>{savingSection === 'keywords' ? 'Saving…' : 'Save keywords'}</button>}
        />

        <div className="oswp-admin-panel-grid oswp-admin-panel-grid-wide">
          <div className="oswp-admin-form-card">
            <h2>Bulk editor</h2>
            <p className="oswp-admin-muted">One keyword per line. Saving replaces the current list.</p>
            <textarea className="oswp-input oswp-admin-textarea oswp-admin-textarea-tall" value={keywordsForm.keywordsText || ''} onChange={(event) => setKeywordsForm((prev) => ({ ...prev, keywordsText: event.target.value }))} />
          </div>

          <div className="oswp-admin-form-card">
            <div className="oswp-admin-builder-head">
              <div>
                <h2>Current list</h2>
                <p>{keywordsForm.total || 0} stored keywords</p>
              </div>
              <input className="oswp-input oswp-admin-search" value={keywordsForm.filter || ''} onChange={(event) => setKeywordsForm((prev) => ({ ...prev, filter: event.target.value }))} placeholder="Filter keywords" />
            </div>
            <div className="oswp-admin-keyword-list">
              {filteredKeywordItems.length ? filteredKeywordItems.map((item) => (
                <div className="oswp-admin-keyword-row" key={item.keyword}>
                  <strong>{item.keyword}</strong>
                </div>
              )) : <div className="oswp-admin-empty">No keywords matched the current filter.</div>}
            </div>
          </div>
        </div>
      </>
    );
  }

  function renderMenuVisibility() {
    return (
      <>
        <SectionHeader
          title="Menu Visibility"
          description="Control whether menu items are shown to logged-in users, logged-out users, or hidden entirely. Edit individual menu items in Appearance → Menus."
        />

        <div className="oswp-admin-form-card">
          <h2>Visibility Settings</h2>
          <div className="oswp-admin-toggle-stack">
            <ToggleCard
              label="Enable menu visibility rules"
              help="When enabled, you can assign visibility roles to each menu item in Appearance → Menus."
              checked={settingsForm.menu_visibility_enabled}
              onChange={(value) => setSettingsForm((prev) => ({ ...prev, menu_visibility_enabled: value }))}
            />
          </div>

          <label className="oswp-admin-field oswp-admin-field--menu-visibility" style={{ marginTop: 20, display: 'block' }}>
            <strong className="oswp-admin-label oswp-admin-label--menu-visibility">Default for new menu items</strong>
            <select
              className="oswp-input oswp-select oswp-admin-select oswp-admin-select--menu-visibility"
              value={settingsForm.menu_visibility_default || 'logged_out'}
              onChange={(event) => setSettingsForm((prev) => ({ ...prev, menu_visibility_default: event.target.value }))}
              style={{ marginTop: 8 }}
            >
              <option value="everyone" className="oswp-admin-option oswp-admin-option--everyone">Everyone (all users see this)</option>
              <option value="logged_in" className="oswp-admin-option oswp-admin-option--logged-in">Logged-in users only</option>
              <option value="logged_out" className="oswp-admin-option oswp-admin-option--logged-out">Logged-out users only (default)</option>
              <option value="hidden" className="oswp-admin-option oswp-admin-option--hidden">Hidden (never show)</option>
            </select>
          </label>

          <div style={{ marginTop: 20 }}>
            <p className="oswp-admin-muted">New menu items (without custom visibility rules) will default to: <strong>{settingsForm.menu_visibility_default === 'everyone' ? 'Everyone' : settingsForm.menu_visibility_default === 'logged_in' ? 'Logged-in users' : settingsForm.menu_visibility_default === 'hidden' ? 'Hidden' : 'Logged-out users'}</strong></p>
          </div>

          <div className="oswp-admin-form-actions" style={{ marginTop: 20 }}>
            <button
              className="oswp-btn oswp-btn-primary"
              type="button"
              disabled={savingSection === 'settings'}
              onClick={() => saveSection('settings', settingsForm)}
            >
              {savingSection === 'settings' ? 'Saving…' : 'Save settings'}
            </button>
            <a className="oswp-btn oswp-btn-secondary" href="/wp-admin/nav-menus.php" target="_blank" rel="noreferrer">
              Edit Menus (Appearance → Menus)
            </a>
          </div>
        </div>

        {settingsForm.menu_visibility_enabled && menus.length > 0 && (
          <div className="oswp-admin-form-card" style={{ marginTop: 24 }}>
            <h2>Your Menus</h2>
            <p className="oswp-admin-muted">Click "Edit Menus" to assign visibility roles to individual menu items.</p>
            <div style={{ marginTop: 16 }}>
              {menus.map((menu) => (
                <div
                  key={menu.id}
                  style={{
                    padding: 12,
                    border: '1px solid #ddd',
                    borderRadius: 6,
                    marginBottom: 12,
                    backgroundColor: '#fafafa',
                  }}
                >
                  <div style={{ fontWeight: 'bold', marginBottom: 4 }}>
                    {menu.name}
                  </div>
                  <div style={{ fontSize: 12, color: '#666' }}>
                    {menu.description || '(no description)'}
                  </div>
                  <div style={{ fontSize: 11, color: '#999', marginTop: 4 }}>
                    ID: {menu.id}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {settingsForm.menu_visibility_enabled && menus.length === 0 && (
          <div className="oswp-admin-form-card" style={{ marginTop: 24 }}>
            <p className="oswp-admin-muted">No menus found. <a href="/wp-admin/nav-menus.php">Create a menu</a> first.</p>
          </div>
        )}
      </>
    );
  }

  function renderHelp() {
    return (
      <>
        <SectionHeader title="Help" description="Quick map of the current architecture and where the new admin data comes from." />
        <div className="oswp-admin-panel-grid">
          <div className="oswp-admin-panel-card">
            <h2>Frontend</h2>
            <p>Use <code>/portal/*</code> for login, register, dashboard, post editor, and profile workflows.</p>
          </div>
          <div className="oswp-admin-panel-card">
            <h2>Admin API</h2>
            <p><code>/wp-json/oswp/v1/admin/*</code> now powers settings, forms, posts, keywords, email templates, and AI configuration.</p>
          </div>
          <div className="oswp-admin-panel-card">
            <h2>Dynamic fields</h2>
            <p>The registration and post form builders save directly to the same setting arrays used by the runtime controllers.</p>
          </div>
        </div>
      </>
    );
  }

  let content = null;
  if (activeSection === 'dashboard') content = renderDashboard();
  else if (activeSection === 'settings') content = renderSettings();
  else if (activeSection === 'pages') content = renderPages();
  else if (activeSection === 'forms') content = renderForms();
  else if (activeSection === 'posts') content = renderPosts();
  else if (activeSection === 'menu_visibility') content = renderMenuVisibility();
  else if (activeSection === 'emails') content = renderEmails();
  else if (activeSection === 'keywords') content = renderKeywords();
  else content = renderHelp();

  return (
    <div className="oswp-admin-shell">
      {loading ? (
        <div className="oswp-admin-layout">
          <SidebarSkeleton />
          <ContentSkeleton />
        </div>
      ) : (
        <div className="oswp-admin-layout">
          <aside className="oswp-admin-sidebar">
            <div className="oswp-admin-sidebar-links">
              {sections.map((item) => (
                <button
                  type="button"
                  key={item.id}
                  className={`oswp-admin-sidebar-link ${activeSection === item.id ? 'active' : ''}`}
                  onClick={() => {
                    setNotice('');
                    setSection(item.id);
                  }}
                >
                  <span>{item.label}</span>
                </button>
              ))}
            </div>
            <div className="oswp-admin-sidebar-footer">
              <div className="oswp-admin-version-info">
                <span>Version {window.oswpAdmin?.version || '0.9.0'}</span>
                <span>
                  by <a href="https://onliveserver.com/" target="_blank" rel="noopener noreferrer">Onlive Server</a>
                </span>
              </div>
            </div>
          </aside>
          <main className="oswp-admin-content">
            {error ? <div className="oswp-alert oswp-alert-error">{error}</div> : null}
            {notice ? <div className="oswp-alert oswp-alert-success">{notice}</div> : null}
            {content}
          </main>
        </div>
      )}
    </div>
  );
}
