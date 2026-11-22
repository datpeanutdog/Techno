const themePreferenceKey = 'pasabuy:theme';
const toast = document.querySelector('#toast');

const themeSettings = document.querySelector('[data-theme-settings]');
if (themeSettings) {
    const themeStatus = themeSettings.querySelector('[data-theme-status]');
    const selectedTheme = themeSettings.querySelector(`input[name="theme"][value="${document.documentElement.dataset.theme}"]`);

    if (selectedTheme instanceof HTMLInputElement) {
        selectedTheme.checked = true;
    }

    themeSettings.addEventListener('change', (event) => {
        const themeInput = event.target;

        if (!(themeInput instanceof HTMLInputElement) || themeInput.name !== 'theme') return;
        if (themeInput.value !== 'light' && themeInput.value !== 'dark') return;

        document.documentElement.dataset.theme = themeInput.value;

        try {
            window.localStorage.setItem(themePreferenceKey, themeInput.value);
            themeStatus.textContent = '';
        } catch (error) {
            console.error('Unable to save the theme preference.', error);
            themeStatus.textContent = 'Your theme changed, but this browser could not save your preference.';
        }
    });
}

const supportReportForm = document.querySelector('[data-support-report-form]');
if (supportReportForm) {
    const proofsInput = supportReportForm.querySelector('input[name="proofs[]"]');
    const proofSizeError = supportReportForm.querySelector('[data-proof-size-error]');
    const maxProofCount = 3;
    const maxProofSize = 4 * 1024 * 1024;
    const maxTotalProofSize = 12 * 1024 * 1024;

    const validateProofSizes = () => {
        const files = Array.from(proofsInput.files ?? []);
        let error = '';

        if (files.length > maxProofCount) {
            error = 'Choose no more than 3 proof images.';
        } else if (files.some((file) => file.size > maxProofSize)) {
            error = 'Each proof image must be 4 MB or smaller.';
        } else if (files.reduce((total, file) => total + file.size, 0) > maxTotalProofSize) {
            error = 'Proof images must total 12 MB or less.';
        }

        proofSizeError.textContent = error;
        proofSizeError.hidden = !error;
        proofsInput.setCustomValidity(error);

        return !error;
    };

    proofsInput.addEventListener('change', validateProofSizes);
    supportReportForm.addEventListener('submit', (event) => {
        if (!validateProofSizes()) {
            event.preventDefault();
        }
    });
}

const liveThread = document.querySelector('[data-live-messages]');

if (liveThread) {
    const updatesUrl = new URL(liveThread.dataset.updatesUrl, window.location.href);
    const currentUserId = Number(liveThread.dataset.currentUser);
    const liveStatus = document.querySelector('[data-live-status]');
    const composer = document.querySelector('[data-live-compose]');
    const composerError = composer.querySelector('[data-compose-error]');
    const composerStatus = composer.querySelector('[data-compose-status]');
    const messageInput = composer.querySelector('textarea[name="body"]');
    const submitButton = composer.querySelector('button[type="submit"]');
    const pagination = liveThread.querySelector('.pagination-wrap');
    const messageIds = Array.from(liveThread.querySelectorAll('[data-message-id]'))
        .map((message) => Number(message.dataset.messageId));
    const latestMessageId = Number(liveThread.dataset.lastMessageId);
    let lastMessageId = Math.max(0, Number.isSafeInteger(latestMessageId) ? latestMessageId : 0, ...messageIds);
    let isCheckingForMessages = false;
    let isSendingMessage = false;
    let pollTimeout;

    liveThread.scrollTop = liveThread.scrollHeight;

    const addMessage = (message) => {
        const messageId = Number(message.id);

        if (!Number.isSafeInteger(messageId) || messageId <= lastMessageId) return false;

        const bubble = document.createElement('article');
        bubble.classList.add('message-bubble');

        if (Number(message.sender_id) === currentUserId) {
            bubble.classList.add('message-own');
        }

        bubble.dataset.messageId = String(messageId);

        const sender = document.createElement('span');
        sender.classList.add('message-sender');
        sender.textContent = message.sender_name;

        const body = document.createElement('p');
        body.textContent = message.body;

        const time = document.createElement('time');
        time.dateTime = message.created_at;
        time.textContent = message.display_time;

        bubble.append(sender, body, time);
        liveThread.insertBefore(bubble, pagination);
        liveThread.querySelector('.empty-state')?.remove();
        lastMessageId = messageId;

        return true;
    };

    const pollForMessages = async () => {
        if (isCheckingForMessages || document.hidden) return;

        isCheckingForMessages = true;

        try {
            updatesUrl.searchParams.set('after', String(lastMessageId));
            const response = await fetch(updatesUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Message update request failed with status ${response.status}.`);
            }

            const { data } = await response.json();

            if (!Array.isArray(data)) {
                throw new Error('Message update response did not contain a message list.');
            }

            const wasAtBottom = liveThread.scrollHeight - liveThread.scrollTop - liveThread.clientHeight < 60;
            const previousScrollTop = liveThread.scrollTop;
            const addedMessages = data.reduce((added, message) => addMessage(message) || added, false);

            if (addedMessages && wasAtBottom) {
                liveThread.scrollTop = liveThread.scrollHeight;
            } else if (addedMessages) {
                liveThread.scrollTop = previousScrollTop;
            }

            if (liveStatus) liveStatus.textContent = '';
        } catch (error) {
            if (liveStatus) liveStatus.textContent = 'Connection interrupted. Retrying for new messages…';
            console.error(error);
        } finally {
            isCheckingForMessages = false;
        }
    };

    composer.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSendingMessage) return;

        isSendingMessage = true;
        composerError.hidden = true;
        composerError.textContent = '';
        composerStatus.textContent = 'Sending message…';
        submitButton.disabled = true;

        try {
            const response = await fetch(composer.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(composer),
                credentials: 'same-origin',
            });
            const payload = await response.json();

            if (!response.ok) {
                const validationError = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationError ?? payload.message ?? `Message could not be sent (status ${response.status}).`);
            }

            addMessage(payload.data);
            messageInput.value = '';
            liveThread.scrollTop = liveThread.scrollHeight;
            composerStatus.textContent = 'Message sent.';
        } catch (error) {
            composerStatus.textContent = '';
            composerError.textContent = error.message || 'Message could not be sent. Please try again.';
            composerError.hidden = false;
            console.error(error);
        } finally {
            submitButton.disabled = false;
            isSendingMessage = false;
        }
    });

    pollForMessages();
    const schedulePoll = () => {
        window.clearTimeout(pollTimeout);
        pollTimeout = window.setTimeout(async () => {
            await pollForMessages();
            schedulePoll();
        }, 2000);
    };

    schedulePoll();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollForMessages();
            schedulePoll();
        }
    });

    window.addEventListener('focus', () => {
        pollForMessages();
        schedulePoll();
    });
}

const savedFavorites = () => {
    return new Set(
        Array.from(document.querySelectorAll('[data-favorite][aria-pressed="true"]'))
            .map((button) => button.dataset.favorite)
            .filter((id) => /^[1-9]\d*$/.test(id ?? '')),
    );
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

const savedLink = document.querySelector('[data-saved-link]');
savedLink?.addEventListener('click', (event) => {
    event.preventDefault();
    window.location.assign(savedLink.href);
});

const savedView = document.querySelector('[data-saved-view]');
if (savedView) {
}

document.querySelectorAll('[data-favorite]').forEach((button) => {
    button.addEventListener('click', async () => {
        const favorites = savedFavorites();
        const wasSaved = favorites.has(button.dataset.favorite);
        const url = wasSaved ? button.dataset.unsaveUrl : button.dataset.saveUrl;
        const method = wasSaved ? 'DELETE' : 'POST';

        button.disabled = true;

        try {
            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Saving the listing failed with status ${response.status}.`);
            }

            const payload = await response.json();
            button.setAttribute('aria-pressed', payload.saved ? 'true' : 'false');
            button.setAttribute('aria-label', `${payload.saved ? 'Remove' : 'Save'} ${button.getAttribute('aria-label').replace(/^(Save|Remove) /, '')}`);
        } catch (error) {
            console.error(error);
            showToast('Your saved listing could not be updated. Please try again.');
            button.disabled = false;
            return;
        }

        button.disabled = false;
        updateFavoriteButtons();

        if (wasSaved && savedView) {
            button.closest('.listing-card')?.remove();
            window.location.reload();
            return;
        }

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

const emailVerificationCard = document.querySelector('[data-email-verification]');

if (emailVerificationCard) {
    const pendingVerification = emailVerificationCard.querySelector('[data-verification-pending]');
    const completedVerification = emailVerificationCard.querySelector('[data-verification-complete]');
    const verificationMessage = emailVerificationCard.querySelector('[data-verification-message]');
    const initialVerificationMessage = verificationMessage.textContent;
    let isCheckingVerification = false;

    const checkVerificationStatus = async () => {
        if (document.hidden || pendingVerification.hidden || isCheckingVerification) return;

        isCheckingVerification = true;

        try {
            const response = await fetch(emailVerificationCard.dataset.statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Verification status request failed with status ${response.status}.`);
            }

            const payload = await response.json();

            if (typeof payload.verified !== 'boolean') {
                throw new Error('Verification status response did not contain a verified state.');
            }

            if (payload.verified) {
                pendingVerification.hidden = true;
                completedVerification.hidden = false;
            } else {
                verificationMessage.textContent = initialVerificationMessage;
            }
        } catch (error) {
            verificationMessage.textContent = 'We could not check verification status. Refresh this page after opening the email link.';
            console.error('Unable to check email verification status.', error);
        } finally {
            isCheckingVerification = false;
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            checkVerificationStatus();
        }
    });

    window.addEventListener('focus', checkVerificationStatus);
}
