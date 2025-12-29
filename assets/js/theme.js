(() => {
    const storageKey = 'theme-preference';
    const root = document.documentElement;
    const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function getSavedPreference() {
        try {
            const saved = localStorage.getItem(storageKey);
            if (saved === 'light' || saved === 'dark' || saved === 'auto') return saved;
        } catch (_) {}
        return 'auto';
    }

    function setSavedPreference(value) {
        try {
            localStorage.setItem(storageKey, value);
        } catch (_) {}
    }

    function applyPreference(preference) {
        if (preference === 'light' || preference === 'dark') {
            root.dataset.theme = preference;
        } else {
            root.removeAttribute('data-theme');
        }
    }

    function getEffectiveTheme(preference) {
        if (preference === 'light' || preference === 'dark') return preference;
        return media && media.matches ? 'dark' : 'light';
    }

    function getLabel(preference) {
        const effective = getEffectiveTheme(preference);
        if (preference === 'auto') return `自动（当前：${effective === 'dark' ? '深色' : '浅色'}）`;
        return preference === 'dark' ? '深色' : '浅色';
    }

    function getIcon(preference) {
        if (preference === 'auto') return '🖥️';
        return preference === 'dark' ? '🌙' : '☀️';
    }

    function updateToggleButton(preference) {
        const button = document.getElementById('themeToggle');
        if (!button) return;
        button.textContent = getIcon(preference);
        const label = getLabel(preference);
        button.setAttribute('aria-label', `主题：${label}（点击切换）`);
        button.setAttribute('title', `主题：${label}`);
    }

    function nextPreference(preference) {
        if (preference === 'auto') return 'dark';
        if (preference === 'dark') return 'light';
        return 'auto';
    }

    function init() {
        let preference = getSavedPreference();
        applyPreference(preference);
        updateToggleButton(preference);

        const button = document.getElementById('themeToggle');
        if (button) {
            button.addEventListener('click', () => {
                preference = nextPreference(preference);
                setSavedPreference(preference);
                applyPreference(preference);
                updateToggleButton(preference);
            });
        }

        if (media) {
            const onChange = () => {
                if (preference === 'auto') updateToggleButton(preference);
            };
            if (typeof media.addEventListener === 'function') media.addEventListener('change', onChange);
            else if (typeof media.addListener === 'function') media.addListener(onChange);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
