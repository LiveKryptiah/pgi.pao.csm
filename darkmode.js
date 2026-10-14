/**
 * CSM Unified Dark Mode Controller
 * Handles persistent theme switching, icon state updates, and custom theme events.
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'csm_theme';

    function getSavedTheme() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }
        return null;
    }

    function getPreferredTheme() {
        const saved = getSavedTheme();
        if (saved) return saved;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function updateUi(theme) {
        // Update all toggle buttons on page
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            const moonIcon = btn.querySelector('.theme-icon-moon');
            const sunIcon = btn.querySelector('.theme-icon-sun');
            const labelSpan = btn.querySelector('.theme-label');

            if (theme === 'dark') {
                if (moonIcon) moonIcon.classList.add('d-none');
                if (sunIcon) sunIcon.classList.remove('d-none');
                if (labelSpan) labelSpan.textContent = 'Light';
                btn.setAttribute('title', 'Switch to Light Mode');
                btn.setAttribute('aria-label', 'Switch to Light Mode');
            } else {
                if (moonIcon) moonIcon.classList.remove('d-none');
                if (sunIcon) sunIcon.classList.add('d-none');
                if (labelSpan) labelSpan.textContent = 'Dark';
                btn.setAttribute('title', 'Switch to Dark Mode');
                btn.setAttribute('aria-label', 'Switch to Dark Mode');
            }
        });
    }

    function applyTheme(theme, notify) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        updateUi(theme);

        if (notify !== false) {
            try {
                window.dispatchEvent(new CustomEvent('csmThemeChanged', { detail: { theme: theme } }));
            } catch(e) {}
        }
    }

    window.setCsmTheme = function(theme) {
        if (theme !== 'dark' && theme !== 'light') return;
        localStorage.setItem(STORAGE_KEY, theme);
        applyTheme(theme, true);
    };

    window.toggleCsmTheme = function() {
        const current = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        window.setCsmTheme(next);
    };

    // Apply immediate initial theme
    const initialTheme = getPreferredTheme();
    applyTheme(initialTheme, false);

    // Bind DOM events
    function initEvents() {
        updateUi(document.documentElement.getAttribute('data-bs-theme') || initialTheme);

        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            if (!btn.dataset.themeBound) {
                btn.dataset.themeBound = 'true';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.toggleCsmTheme();
                });
            }
        });

        // Listen for OS scheme change if user hasn't explicitly set preference
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (!getSavedTheme()) {
                    applyTheme(e.matches ? 'dark' : 'light', true);
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEvents);
    } else {
        initEvents();
    }
})();
