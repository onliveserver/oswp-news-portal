import React, { useRef, useState } from 'react';

/**
 * ImageUploader — drag-and-drop / click-to-upload with live preview.
 *
 * Props:
 *  value     – string | null  (existing image URL for preview)
 *  onChange  – fn(file, previewUrl) called after user picks a file
 *  onRemove  – fn() called when user removes the image
 *  accept    – string, default 'image/*'
 *  label     – label string, default 'Featured Image'
 */
export default function ImageUploader({
  value = null,
  onChange,
  onRemove,
  accept = 'image/*',
  label = 'Featured Image',
}) {
  const inputRef             = useRef(null);
  const [dragging, setDrag]  = useState(false);
  const [hover, setHover]    = useState(false);

  const processFile = (file) => {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      onChange?.(file, e.target.result);
    };
    reader.readAsDataURL(file);
  };

  const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    if (file) processFile(file);
    // Reset input so re-selecting the same file triggers onChange
    e.target.value = '';
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setDrag(false);
    const file = e.dataTransfer.files?.[0];
    if (file) processFile(file);
  };

  const handleDragOver  = (e) => { e.preventDefault(); setDrag(true);  };
  const handleDragLeave = ()      => setDrag(false);

  return (
    <div className="oswp-img-uploader-wrap">
      {label && <label className="oswp-form-label">{label}</label>}

      <input
        ref={inputRef}
        type="file"
        accept={accept}
        style={{ display: 'none' }}
        onChange={handleFileChange}
      />

      {value ? (
        /* Preview state */
        <div
          className={`oswp-img-preview${hover ? ' hovered' : ''}`}
          onMouseEnter={() => setHover(true)}
          onMouseLeave={() => setHover(false)}
        >
          <img src={value} alt="Feature preview" />
          {hover && (
            <div className="oswp-img-preview-actions">
              <button
                type="button"
                className="oswp-img-action-btn"
                onClick={() => inputRef.current?.click()}
                title="Change image"
              >
                ✏️ Change
              </button>
              <button
                type="button"
                className="oswp-img-action-btn oswp-img-action-remove"
                onClick={onRemove}
                title="Remove image"
              >
                🗑 Remove
              </button>
            </div>
          )}
        </div>
      ) : (
        /* Drop zone */
        <div
          className={`oswp-img-uploader${dragging ? ' drag-over' : ''}`}
          onClick={() => inputRef.current?.click()}
          onDrop={handleDrop}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          role="button"
          tabIndex={0}
          onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') inputRef.current?.click(); }}
          aria-label="Upload image"
        >
          <div className="oswp-img-uploader-inner">
            <span className="oswp-img-icon">🖼️</span>
            <span className="oswp-img-uploader-title">
              {dragging ? 'Drop image here' : 'Click or drag an image here'}
            </span>
            <span className="oswp-img-uploader-hint">PNG, JPG, WebP — max 5 MB</span>
          </div>
        </div>
      )}
    </div>
  );
}
