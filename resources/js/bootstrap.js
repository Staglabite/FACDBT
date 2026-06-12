import axios from 'axios';
import { Notyf } from 'notyf';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.baseURL = window.location.origin;

//for original button content
let activeButton = null;
let originalContent = null;
const loadingContent = `
    <span class="spinner-border me-1" role="status" aria-hidden="true"></span>
    Loading...
`;

// Add an interceptor for event before submitting request
window.axios.interceptors.request.use(
    config => {
        activeButton =
            document.activeElement?.type === 'submit'
                ? document.activeElement
                : document.querySelector('button[type="submit"]');

        if (activeButton) {
            // Store the original HTML

            activeButton.querySelectorAll('.waves-ripple').forEach(r => r.remove());
            originalContent = activeButton.innerHTML;

            //embed to data for restore original
            if (!activeButton.hasAttribute('data-original-content')) {
                activeButton.setAttribute('data-original-content', originalContent);
            }

            // Create loading button HTML
            activeButton.innerHTML = loadingContent;
            activeButton.disabled = true;
        }

        return config;
    },
    error => Promise.reject(error)
);

// Add an interceptor to handle 401 unauthenticated responses
window.axios.interceptors.response.use(
    response => {
        restoreButton();
        return response;
    }, // Pass through successful responses
    error => {
        if (error.response && error.response.status === 401) {
            window.location.href = '/HITUAM/auth/login'; // Redirect to login page
        }
        restoreButton();
        return Promise.reject(error); // Reject other errors
    }
);

function restoreButton() {
    if (activeButton) {
        const original = activeButton.getAttribute('data-original-content');
        if (original !== null) {
            activeButton.innerHTML = original;
            activeButton.disabled = false;
            activeButton.removeAttribute('data-original-content');
        }

        activeButton = null;
    }
}
