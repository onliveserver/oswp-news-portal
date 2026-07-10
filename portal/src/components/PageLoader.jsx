import React from 'react';

export default function PageLoader({ message = 'Loading…' }) {
  return (
    <div style={{ padding: 24, textAlign: 'center' }}>
      <div style={{ marginBottom: 12 }}>
        <span className="oswp-skeleton" style={{ width: 180, height: 18, display: 'inline-block' }} />
      </div>
      <div style={{ marginTop: 10 }}>
        <span className="oswp-skeleton" style={{ width: '60%', height: 14, display: 'inline-block' }} />
        <span className="oswp-skeleton" style={{ width: '70%', height: 14, display: 'inline-block', margin: '8px 0', backgroundColor: 'rgba(0,0,0,0.08)' }} />
        <span className="oswp-skeleton" style={{ width: '50%', height: 14, display: 'inline-block' }} />
      </div>
      <div style={{ marginTop: 12, color: 'var(--color-text-muted)', fontSize: 12 }}>{message}</div>
    </div>
  );
}
