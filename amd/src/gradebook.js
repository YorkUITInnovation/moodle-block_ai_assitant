import ajax from 'core/ajax';
import notification from 'core/notification';
import * as Str from 'core/str';

const STORAGE_PREFIX = 'block_ai_assistant_gradebook';
const NOT_GRADED = '__not_graded__';
const DEFAULT_CATEGORIES = ['Assignments', 'Quizzes', 'Labs', 'Exams', 'Projects', 'Participation'];
let sessionInitPromise = null;
let requestInFlight = false;
let proposalCategories = [];
let latestProposalWeightCheck = {known: false, total: null, valid: true};
let lastKnownStateTimemodified = 0;
let pendingServerSave = null;
let saveInFlight = Promise.resolve();
let suspendAutoSave = false;
let notGradedLabel = '— Not graded —';
let selectCategoryLabel = 'Select a category…';
let missingCategoryErrorLabel = 'Please pick a category for every row (or set it to Not graded).';

let currentCourseId = 0;

const el = (id) => document.getElementById(id);

const getChatMessages = () => el('gradebook-chat-messages');

const getCourseId = () => {
    if (currentCourseId > 0) {
        return String(currentCourseId);
    }
    const node = el('block-ai-assistant-gradebook-courseid');
    const val = node ? String(node.value || '').trim() : '';
    if (val && val !== '0') {
        currentCourseId = Number(val);
        return val;
    }
    return '';
};

const getStorageKey = (kind) => {
    const cid = getCourseId();
    if (!cid) {
        return null;
    }
    return `${STORAGE_PREFIX}_${kind}_${cid}`;
};

const loadJson = (key, fallback) => {
    if (!key) {
        return fallback;
    }
    try {
        const raw = localStorage.getItem(key);
        if (!raw) {
            return fallback;
        }
        return JSON.parse(raw);
    } catch (e) {
        return fallback;
    }
};

const saveJson = (key, value) => {
    if (!key) {
        return;
    }
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
    }
};

const removeKey = (key) => {
    if (!key) {
        return;
    }
    try {
        localStorage.removeItem(key);
    } catch (e) {
    }
};

const setText = (id, value) => {
    const node = el(id);
    if (node) {
        node.textContent = value;
    }
};

const setSessionId = (sessionId) => {
    const input = el('block-ai-assistant-gradebook-sessionid');
    if (input) {
        input.value = sessionId || '';
    }
    setText('gradebook-session-badge', sessionId || '-');
    if (sessionId && sessionId !== 'starting…') {
        saveJson(getStorageKey('session_id'), sessionId);
    } else if (!sessionId) {
        removeKey(getStorageKey('session_id'));
    }
};

const getSessionId = () => {
    const input = el('block-ai-assistant-gradebook-sessionid');
    return String(input ? (input.value || '') : '').trim();
};

const resolveNextActionStage = (phase) => {
    const phaseUpper = String(phase || '').toUpperCase();
    if (!phaseUpper || phaseUpper === '—') {
        return null;
    }
    if (['INTAKE', 'INITIAL', 'ANALYZE', 'ANALYSE', 'ANALYZING', 'ANALYSING', 'DRAFT'].includes(phaseUpper)) {
        return 'proposal';
    }
    if (['PROPOSAL', 'PROPOSED'].includes(phaseUpper)) {
        return 'accept';
    }
    if (['ACCEPTED', 'MAPPING', 'MAPPED', 'READY_TO_FINALIZE'].includes(phaseUpper)) {
        return 'finalize';
    }
    if (['COMPLETED', 'FINALIZED'].includes(phaseUpper)) {
        return null;
    }
    return null;
};

const updateActionStageHighlight = (phase) => {
    const buttons = document.querySelectorAll('[data-stage]');
    const activeStage = resolveNextActionStage(phase);
    buttons.forEach((button) => {
        button.classList.remove('gradebook-action-active');
        if (button.getAttribute('data-stage') === activeStage) {
            button.classList.add('gradebook-action-active');
        }
    });
};

const setPhase = (phase) => {
    const badge = el('gradebook-phase-badge');
    if (!badge) {
        return;
    }

    badge.textContent = phase || '-';
    badge.classList.remove('bg-secondary', 'bg-info', 'bg-success', 'bg-warning', 'bg-danger');

    const phaseUpper = String(phase || '').toUpperCase();
    if (phaseUpper === 'COMPLETED') {
        badge.classList.add('bg-success');
    } else if (phaseUpper === 'ACCEPTED') {
        badge.classList.add('bg-info');
    } else if (phaseUpper === 'INTAKE') {
        badge.classList.add('bg-warning');
    } else {
        badge.classList.add('bg-secondary');
    }

    updateActionStageHighlight(phase);

    if (phase && phase !== '—') {
        saveJson(getStorageKey('phase'), phase);
    } else if (!phase) {
        removeKey(getStorageKey('phase'));
    }
};

const parseResponse = (raw) => {
    if (typeof raw !== 'string') {
        return raw;
    }

    const text = raw.trim();
    if (!text) {
        throw new Error('Empty response from server.');
    }

    try {
        return JSON.parse(text);
    } catch (e) {
        const firstObjectStart = text.indexOf('{');
        const firstArrayStart = text.indexOf('[');
        let start = -1;
        if (firstObjectStart >= 0 && firstArrayStart >= 0) {
            start = Math.min(firstObjectStart, firstArrayStart);
        } else {
            start = Math.max(firstObjectStart, firstArrayStart);
        }
        const endObject = text.lastIndexOf('}');
        const endArray = text.lastIndexOf(']');
        const end = Math.max(endObject, endArray);

        if (start >= 0 && end > start) {
            const candidate = text.slice(start, end + 1);
            try {
                return JSON.parse(candidate);
            } catch (ignored) {
            }
        }

        throw new Error(
            `Invalid non-JSON response from server: ${text.slice(0, 180)}`
        );
    }
};

const callWs = async (methodname, args) => {
    const response = await ajax.call([{methodname, args}])[0];
    return response;
};

const isSessionNotFound = (parsed) => {
    if (!parsed || typeof parsed !== 'object') {
        return false;
    }
    const status = Number(parsed.status || parsed.code_status || 0);
    const code = String(parsed.code || parsed.error_code || '').toUpperCase();
    const message = String(parsed.message || parsed.detail || parsed.error || '').toLowerCase();
    if (status === 404) {
        return true;
    }
    if (code === 'SESSION_NOT_FOUND' || code === 'GRADEBOOK_SESSION_NOT_FOUND') {
        return true;
    }
    return message.indexOf('session not found') !== -1 ||
        message.indexOf('session expired') !== -1 ||
        message.indexOf('no such session') !== -1;
};

const appendMessage = (container, text, isHuman, skipPersist) => {
    if (!container) {
        return;
    }

    const div = document.createElement('div');
    div.className = `chat-message ${isHuman ? 'human-message' : 'bot-message'}`;

    const content = document.createElement('div');
    content.className = 'message-content';
    content.textContent = text || '';

    div.appendChild(content);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;

    if (!skipPersist) {
        const history = loadJson(getStorageKey('chat_history'), []);
        history.push({role: isHuman ? 'human' : 'bot', text: text || ''});
        saveJson(getStorageKey('chat_history'), history);
        scheduleServerSave();
    }
};

const appendSystemMessage = (text) => {
    appendMessage(getChatMessages(), text, false, false);
};

const clearChatUI = () => {
    const chatMessages = getChatMessages();
    if (chatMessages) {
        chatMessages.innerHTML = '';
    }
};

const renderHistory = (history) => {
    const chatMessages = getChatMessages();
    if (!chatMessages) {
        return false;
    }
    if (!Array.isArray(history) || history.length < 1) {
        return false;
    }
    chatMessages.innerHTML = '';
    history.forEach((item) => {
        appendMessage(chatMessages, String(item.text || ''), item.role === 'human', true);
    });
    return true;
};

const restoreChatHistory = () => {
    const history = loadJson(getStorageKey('chat_history'), []);
    return renderHistory(history);
};

const isNotGraded = (category) => String(category || '').trim() === NOT_GRADED;

const rowHasCategory = (item) => item && String(item.category || '').trim().length > 0;

const getConfirmedMapping = () => {
    const node = el('gradebook-confirmed-mapping');
    try {
        return JSON.parse((node ? node.value : '') || '[]');
    } catch (e) {
        return [];
    }
};

const findMissingCategoryIndexes = (confirmed) => {
    const missing = [];
    if (!Array.isArray(confirmed)) {
        return missing;
    }
    confirmed.forEach((item, idx) => {
        if (!rowHasCategory(item)) {
            missing.push(idx);
        }
    });
    return missing;
};

const findEmptyCategories = (confirmed) => {
    if (!Array.isArray(proposalCategories) || proposalCategories.length < 1) {
        return [];
    }
    const used = new Set();
    if (Array.isArray(confirmed)) {
        confirmed.forEach((item) => {
            const cat = String(item.category || '').trim();
            if (cat !== '' && !isNotGraded(cat)) {
                used.add(cat);
            }
        });
    }
    return proposalCategories.filter((cat) => !used.has(cat));
};

const showMappingError = (text) => {
    const errorEl = el('gradebook-mapping-error');
    const textEl = el('gradebook-mapping-error-text');
    if (!errorEl || !textEl) {
        return;
    }

    textEl.textContent = text;
    errorEl.classList.remove('d-none');
    errorEl.classList.add('gradebook-error-shake');

    setTimeout(() => {
        errorEl.classList.remove('gradebook-error-shake');
    }, 500);

    const body = el('gradebook-mapping-body');
    if (body) {
        body.scrollTop = 0;
    }
};

const clearMappingError = () => {
    const errorEl = el('gradebook-mapping-error');
    if (errorEl) {
        errorEl.classList.add('d-none');
    }
};

const clearMissingHighlights = () => {
    document.querySelectorAll('#gradebook-mapping-rows tr.gradebook-row-missing').forEach((tr) => {
        tr.classList.remove('gradebook-row-missing');
    });
};

const setFinalizeEnabled = (enabled) => {
    const blockedByWeight = latestProposalWeightCheck.known && !latestProposalWeightCheck.valid;
    ['btn-gradebook-finalize', 'btn-gradebook-generate'].forEach((id) => {
        const btn = el(id);
        if (btn) {
            btn.disabled = !enabled || blockedByWeight;
        }
    });
};

const setAcceptEnabled = (enabled) => {
    const blockedByWeight = latestProposalWeightCheck.known && !latestProposalWeightCheck.valid;
    const btn = el('btn-gradebook-accept');
    if (btn) {
        btn.disabled = !enabled || blockedByWeight;
    }
};

const proposalTotalAndValid = (proposal) => {
    if (!proposal || !Array.isArray(proposal.categories)) {
        return {total: null, valid: true};
    }
    const total = proposal.categories.reduce((sum, c) => sum + Number(c.weight || 0), 0);
    const valid = Math.abs(total - 100.0) <= 0.1;
    return {total, valid};
};

const applyProposalWeightGate = (proposal) => {
    const {total, valid} = proposalTotalAndValid(proposal);
    if (total === null) {
        latestProposalWeightCheck = {known: false, total: null, valid: true};
        setAcceptEnabled(false);
        setFinalizeEnabled(false);
        return;
    }

    latestProposalWeightCheck = {known: true, total, valid};

    if (!valid) {
        setAcceptEnabled(false);
        setFinalizeEnabled(false);
        appendSystemMessage(`Weight check: total is ${total.toFixed(1)}% (expected 100%). Update weights before accepting or generating mapping.`);
    } else {
        setAcceptEnabled(true);
        setFinalizeEnabled(true);
    }
};

const setUiBusy = (isBusy) => {
    [
        'block-ai-assistant-gradebook-input',
        'block-ai-assistant-gradebook-send-btn',
        'btn-gradebook-proposal',
        'btn-gradebook-accept',
        'btn-gradebook-finalize',
        'btn-gradebook-generate',
        'btn-gradebook-reset'
    ].forEach((id) => {
        const node = el(id);
        if (node) {
            node.disabled = isBusy;
        }
    });

    if (!isBusy) {
        setFinalizeEnabled(true);
    }
};

const isWeightGateBlocked = () => latestProposalWeightCheck.known && !latestProposalWeightCheck.valid;

const getWeightGateMessage = () => {
    const total = Number(latestProposalWeightCheck.total);
    if (Number.isFinite(total)) {
        return `Weight check: total is ${total.toFixed(1)}% (expected 100%). Fix proposal weights before mapping/finalizing.`;
    }
    return 'Weight check failed. Fix proposal weights to total 100% before mapping/finalizing.';
};

const buildCategoryOptions = (currentCat) => {
    const base = proposalCategories.length > 0 ? proposalCategories.slice() : DEFAULT_CATEGORIES.slice();
    if (currentCat && !base.includes(currentCat) && currentCat !== NOT_GRADED) {
        base.push(currentCat);
    }
    return base;
};

const applyNotGradedRowStyle = (tr, isNG) => {
    if (isNG) {
        tr.classList.add('gradebook-row-not-graded');
    } else {
        tr.classList.remove('gradebook-row-not-graded');
    }
};

const persistMappingInputs = (confirmed) => {
    const jsonNode = el('gradebook-confirmed-mapping');
    if (jsonNode) {
        jsonNode.value = JSON.stringify(confirmed, null, 2);
    }
    updateMappingSummary(confirmed);
    setFinalizeEnabled(true);
    scheduleServerSave();
};

const updateMappingSummary = (confirmed) => {
    const badge = el('gradebook-mapping-count');
    if (!badge) {
        return;
    }
    if (!Array.isArray(confirmed) || confirmed.length < 1) {
        badge.textContent = '0';
        return;
    }
    const graded = confirmed.filter((item) => !isNotGraded(item.category)).length;
    badge.textContent = `${graded} / ${confirmed.length}`;
};

const syncMappingUIFromJson = () => {
    const confirmed = getConfirmedMapping();

    const empty = el('gradebook-mapping-empty');
    const wrapper = el('gradebook-mapping-table-wrapper');
    const rows = el('gradebook-mapping-rows');
    if (!rows || !empty || !wrapper) {
        return;
    }

    rows.innerHTML = '';
    if (!Array.isArray(confirmed) || confirmed.length < 1) {
        empty.classList.remove('d-none');
        wrapper.classList.add('d-none');
        updateMappingSummary([]);
        return;
    }

    empty.classList.add('d-none');
    wrapper.classList.remove('d-none');

    confirmed.forEach((item) => {
        const tr = document.createElement('tr');

        const cmid = document.createElement('td');
        cmid.textContent = item.moodle_cmid != null ? item.moodle_cmid : '';
        cmid.className = 'text-muted small';
        tr.appendChild(cmid);

        const activity = document.createElement('td');
        activity.textContent = item.activity_name || item.name || '';
        activity.className = 'gradebook-activity-name';
        tr.appendChild(activity);

        const category = document.createElement('td');

        const sel = document.createElement('select');
        sel.className = 'form-select form-select-sm gradebook-category-select';
        sel.setAttribute('aria-label', `Category for ${item.activity_name || ''}`);

        const currentValue = String(item.category || '');

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = selectCategoryLabel;
        placeholder.disabled = true;
        placeholder.hidden = true;
        if (!currentValue) {
            placeholder.selected = true;
        }
        sel.appendChild(placeholder);

        const options = buildCategoryOptions(currentValue);
        options.forEach((catName) => {
            const opt = document.createElement('option');
            opt.value = catName;
            opt.textContent = catName;
            if (catName === currentValue) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });

        const sep = document.createElement('option');
        sep.disabled = true;
        sep.textContent = '──────────';
        sel.appendChild(sep);
        const ngOpt = document.createElement('option');
        ngOpt.value = NOT_GRADED;
        ngOpt.textContent = notGradedLabel;
        if (currentValue === NOT_GRADED) {
            ngOpt.selected = true;
        }
        sel.appendChild(ngOpt);

        applyNotGradedRowStyle(tr, isNotGraded(currentValue));
        if (!currentValue) {
            sel.classList.add('gradebook-select-empty');
        }

        sel.addEventListener('change', () => {
            const val = sel.value;
            item.category = val;
            applyNotGradedRowStyle(tr, isNotGraded(val));
            tr.classList.remove('gradebook-row-missing');
            if (val) {
                sel.classList.remove('gradebook-select-empty');
            } else {
                sel.classList.add('gradebook-select-empty');
            }
            persistMappingInputs(confirmed);
            clearMappingError();
        });

        category.appendChild(sel);
        tr.appendChild(category);

        rows.appendChild(tr);
    });

    updateMappingSummary(confirmed);
    setFinalizeEnabled(true);
};

const extractContentMapping = (payload) => {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    return payload.content_mapping ||
        payload.contentMapping ||
        payload.mapping ||
        (payload.session && (payload.session.content_mapping || payload.session.contentMapping)) ||
        null;
};

const mappingToConfirmedRows = (mapping) => {
    if (!mapping || typeof mapping !== 'object') {
        return [];
    }

    const activities = mapping.graded_activities || mapping.activities || mapping.mapped_activities || [];
    if (!Array.isArray(activities)) {
        return [];
    }

    return activities.map((item) => {
        const name = item.activity_name || item.name || item.module_name || '';
        const module = item.module || item.modname || item.activity_type || item.type || '';
        const confirmed = item.confirmed_category && String(item.confirmed_category).trim().length > 0
            ? String(item.confirmed_category).trim()
            : '';
        const suggested = !confirmed && item.suggested_category && String(item.suggested_category).trim().length > 0
            ? String(item.suggested_category).trim()
            : '';
        return {
            moodle_cmid: item.moodle_cmid != null ? item.moodle_cmid : (item.cmid != null ? item.cmid : item.id),
            activity_name: name,
            module: module,
            category: confirmed,
            suggested_category: suggested
        };
    }).filter((item) => item.moodle_cmid != null);
};

const normalizePrompt = (prompt) => {
    let p = String(prompt || '').trim();
    p = p.replace(/\badjuest\b/gi, 'adjust');
    return p;
};

const extractProposalCategories = (proposal) => {
    if (proposal && Array.isArray(proposal.categories)) {
        return proposal.categories.map((c) => c.name).filter(Boolean);
    }
    return [];
};

const summarizeProposalCategories = (proposal) => {
    if (!proposal || !Array.isArray(proposal.categories)) {
        return '';
    }
    return proposal.categories.map((c) => {
        const base = `${c.name}: ${c.weight}%`;
        const subs = Array.isArray(c.subcategories) ? c.subcategories : [];
        if (!subs.length) {
            return base;
        }
        const subText = subs
            .map((s) => `${s.name} ${s.weight}%`)
            .join(', ');
        return `${base} [${subText}]`;
    }).join(' | ');
};

const extractProposalEffects = (proposal) => {
    const notes = Array.isArray(proposal?.notes) ? proposal.notes : [];
    const effects = notes
        .filter((n) => typeof n === 'string' && n.startsWith('Effect:'))
        .map((n) => n.slice('Effect:'.length).trim());
    // Keep newest first for display.
    return effects.reverse();
};

const extractProposalChecks = (proposal) => {
    const notes = Array.isArray(proposal?.notes) ? proposal.notes : [];
    return notes.filter((n) => typeof n === 'string' && !n.startsWith('Effect:'));
};

const setProposalCategories = (cats) => {
    proposalCategories = Array.isArray(cats) ? cats : [];
    if (proposalCategories.length > 0) {
        saveJson(getStorageKey('proposal_categories'), proposalCategories);
    } else {
        removeKey(getStorageKey('proposal_categories'));
    }
    syncMappingUIFromJson();
};

const hydrateFromStatusPayload = (parsed) => {
    const statusPayload = parsed && parsed.session ? parsed.session : null;
    if (!statusPayload || typeof statusPayload !== 'object') {
        return;
    }

    setPhase(statusPayload.phase || parsed.phase || parsed.state || '-');

    const statusProposal = statusPayload.proposal || null;
    const cats = extractProposalCategories(statusProposal);
    if (cats.length > 0) {
        setProposalCategories(cats);
    }
    if (statusProposal && Array.isArray(statusProposal.categories)) {
        applyProposalWeightGate(statusProposal);
    }

    const mapping = extractContentMapping(statusPayload);
    const confirmed = mappingToConfirmedRows(mapping);
    if (confirmed.length > 0) {
        const node = el('gradebook-confirmed-mapping');
        if (node) {
            node.value = JSON.stringify(confirmed, null, 2);
        }
        syncMappingUIFromJson();
    }
};

const bumpLastKnown = (value) => {
    const t = Number(value) || 0;
    if (t > lastKnownStateTimemodified) {
        lastKnownStateTimemodified = t;
    }
};

const localHistoryLength = () => {
    const key = getStorageKey('chat_history');
    if (!key) {
        return 0;
    }
    const arr = loadJson(key, []);
    return Array.isArray(arr) ? arr.length : 0;
};

const stateHistoryLength = (state) => {
    if (!state) {
        return 0;
    }
    try {
        const arr = JSON.parse(state.chat_history_json || '[]');
        return Array.isArray(arr) ? arr.length : 0;
    } catch (e) {
        return 0;
    }
};

const collectStateSnapshot = () => {
    const sid = getSessionId();
    const phaseNode = el('gradebook-phase-badge');
    const phaseText = phaseNode ? phaseNode.textContent.trim() : '';
    const mappingNode = el('gradebook-confirmed-mapping');

    return {
        session_id: (sid && sid !== 'starting…') ? sid : null,
        phase: (phaseText && phaseText !== '—') ? phaseText : null,
        chat_history_json: JSON.stringify(loadJson(getStorageKey('chat_history'), [])),
        confirmed_mapping_json: mappingNode ? String(mappingNode.value || '') : null,
        result_json: JSON.stringify(loadJson(getStorageKey('result'), null))
    };
};

let consecutiveSaveFailures = 0;
let saveWarningShown = false;

const warnSaveFailure = async () => {
    if (saveWarningShown) {
        return;
    }
    saveWarningShown = true;
    try {
        const msg = await Str.get_string('gradebook_save_warning', 'block_ai_assistant');
        // eslint-disable-next-line no-console
        console.warn('[gradebook]', msg);
        if (notification && typeof notification.addNotification === 'function') {
            notification.addNotification({
                message: msg,
                type: 'warning'
            });
        }
    } catch (e) {
        // eslint-disable-next-line no-console
        console.warn('[gradebook] Failed to save session state to the server.');
    }
};

const serverSaveState = (overrides = {}) => {
    const run = async () => {
        const cid = Number(getCourseId());
        if (!cid) {
            return null;
        }
        const force = overrides && overrides.force === true;
        const snap = Object.assign({}, collectStateSnapshot(), overrides);
        try {
            const payload = {
                courseid: cid,
                session_id: snap.session_id,
                phase: snap.phase,
                chat_history_json: snap.chat_history_json,
                confirmed_mapping_json: snap.confirmed_mapping_json,
                result_json: snap.result_json,
                last_known_timemodified: Number(lastKnownStateTimemodified) || 0,
                force: !!force
            };
            const response = await callWs('block_ai_assistant_gradebook_save_state', payload);
            if (response && response.timemodified) {
                bumpLastKnown(response.timemodified);
            }
            if (response && response.conflict) {
                const serverLen = stateHistoryLength(response);
                const localLen = localHistoryLength();
                if (serverLen > localLen) {
                    hydrateFromServerState(response);
                } else if (!force) {
                    setTimeout(() => { serverSaveState({force: true}); }, 0);
                }
            }
            consecutiveSaveFailures = 0;
            return response;
        } catch (e) {
            consecutiveSaveFailures += 1;
            if (consecutiveSaveFailures >= 2) {
                warnSaveFailure();
            }
            return null;
        }
    };

    const next = saveInFlight.then(run, run);
    saveInFlight = next.catch(() => null);
    return next;
};

const scheduleServerSave = () => {
    if (pendingServerSave) {
        clearTimeout(pendingServerSave);
        pendingServerSave = null;
    }
    if (suspendAutoSave) {
        return;
    }
    pendingServerSave = setTimeout(() => {
        pendingServerSave = null;
        if (suspendAutoSave) {
            return;
        }
        serverSaveState();
    }, 400);
};

const serverClearState = async () => {
    const cid = Number(getCourseId());
    if (!cid) {
        return;
    }
    try {
        await callWs('block_ai_assistant_gradebook_save_state', {
            courseid: cid,
            clear: true
        });
    } catch (e) {
    }
    lastKnownStateTimemodified = 0;
};

const sendStateBeacon = () => {
    try {
        if (!navigator || typeof navigator.sendBeacon !== 'function') {
            return false;
        }
        const cid = Number(getCourseId());
        if (!cid) {
            return false;
        }
        const snap = collectStateSnapshot();
        const payload = JSON.stringify({
            courseid: cid,
            session_id: snap.session_id,
            phase: snap.phase,
            chat_history_json: snap.chat_history_json,
            confirmed_mapping_json: snap.confirmed_mapping_json,
            result_json: snap.result_json,
            last_known_timemodified: Number(lastKnownStateTimemodified) || 0
        });
        const url = (M && M.cfg && M.cfg.wwwroot ? M.cfg.wwwroot : '')
            + '/blocks/ai_assistant/gradebook_beacon.php';
        const blob = new Blob([payload], {type: 'application/json'});
        return navigator.sendBeacon(url, blob);
    } catch (e) {
        return false;
    }
};

const serverGetState = async () => {
    const cid = Number(getCourseId());
    if (!cid) {
        return null;
    }
    try {
        const response = await callWs('block_ai_assistant_gradebook_get_state', {
            courseid: cid
        });
        if (response && response.timemodified) {
            bumpLastKnown(response.timemodified);
        }
        return response && response.found ? response : null;
    } catch (e) {
        return null;
    }
};

const hydrateFromServerState = (state) => {
    if (!state) {
        return false;
    }

    let hydrated = false;

    try {
        const history = JSON.parse(state.chat_history_json || '[]');
        const localLen = localHistoryLength();
        if (Array.isArray(history) && history.length > 0 && history.length >= localLen) {
            saveJson(getStorageKey('chat_history'), history);
            renderHistory(history);
            hydrated = true;
        } else if (localLen > 0 && (!Array.isArray(history) || history.length < localLen)) {
            scheduleServerSave();
        }
    } catch (e) {
    }

    if (state.session_id && state.session_id !== 'starting…') {
        setSessionId(state.session_id);
        hydrated = true;
    }
    if (state.phase && state.phase !== '—') {
        setPhase(state.phase);
    }

    if (state.confirmed_mapping_json && state.confirmed_mapping_json !== '[]') {
        const node = el('gradebook-confirmed-mapping');
        if (node) {
            node.value = state.confirmed_mapping_json;
            syncMappingUIFromJson();
        }
    }

    try {
        const result = state.result_json ? JSON.parse(state.result_json) : null;
        if (result) {
            saveJson(getStorageKey('result'), result);
            renderResultPanel(result);
            hydrated = true;
        }
    } catch (e) {
    }

    return hydrated;
};

const buildSummaryText = (result) => {
    if (!result || typeof result !== 'object') {
        return '';
    }

    const lines = [];
    const summary = result.summary || result.details || null;
    if (summary && typeof summary === 'object') {
        if (summary.course_name) {
            lines.push(`Course: ${summary.course_name}`);
        }
        if (summary.total_items != null) {
            lines.push(`Total items: ${summary.total_items}`);
        }
        if (summary.created_categories != null) {
            lines.push(`Created categories: ${summary.created_categories}`);
        }
        if (summary.total_weight != null) {
            lines.push(`Total weight: ${summary.total_weight}%`);
        }
    }

    const mapping = extractContentMapping(result) || {};
    const categories = mapping.categories || (result.proposal && result.proposal.categories) || [];
    if (Array.isArray(categories) && categories.length) {
        lines.push('');
        lines.push('Categories:');
        categories.forEach((c) => {
            lines.push(`- ${c.name || ''}: ${c.weight != null ? c.weight + '%' : ''}`);
        });
    }

    if (lines.length < 1) {
        return 'Gradebook finalized.';
    }
    return lines.join('\n');
};

const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const summarizeResultCategories = (categories) => {
    if (!Array.isArray(categories) || !categories.length) {
        return '';
    }
    return categories.map((c) => {
        const base = `${c.name || ''}: ${c.weight != null ? c.weight + '%' : ''}`;
        const subs = Array.isArray(c.subcategories) ? c.subcategories : [];
        if (!subs.length) {
            return base;
        }
        const subText = subs.map((s) => `${s.name} ${s.weight}%`).join(', ');
        return `${base} [${subText}]`;
    }).join(' | ');
};

const buildSummaryHtml = (result) => {
    const summary = result && result.summary ? result.summary : {};
    const mapping = extractContentMapping(result) || {};
    const categories = mapping.categories || (result.proposal && result.proposal.categories) || [];

    const parts = [];
    if (summary.total_weight != null) {
        parts.push(`<div class="mb-1"><strong>Total weight:</strong> ${escapeHtml(summary.total_weight)}%</div>`);
    }
    if (summary.created_categories != null) {
        parts.push(`<div class="mb-1"><strong>Created categories:</strong> ${escapeHtml(summary.created_categories)}</div>`);
    }
    if (Array.isArray(categories) && categories.length) {
        parts.push(`<div class="mb-0"><strong>Categories:</strong> ${escapeHtml(summarizeResultCategories(categories))}</div>`);
    }
    if (!parts.length) {
        parts.push('<div>Gradebook finalized.</div>');
    }
    return parts.join('');
};

const renderResultPanel = (result) => {
    const panel = el('gradebook-result-panel');
    const summaryEl = el('gradebook-result-summary');
    const setupBtn = el('btn-gradebook-open-setup');
    if (!panel || !summaryEl) {
        return;
    }
    if (!result) {
        panel.classList.add('d-none');
        summaryEl.innerHTML = '';
        if (setupBtn) {
            setupBtn.setAttribute('href', '#');
        }
        return;
    }
    summaryEl.innerHTML = buildSummaryHtml(result);
    if (setupBtn) {
        const root = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? String(M.cfg.wwwroot) : '';
        const href = `${root}/grade/edit/tree/index.php?id=${encodeURIComponent(getCourseId())}`;
        setupBtn.setAttribute('href', href);
    }
    panel.classList.remove('d-none');
};

const base64ToUint8Array = (b64) => {
    const binary = atob(b64);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
};

const downloadExport = async (format) => {
    const buttons = ['btn-gradebook-download-word', 'btn-gradebook-download-pdf']
        .map((id) => el(id))
        .filter(Boolean);
    buttons.forEach((b) => { b.disabled = true; });
    try {
        const response = await callWs('block_ai_assistant_gradebook_export', {
            courseid: Number(getCourseId()),
            format: format
        });
        if (!response || !response.base64) {
            throw new Error('Empty export payload');
        }
        const bytes = base64ToUint8Array(response.base64);
        const blob = new Blob([bytes], {type: response.mime || 'application/octet-stream'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = response.filename || ('gradebook.' + (format === 'pdf' ? 'pdf' : 'doc'));
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        try {
            const msg = await Str.get_string('gradebook_download_failed', 'block_ai_assistant');
            if (notification && typeof notification.addNotification === 'function') {
                notification.addNotification({message: msg, type: 'error'});
            }
        } catch (_) {
            notification.exception(e);
        }
    } finally {
        buttons.forEach((b) => { b.disabled = false; });
    }
};

const downloadFinalizeResultAsWord = () => downloadExport('docx');
const downloadFinalizeResultAsPdf = () => downloadExport('pdf');

const doStartSession = async () => {
    const raw = await callWs('block_ai_assistant_gradebook_start', {courseid: getCourseId()});
    const parsed = parseResponse(raw);
    const sessionId = parsed.session_id || parsed.sessionId || parsed.session || '';
    setSessionId(sessionId);
    setPhase(parsed.phase || parsed.state || '-');

    const initial = parsed.initial_message || parsed.message || 'Gradebook session started.';
    appendSystemMessage(initial);

    serverSaveState();

    return sessionId;
};

const clearLocalGradebookState = async () => {
    removeKey(getStorageKey('session_id'));
    removeKey(getStorageKey('chat_history'));
    removeKey(getStorageKey('phase'));
    removeKey(getStorageKey('result'));
    removeKey(getStorageKey('proposal_categories'));
    proposalCategories = [];
    setSessionId('');
    setPhase('—');
    const node = el('gradebook-confirmed-mapping');
    if (node) {
        node.value = '';
    }
    clearChatUI();
    renderResultPanel(null);
    syncMappingUIFromJson();
    await serverClearState();
};

const remoteResetSession = async (sessionId) => {
    if (!sessionId || sessionId === 'starting…') {
        return null;
    }
    const raw = await callWs('block_ai_assistant_gradebook_reset', {
        courseid: getCourseId(),
        session_id: sessionId,
        keep_extraction: true
    });
    return parseResponse(raw);
};

const remoteDeleteSession = async (sessionId) => {
    if (!sessionId || sessionId === 'starting…') {
        return null;
    }
    const raw = await callWs('block_ai_assistant_gradebook_delete', {
        courseid: getCourseId(),
        session_id: sessionId
    });
    return parseResponse(raw);
};

const resetSession = async (systemMessageKey) => {
    suspendAutoSave = true;
    if (pendingServerSave) {
        clearTimeout(pendingServerSave);
        pendingServerSave = null;
    }
    try { await saveInFlight; } catch (_) { }

    const currentSessionId = getSessionId();
    let resetResponse = null;
    try {
        resetResponse = await remoteResetSession(currentSessionId);
    } catch (e) {
        resetResponse = null;
    }

    await clearLocalGradebookState();

    let nextSessionId = '';
    if (resetResponse && !isSessionNotFound(resetResponse)) {
        nextSessionId = String(resetResponse.session_id || currentSessionId || '').trim();
    }

    suspendAutoSave = false;

    if (nextSessionId) {
        setSessionId(nextSessionId);
        setPhase((resetResponse && (resetResponse.phase || resetResponse.state)) || 'INITIAL');
        if (systemMessageKey) {
            try {
                const msg = await Str.get_string(systemMessageKey, 'block_ai_assistant');
                appendSystemMessage(msg);
            } catch (e) {
            }
        } else if (resetResponse && resetResponse.message) {
            appendSystemMessage(String(resetResponse.message));
        }
        await serverSaveState();
        return nextSessionId;
    }

    if (systemMessageKey) {
        try {
            const msg = await Str.get_string(systemMessageKey, 'block_ai_assistant');
            appendSystemMessage(msg);
        } catch (e) {
        }
    }

    return doStartSession();
};

const deleteSessionAndRestart = async () => {
    suspendAutoSave = true;
    if (pendingServerSave) {
        clearTimeout(pendingServerSave);
        pendingServerSave = null;
    }
    try { await saveInFlight; } catch (_) { }

    const currentSessionId = getSessionId();
    let deleteResponse = null;
    try {
        deleteResponse = await remoteDeleteSession(currentSessionId);
    } catch (e) {
        deleteResponse = null;
    }

    if (deleteResponse && deleteResponse.data && deleteResponse.data.blocked) {
        suspendAutoSave = false;
        appendSystemMessage(String(deleteResponse.message || 'Nothing to delete.'));
        return currentSessionId;
    }

    await clearLocalGradebookState();
    suspendAutoSave = false;

    // Build detailed delete message
    const messages = [];
    if (deleteResponse && deleteResponse.message) {
        messages.push(String(deleteResponse.message));
    }
    
    if (deleteResponse && deleteResponse.data) {
        const data = deleteResponse.data;
        if (data.grade_setup_cleaned) {
            messages.push(data.grade_setup_message || '✓ Grade setup cleaned.');
        } else if (data.grade_setup_message) {
            messages.push('⚠ ' + data.grade_setup_message);
        }
    }

    const finalMessage = messages.length > 0 ? messages.join(' ') : 'Session deleted.';
    appendSystemMessage(finalMessage);

    return doStartSession();
};

const moodleConfirm = async ({title, message, yesLabel, noLabel}) => {
    return new Promise((resolve) => {
        notification.confirm(
            title,
            message,
            yesLabel,
            noLabel,
            () => resolve(true),
            () => resolve(false)
        );
    });
};

const softRestartSession = async (systemMessageKey) => {
    setSessionId('');
    if (systemMessageKey) {
        try {
            const msg = await Str.get_string(systemMessageKey, 'block_ai_assistant');
            appendSystemMessage(msg);
        } catch (e) {
        }
    }
    return doStartSession();
};

const restoreOrStartSession = async () => {
    const serverState = await serverGetState();
    if (serverState) {
        hydrateFromServerState(serverState);
    }

    const candidateSession = (serverState && serverState.session_id) ||
        String(loadJson(getStorageKey('session_id'), '') || '').trim();

    if (candidateSession && candidateSession !== 'starting…') {
        try {
            const raw = await callWs('block_ai_assistant_gradebook_status', {
                courseid: getCourseId(),
                session_id: candidateSession
            });
            const parsed = parseResponse(raw);
            if (isSessionNotFound(parsed)) {
                return softRestartSession('gradebook_session_expired');
            }
            setSessionId(candidateSession);
            hydrateFromStatusPayload(parsed);
            if (!serverState) {
                serverSaveState();
            }
            return candidateSession;
        } catch (e) {
            setSessionId(candidateSession);
            return candidateSession;
        }
    }

    return doStartSession();
};

let authoritativeCheckDone = false;

const ensureSession = async () => {
    if (authoritativeCheckDone && getSessionId() && getSessionId() !== 'starting…') {
        return getSessionId();
    }
    if (!sessionInitPromise) {
        sessionInitPromise = restoreOrStartSession().finally(() => {
            sessionInitPromise = null;
            authoritativeCheckDone = true;
        });
    }
    return sessionInitPromise;
};

const withRequestLock = async (action) => {
    if (requestInFlight) {
        return;
    }
    requestInFlight = true;
    setUiBusy(true);
    try {
        await action();
    } finally {
        requestInFlight = false;
        setUiBusy(false);
    }
};

const callWithSessionRetry = async (fn) => {
    try {
        const sid = getSessionId();
        const parsed = await fn(sid);
        if (isSessionNotFound(parsed)) {
            const newId = await softRestartSession('gradebook_session_expired');
            return fn(newId);
        }
        return parsed;
    } catch (e) {
        throw e;
    }
};

const shouldUseUploadFallback = (error) => {
    const raw = String((error && (error.message || error.error || error.detail)) || '').toLowerCase();
    return raw.includes('external_functions') ||
        raw.includes('invalidrecord') ||
        raw.includes('block_ai_assistant_gradebook_upload');
};

const uploadViaFallbackEndpoint = async ({courseid, session_id, filename, filetype, base64}) => {
    const root = (typeof M !== 'undefined' && M && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : '';
    const url = `${root}/blocks/ai_assistant/gradebook_upload.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}`;
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({courseid, session_id, filename, filetype, base64})
    });

    const text = await response.text();
    if (!response.ok) {
        throw new Error(text || `Upload fallback failed (${response.status})`);
    }
    return parseResponse(text);
};

const sendPrompt = async () => {
    const input = el('block-ai-assistant-gradebook-input');
    const typed = String(input.value || '').trim();
    if (!typed) {
        return;
    }
    const normalized = normalizePrompt(typed);

    await withRequestLock(async () => {
        appendMessage(getChatMessages(), typed, true, false);
        input.value = '';

        await ensureSession();

        let loadingText = 'Loading...';
        try {
            loadingText = await Str.get_string('gradebook_loading', 'block_ai_assistant');
        } catch (e) {
        }
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'chat-message bot-message';
        loadingDiv.id = 'gradebook-loading-indicator';
        loadingDiv.innerHTML = `
            <div class="message-content">
                <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
                <span class="sr-only">${loadingText}</span>
            </div>`;
        getChatMessages().appendChild(loadingDiv);

        try {
            const parsed = await callWithSessionRetry(async (sid) => {
                const raw = await callWs('block_ai_assistant_gradebook_chat', {
                    courseid: getCourseId(),
                    session_id: sid,
                    prompt: normalized
                });
                return parseResponse(raw);
            });
            setPhase(parsed.phase || parsed.state || '-');
            appendSystemMessage(parsed.reply || parsed.message || 'Updated.');
            if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
                applyProposalWeightGate(parsed.proposal);
            }
            await serverSaveState();
        } finally {
            const indicator = document.getElementById('gradebook-loading-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
    });
};

const fetchProposal = async () => {
    await withRequestLock(async () => {
        await ensureSession();

        const parsed = await callWithSessionRetry(async (sid) => {
            const raw = await callWs('block_ai_assistant_gradebook_proposal', {
                courseid: getCourseId(),
                session_id: sid
            });
            return parseResponse(raw);
        });

        const proposal = parsed.proposal || null;
        setPhase(parsed.phase || parsed.state || '-');
        appendSystemMessage('Proposal loaded.');

        if (proposal && Array.isArray(proposal.categories)) {
            const cats = extractProposalCategories(proposal);
            if (cats.length) {
                setProposalCategories(cats);
            }

            const lines = summarizeProposalCategories(proposal);
            appendSystemMessage(`Current proposal: ${lines}`);

            const effects = extractProposalEffects(proposal);
            if (effects.length) {
                const recent = effects.slice(0, 6).join(' | ');
                appendSystemMessage(`Effects (newest first): ${recent}`);
            }

            const checks = extractProposalChecks(proposal);
            if (checks.length) {
                const checkText = checks.slice(-3).join(' | ');
                appendSystemMessage(`Checks: ${checkText}`);
            }

            applyProposalWeightGate(proposal);
        } else {
            appendSystemMessage('No proposal yet. Send a prompt to generate one.');
            setAcceptEnabled(false);
        }
        await serverSaveState();
    });
};

const acceptProposal = async () => {
    await withRequestLock(async () => {
        await ensureSession();

        if (isWeightGateBlocked()) {
            appendSystemMessage(getWeightGateMessage());
            return;
        }

        const parsed = await callWithSessionRetry(async (sid) => {
            const raw = await callWs('block_ai_assistant_gradebook_accept', {
                courseid: getCourseId(),
                session_id: sid
            });
            return parseResponse(raw);
        });

        setPhase(parsed.phase || parsed.state || '-');

        if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
            applyProposalWeightGate(parsed.proposal);
        }

        const cats = extractProposalCategories(parsed.proposal || null);
        if (cats.length) {
            setProposalCategories(cats);
        }

        const confirmed = mappingToConfirmedRows(extractContentMapping(parsed));
        if (confirmed.length > 0) {
            const node = el('gradebook-confirmed-mapping');
            if (node) {
                node.value = JSON.stringify(confirmed, null, 2);
            }
            syncMappingUIFromJson();
            const drawer = el('gradebook-mapping-drawer');
            if (drawer && drawer.classList.contains('is-collapsed')) {
                drawer.classList.remove('is-collapsed');
                const toggle = el('gradebook-mapping-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
            const catHint = proposalCategories.length
                ? ` Each of your ${proposalCategories.length} categories (${proposalCategories.join(', ')}) needs at least one activity.`
                : '';
            appendSystemMessage(
                `Mapping ready: ${confirmed.length} activities. ` +
                `Pick a category for each row (or "Not graded"), then click "Generate gradebook".${catHint}`
            );
        } else {
            syncMappingUIFromJson();
            appendSystemMessage('Accepted, but mapping is empty. Check course activities and try Proposal then Generate mapping again.');
        }

        await serverSaveState();
    });
};

const highlightMissingRows = (missingIdx) => {
    clearMissingHighlights();
    const rowsWrap = el('gradebook-mapping-rows');
    if (!rowsWrap) {
        return;
    }
    const trs = rowsWrap.querySelectorAll('tr');
    missingIdx.forEach((idx) => {
        const tr = trs[idx];
        if (tr) {
            tr.classList.add('gradebook-row-missing');
        }
    });
    if (missingIdx.length > 0 && trs[missingIdx[0]] && trs[missingIdx[0]].scrollIntoView) {
        trs[missingIdx[0]].scrollIntoView({behavior: 'smooth', block: 'center'});
    }
    const drawer = el('gradebook-mapping-drawer');
    if (drawer && drawer.classList.contains('is-collapsed')) {
        drawer.classList.remove('is-collapsed');
        const toggle = el('gradebook-mapping-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    }
};

const finalizeGradebook = async () => {
    await withRequestLock(async () => {
        await ensureSession();

        if (isWeightGateBlocked()) {
            const msg = getWeightGateMessage();
            appendSystemMessage(msg);
            showMappingError(msg);
            return;
        }

        const confirmed = getConfirmedMapping();

        if (!Array.isArray(confirmed) || confirmed.length < 1) {
            appendSystemMessage('There is nothing to finalize yet. Click "Generate mapping" first.');
            return;
        }

        clearMappingError();

        const missing = findMissingCategoryIndexes(confirmed);
        if (missing.length > 0) {
            highlightMissingRows(missing);
            const template = String(missingCategoryErrorLabel || '');
            const msg = template.indexOf('{$a}') !== -1
                ? template.replace('{$a}', String(missing.length))
                : `${template} (${missing.length} row${missing.length === 1 ? '' : 's'} missing)`;
            appendSystemMessage(msg);
            showMappingError(msg);
            return;
        }

        const emptyCats = findEmptyCategories(confirmed);
        if (emptyCats.length > 0) {
            const names = emptyCats.join(', ');
            const msg = `These categories have no activities assigned: ${names}. ` +
                `Pick at least one row for each, or remove the category from the proposal.`;
            appendSystemMessage(msg);
            showMappingError(msg);

            const drawer = el('gradebook-mapping-drawer');
            if (drawer && drawer.classList.contains('is-collapsed')) {
                drawer.classList.remove('is-collapsed');
                const toggle = el('gradebook-mapping-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
            return;
        }

        const gradedRows = confirmed.filter((item) => !isNotGraded(item.category))
            .map((item) => ({moodle_cmid: item.moodle_cmid, category: item.category}));
        if (gradedRows.length < 1) {
            const msg = 'Every row is set to "Not graded" — nothing would be added to the gradebook. ' +
                'Pick a real category for at least one activity.';
            appendSystemMessage(msg);
            showMappingError(msg);
            return;
        }

        clearMissingHighlights();

        const parsed = await callWithSessionRetry(async (sid) => {
            const raw = await callWs('block_ai_assistant_gradebook_finalize', {
                courseid: getCourseId(),
                session_id: sid,
                confirmed_mapping_json: JSON.stringify(gradedRows)
            });
            return parseResponse(raw);
        });

        setPhase(parsed.phase || parsed.state || 'COMPLETED');
        appendSystemMessage(parsed.message || 'Gradebook finalized.');

        saveJson(getStorageKey('result'), parsed);
        renderResultPanel(parsed);
        await serverSaveState({result_json: JSON.stringify(parsed)});
    });
};

const resetSessionHandler = async () => {
    const title = await Str.get_string('gradebook_reset_session', 'block_ai_assistant');
    const confirmMsg = await Str.get_string('gradebook_reset_session_confirm', 'block_ai_assistant');
    const yesLabel = await Str.get_string('gradebook_reset_session', 'block_ai_assistant');
    const noLabel = await Str.get_string('cancel', 'moodle');
    const confirmed = await moodleConfirm({
        title,
        message: confirmMsg,
        yesLabel,
        noLabel
    });
    if (!confirmed) {
        return;
    }
    await withRequestLock(async () => {
        await resetSession(null);
    });
};

const deleteSessionHandler = async () => {
    const title = await Str.get_string('gradebook_delete_session', 'block_ai_assistant');
    const confirmMsg = await Str.get_string('gradebook_delete_session_confirm', 'block_ai_assistant');
    const yesLabel = await Str.get_string('gradebook_delete_session', 'block_ai_assistant');
    const noLabel = await Str.get_string('cancel', 'moodle');
    const confirmed = await moodleConfirm({
        title,
        message: confirmMsg,
        yesLabel,
        noLabel
    });
    if (!confirmed) {
        return;
    }
    await withRequestLock(async () => {
        await deleteSessionAndRestart();
    });
};

const handleFileUpload = async () => {
    const fileInput = el('gradebook-file-upload-input');
    if (!fileInput || !fileInput.files || fileInput.files.length < 1) {
        return;
    }

    const file = fileInput.files[0];
    const maxSizeMB = 10;
    if (file.size > maxSizeMB * 1024 * 1024) {
        appendSystemMessage(`⚠ File is too large (max ${maxSizeMB}MB). Please upload a smaller file.`);
        fileInput.value = '';
        return;
    }

    const allowedTypes = ['application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    const fileName = String(file.name || '').toLowerCase();
    const allowedExtensions = ['.pdf', '.doc', '.docx', '.txt', '.md', '.xls', '.xlsx'];
    const extensionAllowed = allowedExtensions.some((ext) => fileName.endsWith(ext));
    const mimeAllowed = allowedTypes.includes(file.type) || file.type === 'application/octet-stream';

    if (!mimeAllowed && !extensionAllowed) {
        appendSystemMessage('⚠ Unsupported file type. Please upload PDF, Word, or text files.');
        fileInput.value = '';
        return;
    }

    const progressEl = el('gradebook-upload-progress');
    const progressBar = el('gradebook-upload-progress-bar');
    const uploadStatus = el('gradebook-upload-status');
    
    if (progressEl) {
        progressEl.classList.remove('d-none');
    }

    try {
        const reader = new FileReader();
        
        reader.onprogress = (event) => {
            if (event.lengthComputable && progressBar) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
            }
        };

        reader.onload = async () => {
            try {
                if (uploadStatus) {
                    uploadStatus.textContent = 'Processing file...';
                }

                const base64 = btoa(String.fromCharCode.apply(null, new Uint8Array(reader.result)));
                
                await ensureSession();
                
                const parsed = await callWithSessionRetry(async (sid) => {
                    try {
                        const raw = await callWs('block_ai_assistant_gradebook_upload', {
                            courseid: getCourseId(),
                            session_id: sid,
                            filename: file.name,
                            filetype: file.type,
                            base64: base64
                        });
                        return parseResponse(raw);
                    } catch (error) {
                        if (!shouldUseUploadFallback(error)) {
                            throw error;
                        }
                        return uploadViaFallbackEndpoint({
                            courseid: getCourseId(),
                            session_id: sid,
                            filename: file.name,
                            filetype: file.type,
                            base64: base64
                        });
                    }
                });

                appendSystemMessage(`✓ Uploaded "${file.name}". I'm analyzing the grading structure...`);
                
                if (parsed.phase) {
                    setPhase(parsed.phase);
                }
                if (parsed.reply) {
                    appendSystemMessage(parsed.reply);
                }

                await serverSaveState();
            } catch (error) {
                appendSystemMessage('⚠ Failed to process uploaded file. Please try again or provide the grading breakdown in the chat.');
            } finally {
                fileInput.value = '';
                if (progressEl) {
                    progressEl.classList.add('d-none');
                }
                if (progressBar) {
                    progressBar.style.width = '0%';
                }
                if (uploadStatus) {
                    uploadStatus.textContent = 'Uploading...';
                }
            }
        };

        reader.onerror = () => {
            appendSystemMessage('⚠ Failed to read file. Please try again.');
            fileInput.value = '';
            if (progressEl) {
                progressEl.classList.add('d-none');
            }
        };

        reader.readAsArrayBuffer(file);
    } catch (error) {
        appendSystemMessage('⚠ Error uploading file. Please try again.');
        fileInput.value = '';
        if (progressEl) {
            progressEl.classList.add('d-none');
        }
    }
};

const guardedAction = (action) => {
    return () => {
        action().catch((error) => {
            notification.exception(error);
        });
    };
};

const attachListener = (id, event, handler) => {
    const node = el(id);
    if (node) {
        node.addEventListener(event, handler);
    }
};

export const init = (courseId) => {
    if (courseId) {
        currentCourseId = Number(courseId);
    }
    if (!getCourseId()) {
        return;
    }

    restoreChatHistory();
    syncMappingUIFromJson();

    const cachedSessionId = loadJson(getStorageKey('session_id'), '');
    if (cachedSessionId && cachedSessionId !== 'starting…') {
        setSessionId(cachedSessionId);
    }
    const cachedPhase = loadJson(getStorageKey('phase'), '');
    if (cachedPhase && cachedPhase !== '—') {
        setPhase(cachedPhase);
    }
    const cachedCats = loadJson(getStorageKey('proposal_categories'), []);
    if (Array.isArray(cachedCats) && cachedCats.length > 0) {
        proposalCategories = cachedCats;
        syncMappingUIFromJson();
    }

    const cachedResult = loadJson(getStorageKey('result'), null);
    if (cachedResult && cachedResult.proposal && Array.isArray(cachedResult.proposal.categories)) {
        applyProposalWeightGate(cachedResult.proposal);
    } else {
        setAcceptEnabled(false);
        setFinalizeEnabled(false);
    }

    if (cachedResult) {
        renderResultPanel(cachedResult);
    }

    ensureSession().catch((error) => {
        notification.exception(error);
    });

    attachListener('block-ai-assistant-gradebook-send-btn', 'click', guardedAction(sendPrompt));
    attachListener('gradebook-chat-form', 'submit', (e) => {
        e.preventDefault();
        guardedAction(sendPrompt)();
    });
    attachListener('block-ai-assistant-gradebook-input', 'keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            guardedAction(sendPrompt)();
        }
    });

    // Upload button handler
    attachListener('gradebook-file-upload-btn', 'click', () => {
        const fileInput = el('gradebook-file-upload-input');
        if (fileInput) {
            fileInput.click();
        }
    });

    // File selection handler
    attachListener('gradebook-file-upload-input', 'change', guardedAction(handleFileUpload));

    attachListener('btn-gradebook-proposal', 'click', guardedAction(fetchProposal));
    attachListener('btn-gradebook-accept', 'click', guardedAction(acceptProposal));
    attachListener('btn-gradebook-finalize', 'click', guardedAction(finalizeGradebook));
    attachListener('btn-gradebook-generate', 'click', guardedAction(finalizeGradebook));
    attachListener('btn-gradebook-reset', 'click', guardedAction(resetSessionHandler));
    attachListener('btn-gradebook-delete', 'click', guardedAction(deleteSessionHandler));
    attachListener('btn-gradebook-download-word', 'click', () => downloadFinalizeResultAsWord());
    attachListener('btn-gradebook-download-pdf', 'click', () => downloadFinalizeResultAsPdf());
    attachListener('btn-gradebook-open-setup', 'click', (e) => {
        const root = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? String(M.cfg.wwwroot) : '';
        if (!root) {
            return;
        }
        e.preventDefault();
        window.location.href = `${root}/grade/edit/tree/index.php?id=${encodeURIComponent(getCourseId())}`;
    });

    attachListener('gradebook-confirmed-mapping', 'input', () => {
        syncMappingUIFromJson();
        scheduleServerSave();
        clearMappingError();
    });

    const toggleMappingDrawer = () => {
        const drawer = el('gradebook-mapping-drawer');
        const toggle = el('gradebook-mapping-toggle');
        if (!drawer || !toggle) {
            return;
        }
        drawer.classList.toggle('is-collapsed');
        const expanded = !drawer.classList.contains('is-collapsed');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };
    attachListener('gradebook-mapping-toggle', 'click', toggleMappingDrawer);
    attachListener('gradebook-mapping-toggle', 'keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleMappingDrawer();
        }
    });

    const confirmedOnLoad = getConfirmedMapping();
    if (Array.isArray(confirmedOnLoad) && confirmedOnLoad.length > 0) {
        const drawer = el('gradebook-mapping-drawer');
        if (drawer) {
            drawer.classList.remove('is-collapsed');
            const toggle = el('gradebook-mapping-toggle');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    }

    Str.get_string('gradebook_not_graded', 'block_ai_assistant').then((label) => {
        if (label) {
            notGradedLabel = label;
            syncMappingUIFromJson();
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_select_category', 'block_ai_assistant').then((label) => {
        if (label) {
            selectCategoryLabel = label;
            syncMappingUIFromJson();
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_mapping_missing', 'block_ai_assistant').then((label) => {
        if (label) {
            missingCategoryErrorLabel = label;
        }
    }).catch(() => {
    });

    const flushBeforeUnload = () => {
        if (pendingServerSave) {
            clearTimeout(pendingServerSave);
            pendingServerSave = null;
        }
        sendStateBeacon();
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flushBeforeUnload();
        }
    });
    window.addEventListener('pagehide', flushBeforeUnload);
    window.addEventListener('beforeunload', flushBeforeUnload);
};
