const favoriteKey = 'pasabuy:favorites';
const toast = document.querySelector('#toast');

const savedFavorites = () => {
    const stored = JSON.parse(window.localStorage.getItem(favoriteKey) || '[]');

    return new Set(stored.map(String));
};

const updateFavoriteButtons = () => {
    const favorites = savedFavorites();

    document.querySelectorAll('[data-favorite]').forEach((button) => {
        const isSaved = favorites.has(button.dataset.favorite);
        const listingName = button.getAttribute('aria-label').replace(/^(Save|Remove) /, '');

        button.setAttribute('aria-pressed', String(isSaved));
        button.setAttribute('aria-label', `${isSaved ? 'Remove' : 'Save'} ${listingName}`);
    });
};

const showToast = (message) => {
    if (!toast) return;

    toast.textContent = message;
    toast.classList.add('toast-visible');
    window.clearTimeout(showToast.timeout);
    showToast.timeout = window.setTimeout(() => toast.classList.remove('toast-visible'), 2400);
};

updateFavoriteButtons();

document.querySelectorAll('[data-favorite]').forEach((button) => {
    button.addEventListener('click', () => {
        const favorites = savedFavorites();
        const wasSaved = favorites.has(button.dataset.favorite);

        if (wasSaved) {
            favorites.delete(button.dataset.favorite);
        } else {
            favorites.add(button.dataset.favorite);
        }

        window.localStorage.setItem(favoriteKey, JSON.stringify([...favorites]));
        updateFavoriteButtons();
        showToast(wasSaved ? 'Removed from your saved finds.' : 'Saved for later. Good choice!');
    });
});

document.querySelector('#sort')?.addEventListener('change', (event) => {
    event.target.form.requestSubmit();
});

const listingType = document.querySelector('#type');
const rentPeriodField = document.querySelector('#rent-period-field');
const rentPeriodSelect = document.querySelector('#rent_period');

const updateRentPeriod = () => {
    if (!listingType || !rentPeriodField || !rentPeriodSelect) return;

    const isRenting = listingType.value === 'rent';
    rentPeriodField.hidden = !isRenting;
    rentPeriodSelect.required = isRenting;
};

listingType?.addEventListener('change', updateRentPeriod);
updateRentPeriod();

const sidebar = document.querySelector('#site-sidebar');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');
const sidebarCollapseButton = document.querySelector('[data-sidebar-collapse]');
const sidebarLayout = sidebar?.closest('.site-layout');
const sidebarBreakpoint = window.matchMedia('(max-width: 900px)');
const sidebarPreferenceKey = 'pasabuy:sidebar-collapsed';

if (sidebar && sidebarToggle && sidebarLayout) {
    if (!sidebarBreakpoint.matches && window.localStorage.getItem(sidebarPreferenceKey) === 'true') {
        sidebarLayout.classList.add('sidebar-collapsed');
    }

    const updateSidebarControls = () => {
        const isMobile = sidebarBreakpoint.matches;
        const isExpanded = isMobile
            ? document.body.classList.contains('sidebar-open')
            : !sidebarLayout.classList.contains('sidebar-collapsed');
        const label = isMobile
            ? (isExpanded ? 'Close navigation menu' : 'Open navigation menu')
            : (isExpanded ? 'Hide sidebar' : 'Show sidebar');

        sidebarToggle.setAttribute('aria-expanded', String(isExpanded));
        sidebarToggle.setAttribute('aria-label', label);
        sidebarToggle.setAttribute('title', label);
        sidebarCollapseButton?.setAttribute('aria-expanded', String(!sidebarLayout.classList.contains('sidebar-collapsed')));
    };

    const setSidebarOpen = (isOpen) => {
        document.body.classList.toggle('sidebar-open', isOpen);
        updateSidebarControls();

        if (isOpen) {
            const activeLink = sidebar.querySelector('[aria-current="page"]') ?? sidebar.querySelector('.main-nav a');
            activeLink?.focus();
        } else {
            sidebarToggle.focus();
        }
    };

    const setSidebarCollapsed = (isCollapsed) => {
        sidebarLayout.classList.toggle('sidebar-collapsed', isCollapsed);
        window.localStorage.setItem(sidebarPreferenceKey, String(isCollapsed));
        updateSidebarControls();

        if (isCollapsed) {
            sidebarToggle.focus();
        } else {
            const activeLink = sidebar.querySelector('[aria-current="page"]') ?? sidebar.querySelector('.main-nav a');
            activeLink?.focus();
        }
    };

    updateSidebarControls();

    sidebarToggle.addEventListener('click', () => {
        if (sidebarBreakpoint.matches) {
            setSidebarOpen(!document.body.classList.contains('sidebar-open'));
        } else {
            setSidebarCollapsed(!sidebarLayout.classList.contains('sidebar-collapsed'));
        }
    });

    sidebarCollapseButton?.addEventListener('click', () => setSidebarCollapsed(true));

    sidebarCloseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (sidebarBreakpoint.matches) {
                setSidebarOpen(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebarBreakpoint.matches && document.body.classList.contains('sidebar-open')) {
            setSidebarOpen(false);
        }
    });

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 900px)').matches) {
                setSidebarOpen(false);
            }
        });
    });

    sidebarBreakpoint.addEventListener('change', () => {
        document.body.classList.remove('sidebar-open');

        if (!sidebarBreakpoint.matches) {
            sidebarLayout.classList.toggle('sidebar-collapsed', window.localStorage.getItem(sidebarPreferenceKey) === 'true');
        }

        updateSidebarControls();
    });
}
