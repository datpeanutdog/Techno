const themePreferenceKey = 'pasabuy:theme';
const toast = document.querySelector('#toast');

const presenceHeartbeatUrl = document.body.dataset.presenceHeartbeatUrl;

if (presenceHeartbeatUrl) {
    let lastActivityAt = Date.now();
    let isSendingHeartbeat = false;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const recordActivity = () => {
        lastActivityAt = Date.now();
    };

    const sendPresenceHeartbeat = async () => {
        if (document.hidden || isSendingHeartbeat) return;

        isSendingHeartbeat = true;

        try {
            const response = await fetch(presenceHeartbeatUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    active: Date.now() - lastActivityAt < 15 * 60 * 1000,
                }),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Presence heartbeat failed with status ${response.status}.`);
            }
        } catch (error) {
            console.error('Unable to update messaging presence.', error);
        } finally {
            isSendingHeartbeat = false;
        }
    };

    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
        document.addEventListener(eventName, recordActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            recordActivity();
            sendPresenceHeartbeat();
        }
    });

    sendPresenceHeartbeat();
    window.setInterval(sendPresenceHeartbeat, 60 * 1000);
}

const themeSettings = document.querySelector('[data-theme-settings]');
const prefersDarkTheme = window.matchMedia('(prefers-color-scheme: dark)');
let themePreference = 'system';

try {
    const storedTheme = window.localStorage.getItem(themePreferenceKey);
    if (storedTheme === 'light' || storedTheme === 'dark' || storedTheme === 'system') {
        themePreference = storedTheme;
    }
} catch (error) {
    console.error('Unable to read the saved theme preference.', error);
}
const applyTheme = () => {
    document.documentElement.dataset.theme = themePreference === 'dark'
        || (themePreference === 'system' && prefersDarkTheme.matches)
        ? 'dark'
        : 'light';
};

applyTheme();
prefersDarkTheme.addEventListener('change', applyTheme);

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
    let lastSeenMessageId = Number(liveThread.dataset.lastSeenMessageId);
    let isCheckingForMessages = false;
    let isSendingMessage = false;
    let pollTimeout;

    liveThread.scrollTop = liveThread.scrollHeight;

    const createAvatar = (avatarUrl, name, className) => {
        const avatar = document.createElement('span');
        avatar.classList.add(className);
        avatar.setAttribute('aria-hidden', 'true');

        if (avatarUrl) {
            const image = document.createElement('img');
            image.src = avatarUrl;
            image.alt = '';
            avatar.append(image);
        } else {
            const initial = document.createElement('span');
            initial.textContent = name.trim().charAt(0).toUpperCase() || '?';
            avatar.append(initial);
        }

        return avatar;
    };

    const updateSeenMessage = (messageId) => {
        liveThread.querySelectorAll('[data-message-seen]').forEach((receipt) => receipt.remove());

        const parsedMessageId = Number(messageId);

        if (!Number.isSafeInteger(parsedMessageId) || parsedMessageId < 1) return;

        const bubble = liveThread.querySelector(`[data-message-id="${parsedMessageId}"]`);

        if (!bubble?.classList.contains('message-own')) return;

        const receipt = document.createElement('span');
        receipt.classList.add('message-seen');
        receipt.dataset.messageSeen = '';
        receipt.append(createAvatar(
            liveThread.dataset.seenAvatarUrl,
            liveThread.dataset.seenInitial,
            'message-seen-avatar',
        ));

        const label = document.createElement('span');
        label.textContent = 'Seen';
        receipt.append(label);
        bubble.append(receipt);
    };

    const addMessage = (message) => {
        const messageId = Number(message.id);

        if (!Number.isSafeInteger(messageId) || messageId <= lastMessageId) return false;

        const isOwnMessage = Number(message.sender_id) === currentUserId;
        const row = document.createElement('div');
        row.classList.add('message-row');
        if (isOwnMessage) row.classList.add('message-row-own');

        const bubble = document.createElement('article');
        bubble.classList.add('message-bubble');
        if (isOwnMessage) bubble.classList.add('message-own');

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
        const content = document.createElement('div');
        content.classList.add('message-content');
        content.append(bubble);
        const avatar = createAvatar(message.sender_avatar_url, message.sender_name, 'message-avatar');
        row.append(...(isOwnMessage ? [content, avatar] : [avatar, content]));
        liveThread.insertBefore(row, pagination);
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

            const payload = await response.json();
            const { data } = payload;

            if (!Array.isArray(data)) {
                throw new Error('Message update response did not contain a message list.');
            }

            const wasAtBottom = liveThread.scrollHeight - liveThread.scrollTop - liveThread.clientHeight < 60;
            const previousScrollTop = liveThread.scrollTop;
            const addedMessages = data.reduce((added, message) => addMessage(message) || added, false);
            lastSeenMessageId = Number.isSafeInteger(Number(payload.last_seen_message_id))
                ? Number(payload.last_seen_message_id)
                : 0;
            liveThread.dataset.lastSeenMessageId = String(lastSeenMessageId);
            updateSeenMessage(lastSeenMessageId);

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

document.querySelectorAll('[data-fetch-duration]').forEach((fetchDuration) => {
    const durationInput = fetchDuration.querySelector('input[type="number"]');
    const durationUnit = fetchDuration.querySelector('select');
    const durationHelp = document.getElementById(durationInput.getAttribute('aria-describedby'));
    const isDeliveryDeadline = fetchDuration.dataset.fetchDuration === 'delivery';
    const maximumDays = isDeliveryDeadline ? 30 : 1;
    const durationBounds = {
        minutes: { min: 5, max: maximumDays * 1440 },
        hours: { min: 1, max: maximumDays * 24 },
        days: { min: 1, max: maximumDays },
    };

    const updateDurationBounds = () => {
        const bounds = durationBounds[durationUnit.value] ?? durationBounds.minutes;
        const unitLabel = durationUnit.value === 'days' ? 'day' : durationUnit.value;

        durationInput.min = String(bounds.min);
        durationInput.max = String(bounds.max);
        durationHelp.textContent = isDeliveryDeadline
            ? `Choose ${bounds.min} to ${bounds.max} ${unitLabel}, later than the live listing expiration. Maximum: 30 days.`
            : durationUnit.value === 'days'
                ? 'Choose 1 day. Minimum request length: 5 minutes.'
                : `Choose ${bounds.min} to ${bounds.max} ${unitLabel}. Minimum: 5 minutes. Maximum: 1 day.`;
    };

    durationUnit.addEventListener('change', updateDurationBounds);
    updateDurationBounds();
});

document.querySelectorAll('[data-fetch-active-job]').forEach((job) => {
    const deadline = Date.parse(job.dataset.deliveryDeadline);
    if (!Number.isFinite(deadline)) return;

    const markDeadlinePassed = () => {
        if (Date.now() <= deadline) return;

        job.querySelector('[data-fetch-deliver-button]')?.closest('form')?.remove();

        const status = job.querySelector('[data-fetch-job-status]');
        if (status) status.textContent = 'Deadline passed';

        if (!job.querySelector('[data-fetch-deadline-warning]')) {
            const warning = document.createElement('p');
            warning.classList.add('fetch-deadline-warning');
            warning.textContent = 'The delivery deadline passed. This request cannot be marked delivered.';
            warning.dataset.fetchDeadlineWarning = '';
            job.append(warning);
        }
    };

    window.setTimeout(markDeadlinePassed, Math.max(deadline - Date.now(), 0));
});

const fetchFeed = document.querySelector('[data-fetch-feed]');

if (fetchFeed) {
    const requestList = fetchFeed.querySelector('[data-fetch-requests]');
    const fetchStatus = fetchFeed.querySelector('[data-fetch-status]');
    const updatesUrl = new URL(fetchFeed.dataset.updatesUrl, window.location.href);
    const requestIds = new Set(
        Array.from(requestList.querySelectorAll('[data-fetch-request-id]'))
            .map((request) => request.dataset.fetchRequestId),
    );
    let lastId = fetchFeed.dataset.lastId || '0';
    let isCheckingForRequests = false;
    let pollTimeout;

    const createTextElement = (tagName, className, text) => {
        const element = document.createElement(tagName);
        element.classList.add(className);
        element.textContent = text;

        return element;
    };

    const createAcceptForm = (fetchRequestId) => {
        const form = document.createElement('form');
        form.classList.add('fetch-action-form');
        form.method = 'POST';
        form.action = `${fetchFeed.dataset.acceptUrlPrefix}/${fetchRequestId}/accept`;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrfToken;
            form.append(tokenInput);
        }

        const acceptButton = document.createElement('button');
        acceptButton.classList.add('button', 'button-navy');
        acceptButton.name = 'accept';
        acceptButton.type = 'submit';
        acceptButton.textContent = 'Accept this request';
        form.append(acceptButton);

        return form;
    };

    const formatFetchTime = (timeElement) => {
        const date = new Date(timeElement.dateTime);

        if (!Number.isFinite(date.getTime())) return;

        const displayTime = new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
        timeElement.textContent = `${timeElement.dataset.fetchTimeLabel} ${displayTime}`;
    };

    requestList.querySelectorAll('time[data-fetch-time-label]').forEach(formatFetchTime);

    const showEmptyState = () => {
        if (requestList.querySelector('[data-fetch-request-id]') || requestList.querySelector('[data-fetch-empty]')) return;

        const emptyState = document.createElement('div');
        emptyState.classList.add('empty-state', 'fetch-empty-state');
        emptyState.dataset.fetchEmpty = '';
        emptyState.append(
            createTextElement('h2', 'fetch-empty-heading', 'No live requests at the moment.'),
            createTextElement('p', 'fetch-empty-copy', 'Check back soon or post what you need.'),
        );
        requestList.append(emptyState);
    };

    const removeExpiredFetchRequests = () => {
        requestList.querySelectorAll('[data-fetch-request-id]').forEach((request) => {
            const expiresAt = Date.parse(request.dataset.expiresAt);

            if (Number.isFinite(expiresAt) && expiresAt <= Date.now()) {
                requestIds.delete(request.dataset.fetchRequestId);
                request.remove();
            }
        });

        showEmptyState();
    };

    const scheduleFetchRequestExpiry = (request) => {
        const expiresAt = Date.parse(request.dataset.expiresAt);

        if (!Number.isFinite(expiresAt)) return;

        window.setTimeout(() => {
            requestIds.delete(request.dataset.fetchRequestId);
            request.remove();
            showEmptyState();
        }, Math.max(expiresAt - Date.now(), 0));
    };

    requestList.querySelectorAll('[data-fetch-request-id]').forEach(scheduleFetchRequestExpiry);

    const addFetchRequest = (fetchRequest) => {
        const requestId = String(fetchRequest.id);

        if (requestIds.has(requestId)) return;

        const article = document.createElement('article');
        article.classList.add('listing-card', 'fetch-request-card');
        article.dataset.fetchRequestId = requestId;
        article.dataset.expiresAt = fetchRequest.expires_at;

        const heading = document.createElement('div');
        heading.classList.add('fetch-request-heading');

        const requesterInfo = document.createElement('div');
        const title = createTextElement('h3', 'fetch-request-title', fetchRequest.item_name);
        const requester = createTextElement('p', 'fetch-requester', `Posted by ${fetchRequest.requester.name}`);
        requesterInfo.append(title, requester);

        const budget = fetchRequest.budget === null
            ? 'Budget flexible'
            : `Up to ₱${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(Number(fetchRequest.budget))}`;
        heading.append(requesterInfo, createTextElement('span', 'fetch-budget', budget));

        const details = createTextElement('p', 'fetch-request-details', fetchRequest.details);
        const meta = document.createElement('div');
        meta.classList.add('fetch-request-meta');
        const delivery = document.createElement('span');
        delivery.append('Deliver to ', createTextElement('strong', 'fetch-delivery-location', fetchRequest.delivery_location));
        const postedAt = createTextElement('time', 'fetch-posted', '');
        postedAt.dateTime = fetchRequest.created_at;
        postedAt.dataset.fetchTimeLabel = 'Posted';
        formatFetchTime(postedAt);
        const expiresAt = createTextElement('time', 'fetch-expiry', '');
        expiresAt.dateTime = fetchRequest.expires_at;
        expiresAt.dataset.fetchTimeLabel = 'Expires';
        formatFetchTime(expiresAt);
        const deliveryDeadline = createTextElement('time', 'fetch-expiry', '');
        deliveryDeadline.dateTime = fetchRequest.delivery_deadline;
        deliveryDeadline.dataset.fetchTimeLabel = 'Deliver by';
        formatFetchTime(deliveryDeadline);
        meta.append(delivery, postedAt, expiresAt, deliveryDeadline);

        article.append(heading, details, meta);
        if (fetchRequest.can_accept) {
            article.append(createAcceptForm(requestId));
        } else {
            article.append(createTextElement('p', 'fetch-own-request-label', 'This is your request.'));
        }
        requestList.querySelector('[data-fetch-empty]')?.remove();
        requestList.prepend(article);
        requestIds.add(requestId);
        scheduleFetchRequestExpiry(article);
        lastId = requestId;

        while (requestList.children.length > 30) {
            requestList.lastElementChild.remove();
        }
    };

    const pollForFetchRequests = async () => {
        if (isCheckingForRequests || document.hidden) return;

        removeExpiredFetchRequests();
        isCheckingForRequests = true;

        try {
            updatesUrl.searchParams.set('after', lastId);
            const response = await fetch(updatesUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Fetch request update failed with status ${response.status}.`);
            }

            const payload = await response.json();

            if (!Array.isArray(payload.data)) {
                throw new Error('Fetch request update response did not contain a request list.');
            }

            if (Array.isArray(payload.available_ids)) {
                const availableIds = new Set(payload.available_ids.map(String));
                requestList.querySelectorAll('[data-fetch-request-id]').forEach((request) => {
                    if (availableIds.has(request.dataset.fetchRequestId)) return;

                    requestIds.delete(request.dataset.fetchRequestId);
                    request.remove();
                });
            }

            payload.data.forEach(addFetchRequest);
            showEmptyState();
            if (fetchStatus) fetchStatus.textContent = 'Live updates are on.';
        } catch (error) {
            if (fetchStatus) fetchStatus.textContent = 'Connection interrupted. Retrying for new requests…';
            console.error(error);
        } finally {
            isCheckingForRequests = false;
        }
    };

    const scheduleFetchPoll = (delay = 15000) => {
        window.clearTimeout(pollTimeout);
        pollTimeout = window.setTimeout(async () => {
            await pollForFetchRequests();
            scheduleFetchPoll();
        }, delay);
    };

    pollForFetchRequests();
    scheduleFetchPoll();
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollForFetchRequests();
            scheduleFetchPoll();
        }
    });
    window.addEventListener('focus', () => {
        pollForFetchRequests();
        scheduleFetchPoll();
    });
}

const presenceIndicators = Array.from(document.querySelectorAll('[data-presence-url]'));

if (presenceIndicators.length) {
    const validPresenceStatuses = new Set(['online', 'away', 'offline']);

    const refreshPresence = async (indicator) => {
        try {
            const response = await fetch(indicator.dataset.presenceUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Presence status request failed with status ${response.status}.`);
            }

            const payload = await response.json();
            const status = payload.data?.presence_status;

            if (!validPresenceStatuses.has(status)) {
                throw new Error('Presence status response contained an invalid state.');
            }

            indicator.dataset.presenceStatus = status;
            indicator.querySelector('[data-presence-label]').textContent =
                status.charAt(0).toUpperCase() + status.slice(1);
        } catch (error) {
            console.error('Unable to refresh messaging presence.', error);
        }
    };

    const refreshAllPresence = () => {
        if (document.hidden) return;
        presenceIndicators.forEach((indicator) => refreshPresence(indicator));
    };

    refreshAllPresence();
    window.setInterval(refreshAllPresence, 30 * 1000);
    document.addEventListener('visibilitychange', refreshAllPresence);
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
