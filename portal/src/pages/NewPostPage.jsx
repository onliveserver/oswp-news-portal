import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import Sidebar from '../components/Sidebar';
import RichEditor from '../components/RichEditor';
import ImageUploader from '../components/ImageUploader';
import { postsApi, settingsApi } from '../api/endpoints';
import { getFieldWidth } from '../utils/formFields';
import {
  getPlainLength,
  getFieldLength,
  getPlainWordCount,
  findBlockedKeywords,
  validateWysiwygContent,
} from '../utils/postValidation';
import { toastPromise } from '../utils/toast';

const NEW_POST_DRAFT_KEY = 'oswp_portal_new_post_draft_v1';

function readDraft(key) {
  try {
    const raw = window.localStorage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  } catch (_error) {
    return null;
  }
}

function writeDraft(key, value) {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch (_error) {
    // Ignore storage failures.
  }
}

function clearDraft(key) {
  try {
    window.localStorage.removeItem(key);
  } catch (_error) {
    // Ignore storage failures.
  }
}

function getFirstTag(value) {
  return String(value || '')
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean)[0] || '';
}

export default function NewPostPage() {
  const navigate = useNavigate();
  const [loading, setLoading]       = useState(true);
  const [fields, setFields]         = useState([]);
  const [categories, setCategories] = useState([]);
  const [formDescription, setFormDescription] = useState('');
  const [postRules, setPostRules]   = useState({
    seo_title_min_length: 50,
    seo_meta_desc_min_length: 150,
    max_tags_per_post: 5,
    auto_focus_keyword: true,
    content_min_words: 800,
    content_max_words: 2000,
    blocked_keywords: [],
  });
  const [form, setForm]             = useState({});
  const [thumbnail, setThumbnail]   = useState(null);
  const [preview, setPreview]       = useState(null);
  const [errors, setErrors]         = useState({});
  const [busy, setBusy]             = useState(false);

  useEffect(() => {
    Promise.all([
      settingsApi.getPublic(),
      settingsApi.getCategories(),
    ]).then(([settings, cats]) => {
      const postFields = settings.post_fields || [];
      setFields(postFields);
      setCategories(cats || []);
      setFormDescription(settings.post_form_description || '');
      setPostRules((prev) => ({
        ...prev,
        ...(settings.post_rules || {}),
        blocked_keywords: Array.isArray(settings.post_rules?.blocked_keywords)
          ? settings.post_rules.blocked_keywords
          : prev.blocked_keywords,
      }));
      const init = {};
      postFields.forEach((f) => {
        if (f.type !== 'tab' && f.type !== 'media') init[f.id] = '';
      });
      const storedDraft = readDraft(NEW_POST_DRAFT_KEY);
      setForm(storedDraft?.form ? { ...init, ...storedDraft.form } : init);
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (loading || !Object.keys(form).length) {
      return;
    }

    writeDraft(NEW_POST_DRAFT_KEY, { form });
  }, [form, loading]);

  const set = useCallback((key, val) => setForm((prev) => ({ ...prev, [key]: val })), []);

  const getValidationErrors = useCallback((err) => {
    const details = err?.data?.data?.details || err?.data?.details;
    if (details && typeof details === 'object') {
      return details;
    }

    return { submit: err?.message || 'Failed to submit post.' };
  }, []);



  // Derive the "title" and "tags" field IDs so the AI modal can prefill them
  const titleFieldId = fields.find((f) => ['post_title', 'title', 'post_name'].includes(f.id))?.id || '';
  const tagsFieldId  = fields.find((f) => ['post_tags', 'tags', 'post_tag'].includes(f.id))?.id || '';
  const catFieldId   = fields.find((f) => f.type === 'category')?.id || '';
  const contentFieldId = fields.find((f) => f.type === 'wysiwyg')?.id || '';
  const excerptFieldId = fields.find((f) => ['post_excerpt', 'excerpt', '_yoast_wpseo_metadesc', 'meta_description', 'description'].includes(f.id))?.id || '';
  const focusKeywordFieldId = fields.find((f) => ['_yoast_wpseo_focuskw', 'focus_keyword', 'focuskw', 'rank_math_focus_keyword'].includes(f.id))?.id || '';
  const titleField = fields.find((f) => f.id === titleFieldId);
  const contentField = fields.find((f) => f.id === contentFieldId);
  const excerptField = fields.find((f) => f.id === excerptFieldId);

  const renderFieldNotes = (field) => {
    const value = form[field.id] || '';
    const length = getFieldLength(field, value);
    const min = field.min_limit ? Number(field.min_limit) : 0;
    const max = field.max_limit ? Number(field.max_limit) : 0;
    const wordCount = field.id === contentFieldId ? getPlainWordCount(value) : 0;
    const minWords = Number(postRules.content_min_words || 0);
    const maxWords = Number(postRules.content_max_words || 0);

    return (
      <>
        {(field.min_limit || field.max_limit) && field.type !== 'category' && (
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginTop: 4 }}>
            {length} characters{max > 0 ? ` / ${max}` : ''}{min > 0 ? ` (min ${min})` : ''}
            {min > 0 && length < min ? ` — ${min - length} more required` : ''}
          </div>
        )}
        {field.type === 'wysiwyg' && field.id === contentFieldId && (
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginTop: 4 }}>
            Words: {wordCount}{minWords > 0 ? ` / min ${minWords}` : ''}{maxWords > 0 ? ` / max ${maxWords}` : ''}
          </div>
        )}
        {field.id === 'post_tags' && value && (
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginTop: 4 }}>
            {String(value).split(',').filter(t => t.trim()).length} / {postRules.max_tags_per_post || 5} tags
          </div>
        )}
        {field.description && (
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginTop: 6 }}>
            {field.description}
          </div>
        )}
        {errors[field.id] && (
          <div style={{ fontSize: 12, color: 'var(--color-danger)', marginTop: 4 }}>
            {errors[field.id]}
          </div>
        )}
      </>
    );
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});
    
    // Validate all required fields
    const newErrors = {};
    const blockedList = Array.isArray(postRules.blocked_keywords) ? postRules.blocked_keywords : [];
    const contentMinWords = Number(postRules.content_min_words || 0);
    const contentMaxWords = Number(postRules.content_max_words || 0);
    fields.forEach((field) => {
      if (field.type === 'tab' || field.type === 'media') return;
      
      const value = form[field.id];
      const stringValue = value != null ? String(value) : '';
      
      // Check if required and empty
      if (field.required && stringValue.trim() === '') {
        newErrors[field.id] = `${field.label} is required`;
        return;
      }
      
      if (field.id === 'post_tags' && value) {
        const tags = value.split(',').map(t => t.trim()).filter(t => t.length > 0);
        if (tags.length > Number(postRules.max_tags_per_post || 5)) {
          newErrors[field.id] = `Maximum ${postRules.max_tags_per_post || 5} tags allowed`;
          return;
        }
      }

      if (blockedList.length) {
        const blocked = findBlockedKeywords(value, blockedList);
        if (blocked.length) {
          const label = field.label || field.id;
          newErrors[field.id] = `${label} contains blocked keyword${blocked.length > 1 ? 's' : ''}: ${blocked.join(', ')}.`;
          return;
        }
      }

      if (field.type === 'wysiwyg' && value) {
        const wysiwygError = validateWysiwygContent(value, {
          content_min_words: contentMinWords,
          content_max_words: contentMaxWords,
        });
        if (wysiwygError) {
          newErrors[field.id] = wysiwygError;
          return;
        }
      }
      
      // Validate min/max length
      const currentLength = getFieldLength(field, value);

      if (field.min_limit && value && currentLength < field.min_limit) {
        newErrors[field.id] = `${field.label} must be at least ${field.min_limit} characters`;
      }
      if (field.max_limit && value && currentLength > field.max_limit) {
        newErrors[field.id] = `${field.label} must not exceed ${field.max_limit} characters`;
      }

    });

    if (titleFieldId && form[titleFieldId]) {
      const titleLength = String(form[titleFieldId]).trim().length;
      if (titleLength < Number(postRules.seo_title_min_length || 0)) {
        newErrors[titleFieldId] = `Post Title must be at least ${postRules.seo_title_min_length} characters`;
      }
    }

    if (excerptFieldId && form[excerptFieldId]) {
      const metaLength = String(form[excerptFieldId]).trim().length;
      if (metaLength < Number(postRules.seo_meta_desc_min_length || 0)) {
        newErrors[excerptFieldId] = `Meta Description must be at least ${postRules.seo_meta_desc_min_length} characters`;
      }
    }
    
    // Show all errors
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    
    setBusy(true);
    try {
      const fd = new FormData();
      Object.entries(form).forEach(([k, v]) => fd.append(k, v));

      if (thumbnail) fd.append('post_thumbnail', thumbnail);
      await toastPromise(() => postsApi.create(fd), {
        loading: 'Submitting post…',
        success: 'Post submitted successfully.',
        error: 'Failed to submit post.',
      });
      clearDraft(NEW_POST_DRAFT_KEY);
      navigate('/posts');
    } catch (err) {
      setErrors(getValidationErrors(err));
    } finally {
      setBusy(false);
    }
  };

  const renderField = (field) => {
    if (field.type === 'tab') {
      return (
        <div key={field.id} className="oswp-divider" style={{ width: '100%' }}>
          <p style={{ fontSize: 13, fontWeight: 500, color: 'var(--color-text-secondary)', marginTop: 4 }}>
            {field.label}
          </p>
        </div>
      );
    }

    if (field.type === 'media') {
      return (
        <div key={field.id} className="oswp-form-group oswp-form-group--grid" style={{ width: getFieldWidth(field.width) }}>
          <ImageUploader
            label={field.label}
            value={preview}
            onChange={(file, url) => { setThumbnail(file); setPreview(url); }}
            onRemove={() => { setThumbnail(null); setPreview(null); }}
          />
        </div>
      );
    }

    if (field.type === 'category') {
      return (
        <div key={field.id} className="oswp-form-group oswp-form-group--grid" style={{ width: getFieldWidth(field.width) }}>
          <label className="oswp-form-label">
            {field.label}
            {field.required && <span className="required">*</span>}
          </label>
          <select
            className={`oswp-input ${errors[field.id] ? 'error' : ''}`}
            value={form[field.id] || ''}
            onChange={(e) => set(field.id, e.target.value)}
            required={field.required}
          >
            <option value="">Select category</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
          {renderFieldNotes(field)}
        </div>
      );
    }

    if (field.type === 'wysiwyg') {
      return (
        <div key={field.id} className="oswp-form-group oswp-form-group--grid" style={{ width: getFieldWidth(field.width) }}>
          <div className="oswp-field-header">
            <div className="oswp-field-label-row">
              <label className="oswp-form-label" style={{ margin: 0 }}>
                {field.label}
                {field.required && <span className="required">*</span>}
              </label>
            </div>
          </div>
          <RichEditor
            value={form[field.id] || ''}
            onChange={(html) => set(field.id, html)}
            placeholder={`Write your ${field.label.toLowerCase()} here…`}
            minLength={field.min_limit ? Number(field.min_limit) : undefined}
            maxLength={field.max_limit ? Number(field.max_limit) : undefined}
          />
          {renderFieldNotes(field)}
        </div>
      );
    }

    if (field.type === 'textarea') {
      return (
        <div key={field.id} className="oswp-form-group oswp-form-group--grid" style={{ width: getFieldWidth(field.width) }}>
          <label className="oswp-form-label">
            {field.label}
            {field.required && <span className="required">*</span>}
            {field.min_limit && field.max_limit && (
              <span style={{ fontWeight: 400, color: 'var(--color-text-muted)', marginLeft: 8 }}>
                ({field.min_limit}–{field.max_limit} chars)
              </span>
            )}
          </label>
          <textarea
            className={`oswp-input ${errors[field.id] ? 'error' : ''}`}
            value={form[field.id] || ''}
            onChange={(e) => set(field.id, e.target.value)}
            required={field.required}
            rows={4}
            minLength={field.min_limit || undefined}
            maxLength={field.max_limit || undefined}
          />
          {renderFieldNotes(field)}
        </div>
      );
    }

    return (
      <div
        key={field.id}
        className="oswp-form-group oswp-form-group--grid"
        style={{ width: getFieldWidth(field.width) }}
      >
        <label className="oswp-form-label">
          {field.label}
          {field.required && <span className="required">*</span>}
          {field.max_limit && (
            <span style={{ fontWeight: 400, color: 'var(--color-text-muted)', marginLeft: 8 }}>
              (max {field.max_limit})
            </span>
          )}
          {field.min_limit && field.max_limit && (
            <span style={{ fontWeight: 400, color: 'var(--color-text-muted)', marginLeft: 8 }}>
              ({field.min_limit}–{field.max_limit} {field.id === 'post_title' ? 'chars' : 'chars'})
            </span>
          )}
        </label>
        <input
          type="text"
          className={`oswp-input ${errors[field.id] ? 'error' : ''}`}
          value={form[field.id] || ''}
          onChange={(e) => set(field.id, e.target.value)}
          required={field.required}
          maxLength={field.max_limit || undefined}
        />
        {renderFieldNotes(field)}
      </div>
    );
  };

  return (
    <div className="oswp-dashboard">
      <Sidebar />
      <div className="oswp-content-area">
        <div className="oswp-card">
          <div className="oswp-card-header">
            <h2>New Post</h2>
          </div>

          {loading ? (
            <div>
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="oswp-form-group">
                  <span className="oswp-skeleton" style={{ width: 100, height: 12, display: 'block', marginBottom: 8 }} />
                  <span className="oswp-skeleton" style={{ width: '100%', height: 40, display: 'block' }} />
                </div>
              ))}
            </div>
          ) : (
            <>
              {formDescription && <div className="oswp-alert">{formDescription}</div>}
              {Object.keys(errors).length > 0 && (
                <div className="oswp-alert oswp-alert-error">
                  <strong>Please fix the following errors:</strong>
                  <ul style={{ margin: '8px 0 0 20px', paddingLeft: 0 }}>
                    {Object.entries(errors).map(([fieldId, errorMsg]) => (
                      <li key={fieldId}>{errorMsg}</li>
                    ))}
                  </ul>
                </div>
              )}

              <form onSubmit={handleSubmit}>
                <div className="oswp-form-layout">
                  {fields.map(renderField)}
                </div>

                <div style={{ marginTop: 16, display: 'flex', gap: 10 }}>
                  <button type="submit" className="oswp-btn oswp-btn-primary" style={{ width: 'auto' }} disabled={busy}>
                    {busy ? (
                      <><span className="oswp-btn-spinner" style={{ borderColor: 'rgba(255,255,255,0.4)', borderTopColor: '#fff' }} />Submitting…</>
                    ) : 'Submit post'}
                  </button>
                  <button type="button" className="oswp-btn oswp-btn-secondary" style={{ width: 'auto' }} onClick={() => navigate('/posts')}>
                    Cancel
                  </button>
                </div>
              </form>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
