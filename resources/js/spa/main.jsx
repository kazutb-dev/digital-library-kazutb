import React, { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/spa.css';
import { App } from './App';

const root = document.getElementById('spa-root');
if (root) {
  createRoot(root).render(
    <StrictMode>
      <App />
    </StrictMode>
  );
}
