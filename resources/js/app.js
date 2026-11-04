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
