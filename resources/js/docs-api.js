import { createApiReference } from '@scalar/api-reference';
import '@scalar/api-reference/style.css';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('api-docs');

    if (!container) {
        return;
    }

    const specUrl = container.dataset.specUrl;
    const apiBaseUrl = container.dataset.apiBaseUrl;

    if (!specUrl || !apiBaseUrl) {
        return;
    }

    createApiReference('#api-docs', {
        url: specUrl,
        servers: [
            {
                url: apiBaseUrl,
                description: 'Current environment',
            },
        ],
        darkMode: true,
        forceDarkModeState: 'dark',
    });
});
