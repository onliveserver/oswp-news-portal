export function getPlainText(value) {
  if (!value) return '';
  if (typeof value !== 'string') value = String(value);

  const tmp = document.createElement('div');
  tmp.innerHTML = value;
  const text = tmp.textContent || tmp.innerText || '';

  return String(text)
    .replace(/\u00a0/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function getPlainLength(value) {
  return getPlainText(value).length;
}

export function getPlainWordCount(value) {
  const text = getPlainText(value);
  if (!text) {
    return 0;
  }

  return text.split(/\s+/).length;
}

export function getFieldLength(field, value) {
  if (field?.type === 'wysiwyg') {
    return getPlainLength(value);
  }
  return String(value || '').length;
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function findBlockedKeywords(value, keywords = []) {
  if (!value || !keywords || !keywords.length) {
    return [];
  }

  const normalized = getPlainText(value).toLowerCase();
  const found = [];

  keywords.forEach((keyword) => {
    const trimmed = String(keyword || '').toLowerCase().trim();
    if (!trimmed) {
      return;
    }

    const pattern = new RegExp(`\\b${escapeRegExp(trimmed)}\\b`, 'iu');
    if (pattern.test(normalized) && !found.includes(keyword)) {
      found.push(keyword);
    }
  });

  return found;
}

export function validateWysiwygContent(html, options = {}) {
  if (!html || !html.trim()) {
    return null;
  }

  const parser = new DOMParser();
  const doc = parser.parseFromString(html, 'text/html');

  const contentAnchors = Array.from(doc.querySelectorAll('a')).length;
  if (contentAnchors > 1) {
    return 'Only one hyperlink is allowed in full content.';
  }

  const firstParagraph = doc.querySelector('p');
  if (firstParagraph && firstParagraph.querySelector('a')) {
    return 'The first paragraph cannot contain a hyperlink.';
  }

  const anchors = Array.from(doc.querySelectorAll('a'));
  for (const anchor of anchors) {
    const closestP = anchor.closest('p');
    if (!closestP) {
      return 'Hyperlinks must be inside a paragraph element.';
    }
  }

  const wordCount = getPlainWordCount(html);
  const { content_min_words: minWords = 0, content_max_words: maxWords = 0 } = options;

  if (minWords > 0 && wordCount < minWords) {
    return `Content must be at least ${minWords} words (currently ${wordCount}).`;
  }
  if (maxWords > 0 && wordCount > maxWords) {
    return `Content must not exceed ${maxWords} words (currently ${wordCount}).`;
  }

  return null;
}
