import React from 'react';
import { getFieldWidth } from '../utils/formFields';

function getInputType(type) {
  switch (type) {
    case 'email':
    case 'password':
    case 'tel':
    case 'url':
    case 'number':
    case 'date':
      return type;
    default:
      return 'text';
  }
}

export default function DynamicFormField({ field, value, onChange, disabled = false }) {
  if (field.type === 'tab') {
    return (
      <div className="oswp-form-section-title">
        <p>{field.label}</p>
      </div>
    );
  }

  return (
    <div
      className="oswp-form-group oswp-form-group--grid"
      style={{ width: getFieldWidth(field.width) }}
    >
      <label className="oswp-form-label" htmlFor={field.id}>
        {field.label}
        {field.required ? <span className="required">*</span> : null}
      </label>

      {field.type === 'textarea' ? (
        <textarea
          id={field.id}
          className="oswp-input"
          value={value ?? ''}
          onChange={(event) => onChange(field.id, event.target.value)}
          required={field.required}
          disabled={disabled}
        />
      ) : field.type === 'select' ? (
        <select
          id={field.id}
          className="oswp-input oswp-select"
          value={value ?? ''}
          onChange={(event) => onChange(field.id, event.target.value)}
          required={field.required}
          disabled={disabled}
        >
          <option value="">Select {field.label}</option>
          {(field.options || []).map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      ) : (
        <input
          id={field.id}
          type={getInputType(field.type)}
          className="oswp-input"
          value={value ?? ''}
          onChange={(event) => onChange(field.id, event.target.value)}
          required={field.required}
          disabled={disabled}
        />
      )}
    </div>
  );
}
