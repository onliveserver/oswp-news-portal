import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import AdminApp from './AdminApp';
import AppToastContainer from './components/AppToastContainer';
import { AuthProvider } from './context/AuthContext';
import 'react-toastify/dist/ReactToastify.css';
import './styles/global.css';
import './styles/admin-panel.css';

const portalRoot = document.getElementById('oswp-portal');
const adminRoot = document.getElementById('oswp-admin-app');
const basePath = window.oswpPortal?.basePath || '/portal';

if (portalRoot) {
  ReactDOM.createRoot(portalRoot).render(
    <React.StrictMode>
      <BrowserRouter basename={basePath}>
        <AuthProvider>
          <>
            <App />
            <AppToastContainer />
          </>
        </AuthProvider>
      </BrowserRouter>
    </React.StrictMode>
  );
}

if (adminRoot) {
  ReactDOM.createRoot(adminRoot).render(
    <React.StrictMode>
      <>
        <AdminApp />
        <AppToastContainer />
      </>
    </React.StrictMode>
  );
}
