import React, { useRef, useLayoutEffect, useCallback, useState } from 'react';

const TOOLBAR = [
  { cmd: 'bold',                label: '<b>B</b>',   title: 'Bold (Ctrl+B)' },
  { cmd: 'italic',              label: '<i>I</i>',   title: 'Italic (Ctrl+I)' },
  { cmd: 'underline',           label: '<u>U</u>',   title: 'Underline (Ctrl+U)' },
  { cmd: 'strikeThrough',       label: '<s>S</s>',   title: 'Strikethrough' },
  { type: 'sep' },
  { cmd: 'formatBlock', val: 'h1',  label: 'H1', title: 'Heading 1' },
  { cmd: 'formatBlock', val: 'h2',  label: 'H2', title: 'Heading 2' },
  { cmd: 'formatBlock', val: 'h3',  label: 'H3', title: 'Heading 3' },
  { cmd: 'formatBlock', val: 'h4',  label: 'H4', title: 'Heading 4' },
  { cmd: 'formatBlock', val: 'h5',  label: 'H5', title: 'Heading 5' },
  { cmd: 'formatBlock', val: 'h6',  label: 'H6', title: 'Heading 6' },
  { cmd: 'formatBlock', val: 'p',   label: '¶',  title: 'Paragraph' },
  { type: 'sep' },
  { cmd: 'insertUnorderedList', label: '• List', title: 'Bullet list' },
  { cmd: 'insertOrderedList',   label: '1. List', title: 'Numbered list' },
  { type: 'sep' },
  { cmd: 'createLink',          label: '🔗',  title: 'Insert link', prompt: true },
  { cmd: 'unlink',              label: '✂️',  title: 'Remove link' },
  { type: 'sep' },
  { cmd: 'formatBlock', val: 'blockquote', label: '❝', title: 'Blockquote' },
  { action: 'insertTable',      label: 'Tbl', title: 'Insert table' },
  { action: 'insertHr',         label: '―', title: 'Insert divider' },
  { type: 'sep' },
  { action: 'togglePreview',    label: 'Preview', title: 'Toggle rendered preview' },
  { type: 'sep' },
  { cmd: 'undo',  label: '↶', title: 'Undo (Ctrl+Z)' },
  { cmd: 'redo',  label: '↷', title: 'Redo (Ctrl+Y)' },
  { cmd: 'removeFormat', label: 'Clear', title: 'Clear formatting' },
];

/**
 * RichEditor — a lightweight contenteditable WYSIWYG editor.
 *
 * Props:
 *  value        – current HTML string (syncs into DOM when changed externally)
 *  onChange     – called with new HTML string on every edit
 *  placeholder  – placeholder text
 *  minLength    – optional minimum character count
 *  maxLength    – optional maximum character count
 */
export default function RichEditor({ value = '', onChange, placeholder = 'Start writing…', minLength, maxLength }) {
  const editorRef   = useRef(null);
  const lastHtmlRef = useRef(value);
  const [isPreview, setIsPreview] = useState(false);

  // Sync external value changes (e.g. AI fill, initial load) → DOM
  // without disturbing the cursor during normal typing.
  useLayoutEffect(() => {
    const el = editorRef.current;
    if (!el) return;
    const normalizedValue = value || '';
    if (el.innerHTML !== normalizedValue) {
      const shouldMoveCursor = document.activeElement === el;
      el.innerHTML = value || '';
      lastHtmlRef.current = normalizedValue;

      if (shouldMoveCursor) {
        const range = document.createRange();
        const sel   = window.getSelection();
        range.selectNodeContents(el);
        range.collapse(false);
        sel?.removeAllRanges();
        sel?.addRange(range);
      }
    }
  }, [value]);

  const handleInput = useCallback(() => {
    const html = editorRef.current?.innerHTML || '';
    lastHtmlRef.current = html;
    onChange?.(html);
  }, [onChange]);

  const exec = useCallback((e, cmd, val, isPrompt) => {
    e.preventDefault();
    e.stopPropagation();
    const el = editorRef.current;
    if (!el) return;
    el.focus();
    if (isPrompt) {
      const url = window.prompt('Enter URL (include https://):');
      if (url) document.execCommand(cmd, false, url);
    } else if (val) {
      document.execCommand(cmd, false, val);
    } else {
      document.execCommand(cmd, false, null);
    }
    const html = el.innerHTML || '';
    lastHtmlRef.current = html;
    onChange?.(html);
  }, [onChange]);

  const runAction = useCallback((e, action) => {
    e.preventDefault();
    e.stopPropagation();

    if (action === 'togglePreview') {
      setIsPreview((current) => !current);
      return;
    }

    const el = editorRef.current;
    if (!el) return;

    el.focus();

    if (action === 'insertTable') {
      document.execCommand(
        'insertHTML',
        false,
        '<table><thead><tr><th>Heading</th><th>Details</th></tr></thead><tbody><tr><td>Point 1</td><td>Explain the first point.</td></tr><tr><td>Point 2</td><td>Explain the second point.</td></tr></tbody></table><p></p>'
      );
    }

    if (action === 'insertHr') {
      document.execCommand('insertHTML', false, '<hr /><p></p>');
    }

    const html = el.innerHTML || '';
    lastHtmlRef.current = html;
    onChange?.(html);
  }, [onChange]);

  const plainLen = (value || '').replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim().length;

  return (
    <div className="oswp-rich-editor">
      <div className="oswp-rich-toolbar" onMouseDown={(e) => e.preventDefault()}>
        {TOOLBAR.map((btn, i) => {
          if (btn.type === 'sep') {
            return <span key={i} className="oswp-rich-sep" />;
          }

          if (btn.action) {
            return (
              <button
                key={i}
                type="button"
                className={`oswp-rich-btn${btn.action === 'togglePreview' && isPreview ? ' active' : ''}`}
                title={btn.title}
                onMouseDown={(e) => runAction(e, btn.action)}
              >
                {btn.label}
              </button>
            );
          }

          return (
            <button
              key={i}
              type="button"
              className="oswp-rich-btn"
              title={btn.title}
              onMouseDown={(e) => exec(e, btn.cmd, btn.val, btn.prompt)}
              dangerouslySetInnerHTML={{ __html: btn.label }}
            />
          );
        })}
      </div>

      {isPreview ? (
        <div
          className={`oswp-rich-content oswp-rich-preview${!value ? ' is-empty' : ''}`}
          data-placeholder={placeholder}
          dangerouslySetInnerHTML={{ __html: value || '' }}
        />
      ) : (
        <div
          ref={editorRef}
          className="oswp-rich-content"
          contentEditable
          suppressContentEditableWarning
          onInput={handleInput}
          data-placeholder={placeholder}
        />
      )}

      {(minLength || maxLength) && (
        <div className="oswp-rich-footer">
          <span className={minLength && plainLen < minLength ? 'oswp-rich-count-error' : ''}>
            {plainLen.toLocaleString()} chars
          </span>
          {maxLength && <span> / {maxLength.toLocaleString()} max</span>}
          {minLength && plainLen < minLength && (
            <span className="oswp-rich-count-error"> — need {(minLength - plainLen).toLocaleString()} more</span>
          )}
        </div>
      )}
    </div>
  );
}
