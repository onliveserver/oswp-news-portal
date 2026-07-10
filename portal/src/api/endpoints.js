import { api } from './client';

export const authApi = {
  me: () => api.get('/auth/me'),
  login: (data) => api.post('/auth/login', data),
  register: (data) => api.post('/auth/register', data),
  logout: () => api.post('/auth/logout'),
  forgotPassword: (data) => api.post('/auth/forgot-password', data),
  verifyResetOtp: (data) => api.post('/auth/verify-reset-otp', data),
  resetPassword: (data) => api.post('/auth/reset-password', data),
  verifyOtp: (data) => api.post('/auth/verify-otp', data),
  resendOtp: (data) => api.post('/auth/resend-otp', data),
};

export const profileApi = {
  get: () => api.get('/profile'),
  update: (data) => api.post('/profile', data),
  changePassword: (data) => api.post('/profile/password', data),
};

export const postsApi = {
  list: (params = '') => api.get(`/posts${params ? '?' + params : ''}`),
  get: (id) => api.get(`/posts/${id}`),
  create: (formData) => api.post('/posts', formData),
  update: (id, formData) => api.post(`/posts/${id}`, formData),
  del: (id) => api.del(`/posts/${id}`),
};

export const settingsApi = {
  getPublic: () => api.get('/settings/public'),
  getCategories: () => api.get('/categories'),
};

export const adminApi = {
  getOverview: () => api.get('/admin/overview'),
  getSettings: () => api.get('/admin/settings'),
  saveSettings: (section, values) => api.post('/admin/settings', { section, values }),
};
