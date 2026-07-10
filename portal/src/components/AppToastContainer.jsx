import React from 'react';
import { ToastContainer } from 'react-toastify';

export default function AppToastContainer() {
  return (
    <ToastContainer
      position="bottom-right"
      autoClose={3200}
      hideProgressBar={false}
      newestOnTop
      closeOnClick
      pauseOnFocusLoss
      pauseOnHover
      draggable
      theme="light"
      toastClassName="oswp-toast"
      bodyClassName="oswp-toast-body"
    />
  );
}
