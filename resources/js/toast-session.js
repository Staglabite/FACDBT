import { toast } from './helpers';

const showSessionToast = () => {
  if (!window.toastData) {
    return;
  }

  const type = window.toastData.type || 'info';
  const message = window.toastData.message || window.toastData.text || '';

  if (message && typeof toast[type] === 'function') {
    toast[type](message);
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', showSessionToast);
} else {
  showSessionToast();
}
