export function normalizeField(field = {}) {
  const options = field.options && typeof field.options === 'object'
    ? Object.entries(field.options).map(([value, label]) => ({
        value: String(value ?? ''),
        label: String(label ?? value ?? ''),
      }))
    : [];

  return {
    ...field,
    id: String(field.id || ''),
    label: String(field.label || ''),
    type: String(field.type || 'text'),
    width: String(field.width || '100'),
    required: field.required === true || field.required === 1 || field.required === '1',
    options,
  };
}

export function normalizeFields(fields = []) {
  return Array.isArray(fields) ? fields.map(normalizeField) : [];
}

const FIELD_H_GAP = 14; // matches .oswp-form-layout horizontal gap

export function getFieldSpan(width) {
  const numeric = Number.parseInt(width, 10);
  if (Number.isNaN(numeric) || numeric <= 0 || numeric > 100) {
    return 100;
  }

  return numeric;
}

export function getFieldWidth(widthPercentage) {
  const width = Number.parseFloat(widthPercentage);
  if (!Number.isFinite(width) || width <= 0) {
    return '100%';
  }

  if (width >= 100) {
    return '100%';
  }

  return `calc(${width}% - ${FIELD_H_GAP / 2}px)`;
}

export function getProfileFieldValue(profile, fieldId) {
  if (!profile || !fieldId) {
    return '';
  }

  const directValue = profile[fieldId];
  if (directValue !== undefined && directValue !== null && directValue !== '') {
    return directValue;
  }

  const metaValue = profile.meta?.[fieldId];
  if (metaValue !== undefined && metaValue !== null && metaValue !== '') {
    return metaValue;
  }

  return '';
}
