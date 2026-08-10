import ajax from 'core/ajax';
import notification from 'core/notification';
import * as Str from 'core/str';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

const STORAGE_PREFIX = 'block_ai_assistant_gradebook';
const NOT_GRADED = '__not_graded__';
const DEFAULT_CATEGORIES = ['Assignments', 'Quizzes', 'Labs', 'Exams', 'Projects', 'Participation'];
const MAX_LOCAL_CHAT_MESSAGES = 300;
const CONSTRAINED_MODULES = ['lti', 'tool', 'external'];
const CHAT_LOADER_STAGES = [
    'Connecting to retrieval engines...',
    'Searching course knowledge base...',
    'Retrieving relevant document chunks...',
    'Synthesizing response draft...',
    'Finalizing answer... almost there.'
];
const CHAT_LOADER_STEP_MS = 2200;
let sessionInitPromise = null;
let requestInFlight = false;
let proposalCategories = [];
let proposalCategoriesWithItems = [];
let proposalSubcategoriesByCategory = {};
let proposalPanelSyncPromise = null;
let queuedSystemMessages = [];
let latestProposalWeightCheck = {known: false, total: null, valid: true};
let lastKnownStateTimemodified = 0;
let pendingServerSave = null;
let saveInFlight = Promise.resolve();
let suspendAutoSave = false;
let chatStorageTrimWarned = false;
let gradebookMissingNoticeShown = false;
let notGradedLabel = '— Not graded —';
let selectCategoryLabel = 'Select a category…';
let missingCategoryErrorLabel = 'Please pick a category for every row (or set it to Not graded).';
let emptyCategoryErrorLabel = 'These categories have no activities assigned: {$a}. Pick an activity row, remove the category, or add a manual grade item to assign to it.';
let addManualItemLabel = 'Add manual grade item';
let manualItemLabel = 'Manual grade item';
let addManualItemPromptLabel = 'Name for the manual grade item in {$a}';
let addManualItemDefaultNameLabel = '{$a} Manual Item';
let addManualItemFailedLabel = 'Could not create the manual grade item. Try again.';
let addManualItemCreatedLabel = 'Added manual grade item "{$a}".';
let missingItemsAssessmentLabel = 'Assessment';
let missingItemsActionLabel = 'Action';
let missingItemsActivityLabel = 'Activity';
let missingItemsGradeItemLabel = 'Grade item';
let missingItemsSkipLabel = 'Skip';
let missingItemsSubmitLabel = 'Submit';
let missingItemsPartialFailedLabel = 'Created some items, but these failed. Resolve the remaining rows and submit again:';
let lastMissingItemDecisions = [];
let noSubcategoryLabel = '— None (Parent Category) —';
let subcategorySelectTitle = 'Optional: choose a subcategory, or keep None to stay in the parent category.';

let currentCourseId = 0;
let lastStoredFinalizeResult = null;
let promptHistory = [];
let promptHistoryIndex = -1;
let promptHistoryDraft = '';

const el = (id) => document.getElementById(id);

const getChatMessages = () => el('gradebook-chat-messages');

const focusPromptInput = () => {
    const input = el('block-ai-assistant-gradebook-input');
    if (!input) {
        return;
    }
    setTimeout(() => {
        input.focus();
    }, 0);
};

const rememberPrompt = (prompt) => {
    const text = String(prompt || '').trim();
    if (!text) {
        return;
    }
    const last = promptHistory.length > 0 ? promptHistory[promptHistory.length - 1] : null;
    if (last !== text) {
        promptHistory.push(text);
    }
    promptHistoryIndex = -1;
    promptHistoryDraft = '';
};

const navigatePromptHistory = (direction) => {
    const input = el('block-ai-assistant-gradebook-input');
    if (!input || promptHistory.length < 1) {
        return;
    }

    if (direction === 'up') {
        if (promptHistoryIndex === -1) {
            promptHistoryDraft = String(input.value || '');
            promptHistoryIndex = promptHistory.length - 1;
        } else if (promptHistoryIndex > 0) {
            promptHistoryIndex -= 1;
        }
        input.value = promptHistory[promptHistoryIndex] || '';
    } else if (direction === 'down') {
        if (promptHistoryIndex === -1) {
            return;
        }
        if (promptHistoryIndex < promptHistory.length - 1) {
            promptHistoryIndex += 1;
            input.value = promptHistory[promptHistoryIndex] || '';
        } else {
            promptHistoryIndex = -1;
            input.value = promptHistoryDraft || '';
        }
    }

    const cursor = input.value.length;
    if (input.setSelectionRange) {
        input.setSelectionRange(cursor, cursor);
    }
};

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

const isGradebookDebugEnabled = () => {
    try {
        const key = getStorageKey('debug');
        const localFlag = String((key ? localStorage.getItem(key) : '') || '').trim() === '1';
        const globalFlag = String(localStorage.getItem('block_ai_assistant_gradebook_debug') || '').trim() === '1';
        const queryFlag = typeof window !== 'undefined'
            && window.location
            && String(window.location.search || '').indexOf('gradebookDebug=1') !== -1;
        return localFlag || globalFlag || queryFlag;
    } catch (e) {
        return false;
    }
};

const gradebookDebug = (...args) => {
    if (!isGradebookDebugEnabled()) {
        return;
    }
    try {
        // eslint-disable-next-line no-console
        console.info('[gradebook-debug]', ...args);
    } catch (e) {
    }
};

const normalizeImportMode = (value) => {
    const v = String(value || '').trim().toLowerCase();
    return (v === 'fresh' || v === 'baseline') ? v : '';
};

const getImportMode = () => normalizeImportMode(loadJson(getStorageKey('import_mode'), ''));

const getBaselineAvailable = () => Boolean(loadJson(getStorageKey('baseline_available'), false));

const getRevertAvailable = () => Boolean(loadJson(getStorageKey('revert_available'), false));

const getBaselineModified = () => Boolean(loadJson(getStorageKey('baseline_modified'), false));

const setBaselineModified = (modified) => {
    if (Boolean(modified)) {
        saveJson(getStorageKey('baseline_modified'), true);
    } else {
        removeKey(getStorageKey('baseline_modified'));
    }
};

/** User chose "use existing gradebook as baseline" at session start (not merely course has a gradebook). */
const isBaselineImportSession = () => {
    if (getImportMode() === 'baseline') {
        return true;
    }
    const result = getStoredFinalizeResult();
    if (result && typeof result === 'object') {
        if (result._baseline_applied === true) {
            return true;
        }
        if (normalizeImportMode(result._import_mode) === 'baseline') {
            return true;
        }
    }
    return false;
};

const wasBaselineSession = () => isBaselineImportSession() || getBaselineAvailable();

const unwrapFinalizeResult = (result) => {
    if (!result || typeof result !== 'object') {
        return null;
    }
    if (result.raw && typeof result.raw === 'object' && (result.raw.phase || result.raw.state)) {
        return result.raw;
    }
    return result;
};

const collectFinalizeWarningStrings = (result) => {
    const warnings = [];
    const visit = (node, depth) => {
        if (!node || typeof node !== 'object' || depth > 5) {
            return;
        }
        if (Array.isArray(node.grade_setup_warnings)) {
            warnings.push(...node.grade_setup_warnings);
        }
        if (Array.isArray(node.warnings)) {
            warnings.push(...node.warnings);
        }
        if (typeof node.message === 'string') {
            warnings.push(node.message);
        }
        ['data', 'raw'].forEach((key) => {
            if (node[key] && typeof node[key] === 'object') {
                visit(node[key], depth + 1);
            }
        });
    };
    visit(result, 0);
    return warnings
        .map((w) => String(w || '').trim())
        .filter((w) => w.length > 0);
};

const hasBaselineSnapshotApply = (result) => {
    if (!isBaselineImportSession() || !result || typeof result !== 'object') {
        return false;
    }
    if (result._baseline_applied === true) {
        return true;
    }
    // Pre-apply snapshot is captured on every finalize; only count it for baseline-import sessions.
    return collectFinalizeWarningStrings(result)
        .some((w) => /immutable baseline snapshot/i.test(w));
};

const isSuccessfulFinalizeResult = (result) => {
    const payload = unwrapFinalizeResult(result) || result;
    if (!payload || typeof payload !== 'object') {
        return false;
    }
    const phase = String(payload.phase || payload.state || '').toUpperCase();
    const data = (payload.data && typeof payload.data === 'object') ? payload.data : {};
    const setupSkipped = Boolean(data.grade_setup_skipped || data.grade_setup_apply_rolled_back);
    return ['COMPLETED', 'FINALIZED'].includes(phase) && !setupSkipped;
};

const getStoredFinalizeResult = () => {
    const cached = loadJson(getStorageKey('result'), null);
    if (cached && typeof cached === 'object') {
        return cached;
    }
    return (lastStoredFinalizeResult && typeof lastStoredFinalizeResult === 'object')
        ? lastStoredFinalizeResult
        : null;
};

const syncBaselineModifiedFromFinalizeResult = (result) => {
    if (!isSuccessfulFinalizeResult(result)) {
        return false;
    }
    if (!isBaselineImportSession()) {
        return false;
    }
    setBaselineModified(true);
    return true;
};

/**
 * Restore finalize result + baseline-delete flag after refresh/reopen.
 * Prefers server result_json when it contains a successful baseline finalize.
 */
const rehydrateBaselineDeleteWarningState = (serverState = null) => {
    let result = getStoredFinalizeResult();

    if (serverState && serverState.result_json) {
        try {
            const serverResult = JSON.parse(String(serverState.result_json || 'null'));
            if (serverResult && typeof serverResult === 'object') {
                const serverFinalized = isSuccessfulFinalizeResult(serverResult);
                const localFinalized = Boolean(result && isSuccessfulFinalizeResult(result));
                const serverHasBaseline = hasBaselineSnapshotApply(serverResult);
                if (serverFinalized && (!localFinalized || serverHasBaseline)) {
                    result = serverResult;
                }
            }
        } catch (e) {
        }
    }

    if (!result || typeof result !== 'object') {
        return false;
    }

    const payload = unwrapFinalizeResult(result) || result;
    const phaseFromResult = String(payload.phase || payload.state || '').trim();
    const phase = getPhase() || phaseFromResult;
    const finalized = isSuccessfulFinalizeResult(result)
        || isFinalizeCompletedPhase(phase)
        || isFinalizeCompletedPhase(phaseFromResult);

    if (!finalized) {
        return false;
    }

    let stored = result;
    const storedImportMode = normalizeImportMode(stored._import_mode);
    if (storedImportMode === 'baseline' || stored._baseline_applied === true) {
        setImportMode('baseline', {preserveExisting: true});
    }
    if (hasBaselineSnapshotApply(result)) {
        if (!stored._baseline_applied) {
            stored = {...stored, _baseline_applied: true, _import_mode: stored._import_mode || 'baseline'};
        }
        setBaselineModified(true);
    } else {
        syncBaselineModifiedFromFinalizeResult(stored);
    }

    lastStoredFinalizeResult = stored;
    saveJson(getStorageKey('result'), stored);
    if (phaseFromResult && phaseFromResult !== '—' && phaseFromResult !== '-') {
        setPhase(phaseFromResult);
    }
    renderResultPanel(stored);

    gradebookDebug('rehydrateBaselineDeleteWarningState', {
        baselineModified: getBaselineModified(),
        baselineSnapshot: hasBaselineSnapshotApply(stored),
        phase: getPhase()
    });

    return hasBaselineSnapshotApply(stored) || getBaselineModified();
};

const setBaselineAvailable = (available) => {
    const normalized = Boolean(available);
    if (normalized) {
        saveJson(getStorageKey('baseline_available'), true);
    } else {
        removeKey(getStorageKey('baseline_available'));
    }
    setRevertButtonVisibility(getImportMode(), normalized);
    return normalized;
};

const setRevertAvailable = (available) => {
    const normalized = Boolean(available);
    if (normalized) {
        saveJson(getStorageKey('revert_available'), true);
    } else {
        removeKey(getStorageKey('revert_available'));
    }
    setRevertButtonVisibility(getImportMode(), getBaselineAvailable(), normalized);
    return normalized;
};

const setImportMode = (mode, options = {}) => {
    const normalized = normalizeImportMode(mode);
    const preserveExisting = options && options.preserveExisting === true;
    if (normalized) {
        saveJson(getStorageKey('import_mode'), normalized);
    } else if (!preserveExisting) {
        removeKey(getStorageKey('import_mode'));
    }
    setRevertButtonVisibility(normalized || getImportMode());
    return normalized || getImportMode();
};

const shouldShowRevertButton = (baselineAvailable = null, revertAvailable = null) => {
    if (!isBaselineImportSession()) {
        return false;
    }
    const hasBaseline = baselineAvailable === null ? getBaselineAvailable() : Boolean(baselineAvailable);
    const canRevert = revertAvailable === null ? getRevertAvailable() : Boolean(revertAvailable);
    return hasBaseline && canRevert;
};

const setRevertButtonVisibility = (mode = '', baselineAvailable = null, revertAvailable = null) => {
    const btn = el('btn-gradebook-revert');
    if (!btn) {
        return;
    }

    if (shouldShowRevertButton(baselineAvailable, revertAvailable)) {
        btn.classList.remove('d-none');
    } else {
        btn.classList.add('d-none');
    }
};

const payloadRevertAvailable = (parsed) => {
    if (!parsed || typeof parsed !== 'object') {
        return null;
    }
    const statusPayload = (parsed.session && typeof parsed.session === 'object') ? parsed.session : parsed;
    if (statusPayload.revert_available !== undefined) {
        return Boolean(statusPayload.revert_available);
    }
    if (parsed.revert_available !== undefined) {
        return Boolean(parsed.revert_available);
    }
    if (parsed.data && parsed.data.revert_available !== undefined) {
        return Boolean(parsed.data.revert_available);
    }
    return null;
};

const refreshRevertButtonFromLocalState = () => {
    if (!isBaselineImportSession()) {
        setRevertAvailable(false);
        return;
    }
    if (getRevertAvailable() || getBaselineModified()) {
        setRevertAvailable(true);
    }
    setRevertButtonVisibility(getImportMode(), getBaselineAvailable(), getRevertAvailable());
};

const applyRevertFlagsFromPayload = (parsed) => {
    if (!parsed || typeof parsed !== 'object') {
        refreshRevertButtonFromLocalState();
        return;
    }

    const statusPayload = (parsed.session && typeof parsed.session === 'object') ? parsed.session : parsed;
    const statusImportMode = String(
        statusPayload.import_mode ||
        (statusPayload.extraction && statusPayload.extraction.import_mode) ||
        parsed.import_mode ||
        ''
    );
    const statusNormalized = normalizeImportMode(statusImportMode);
    if (statusNormalized) {
        setImportMode(statusNormalized, {preserveExisting: true});
    }

    const baselineAvailable = statusPayload.baseline_available !== undefined
        ? Boolean(statusPayload.baseline_available)
        : Boolean((statusPayload.extraction && statusPayload.extraction.baseline_available)
            || parsed.baseline_available
            || (parsed.data && parsed.data.baseline_available));
    if (baselineAvailable) {
        setBaselineAvailable(true);
    }

    if (!isBaselineImportSession()) {
        setRevertAvailable(false);
        return;
    }

    const payloadRevert = payloadRevertAvailable(parsed);
    const effectiveRevert = payloadRevert !== null
        ? (payloadRevert && (baselineAvailable || getBaselineAvailable()))
        : (getRevertAvailable() || getBaselineModified());

    setRevertAvailable(Boolean(effectiveRevert));
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

const persistChatHistoryLocal = (history) => {
    const key = getStorageKey('chat_history');
    if (!key) {
        return false;
    }

    let candidate = Array.isArray(history) ? history.slice() : [];
    if (candidate.length > MAX_LOCAL_CHAT_MESSAGES) {
        // Keep most recent messages and drop oldest from the beginning.
        candidate = candidate.slice(candidate.length - MAX_LOCAL_CHAT_MESSAGES);
    }
    while (candidate.length >= 0) {
        try {
            localStorage.setItem(key, JSON.stringify(candidate));
            if (!chatStorageTrimWarned && candidate.length < (Array.isArray(history) ? history.length : 0)) {
                chatStorageTrimWarned = true;
                // eslint-disable-next-line no-console
                console.warn('[gradebook] Chat history trimmed locally to avoid browser storage overflow.');
            }
            return true;
        } catch (e) {
            if (candidate.length < 2) {
                break;
            }
            const drop = Math.max(1, Math.ceil(candidate.length * 0.1));
            candidate = candidate.slice(drop);
        }
    }
    return false;
};

const getStarterChatHistorySessionId = () => String(loadJson(getStorageKey('starter_chat_history_session_id'), '') || '').trim();

const getStarterChatHistory = (expectedSessionId = '') => {
    const normalized = normalizeChatHistoryEntries(loadJson(getStorageKey('starter_chat_history'), []));
    if (normalized.length < 1) {
        return [];
    }

    const expected = String(expectedSessionId || '').trim();
    const storedSession = getStarterChatHistorySessionId();
    if (expected && storedSession && storedSession !== expected) {
        return [];
    }

    return normalized;
};

const persistStarterChatHistory = (history, sessionId = '') => {
    const normalized = normalizeChatHistoryEntries(history);
    const sid = String(sessionId || getSessionId() || '').trim();
    if (normalized.length > 0) {
        saveJson(getStorageKey('starter_chat_history'), normalized);
        if (sid) {
            saveJson(getStorageKey('starter_chat_history_session_id'), sid);
        } else {
            removeKey(getStorageKey('starter_chat_history_session_id'));
        }
    } else {
        removeKey(getStorageKey('starter_chat_history'));
        removeKey(getStorageKey('starter_chat_history_session_id'));
    }
};

const clearStarterChatHistory = () => {
    removeKey(getStorageKey('starter_chat_history'));
    removeKey(getStorageKey('starter_chat_history_session_id'));
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
    if (phaseUpper === 'REFINEMENT') {
        return 'proposal';
    }
    if (['ACCEPTED', 'MAPPING', 'MAPPED', 'READY_TO_FINALIZE'].includes(phaseUpper)) {
        return 'finalize';
    }
    if (['COMPLETED', 'FINALIZED'].includes(phaseUpper)) {
        return 'finalize';
    }
    return null;
};

const isFinalizeCompletedPhase = (phase) => {
    const phaseUpper = String(phase || '').toUpperCase();
    return phaseUpper === 'COMPLETED' || phaseUpper === 'FINALIZED';
};

/**
 * Secondary delete warning: baseline finalize apply only (immutable snapshot captured).
 */
const shouldShowBaselineDeleteWarning = () => {
    if (!isBaselineImportSession()) {
        return false;
    }

    if (getBaselineModified()) {
        return true;
    }

    const result = getStoredFinalizeResult();
    const finalized = isFinalizeCompletedPhase(getPhase())
        || (result && isSuccessfulFinalizeResult(result));
    const baselineApplied = Boolean(result && result._baseline_applied === true);
    return finalized && (baselineApplied || getRevertAvailable() || hasBaselineSnapshotApply(result));
};

const syncButtonsFromPhase = (phase) => {
    const phaseUpper = String(phase || '').toUpperCase();
    const blockedByWeight = latestProposalWeightCheck.known && !latestProposalWeightCheck.valid;

    const proposalBtn = el('btn-gradebook-proposal');
    if (proposalBtn) {
        proposalBtn.disabled = false;
    }

    const acceptPhase = phaseUpper === 'PROPOSAL' || phaseUpper === 'REFINEMENT';
    const acceptBtn = el('btn-gradebook-accept');
    if (acceptBtn) {
        acceptBtn.disabled = !acceptPhase || blockedByWeight;
    }

    const finalizePhase = [
        'ACCEPTED',
        'MAPPING',
        'MAPPED',
        'READY_TO_FINALIZE',
        'REFINEMENT',
        'COMPLETED',
        'FINALIZED'
    ].includes(phaseUpper);

    ['btn-gradebook-finalize', 'btn-gradebook-generate'].forEach((id) => {
        const btn = el(id);
        if (btn) {
            btn.disabled = !finalizePhase || blockedByWeight;
        }
    });
};

const updateActionStageHighlight = (phase) => {
    const buttons = document.querySelectorAll('[data-stage]');
    const activeStage = resolveNextActionStage(phase);
    const phaseUpper = String(phase || '').toUpperCase();
    const completedFinalize = activeStage === 'finalize' && ['COMPLETED', 'FINALIZED'].includes(phaseUpper);
    buttons.forEach((button) => {
        button.classList.remove('gradebook-action-active');
        button.classList.remove('gradebook-action-complete');
        if (button.getAttribute('data-stage') === activeStage) {
            button.classList.add('gradebook-action-active');
            if (completedFinalize) {
                button.classList.add('gradebook-action-complete');
            }
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
    syncButtonsFromPhase(phase);

    if (phase && phase !== '—') {
        saveJson(getStorageKey('phase'), phase);
    } else if (!phase) {
        removeKey(getStorageKey('phase'));
    }
};

const getPhase = () => {
    const badge = el('gradebook-phase-badge');
    const badgePhase = badge ? String(badge.textContent || '').trim() : '';
    if (badgePhase && badgePhase !== '—' && badgePhase !== '-') {
        return badgePhase;
    }
    return String(loadJson(getStorageKey('phase'), '') || '').trim();
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

const extractResponseErrorMessage = (parsed, fallback = 'Request failed.') => {
    if (!parsed || typeof parsed !== 'object') {
        return fallback;
    }

    const direct = String(parsed.message || parsed.error || parsed.detail || '').trim();
    if (direct) {
        return direct;
    }

    if (Array.isArray(parsed.detail) && parsed.detail.length > 0) {
        const first = parsed.detail[0] || {};
        const loc = Array.isArray(first.loc) ? first.loc.join('.') : '';
        const msg = String(first.msg || '').trim();
        if (loc && msg) {
            return `${loc}: ${msg}`;
        }
        if (msg) {
            return msg;
        }
    }

    return fallback;
};

const getStringSafe = async (key, fallback) => {
    try {
        const value = await Str.get_string(key, 'block_ai_assistant');
        return String(value || fallback || '').trim() || fallback;
    } catch (e) {
        return fallback;
    }
};

const askBaselineModeChoice = async () => {
    const title = await getStringSafe('gradebook_baseline_prompt_title', 'Baseline detected');
    const message = await getStringSafe(
        'gradebook_baseline_prompt_message',
        'An existing gradebook layout was found. Do you want to use it as baseline?'
    );
    const yesLabel = await getStringSafe('gradebook_baseline_prompt_yes', 'Use baseline');
    const noLabel = await getStringSafe('gradebook_baseline_prompt_no', 'Start fresh');
    const yesTitle = await getStringSafe(
        'gradebook_baseline_prompt_yes_tooltip',
        'Use your existing course gradebook structure as the baseline.'
    );
    const noTitle = await getStringSafe(
        'gradebook_baseline_prompt_no_tooltip',
        'Discard baseline import and regenerate from syllabus context.'
    );

    return moodleConfirm({
        title,
        message,
        yesLabel,
        noLabel,
        yesTitle,
        noTitle
    });
};

const getExcelFormulaInput = () => {
    const node = el('gradebook-excel-formula');
    return String(node ? (node.value || '') : '').trim();
};

const clearExcelFormulaInput = () => {
    const node = el('gradebook-excel-formula');
    if (node) {
        node.value = '';
    }
};

const buildPromptWithFormula = (typedPrompt) => {
    const typed = String(typedPrompt || '').trim();
    const formula = getExcelFormulaInput();
    if (!formula) {
        return {
            displayText: typed,
            prompt: normalizePrompt(typed),
            usedFormulaInput: false
        };
    }

    if (!typed) {
        const formulaOnly = `Use this formula for grade calculation: ${formula}`;
        return {
            displayText: formulaOnly,
            prompt: normalizePrompt(formulaOnly),
            usedFormulaInput: true
        };
    }

    const lower = typed.toLowerCase();
    const looksLikeFormulaIntent =
        lower.includes('formula') ||
        lower.includes('calculation') ||
        typed.includes('[[') ||
        typed.startsWith('=');

    if (looksLikeFormulaIntent) {
        return {
            displayText: `${typed}\nUse this formula for grade calculation: ${formula}`,
            prompt: normalizePrompt(`${typed}\nUse this formula for grade calculation: ${formula}`),
            usedFormulaInput: true
        };
    }

    // For non-formula prompts, ignore stale advanced-formula input to avoid accidental context pollution.
    return {
        displayText: typed,
        prompt: normalizePrompt(typed),
        usedFormulaInput: false
    };
};

const callWs = async (methodname, args) => {
    const response = await ajax.call([{methodname, args}])[0];
    return response;
};

const GRADEBOOK_CHAT_TIMEOUT_MS = 90000;

const callWsWithTimeout = async (methodname, args, timeoutMs = GRADEBOOK_CHAT_TIMEOUT_MS) => {
    let timeoutId = null;
    const timeoutPromise = new Promise((resolve, reject) => {
        timeoutId = setTimeout(() => {
            reject(new Error(`Gradebook request timed out after ${Math.round(timeoutMs / 1000)}s (${methodname}). Please try again.`));
        }, timeoutMs);
    });
    try {
        return await Promise.race([callWs(methodname, args), timeoutPromise]);
    } finally {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
    }
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

const escapeHtmlText = (str) => {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(str || '').replace(/[&<>"']/g, (char) => map[char]);
};

const PROPOSAL_TREE_LINE_PATTERN = /^\s*(?:[│|]\s*)*(?:├──|└──)\s/;

const isProposalTreeLine = (line) => {
    const value = String(line || '');
    if (/^\s*[│|]\s*$/.test(value)) {
        return true;
    }
    if (PROPOSAL_TREE_LINE_PATTERN.test(value)) {
        return true;
    }
    return /^\s{4}(?:├──|└──)\s/.test(value);
};

const normalizeProposalTreeLine = (line) => {
    let normalized = String(line || '');
    if (/^\s{4}(?:├──|└──)/.test(normalized) && !/^\s*[│|]/.test(normalized)) {
        normalized = normalized.replace(/^\s{4}/, '│   ');
    }
    return normalized;
};

const splitProposalTreeSegments = (text) => {
    const lines = String(text || '').split(/\r?\n/);
    const segments = [];
    let textBuffer = [];
    let treeBuffer = [];

    const flushText = () => {
        if (textBuffer.length) {
            segments.push({type: 'text', value: textBuffer.join('\n')});
            textBuffer = [];
        }
    };
    const flushTree = () => {
        if (treeBuffer.length) {
            segments.push({
                type: 'tree',
                value: treeBuffer.map(normalizeProposalTreeLine).join('\n')
            });
            treeBuffer = [];
        }
    };

    lines.forEach((line) => {
        if (line.trim() === '' && treeBuffer.length > 0) {
            treeBuffer.push('│');
            return;
        }
        if (isProposalTreeLine(line)) {
            flushText();
            treeBuffer.push(line);
            return;
        }
        flushTree();
        textBuffer.push(line);
    });
    flushTree();
    flushText();
    return segments;
};

const formatProposalTreeText = (treeText) => {
    return String(treeText || '')
        .split(/\r?\n/)
        .map(normalizeProposalTreeLine)
        .map((line) => line.trim() === '' ? '│' : line)
        .filter((line, index, all) => line.trim() !== '' || (index > 0 && all[index - 1].trim() !== ''))
        .join('\n')
        .replace(/\n{2,}/g, '\n')
        .trimEnd();
};

const renderProposalTreeHtml = (treeText) => {
    const formatted = formatProposalTreeText(treeText);
    // Escape HTML entities first, then restore **bold** as <strong> (safe: * is not escaped by escapeHtmlText)
    const escaped = escapeHtmlText(formatted);
    const withBold = escaped.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    return `<pre class="gradebook-proposal-tree">${withBold}</pre>`;
};

const convertMarkdownToHtml = (text) => {
    if (!text) {
        return '';
    }

    const formatPlainTextSegment = (segment) => {
        let html = escapeHtmlText(segment);
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, linkText, url) => {
            const escapedUrl = escapeHtmlText(url);
            const escapedText = escapeHtmlText(linkText);
            return `<a href="${escapedUrl}" target="_blank" rel="noopener">${escapedText}</a>`;
        });
        html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/`([^`\n]+)`/g, '<code class="gradebook-inline-code">$1</code>');
        html = html.replace(/\n/g, '<br>');
        return html;
    };

    const formatTextSegment = (segment) => {
        return splitProposalTreeSegments(segment).map((part) => {
            if (part.type === 'tree') {
                return renderProposalTreeHtml(part.value);
            }
            return formatPlainTextSegment(part.value);
        }).join('');
    };

    const parts = String(text).split('```');
    let html = '';
    parts.forEach((part, index) => {
        if (index % 2 === 1) {
            const code = part.replace(/^\s*[a-zA-Z0-9_-]*\r?\n/, '').replace(/\r?\n$/, '');
            html += renderProposalTreeHtml(code.split(/\r?\n/).map(normalizeProposalTreeLine).join('\n'));
            return;
        }
        html += formatTextSegment(part);
    });

    return html;
};

const appendMessage = (container, text, isHuman, skipPersist, quickReplies, uiPayload) => {
    if (!container) {
        return;
    }

    if (isHuman) {
        document.querySelectorAll('.gradebook-quick-replies:not(.is-spent)').forEach((node) => {
            node.classList.add('is-spent');
            node.querySelectorAll('button').forEach((btn) => {
                btn.disabled = true;
            });
        });
    }

    const div = document.createElement('div');
    div.className = `chat-message ${isHuman ? 'human-message' : 'bot-message'}`;

    const content = document.createElement('div');
    content.className = 'message-content';
    content.innerHTML = convertMarkdownToHtml(text || '');

    if (!isHuman && uiPayload && typeof uiPayload === 'object') {
        const panel = buildStructuredChatPanel(uiPayload);
        if (panel) {
            content.appendChild(panel);
        }
    }

    if (!isHuman) {
        const replies = Array.isArray(quickReplies) ? quickReplies : [];
        const actions = buildQuickReplyActions(replies);
        if (actions) {
            content.appendChild(actions);
        }
    }

    div.appendChild(content);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;

    if (!skipPersist) {
        const history = loadJson(getStorageKey('chat_history'), []);
        const entry = {role: isHuman ? 'human' : 'bot', text: text || ''};
        if (!isHuman && Array.isArray(quickReplies) && quickReplies.length > 0) {
            entry.quick_replies = quickReplies;
        }
        if (!isHuman && uiPayload && typeof uiPayload === 'object') {
            entry.ui = uiPayload;
        }
        history.push(entry);
        persistChatHistoryLocal(history);
        const normalizedHistory = normalizeChatHistoryEntries(history);
        const currentSessionId = getSessionId();
        if (isHuman || normalizedHistory.some((item) => item.role === 'human')) {
            clearStarterChatHistory();
        } else {
            persistStarterChatHistory(normalizedHistory, currentSessionId);
        }
        scheduleServerSave();
    }
};

const buildQuickReplyActions = (quickReplies) => {
    const replies = (Array.isArray(quickReplies) ? quickReplies : [])
        .map((item) => {
            if (!item || typeof item !== 'object') {
                return null;
            }
            const prompt = String(item.prompt || '').trim();
            const label = String(item.label || item.prompt || '').trim();
            const action = String(item.action || '').trim();
            const url = String(item.url || '').trim();
            if (!label) {
                return null;
            }
            if (!prompt && !(action === 'open_url' && url)) {
                return null;
            }
            return {prompt, label, action, url};
        })
        .filter(Boolean);

    if (!replies.length) {
        return null;
    }

    // Only the newest set of action buttons should stay interactive.
    document.querySelectorAll('.gradebook-quick-replies').forEach((node) => {
        node.classList.add('is-spent');
        node.querySelectorAll('button').forEach((btn) => {
            btn.disabled = true;
        });
    });

    const actions = document.createElement('div');
    actions.className = 'gradebook-quick-replies';
    actions.setAttribute('role', 'group');
    actions.setAttribute('aria-label', 'Quick replies');

    replies.forEach((item) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gradebook-quick-reply-btn';
        btn.textContent = item.label;
        btn.dataset.prompt = item.prompt;
        if (item.action) {
            btn.dataset.action = item.action;
        }
        if (item.url) {
            btn.dataset.url = item.url;
        }
        btn.addEventListener('click', () => {
            void handleQuickReplyClick(actions, item.prompt, item.label, item.action, item.url);
        });
        actions.appendChild(btn);
    });

    return actions;
};

const buildStructuredChatPanel = (uiPayload) => {
    const type = String((uiPayload && uiPayload.type) || '').trim();
    if (type === 'missing_items_table') {
        return buildMissingItemsTablePanel(uiPayload);
    }
    return null;
};

const setMissingItemDecisions = (decisions) => {
    lastMissingItemDecisions = Array.isArray(decisions)
        ? decisions.filter((item) => item && typeof item === 'object')
        : [];
};

const getSkippedMissingItemNames = () => new Set(
    lastMissingItemDecisions
        .filter((item) => String(item.action || '').trim().toLowerCase() === 'skip')
        .map((item) => String(item.name || '').trim().toLowerCase())
        .filter(Boolean)
);

const getDecidedMissingItemNames = () => new Set(
    lastMissingItemDecisions
        .map((item) => String(item.name || '').trim().toLowerCase())
        .filter(Boolean)
);

const createMissingItemActivity = async (row) => {
    return callWs('block_ai_assistant_gradebook_create_assignment_activity', {
        courseid: getCourseId(),
        activity_name: String((row && row.name) || '').trim(),
        section_num: 0
    });
};

const createMissingItemManualGrade = async (row) => {
    return callWs('block_ai_assistant_gradebook_create_manual_item', {
        courseid: getCourseId(),
        item_name: String((row && row.name) || '').trim(),
        category: String((row && row.category) || '').trim(),
        subcategory: String((row && row.subcategory) || '').trim()
    });
};

const syncMissingItemsContext = async () => {
    await ensureSession();
    return callWs('block_ai_assistant_gradebook_sync_context', {
        courseid: getCourseId(),
        session_id: getSessionId(),
        refresh_proposal_candidates: true
    });
};

const buildMissingItemsTablePanel = (uiPayload) => {
    const rows = Array.isArray(uiPayload.rows) ? uiPayload.rows : [];
    if (rows.length < 1) {
        return null;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'mt-2 p-2 border rounded bg-white';

    const table = document.createElement('table');
    table.className = 'table table-sm mb-2';
    const head = document.createElement('thead');
    head.innerHTML = `<tr><th>${missingItemsAssessmentLabel}</th><th>${missingItemsActionLabel}</th></tr>`;
    table.appendChild(head);

    const body = document.createElement('tbody');
    const optionKeys = ['activity', 'grade_item', 'skip'];
    const optionLabels = {
        activity: missingItemsActivityLabel,
        grade_item: missingItemsGradeItemLabel,
        skip: missingItemsSkipLabel
    };

    rows.forEach((row, index) => {
        const name = String((row && row.name) || '').trim();
        if (!name) {
            return;
        }

        const tr = document.createElement('tr');
        const nameTd = document.createElement('td');
        nameTd.textContent = name;
        tr.appendChild(nameTd);

        const actionTd = document.createElement('td');
        const radioName = `missing-item-action-${Date.now()}-${index}`;
        optionKeys.forEach((actionKey) => {
            const label = document.createElement('label');
            label.className = 'mr-2 mb-0';

            const input = document.createElement('input');
            input.type = 'radio';
            input.name = radioName;
            input.value = actionKey;
            input.dataset.assessmentName = name;
            input.dataset.category = String((row && row.category) || '').trim();
            input.dataset.subcategory = String((row && row.subcategory) || '').trim();
            input.className = 'mr-1';

            label.appendChild(input);
            label.appendChild(document.createTextNode(optionLabels[actionKey]));
            actionTd.appendChild(label);
        });

        tr.appendChild(actionTd);
        body.appendChild(tr);
    });

    table.appendChild(body);
    wrapper.appendChild(table);

    const submitBtn = document.createElement('button');
    submitBtn.type = 'button';
    submitBtn.className = 'btn btn-sm btn-primary';
    submitBtn.textContent = missingItemsSubmitLabel;
    submitBtn.disabled = true;
    wrapper.appendChild(submitBtn);

    const refreshSubmitEnabled = () => {
        const groups = Array.from(body.querySelectorAll('tr')).map((tr) => tr.querySelectorAll('input[type="radio"]'));
        const allSelected = groups.every((group) => Array.from(group).some((input) => input.checked));
        submitBtn.disabled = !allSelected;
    };

    body.addEventListener('change', refreshSubmitEnabled);

    submitBtn.addEventListener('click', async () => {
        if (submitBtn.disabled) {
            return;
        }

        const decisions = [];
        body.querySelectorAll('tr').forEach((tr) => {
            const selected = tr.querySelector('input[type="radio"]:checked');
            if (!selected) {
                return;
            }
            decisions.push({
                name: String(selected.dataset.assessmentName || '').trim(),
                action: String(selected.value || '').trim(),
                category: String(selected.dataset.category || '').trim(),
                subcategory: String(selected.dataset.subcategory || '').trim()
            });
        });

        submitBtn.disabled = true;
        try {
            const createErrors = [];
            const succeeded = [];
            let hasCreateActions = false;

            for (const decision of decisions) {
                const action = String(decision.action || '').trim();
                if (action === 'skip') {
                    succeeded.push(decision);
                    continue;
                }
                if (action !== 'activity' && action !== 'grade_item') {
                    continue;
                }

                hasCreateActions = true;
                try {
                    if (action === 'activity') {
                        const response = await createMissingItemActivity(decision);
                        if (!response || response.success !== true) {
                            const msg = String((response && response.message) || '').trim() || 'Could not create assignment activity.';
                            createErrors.push(`${decision.name}: ${msg}`);
                            continue;
                        }
                    } else {
                        const response = await createMissingItemManualGrade(decision);
                        if (!response || response.success !== true || !response.grade_item_id) {
                            const msg = String((response && response.message) || '').trim() || 'Could not create manual grade item.';
                            createErrors.push(`${decision.name}: ${msg}`);
                            continue;
                        }
                    }
                    succeeded.push(decision);
                } catch (e) {
                    const msg = String((e && e.message) || e || '').trim() || 'Creation request failed.';
                    createErrors.push(`${decision.name}: ${msg}`);
                }
            }

            if (succeeded.length < 1) {
                appendSystemMessage(`Could not complete create actions:\n- ${createErrors.join('\n- ')}`);
                submitBtn.disabled = false;
                return;
            }

            if (hasCreateActions && succeeded.some((item) => item.action !== 'skip')) {
                try {
                    await syncMissingItemsContext();
                } catch (e) {
                    const msg = String((e && e.message) || e || '').trim() || 'Context sync failed.';
                    appendSystemMessage(`Created items, but session sync failed: ${msg}. Retry submit.`);
                    submitBtn.disabled = false;
                    return;
                }
            }

            if (createErrors.length > 0) {
                appendSystemMessage(`${missingItemsPartialFailedLabel}\n- ${createErrors.join('\n- ')}`);
            }

            const prefix = String(uiPayload.submit_prompt_prefix || 'missing_items_submit:').trim() || 'missing_items_submit:';
            const prompt = `${prefix} ${JSON.stringify(succeeded)}`;
            await sendPreparedPrompt({
                typed: prompt,
                prepared: {
                    prompt,
                    displayText: 'Submitted missing-item decisions.',
                    usedFormulaInput: false
                }
            });
        } catch (e) {
            const msg = String((e && e.message) || e || '').trim() || 'Submit failed.';
            appendSystemMessage(`Missing-item submit failed: ${msg}`);
            submitBtn.disabled = false;
        }
    });

    return wrapper;
};

const handleQuickReplyClick = async (actionsNode, prompt, label, action, url) => {
    if (String(action || '').trim() === 'open_url') {
        const targetUrl = String(url || '').trim();
        if (targetUrl) {
            try {
                window.open(targetUrl, '_blank', 'noopener,noreferrer');
            } catch (e) {
            }
            focusPromptInput();
        }
        return;
    }

    const normalized = String(prompt || '').trim();
    if (!normalized) {
        return;
    }

    if (requestInFlight) {
        await showBusyWaitIndicator();
        focusPromptInput();
        return;
    }

    if (actionsNode) {
        actionsNode.classList.add('is-spent');
        actionsNode.querySelectorAll('button').forEach((btn) => {
            btn.disabled = true;
        });
    }

    const displayText = String(label || normalized).trim() || normalized;
    await sendPreparedPrompt({
        typed: normalized,
        prepared: {
            prompt: normalized,
            displayText,
            usedFormulaInput: false
        }
    });
};

const appendSystemMessage = (text) => {
    appendMessage(getChatMessages(), text, false, false);
};

const inferAnalysisQuickRepliesFromText = (text, phase) => {
    const body = String(text || '');
    const phaseUpper = String(phase || '').toUpperCase();
    if (phaseUpper !== 'ANALYSIS') {
        return [];
    }

    const lowered = body.toLowerCase();
    if (
        lowered.includes('use the syllabus as-is')
        || (lowered.includes('syllabus as-is') && lowered.includes('yorku'))
        || (lowered.includes('yorku buckets') && lowered.includes('choose an option below'))
    ) {
        return [
            {label: 'Use syllabus as-is', prompt: 'use syllabus'},
            {label: 'YorkU buckets', prompt: 'yorku buckets'}
        ];
    }

    if (
        lowered.includes('show proposal')
        || lowered.includes('start generating your proposal now')
        || lowered.includes('do you want me to start generating your proposal now')
    ) {
        return [
            {label: 'Yes, start now', prompt: 'yes start now'},
            {label: 'Not yet', prompt: 'not yet'}
        ];
    }

    return [];
};

const appendSystemMessageWithQuickReplies = (text, quickReplies) => {
    appendMessage(getChatMessages(), text, false, false, quickReplies || []);
};

const isSyllabusPrepGateText = (text) => {
    const lowered = String(text || '').toLowerCase();
    if (!lowered.includes('syllabus')) {
        return false;
    }
    const needsUpload = lowered.includes('upload')
        || lowered.includes("couldn't find")
        || lowered.includes('still couldn')
        || lowered.includes('still need the document');
    return needsUpload && (
        lowered.includes('continue')
        || lowered.includes('upload button')
        || lowered.includes('ai assistant block')
        || lowered.includes('syllabus-like file')
        || lowered.includes('syllabus file')
    );
};

const replaceBotMessageContent = (botNode, text, quickReplies) => {
    if (!botNode) {
        return false;
    }
    let content = botNode.querySelector('.message-content');
    if (!content) {
        content = document.createElement('div');
        content.className = 'message-content';
        botNode.appendChild(content);
    }
    content.innerHTML = convertMarkdownToHtml(text || '');
    const replies = Array.isArray(quickReplies) ? quickReplies : [];
    const actions = buildQuickReplyActions(replies);
    if (actions) {
        content.appendChild(actions);
    }
    return true;
};

const findLastSyllabusPrepBotMessage = (container) => {
    if (!container) {
        return null;
    }
    const bots = Array.from(container.querySelectorAll('.chat-message.bot-message'));
    for (let i = bots.length - 1; i >= 0; i -= 1) {
        const node = bots[i];
        const text = String(node.textContent || '');
        if (isSyllabusPrepGateText(text)) {
            return node;
        }
    }
    return null;
};

const updateChatHistoryBotTextInPlace = (previousText, nextText, quickReplies) => {
    const history = loadJson(getStorageKey('chat_history'), []);
    if (!Array.isArray(history) || history.length < 1) {
        return;
    }
    for (let i = history.length - 1; i >= 0; i -= 1) {
        const entry = history[i];
        if (!entry || entry.role !== 'bot') {
            continue;
        }
        const entryText = String(entry.text || '');
        if (entryText === previousText || isSyllabusPrepGateText(entryText)) {
            entry.text = nextText || '';
            if (Array.isArray(quickReplies) && quickReplies.length > 0) {
                entry.quick_replies = quickReplies;
            } else {
                delete entry.quick_replies;
            }
            persistChatHistoryLocal(history);
            const normalizedHistory = normalizeChatHistoryEntries(history);
            persistStarterChatHistory(normalizedHistory, getSessionId());
            scheduleServerSave();
            return;
        }
    }
};

/**
 * Soften Continue-without-syllabus UX: refresh the existing gate bubble instead of
 * stacking a near-duplicate bot message (avoids the chat jump/shake).
 */
const updateSyllabusPrepGateInPlace = (container, text, quickReplies) => {
    const target = findLastSyllabusPrepBotMessage(container);
    if (!target) {
        return false;
    }
    const previousText = String(target.textContent || '').trim();
    const scrollTop = container.scrollTop;
    if (!replaceBotMessageContent(target, text, quickReplies)) {
        return false;
    }
    updateChatHistoryBotTextInPlace(previousText, text, quickReplies);
    // Keep viewport steady — do not force scroll to bottom.
    container.scrollTop = scrollTop;
    return true;
};

const waitForDomSettle = () => new Promise((resolve) => {
    if (typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') {
        setTimeout(resolve, 0);
        return;
    }
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            resolve();
        });
    });
});

const flushQueuedSystemMessages = () => {
    if (proposalPanelSyncPromise || queuedSystemMessages.length < 1) {
        return;
    }
    const queued = queuedSystemMessages.slice();
    queuedSystemMessages = [];
    queued.forEach((msg) => appendSystemMessage(msg));
};

const appendSystemMessageSafely = (text) => {
    const t = String(text || '');

    if (proposalPanelSyncPromise) {
        queuedSystemMessages.push(t);
        return;
    }

    appendSystemMessage(t);
};

const DEFAULT_MAPPING_GUIDANCE_HTML = (
    '<strong>Mapping tip:</strong> choose a top-level category first, then optionally choose a subcategory. ' +
    'Leaving subcategory as "None" keeps the item directly under the parent category.'
);

const setMappingDrawerGuidance = (html) => {
    const help = el('gradebook-mapping-help');
    if (!help) {
        return;
    }
    help.innerHTML = String(html || DEFAULT_MAPPING_GUIDANCE_HTML);
};

const restoreDefaultMappingGuidance = () => {
    setMappingDrawerGuidance(DEFAULT_MAPPING_GUIDANCE_HTML);
};

const showMappingActionNote = (text) => {
    const note = escapeHtmlText(String(text || '').trim());
    if (!note) {
        return;
    }
    setMappingDrawerGuidance(
        `<strong>Latest mapping change:</strong> ${note}<br><span class="text-muted">${DEFAULT_MAPPING_GUIDANCE_HTML}</span>`
    );
};

const appendChatNotice = (text) => {
    appendSystemMessageSafely(String(text || '').trim());
};

const appendOperationalNotice = (text) => {
    const value = String(text || '').trim();
    if (!value) {
        return;
    }
    // Keep chat for user-facing warnings/errors; route routine workflow copy to panels.
    if (/^⚠/.test(value)) {
        appendChatNotice(value);
        return;
    }
    showMappingActionNote(value);
};

const runWithinProposalPanelSync = async (action) => {
    const syncPromise = (async () => {
        await action();
        await waitForDomSettle();
    })();

    proposalPanelSyncPromise = syncPromise;
    try {
        await syncPromise;
    } finally {
        if (proposalPanelSyncPromise === syncPromise) {
            proposalPanelSyncPromise = null;
        }
        flushQueuedSystemMessages();
    }
};

const waitForProposalPanelSync = async () => {
    if (!proposalPanelSyncPromise) {
        return;
    }
    try {
        await proposalPanelSyncPromise;
    } catch (e) {
    }
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
        appendMessage(
            chatMessages,
            String(item.text || ''),
            item.role === 'human',
            true,
            item.role === 'bot' ? item.quick_replies : [],
            item.role === 'bot' ? item.ui : null
        );
    });
    return true;
};

const extractChatHistoryFromStatus = (parsed) => {
    if (!parsed || typeof parsed !== 'object') {
        return [];
    }
    const fromWrapped = parsed.session && typeof parsed.session === 'object'
        ? parsed.session.chat_history
        : null;
    const fromRoot = parsed.chat_history;
    const normalized = normalizeChatHistoryEntries(fromWrapped || fromRoot || []);
    return normalized;
};

const extractChatHistoryFromServerState = (state) => {
    if (!state || typeof state !== 'object') {
        return [];
    }
    try {
        const parsed = JSON.parse(state.chat_history_json || '[]');
        return normalizeChatHistoryEntries(parsed);
    } catch (e) {
        return [];
    }
};

const renderedChatLength = () => {
    const container = getChatMessages();
    if (!container) {
        return 0;
    }
    return container.querySelectorAll('.chat-message').length;
};

const ensureChatRenderedFromAnySource = (parsedStatus, serverState) => {
    if (renderedChatLength() > 0) {
        return false;
    }

    const fromStatus = extractChatHistoryFromStatus(parsedStatus);
    if (fromStatus.length > 0) {
        persistChatHistoryLocal(fromStatus);
        renderHistory(fromStatus);
        gradebookDebug('ensureChatRenderedFromAnySource:status', {length: fromStatus.length});
        return true;
    }

    const fromServer = extractChatHistoryFromServerState(serverState);
    if (fromServer.length > 0) {
        persistChatHistoryLocal(fromServer);
        renderHistory(fromServer);
        gradebookDebug('ensureChatRenderedFromAnySource:serverState', {length: fromServer.length});
        return true;
    }

    const fromLocal = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    if (fromLocal.length > 0) {
        renderHistory(fromLocal);
        gradebookDebug('ensureChatRenderedFromAnySource:local', {length: fromLocal.length});
        return true;
    }

    const starterHistory = getStarterChatHistory();
    if (starterHistory.length > 0) {
        persistChatHistoryLocal(starterHistory);
        renderHistory(starterHistory);
        gradebookDebug('ensureChatRenderedFromAnySource:starter', {length: starterHistory.length});
        return true;
    }

    gradebookDebug('ensureChatRenderedFromAnySource:none');
    return false;
};

const normalizeChatHistoryEntries = (raw) => {
    const normalizeQuickReplies = (rawReplies) => {
        if (!Array.isArray(rawReplies)) {
            return [];
        }
        return rawReplies
            .filter((item) => item && typeof item === 'object')
            .map((item) => {
                const prompt = String(item.prompt || '').trim();
                const label = String(item.label || item.prompt || '').trim();
                const action = String(item.action || '').trim();
                const url = String(item.url || '').trim();
                if (!label) {
                    return null;
                }
                if (!prompt && !(action === 'open_url' && url)) {
                    return null;
                }
                const entry = {label};
                if (prompt) {
                    entry.prompt = prompt;
                }
                if (action) {
                    entry.action = action;
                }
                if (url) {
                    entry.url = url;
                }
                return entry;
            })
            .filter(Boolean);
    };

    const normalizeUiPayload = (rawUi) => {
        if (!rawUi || typeof rawUi !== 'object' || Array.isArray(rawUi)) {
            return null;
        }
        const type = String(rawUi.type || '').trim();
        if (!type) {
            return null;
        }
        try {
            const cloned = JSON.parse(JSON.stringify(rawUi));
            cloned.type = type;
            return cloned;
        } catch (e) {
            return null;
        }
    };

    if (!Array.isArray(raw)) {
        return [];
    }
    return raw
        .filter((item) => item && typeof item === 'object')
        .map((item) => {
            const role = item.role === 'human' ? 'human' : (item.role === 'bot' ? 'bot' : '');
            const text = String(item.text || '');
            const normalized = {role, text};
            if (role === 'bot') {
                const quickReplies = normalizeQuickReplies(item.quick_replies || item.quickReplies || []);
                if (quickReplies.length > 0) {
                    normalized.quick_replies = quickReplies;
                }
                const uiPayload = normalizeUiPayload(item.ui);
                if (uiPayload) {
                    normalized.ui = uiPayload;
                }
            }
            return normalized;
        })
        .filter((item) => item.role && item.text !== '');
};

const mergeBackendWithLocalExtras = (backendHistory, localHistory) => {
    const backend = Array.isArray(backendHistory) ? backendHistory : [];
    const local = Array.isArray(localHistory) ? localHistory : [];
    if (backend.length < 1 || local.length < 1) {
        return backend;
    }

    return backend.map((entry) => {
        if (!entry || entry.role !== 'bot') {
            return entry;
        }
        const hasQuickReplies = Array.isArray(entry.quick_replies) && entry.quick_replies.length > 0;
        const hasUi = Boolean(entry.ui && typeof entry.ui === 'object');
        if (hasQuickReplies && hasUi) {
            return entry;
        }

        const fallback = local.find((candidate) => candidate
            && candidate.role === entry.role
            && String(candidate.text || '') === String(entry.text || '')
            && (
                (Array.isArray(candidate.quick_replies) && candidate.quick_replies.length > 0)
                || (candidate.ui && typeof candidate.ui === 'object')
            ));
        if (!fallback) {
            return entry;
        }

        const merged = {...entry};
        if (!hasQuickReplies && Array.isArray(fallback.quick_replies) && fallback.quick_replies.length > 0) {
            merged.quick_replies = fallback.quick_replies;
        }
        if (!hasUi && fallback.ui && typeof fallback.ui === 'object') {
            merged.ui = fallback.ui;
        }
        return merged;
    });
};

const buildCourseOpenUrl = () => {
    const courseId = String(getCourseId() || '').trim();
    const path = courseId ? `/course/view.php?id=${encodeURIComponent(courseId)}` : '/course/view.php';
    const root = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? String(M.cfg.wwwroot).replace(/\/$/, '') : '';
    return root ? `${root}${path}` : path;
};

const stickyGateExtrasFromExtraction = (statusPayload) => {
    const payload = (statusPayload && typeof statusPayload === 'object') ? statusPayload : {};
    const extraction = (payload.extraction && typeof payload.extraction === 'object') ? payload.extraction : {};
    const phase = String(payload.phase || '').trim().toUpperCase();

    if (Boolean(extraction.missing_items_pending)) {
        const rows = Array.isArray(extraction.missing_items_rows) ? extraction.missing_items_rows : [];
        const normalizedRows = rows
            .filter((row) => row && typeof row === 'object')
            .map((row) => {
                const name = String(row.name || '').trim();
                if (!name) {
                    return null;
                }
                return {
                    name,
                    category: String(row.category || '').trim(),
                    subcategory: String(row.subcategory || '').trim(),
                };
            })
            .filter(Boolean);
        if (normalizedRows.length < 1) {
            return {quick_replies: [], ui: null};
        }
        return {
            quick_replies: [],
            ui: {
                type: 'missing_items_table',
                rows: normalizedRows,
                submit_prompt_prefix: 'missing_items_submit:',
                actions: ['activity', 'grade_item', 'skip'],
            },
        };
    }

    const activityPrepPending = String(extraction.activity_prep_status || '').trim().toLowerCase() === 'pending';
    const syllabusPrepPending = String(extraction.syllabus_prep_status || '').trim().toLowerCase() === 'pending';
    if (syllabusPrepPending && (phase === 'INTAKE' || phase === 'ANALYSIS')) {
        return {
            quick_replies: [
                {label: 'Continue', prompt: 'continue'},
            ],
            ui: null,
        };
    }
    if (activityPrepPending && (phase === 'INTAKE' || phase === 'ANALYSIS')) {
        return {
            quick_replies: [
                {label: 'Open course to add activities', action: 'open_url', url: buildCourseOpenUrl()},
                {label: 'Continue', prompt: 'continue'},
            ],
            ui: null,
        };
    }

    const modePending = String(extraction.proposal_mode_pending || '').trim();
    if (phase === 'ANALYSIS' && modePending === 'ask') {
        return {
            quick_replies: [
                {label: 'Use syllabus as-is', prompt: 'use syllabus'},
                {label: 'YorkU buckets', prompt: 'yorku buckets'},
            ],
            ui: null,
        };
    }

    return {quick_replies: [], ui: null};
};

const reattachStickyGateUiFromExtraction = (statusPayload, options = {}) => {
    const forceRender = Boolean(options && options.forceRender);
    const history = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    if (history.length < 1) {
        return false;
    }

    let lastBotIndex = -1;
    for (let i = history.length - 1; i >= 0; i -= 1) {
        if (history[i] && history[i].role === 'bot') {
            lastBotIndex = i;
            break;
        }
    }
    if (lastBotIndex < 0) {
        return false;
    }

    const extras = stickyGateExtrasFromExtraction(statusPayload);
    const expectedReplies = Array.isArray(extras.quick_replies) ? extras.quick_replies : [];
    const expectedUi = extras.ui && typeof extras.ui === 'object' ? extras.ui : null;
    if (expectedReplies.length < 1 && !expectedUi) {
        return false;
    }

    const lastBot = history[lastBotIndex];
    const hasQuickReplies = Array.isArray(lastBot.quick_replies) && lastBot.quick_replies.length > 0;
    const hasUi = Boolean(lastBot.ui && typeof lastBot.ui === 'object');
    let changed = false;
    const patched = {...lastBot};

    if (!hasQuickReplies && expectedReplies.length > 0) {
        patched.quick_replies = expectedReplies;
        changed = true;
    }
    if (!hasUi && expectedUi) {
        patched.ui = expectedUi;
        changed = true;
    }
    if (!changed) {
        return false;
    }

    const nextHistory = history.slice();
    nextHistory[lastBotIndex] = patched;
    persistChatHistoryLocal(nextHistory);
    saveJson(getStorageKey('chat_history_backend'), nextHistory);
    if (forceRender || renderedChatLength() > 0) {
        renderHistory(nextHistory);
    }
    gradebookDebug('reattachStickyGateUiFromExtraction', {
        lastBotIndex,
        attachedQuickReplies: Boolean(patched.quick_replies && patched.quick_replies.length),
        attachedUi: Boolean(patched.ui && patched.ui.type),
    });
    return true;
};

const chatHistoryTailMatches = (localHistory, backendHistory) => {
    if (!Array.isArray(localHistory) || !Array.isArray(backendHistory) || backendHistory.length < 1) {
        return false;
    }
    const backendLast = backendHistory[backendHistory.length - 1];
    const localSlice = localHistory.slice(Math.max(0, localHistory.length - 20));
    return localSlice.some((item) => item && item.role === backendLast.role && item.text === backendLast.text);
};

const ingestBackendChatHistory = (history, renderIfFresh) => {
    const normalizedBackend = normalizeChatHistoryEntries(history);
    if (normalizedBackend.length < 1) {
        return false;
    }
    const normalizedLocal = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    const mergedBackend = mergeBackendWithLocalExtras(normalizedBackend, normalizedLocal);

    saveJson(getStorageKey('chat_history_backend'), mergedBackend);

    const shouldReplaceLocal = normalizedLocal.length < 1
        || mergedBackend.length > normalizedLocal.length
        || !chatHistoryTailMatches(normalizedLocal, mergedBackend);

    if (!shouldReplaceLocal) {
        return false;
    }

    persistChatHistoryLocal(mergedBackend);
    if (renderIfFresh) {
        renderHistory(mergedBackend);
    }
    return true;
};

const backendHistoryContainsReply = (history, replyText) => {
    const normalizedReply = String(replyText || '').trim();
    if (!normalizedReply) {
        return false;
    }

    const normalizedHistory = normalizeChatHistoryEntries(history);
    if (normalizedHistory.length < 1) {
        return false;
    }

    const lastEntry = normalizedHistory[normalizedHistory.length - 1];
    return Boolean(lastEntry && lastEntry.role === 'bot' && String(lastEntry.text || '').trim() === normalizedReply);
};

const restoreChatHistory = () => {
    const localHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    const backendHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history_backend'), []));
    const starterHistory = getStarterChatHistory(getSessionId());
    gradebookDebug('restoreChatHistory:start', {
        localLength: localHistory.length,
        backendLength: backendHistory.length,
        starterLength: starterHistory.length
    });

    if (backendHistory.length > localHistory.length || !chatHistoryTailMatches(localHistory, backendHistory)) {
        if (backendHistory.length > 0) {
            persistChatHistoryLocal(backendHistory);
            gradebookDebug('restoreChatHistory:renderBackend', {length: backendHistory.length});
            return renderHistory(backendHistory);
        }
    }

    if (localHistory.length < 1 && starterHistory.length > 0) {
        persistChatHistoryLocal(starterHistory);
        gradebookDebug('restoreChatHistory:renderStarter', {length: starterHistory.length});
        return renderHistory(starterHistory);
    }

    gradebookDebug('restoreChatHistory:renderLocal', {length: localHistory.length});
    return renderHistory(localHistory);
};

const isNotGraded = (category) => String(category || '').trim() === NOT_GRADED;

const normalizeModuleName = (moduleName) => String(moduleName || '').trim().toLowerCase();

const isConstrainedModule = (moduleName) => CONSTRAINED_MODULES.includes(normalizeModuleName(moduleName));

const mappingConstraintReason = (item) => {
    if (!item || typeof item !== 'object') {
        return '';
    }

    const explicitReason = String(item.read_only_reason || item.constraint_reason || '').trim();
    if (explicitReason) {
        return explicitReason;
    }

    if (isConstrainedModule(item.itemmodule || item.module)) {
        return 'external/LTI managed';
    }

    return '';
};

const isReadOnlyMappingRow = (item) => {
    if (!item || typeof item !== 'object') {
        return false;
    }

    if (item.read_only === true || item.is_constrained === true || item.readonly === true) {
        return true;
    }

    return isConstrainedModule(item.itemmodule || item.module);
};

const extractFinalizeWarnings = (parsed) => {
    const data = (parsed && typeof parsed === 'object' && parsed.data && typeof parsed.data === 'object')
        ? parsed.data
        : {};
    const warnings = Array.isArray(data.grade_setup_warnings) ? data.grade_setup_warnings : [];
    return warnings
        .map((w) => String(w || '').trim())
        .filter((w) => w.length > 0);
};

const stripDiagnosticSuffix = (text) => {
    const raw = String(text || '').trim();
    if (!raw) {
        return '';
    }
    const markerIndex = raw.search(/\s[ℹ⚠]/u);
    if (markerIndex >= 0) {
        return raw.slice(0, markerIndex).trim();
    }
    return raw;
};

const buildFinalizePrimaryMessage = (parsed) => {
    const fallback = 'Gradebook finalized. Categories and content mapping confirmed.';
    const raw = stripDiagnosticSuffix(String((parsed && parsed.message) || '').trim());
    if (!raw) {
        return fallback;
    }

    const warnings = extractFinalizeWarnings(parsed);
    if (!Array.isArray(warnings) || warnings.length === 0) {
        return raw;
    }

    let cleaned = raw;
    warnings.forEach((w) => {
        const token = String(w || '').trim();
        if (!token) {
            return;
        }
        cleaned = cleaned.split(token).join(' ');
    });

    cleaned = stripDiagnosticSuffix(
        cleaned
            .replace(/\s{2,}/g, ' ')
            .replace(/\s+([.,!?;:])/g, '$1')
            .trim()
    );

    return cleaned || fallback;
};

const extractFinalizeConstraints = (parsed) => {
    const data = (parsed && typeof parsed === 'object' && parsed.data && typeof parsed.data === 'object')
        ? parsed.data
        : {};
    const constraints = Array.isArray(data.grade_setup_constraints) ? data.grade_setup_constraints : [];
    return constraints
        .filter((entry) => entry && typeof entry === 'object')
        .map((entry) => {
            const activity = String(entry.activity_name || '').trim();
            const reason = String(entry.reason || '').trim() || 'constrained item';
            const itemId = Number(entry.grade_item_id || 0);
            return {
                activity,
                reason,
                grade_item_id: itemId
            };
        });
};

const announceFinalizeDiagnostics = (parsed) => {
    const data = (parsed && typeof parsed === 'object' && parsed.data && typeof parsed.data === 'object')
        ? parsed.data
        : {};
    const correlationId = String(data.gradebook_audit_correlation_id || '').trim();

    const warnings = extractFinalizeWarnings(parsed);

    // Avoid leaking backend SQL/stack details in chat while keeping full diagnostics in browser console.
    const hasTechnicalWarning = warnings.some((w) => /sql|database|trace|exception|unknown column|select\s+/i.test(String(w || '')));
    const userWarnings = hasTechnicalWarning
        ? ['A server-side issue occurred while applying gradebook changes. Please retry or contact support.']
        : warnings.filter((w) => /^⚠/.test(String(w || '').trim()));

    userWarnings.forEach((w) => appendChatNotice(w));

    const constraints = extractFinalizeConstraints(parsed);
    constraints.forEach((entry) => {
        const itemLabel = entry.activity || (entry.grade_item_id > 0 ? `grade item #${entry.grade_item_id}` : 'grade item');
        appendChatNotice(`Read-only constraint: ${itemLabel} (${entry.reason}).`);
    });

    if (warnings.length > 0 || constraints.length > 0) {
        const hasWarningLevelIssue = userWarnings.length > 0;
        const payload = {
            correlationId,
            message: parsed && parsed.message ? parsed.message : '',
            warnings,
            constraints,
            data,
            raw: parsed
        };

        if (hasTechnicalWarning) {
            // eslint-disable-next-line no-console
            console.error('[gradebook-finalize-diagnostics]', payload);
        } else if (hasWarningLevelIssue || constraints.length > 0) {
            // eslint-disable-next-line no-console
            console.warn('[gradebook-finalize-diagnostics]', payload);
        } else {
            // eslint-disable-next-line no-console
            console.info('[gradebook-finalize-diagnostics]', payload);
        }
    }
};

const isUncategorizedToken = (category) => {
    const normalized = String(category || '').trim().toLowerCase();
    return normalized === '__uncategorized__' || normalized === 'uncategorized';
};

const pickFallbackCategory = () => {
    const source = proposalCategories.length > 0 ? proposalCategories : DEFAULT_CATEGORIES;
    if (!Array.isArray(source) || source.length < 1) {
        return 'Assignments';
    }

    const preferred = source.find((cat) => String(cat || '').trim().toLowerCase() === 'assignments');
    if (preferred) {
        return preferred;
    }

    return String(source[0] || 'Assignments').trim() || 'Assignments';
};

const normalizeMappedCategory = (category) => {
    const raw = String(category || '').trim();
    if (!raw) {
        return '';
    }
    if (isNotGraded(raw)) {
        return NOT_GRADED;
    }
    if (isUncategorizedToken(raw)) {
        return pickFallbackCategory();
    }
    return raw;
};

const rowHasCategory = (item) => item && String(item.category || '').trim().length > 0;

const applyTemplateValue = (template, value) => {
    const text = String(template || '');
    return text.indexOf('{$a}') !== -1 ? text.replace('{$a}', String(value)) : text;
};

const getEmptyCategoryWarningMessage = (categories) => {
    const names = Array.isArray(categories) ? categories.join(', ') : String(categories || '');
    const template = String(emptyCategoryErrorLabel || '').trim();
    if (!template) {
        return `These categories have no activities assigned: ${names}. Pick an activity row, remove the category, or add a manual grade item to assign to it.`;
    }
    return applyTemplateValue(template, names);
};

const getDefaultManualItemName = (categoryName) => {
    const rendered = applyTemplateValue(addManualItemDefaultNameLabel, categoryName);
    return String(rendered || `${categoryName} Manual Item`).trim();
};

const createManualMappingRow = async (categoryName) => {
    const promptLabel = applyTemplateValue(addManualItemPromptLabel, categoryName);
    const suggestedName = getDefaultManualItemName(categoryName);

    const modal = await ModalFactory.create({
        type: ModalFactory.types.SAVE_CANCEL,
        title: promptLabel,
        body: `<div class="form-group">
                <label for="manual-item-name">${promptLabel}</label>
                <input type="text" id="manual-item-name" class="form-control" value="${suggestedName}">
               </div>`,
        buttons: {
            save: await Str.get_string('add', 'block_ai_assistant'),
        }
    });

    modal.show();

    modal.getRoot().on(ModalEvents.save, async (e) => {
        e.preventDefault();
        const itemName = modal.getRoot().find('#manual-item-name').val().trim();
        if (itemName) {
            modal.hide();
            modal.destroy();

            await withRequestLock(async () => {
                await ensureSession();

                // Pass category so server can create the manual item directly under the intended category/subcategory
                const response = await callWs('block_ai_assistant_gradebook_create_manual_item', {
                    courseid: getCourseId(),
                    item_name: itemName,
                    category: categoryName,
                    subcategory: ''
                });

                if (!response || response.success !== true || !response.grade_item_id) {
                    const msg = String((response && response.message) || addManualItemFailedLabel || '').trim() ||
                        'Could not create the manual grade item. Try again.';
                    appendSystemMessage(msg);
                    showMappingError(msg);
                    return;
                }

                const confirmed = getConfirmedMapping();
                confirmed.push({
                    moodle_cmid: null,
                    activity_name: String(response.activity_name || itemName).trim(),
                    module: '',
                    itemmodule: '',
                    iteminstance: null,
                    grade_item_id: Number(response.grade_item_id),
                    grade_item_type: String(response.itemtype || 'manual').trim(),
                    grade_item_name: String(response.activity_name || itemName).trim(),
                    grade_item_idnumber: '',
                    itemtype: 'manual',
                    item_source: 'manual',
                    category: categoryName,
                    suggested_category: categoryName,
                    read_only: false,
                    read_only_reason: '',
                    is_constrained: false
                });
                persistMappingInputs(confirmed);
                syncMappingUIFromJson();
                clearMappingError();
                showMappingActionNote(
                    applyTemplateValue(addManualItemCreatedLabel, String(response.activity_name || itemName).trim())
                );
            });
        }
    });

    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });
};

const renderEmptyCategoryHelper = (confirmed) => {
    const body = el('gradebook-mapping-body');
    if (!body) {
        return;
    }

    const existing = el('gradebook-mapping-empty-category-helper');
    if (existing) {
        existing.remove();
    }

    const emptyCats = findEmptyCategories(confirmed).filter((categoryName) => {
        const skipped = getSkippedMissingItemNames();
        const decided = getDecidedMissingItemNames();
        if (skipped.size < 1 && decided.size < 1) {
            return true;
        }
        // Avoid re-nagging when Part C already skipped every known leaf for this category.
        const decisionCats = lastMissingItemDecisions
            .filter((item) => String(item.category || '').trim().toLowerCase() === String(categoryName || '').trim().toLowerCase())
            .map((item) => String(item.action || '').trim().toLowerCase());
        if (decisionCats.length > 0 && decisionCats.every((action) => action === 'skip')) {
            return false;
        }
        return true;
    });
    if (emptyCats.length < 1) {
        return;
    }

    const helper = document.createElement('div');
    helper.id = 'gradebook-mapping-empty-category-helper';
    helper.className = 'alert alert-warning mt-2 mb-0';

    const text = document.createElement('div');
    text.className = 'small';
    text.textContent = getEmptyCategoryWarningMessage(emptyCats);
    helper.appendChild(text);

    const actions = document.createElement('div');
    actions.className = 'd-flex flex-wrap gap-2 mt-2';
    emptyCats.forEach((categoryName) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-warning btn-sm';
        button.textContent = `${addManualItemLabel}: ${categoryName}`;
        button.addEventListener('click', () => {
            createManualMappingRow(categoryName);
        });
        actions.appendChild(button);
    });
    helper.appendChild(actions);

    body.appendChild(helper);
};

const getConfirmedMapping = () => {
    const node = el('gradebook-confirmed-mapping');
    try {
        const parsed = JSON.parse((node ? node.value : '') || '[]');
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed.map((item) => {
            if (!item || typeof item !== 'object') {
                return item;
            }
            const normalizedCategory = normalizeMappedCategory(item.category);
            const normalizedSubcategory = String(item.subcategory || '').trim();
            return {
                ...item,
                category: normalizedCategory,
                subcategory: isNotGraded(normalizedCategory) ? '' : normalizedSubcategory
            };
        });
    } catch (e) {
        return [];
    }
};

const getMappingRowActivityName = (item) => String(item.activity_name || item.grade_item_name || item.name || '').trim().toLowerCase();

const isProposalOnlyMappingRow = (item) => {
    const source = String(item.item_source || '').trim().toLowerCase();
    return source === 'proposal_manual' || source === 'proposal';
};

const mappingRowPriority = (item) => {
    const cmid = Number(item.moodle_cmid != null ? item.moodle_cmid : (item.cmid != null ? item.cmid : 0));
    if (Number.isFinite(cmid) && cmid > 0) {
        return 4;
    }
    const gradeItemId = Number(item.grade_item_id != null ? item.grade_item_id : (item.gradeitemid != null ? item.gradeitemid : 0));
    if (Number.isFinite(gradeItemId) && gradeItemId > 0) {
        return 3;
    }
    const module = String(item.itemmodule || item.module || item.modname || '').trim();
    if (module) {
        return 2;
    }
    if (!isProposalOnlyMappingRow(item)) {
        return 1;
    }
    return 0;
};

const dedupeMappingRowsByActivityName = (rows) => {
    if (!Array.isArray(rows) || rows.length < 1) {
        return [];
    }

    const bestByName = new Map();
    const nameOrder = [];
    const unnamed = [];

    rows.forEach((row) => {
        const nameKey = getMappingRowActivityName(row);
        if (!nameKey) {
            unnamed.push(row);
            return;
        }
        if (!bestByName.has(nameKey)) {
            nameOrder.push(nameKey);
        }
        const current = bestByName.get(nameKey);
        if (!current || mappingRowPriority(row) > mappingRowPriority(current)) {
            bestByName.set(nameKey, row);
        }
    });

    const deduped = nameOrder.map((nameKey) => bestByName.get(nameKey)).filter(Boolean);
    return [...deduped, ...unnamed];
};

const buildConfirmedRowIdentity = (item) => {
    if (!item || typeof item !== 'object') {
        return '';
    }

    const gradeItemId = Number(item.grade_item_id != null ? item.grade_item_id : (item.gradeitemid != null ? item.gradeitemid : 0));
    if (Number.isFinite(gradeItemId) && gradeItemId > 0) {
        return `gi:${gradeItemId}`;
    }

    const moodleCmid = Number(item.moodle_cmid != null ? item.moodle_cmid : (item.cmid != null ? item.cmid : 0));
    if (Number.isFinite(moodleCmid) && moodleCmid > 0) {
        return `cm:${moodleCmid}`;
    }

    const activityName = String(item.activity_name || item.grade_item_name || item.name || '').trim().toLowerCase();
    if (!activityName) {
        return '';
    }
    const moduleName = String(item.itemmodule || item.module || item.modname || '').trim().toLowerCase();
    return `name:${activityName}|module:${moduleName}`;
};

const mergeConfirmedRowsWithExisting = (incomingRows, existingRows) => {
    if (!Array.isArray(incomingRows) || incomingRows.length < 1) {
        return [];
    }

    const existingByIdentity = new Map();
    if (Array.isArray(existingRows)) {
        existingRows.forEach((row) => {
            const key = buildConfirmedRowIdentity(row);
            if (key) {
                existingByIdentity.set(key, row);
            }
        });
    }

    const mergedIncoming = incomingRows.map((row) => {
        const key = buildConfirmedRowIdentity(row);
        const existing = key ? existingByIdentity.get(key) : null;
        if (!existing || isReadOnlyMappingRow(row)) {
            return row;
        }

        const merged = {...row};
        const incomingCategory = normalizeMappedCategory(row.category);
        const existingCategory = normalizeMappedCategory(existing.category);

        if (isNotGraded(incomingCategory)) {
            merged.category = NOT_GRADED;
            merged.subcategory = '';
            return merged;
        }

        if (existingCategory) {
            merged.category = existingCategory;
        }

        const mergedCategory = normalizeMappedCategory(merged.category);
        if (isNotGraded(mergedCategory)) {
            merged.subcategory = '';
            return merged;
        }

        const existingSubcategory = String(existing.subcategory || '').trim();
        if (!existingSubcategory) {
            return merged;
        }

        const options = getSubcategoryOptionsForCategory(mergedCategory);
        if (Array.isArray(options) && options.includes(existingSubcategory)) {
            merged.subcategory = existingSubcategory;
        } else {
            merged.subcategory = '';
        }

        return merged;
    });

    const mergedKeys = new Set(
        mergedIncoming.map((row) => buildConfirmedRowIdentity(row)).filter((key) => Boolean(key))
    );
    if (!Array.isArray(existingRows)) {
        return mergedIncoming;
    }
    const coveredNames = new Set(
        mergedIncoming.map((row) => getMappingRowActivityName(row)).filter((name) => name.length > 0)
    );
    existingRows.forEach((row) => {
        const key = buildConfirmedRowIdentity(row);
        const nameKey = getMappingRowActivityName(row);
        if (nameKey && coveredNames.has(nameKey)) {
            return;
        }
        if (key && !mergedKeys.has(key)) {
            mergedIncoming.push(row);
            mergedKeys.add(key);
            if (nameKey) {
                coveredNames.add(nameKey);
            }
        }
    });

    return dedupeMappingRowsByActivityName(mergedIncoming);
};

const syncNotGradedRowsFromProposal = (proposal, rows) => {
    if (!proposal || !Array.isArray(rows) || rows.length < 1) {
        return rows;
    }

    const notGradedItems = new Set(
        (proposal.not_graded_items || [])
            .map((name) => String(name || '').trim().toLowerCase())
            .filter((name) => name.length > 0)
    );
    if (notGradedItems.size < 1) {
        return rows;
    }

    return rows.map((row) => {
        if (!row || isReadOnlyMappingRow(row)) {
            return row;
        }
        const activityName = getMappingRowActivityName(row);
        if (!activityName || !notGradedItems.has(activityName.toLowerCase())) {
            return row;
        }
        return {
            ...row,
            category: NOT_GRADED,
            subcategory: '',
            not_graded: true,
        };
    });
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
    const used = new Set(Array.isArray(proposalCategoriesWithItems) ? proposalCategoriesWithItems : []);
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
    const method = Number(proposal.aggregation_method);
    const requiresStrictWeight = method === 10 || method === 11 || method === 12;
    if (!requiresStrictWeight) {
        return {total: null, valid: true};
    }

    const categories = method === 12
        ? proposal.categories.filter((c) => !Boolean(c && c.extra_credit))
        : proposal.categories;
    const total = categories.reduce((sum, c) => sum + Number(c.weight || 0), 0);
    const valid = Math.abs(total - 100.0) <= 0.1;
    return {total, valid};
};

const WEIGHT_CHECK_MESSAGE_PREFIX = 'Weight check: total is';

const clearWeightCheckSystemMessages = () => {
    const chat = getChatMessages();
    if (!chat) {
        return;
    }
    chat.querySelectorAll('.chat-message.bot-message').forEach((messageNode) => {
        const content = messageNode.querySelector('.message-content');
        const text = String((content && content.textContent) || messageNode.textContent || '').trim();
        if (text.startsWith(WEIGHT_CHECK_MESSAGE_PREFIX)) {
            messageNode.remove();
        }
    });
};

const applyProposalWeightGate = (proposal) => {
    const {total, valid} = proposalTotalAndValid(proposal);
    if (total === null) {
        latestProposalWeightCheck = {known: false, total: null, valid: true};
        clearWeightCheckSystemMessages();
        setAcceptEnabled(false);
        setFinalizeEnabled(false);
        return;
    }

    latestProposalWeightCheck = {known: true, total, valid};

    if (!valid) {
        clearWeightCheckSystemMessages();
        setAcceptEnabled(false);
        setFinalizeEnabled(false);
        appendSystemMessage(`Weight check: total is ${total.toFixed(1)}% (expected 100%). Update weights before accepting or generating mapping.`);
    } else {
        clearWeightCheckSystemMessages();
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
        'btn-gradebook-reset',
        'btn-gradebook-revert'
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

const clearBusyWaitIndicator = () => {
    const existing = document.getElementById('gradebook-busy-wait-indicator');
    if (existing) {
        if (existing.dataset.loaderIntervalId) {
            clearInterval(Number(existing.dataset.loaderIntervalId));
        }
        existing.remove();
    }
};

const appendProgressiveLoader = (container, id, initialText) => {
    if (!container) {
        return null;
    }
    const existing = document.getElementById(id);
    if (existing) {
        if (existing.dataset.loaderIntervalId) {
            clearInterval(Number(existing.dataset.loaderIntervalId));
        }
        existing.remove();
    }

    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'chat-message bot-message';
    loadingDiv.id = id;
    loadingDiv.innerHTML = `
        <div class="message-content cria-loader">
            <div class="cria-loader-stage">${initialText}</div>
            <div class="cria-loader-skeleton" aria-hidden="true">
                <span class="line w100"></span>
                <span class="line w84"></span>
                <span class="line w66"></span>
            </div>
        </div>`;
    container.appendChild(loadingDiv);
    container.scrollTop = container.scrollHeight;

    const stageNode = loadingDiv.querySelector('.cria-loader-stage');
    let stageIndex = 0;
    const intervalId = setInterval(() => {
        if (!loadingDiv.isConnected || !stageNode) {
            clearInterval(intervalId);
            return;
        }
        stageIndex = Math.min(stageIndex + 1, CHAT_LOADER_STAGES.length - 1);
        stageNode.textContent = CHAT_LOADER_STAGES[stageIndex];
        container.scrollTop = container.scrollHeight;
    }, CHAT_LOADER_STEP_MS);
    loadingDiv.dataset.loaderIntervalId = String(intervalId);
    return loadingDiv;
};

const showBusyWaitIndicator = async () => {
    if (document.getElementById('gradebook-busy-wait-indicator')) {
        return;
    }
    const chat = getChatMessages();
    if (!chat) {
        return;
    }
    let loadingText = 'Please wait, still processing your previous request...';
    try {
        loadingText = await Str.get_string('gradebook_loading', 'block_ai_assistant');
    } catch (e) {
    }
    appendProgressiveLoader(chat, 'gradebook-busy-wait-indicator', loadingText);
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

const getSubcategoryOptionsForCategory = (categoryName) => {
    const key = String(categoryName || '').trim().toLowerCase();
    if (!key || typeof proposalSubcategoriesByCategory !== 'object' || proposalSubcategoriesByCategory === null) {
        return [];
    }
    const options = proposalSubcategoriesByCategory[key];
    return Array.isArray(options) ? options : [];
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
    renderEmptyCategoryHelper(confirmed);
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
        renderEmptyCategoryHelper([]);
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
        const activityName = item.activity_name || item.name || '';
        activity.textContent = activityName;
        activity.className = 'gradebook-activity-name';
        if (String(item.item_source || item.itemtype || '').trim().toLowerCase() === 'manual') {
            const marker = document.createElement('div');
            marker.className = 'text-muted small mt-1';
            marker.textContent = manualItemLabel;
            activity.appendChild(marker);
        }
        if (isReadOnlyMappingRow(item)) {
            const marker = document.createElement('div');
            marker.className = 'text-warning small mt-1';
            marker.textContent = `Read-only: ${mappingConstraintReason(item) || 'constrained item'}`;
            activity.appendChild(marker);
        }
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

        if (isReadOnlyMappingRow(item)) {
            sel.value = NOT_GRADED;
            item.category = NOT_GRADED;
            sel.disabled = true;
            sel.setAttribute('title', `Read-only: ${mappingConstraintReason(item) || 'constrained item'}`);
            applyNotGradedRowStyle(tr, true);
            sel.classList.remove('gradebook-select-empty');
        }

        const subcategory = document.createElement('td');
        const subSel = document.createElement('select');
        subSel.className = 'form-select form-select-sm gradebook-subcategory-select';
        subSel.setAttribute('aria-label', `Subcategory for ${item.activity_name || ''}`);
        subSel.setAttribute('title', subcategorySelectTitle);

        const refreshSubcategorySelect = (categoryValue) => {
            const normalizedCategoryValue = String(categoryValue || '').trim();
            const nextSubcategory = String(item.subcategory || '').trim();
            const subcategoryOptions = getSubcategoryOptionsForCategory(normalizedCategoryValue).slice();

            subSel.replaceChildren();

            const noneOpt = document.createElement('option');
            noneOpt.value = '';
            noneOpt.textContent = noSubcategoryLabel;
            subSel.appendChild(noneOpt);

            subcategoryOptions.forEach((subName) => {
                const opt = document.createElement('option');
                opt.value = subName;
                opt.textContent = subName;
                subSel.appendChild(opt);
            });

            const shouldDisableSubcategory = isReadOnlyMappingRow(item)
                || isNotGraded(normalizedCategoryValue)
                || !normalizedCategoryValue
                || subcategoryOptions.length < 1;

            if (subcategoryOptions.length < 1 || isNotGraded(normalizedCategoryValue)) {
                item.subcategory = '';
            } else if (!subcategoryOptions.includes(nextSubcategory)) {
                item.subcategory = '';
            }

            subSel.value = String(item.subcategory || '').trim();
            if (!subSel.value) {
                subSel.value = '';
            }
            subSel.disabled = shouldDisableSubcategory;
        };

        refreshSubcategorySelect(currentValue);

        sel.addEventListener('change', () => {
            const val = sel.value;
            item.category = val;
            const validSubcategories = getSubcategoryOptionsForCategory(val);
            if (!Array.isArray(validSubcategories) || validSubcategories.length < 1) {
                item.subcategory = '';
            } else if (!validSubcategories.includes(String(item.subcategory || '').trim())) {
                item.subcategory = '';
            }
            refreshSubcategorySelect(val);
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

        subSel.addEventListener('change', () => {
            item.subcategory = String(subSel.value || '').trim();
            persistMappingInputs(confirmed);
            clearMappingError();
        });

        subcategory.appendChild(subSel);
        tr.appendChild(subcategory);

        rows.appendChild(tr);
    });

    updateMappingSummary(confirmed);
    renderEmptyCategoryHelper(confirmed);
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

const applyProposalFromPayload = (parsed) => {
    if (!parsed || typeof parsed !== 'object' || !parsed.proposal || !Array.isArray(parsed.proposal.categories)) {
        return;
    }
    const cats = extractProposalCategories(parsed.proposal);
    const catsWithItems = extractProposalCategoriesWithItems(parsed.proposal);
    const subcategoriesByCategory = extractProposalSubcategoriesMap(parsed.proposal);
    if (cats.length) {
        setProposalCategories(cats, catsWithItems, subcategoriesByCategory);
    }
    applyProposalWeightGate(parsed.proposal);
};

const pruneStaleSubcategoriesFromMapping = (rows) => {
    if (!Array.isArray(rows) || rows.length < 1) {
        return rows;
    }
    return rows.map((row) => {
        if (!row || isReadOnlyMappingRow(row)) {
            return row;
        }
        const category = normalizeMappedCategory(row.category);
        if (isNotGraded(category)) {
            if (String(row.subcategory || '').trim()) {
                return {...row, subcategory: ''};
            }
            return row;
        }
        const options = getSubcategoryOptionsForCategory(category);
        const subcategory = String(row.subcategory || '').trim();
        if (!subcategory) {
            return row;
        }
        if (Array.isArray(options) && options.includes(subcategory)) {
            return row;
        }
        return {...row, subcategory: ''};
    });
};

const applyMappingPayload = (parsed) => {
    if (!parsed || typeof parsed !== 'object') {
        return 0;
    }

    let incoming = mappingToConfirmedRows(extractContentMapping(parsed));
    if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
        incoming = appendProposalManualRowsToMapping(parsed.proposal, incoming);
    }
    if (!incoming.length) {
        return 0;
    }

    const existing = getConfirmedMapping();
    const merged = pruneStaleSubcategoriesFromMapping(mergeConfirmedRowsWithExisting(incoming, existing));
    persistMappingInputs(merged);
    syncMappingUIFromJson();
    applyProposalFromPayload(parsed);
    return merged.length;
};

const refreshMappingBeforeFinalize = async () => {
    try {
        const parsed = await callWithSessionRetry(async (sid) => {
            const raw = await callWs('block_ai_assistant_gradebook_accept', {
                courseid: getCourseId(),
                session_id: sid
            });
            return parseResponse(raw);
        });
        if (parsed.phase || parsed.state) {
            setPhase(parsed.phase || parsed.state);
        }
        return applyMappingPayload(parsed);
    } catch (e) {
        gradebookDebug('refreshMappingBeforeFinalize:error', {
            message: String((e && e.message) || e || '')
        });
        return getConfirmedMapping().length;
    }
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
        const gradeItemId = item.grade_item_id != null
            ? item.grade_item_id
            : (item.gradeitemid != null ? item.gradeitemid : null);
        const gradeItemType = item.grade_item_type || item.itemtype || '';
        const gradeItemName = item.grade_item_name || item.itemname || name;
        const gradeItemIdnumber = item.grade_item_idnumber || item.idnumber || '';
        const iteminstance = item.iteminstance != null ? item.iteminstance : null;
        const confirmed = item.confirmed_category && String(item.confirmed_category).trim().length > 0
            ? String(item.confirmed_category).trim()
            : '';
        const suggested = !confirmed && item.suggested_category && String(item.suggested_category).trim().length > 0
            ? String(item.suggested_category).trim()
            : '';
        const confirmedSubcategory = item.confirmed_subcategory && String(item.confirmed_subcategory).trim().length > 0
            ? String(item.confirmed_subcategory).trim()
            : '';
        const suggestedSubcategory = !confirmedSubcategory && item.suggested_subcategory && String(item.suggested_subcategory).trim().length > 0
            ? String(item.suggested_subcategory).trim()
            : '';
        const normalizedCategory = normalizeMappedCategory(confirmed || suggested);
        const readOnly = isReadOnlyMappingRow(item);
        const readOnlyReason = mappingConstraintReason(item);
        const moodleCmid = item.moodle_cmid != null
            ? item.moodle_cmid
            : (item.cmid != null ? item.cmid : null);
        const itemSource = String(item.item_source || '').trim() || (moodleCmid == null ? 'manual' : 'activity');
        return {
            moodle_cmid: moodleCmid,
            activity_name: name,
            module: module,
            itemmodule: module,
            iteminstance: iteminstance,
            grade_item_id: gradeItemId,
            grade_item_type: gradeItemType,
            grade_item_name: gradeItemName,
            grade_item_idnumber: gradeItemIdnumber,
            itemtype: gradeItemType,
            item_source: itemSource,
            category: readOnly ? NOT_GRADED : normalizedCategory,
            subcategory: readOnly ? '' : (confirmedSubcategory || suggestedSubcategory || ''),
            suggested_category: suggested,
            suggested_subcategory: suggestedSubcategory,
            read_only: readOnly,
            read_only_reason: readOnlyReason,
            is_constrained: readOnly
        };
    }).filter((item) => {
        if (item.grade_item_id != null || item.moodle_cmid != null) {
            return true;
        }
        const itemSource = String(item.item_source || '').trim().toLowerCase();
        const activityName = String(item.activity_name || item.name || '').trim();
        return activityName.length > 0
            && (itemSource === 'manual' || itemSource === 'proposal_manual' || itemSource === 'proposal');
    });
};

const appendProposalManualRowsToMapping = (proposal, existingRows = []) => {
    if (!proposal || !Array.isArray(proposal.categories)) {
        return Array.isArray(existingRows) ? existingRows : [];
    }

    const rows = Array.isArray(existingRows) ? [...existingRows] : [];
    const identityKeys = new Set(rows.map((row) => buildConfirmedRowIdentity(row)).filter((key) => Boolean(key)));
    const existingNames = new Set(
        rows
            .map((row) => String(row.activity_name || row.grade_item_name || row.name || '').trim().toLowerCase())
            .filter((name) => name.length > 0)
    );

    proposal.categories.forEach((category) => {
        const categoryName = String((category && category.name) || '').trim();
        if (!categoryName || !Array.isArray(category.items)) {
            return;
        }

        category.items.forEach((rawItem) => {
            const itemName = String(rawItem || '').trim();
            if (!itemName) {
                return;
            }

            const itemKey = itemName.toLowerCase();
            if (existingNames.has(itemKey)) {
                return;
            }

            const candidate = {
                moodle_cmid: null,
                activity_name: itemName,
                grade_item_id: null,
                grade_item_type: 'manual',
                itemtype: 'manual',
                item_source: 'proposal_manual',
                category: categoryName,
                subcategory: '',
                mapping_method: 'proposal_manual'
            };
            const key = buildConfirmedRowIdentity(candidate);
            if (!key || identityKeys.has(key)) {
                return;
            }
            rows.push(candidate);
            identityKeys.add(key);
            existingNames.add(itemKey);
        });
    });

    return dedupeMappingRowsByActivityName(rows);
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

const extractProposalCategoriesWithItems = (proposal) => {
    if (!proposal || !Array.isArray(proposal.categories)) {
        return [];
    }
    return proposal.categories
        .filter((c) => c && Array.isArray(c.items) && c.items.length > 0)
        .map((c) => c.name)
        .filter(Boolean);
};

const extractProposalSubcategoriesMap = (proposal) => {
    const map = {};
    if (!proposal || !Array.isArray(proposal.categories)) {
        return map;
    }
    proposal.categories.forEach((category) => {
        const categoryName = String((category && category.name) || '').trim();
        if (!categoryName) {
            return;
        }
        const subcategories = Array.isArray(category.subcategories) ? category.subcategories : [];
        map[categoryName.toLowerCase()] = subcategories
            .map((sub) => String((sub && sub.name) || '').trim())
            .filter(Boolean);
    });
    return map;
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

const setProposalCategories = (cats, categoriesWithItems = [], subcategoriesByCategory = {}) => {
    proposalCategories = Array.isArray(cats) ? cats : [];
    proposalCategoriesWithItems = Array.isArray(categoriesWithItems) ? categoriesWithItems : [];
    proposalSubcategoriesByCategory = subcategoriesByCategory && typeof subcategoriesByCategory === 'object'
        ? subcategoriesByCategory
        : {};
    if (proposalCategories.length > 0) {
        saveJson(getStorageKey('proposal_categories'), proposalCategories);
    } else {
        removeKey(getStorageKey('proposal_categories'));
    }
    syncMappingUIFromJson();
};

const hydrateFromStatusPayload = (parsed, serverState = null) => {
    const statusPayload = (parsed && typeof parsed === 'object')
        ? (parsed.session && typeof parsed.session === 'object' ? parsed.session : parsed)
        : null;
    if (!statusPayload || typeof statusPayload !== 'object') {
        gradebookDebug('hydrateFromStatusPayload:skip-invalid', {
            parsedType: typeof parsed
        });
        return;
    }

    gradebookDebug('hydrateFromStatusPayload:start', {
        hasSessionWrapper: Boolean(parsed && parsed.session),
        phase: statusPayload.phase || parsed.phase || parsed.state || '-',
        importMode: statusPayload.import_mode || (statusPayload.extraction && statusPayload.extraction.import_mode) || parsed.import_mode || '',
        chatLength: Array.isArray(statusPayload.chat_history || parsed.chat_history || [])
            ? (statusPayload.chat_history || parsed.chat_history || []).length
            : 0
    });

    applyRevertFlagsFromPayload(parsed);

    const statusPhase = String(statusPayload.phase || parsed.phase || parsed.state || '-');
    let effectivePhase = statusPhase;

    const statePhase = String((serverState && serverState.phase) || '').trim().toUpperCase();
    if (statePhase === 'REFINEMENT' && isFinalizeCompletedPhase(statusPhase)) {
        let stateResultData = {};
        try {
            const stateResult = JSON.parse(String((serverState && serverState.result_json) || 'null'));
            stateResultData = (stateResult && typeof stateResult === 'object' && stateResult.data && typeof stateResult.data === 'object')
                ? stateResult.data
                : {};
        } catch (e) {
        }

        if (Boolean(stateResultData.grade_setup_skipped || stateResultData.grade_setup_apply_rolled_back)) {
            effectivePhase = 'REFINEMENT';
        }
    }

    setPhase(effectivePhase || '-');
    const phaseUpper = String(effectivePhase || '-').toUpperCase();

    ingestBackendChatHistory(statusPayload.chat_history || parsed.chat_history || [], true);
    reattachStickyGateUiFromExtraction(statusPayload, {forceRender: renderedChatLength() > 0});
    const extraction = (statusPayload.extraction && typeof statusPayload.extraction === 'object')
        ? statusPayload.extraction
        : {};
    setMissingItemDecisions(extraction.missing_item_decisions || []);

    const statusProposal = statusPayload.proposal || null;
    const cats = extractProposalCategories(statusProposal);
    const catsWithItems = extractProposalCategoriesWithItems(statusProposal);
    const subcategoriesByCategory = extractProposalSubcategoriesMap(statusProposal);
    if (cats.length > 0) {
        setProposalCategories(cats, catsWithItems, subcategoriesByCategory);
    }
    if (statusProposal && Array.isArray(statusProposal.categories)) {
        applyProposalWeightGate(statusProposal);
    }

    if (isFinalizeCompletedPhase(phaseUpper)) {
        rehydrateBaselineDeleteWarningState(serverState);
    } else {
        const kept = getStoredFinalizeResult();
        const keepFinalized = Boolean(kept && isSuccessfulFinalizeResult(kept));
        if (!keepFinalized) {
            removeKey(getStorageKey('result'));
            lastStoredFinalizeResult = null;
            renderResultPanel(null);
        } else {
            rehydrateBaselineDeleteWarningState(serverState);
        }
    }

    const mapping = extractContentMapping(statusPayload);
    const confirmed = mappingToConfirmedRows(mapping);
    if (confirmed.length > 0) {
        const node = el('gradebook-confirmed-mapping');
        if (node) {
            const existing = getConfirmedMapping();
            const merged = mergeConfirmedRowsWithExisting(confirmed, existing);
            node.value = JSON.stringify(merged, null, 2);
        }
        syncMappingUIFromJson();
    }

    const statusData = (parsed && typeof parsed.data === 'object') ? parsed.data : {};
    const missingAfterFinalize = Boolean(statusData.grade_setup_missing_after_finalize);
    const deletedEventDetected = Boolean(statusData.grade_setup_deleted_event_detected);
    if (missingAfterFinalize || deletedEventDetected) {
        const mappingNode = el('gradebook-confirmed-mapping');
        if (mappingNode) {
            mappingNode.value = '[]';
        }
        syncMappingUIFromJson();
        setRevertAvailable(false);
        setPhase('REFINEMENT');
        if (!gradebookMissingNoticeShown) {
            appendSystemMessage('Gradebook structure was deleted outside this session. Mapping was reset. Regenerate mapping and finalize again.');
            gradebookMissingNoticeShown = true;
        }
    }
};

const bumpLastKnown = (value) => {
    const t = Number(value) || 0;
    if (t > lastKnownStateTimemodified) {
        lastKnownStateTimemodified = t;
    }
};

const localHistoryLength = () => {
    const localHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    const backendHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history_backend'), []));
    if (backendHistory.length > localHistory.length || !chatHistoryTailMatches(localHistory, backendHistory)) {
        return backendHistory.length;
    }
    return localHistory.length;
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
    const normalizedMapping = (() => {
        if (!mappingNode) {
            return null;
        }
        const rows = getConfirmedMapping();
        if (!Array.isArray(rows) || rows.length < 1) {
            return '';
        }
        return JSON.stringify(rows, null, 2);
    })();

    const localHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    const backendHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history_backend'), []));
    const starterHistory = getStarterChatHistory(String(sid || '').trim());
    const preferredHistory = (backendHistory.length > localHistory.length || !chatHistoryTailMatches(localHistory, backendHistory))
        ? backendHistory
        : localHistory;
    const snapshotHistory = preferredHistory.length > 0 ? preferredHistory : starterHistory;

    return {
        session_id: (sid && sid !== 'starting…') ? sid : null,
        phase: (phaseText && phaseText !== '—') ? phaseText : null,
        chat_history_json: JSON.stringify(snapshotHistory),
        confirmed_mapping_json: normalizedMapping,
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
                    if (!requestInFlight && !proposalPanelSyncPromise) {
                        hydrateFromServerState(response);
                    }
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
        gradebookDebug('serverGetState:response', {
            found: Boolean(response && response.found),
            sessionId: response && response.session_id ? String(response.session_id) : '',
            hasChat: Boolean(response && response.chat_history_json && String(response.chat_history_json).trim() !== ''),
            timemodified: Number(response && response.timemodified || 0)
        });
        if (response && response.timemodified) {
            bumpLastKnown(response.timemodified);
        }
        return response && response.found ? response : null;
    } catch (e) {
        gradebookDebug('serverGetState:error', {message: String((e && e.message) || e || '')});
        return null;
    }
};

const hydrateFromServerState = (state) => {
    if (!state) {
        gradebookDebug('hydrateFromServerState:skip-null');
        return false;
    }

    let hydrated = false;

    try {
        const history = JSON.parse(state.chat_history_json || '[]');
        const localLen = localHistoryLength();
        const starterHistory = getStarterChatHistory();
        gradebookDebug('hydrateFromServerState:history-compare', {
            serverLength: Array.isArray(history) ? history.length : 0,
            localLength: localLen,
            sessionId: state.session_id || ''
        });
        if (Array.isArray(history) && history.length > 0 && history.length >= localLen) {
            persistChatHistoryLocal(history);
            renderHistory(history);
            hydrated = true;
        } else if ((!Array.isArray(history) || history.length < 1) && localLen < 1 && starterHistory.length > 0) {
            persistChatHistoryLocal(starterHistory);
            renderHistory(starterHistory);
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
    const restoredPhase = state.phase && state.phase !== '—' ? String(state.phase) : '';
    if (restoredPhase) {
        setPhase(restoredPhase);
    }

    if (state.confirmed_mapping_json && state.confirmed_mapping_json !== '[]') {
        const node = el('gradebook-confirmed-mapping');
        if (node) {
            node.value = state.confirmed_mapping_json;
            syncMappingUIFromJson();
        }
    }

    try {
        if (state.result_json) {
            if (rehydrateBaselineDeleteWarningState(state)) {
                hydrated = true;
            } else {
                const result = JSON.parse(String(state.result_json || 'null'));
                if (result) {
                    removeKey(getStorageKey('result'));
                    lastStoredFinalizeResult = null;
                    renderResultPanel(null);
                    scheduleServerSave();
                }
            }
        }
    } catch (e) {
    }

    refreshRevertButtonFromLocalState();

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
    const data = (result && typeof result === 'object' && result.data && typeof result.data === 'object')
        ? result.data
        : {};
    const mapping = extractContentMapping(result) || {};
    const categories = mapping.categories || (result.proposal && result.proposal.categories) || [];
    const warnings = Array.isArray(data.grade_setup_warnings) ? data.grade_setup_warnings : [];
    const infoLines = warnings
        .map((w) => String(w || '').trim())
        .filter((w) => w.length > 0 && /^ℹ/.test(w));
    const warnLines = warnings
        .map((w) => String(w || '').trim())
        .filter((w) => w.length > 0 && /^⚠/.test(w));
    const correlationId = String(data.gradebook_audit_correlation_id || '').trim();
    const primaryMessage = buildFinalizePrimaryMessage(result);

    const parts = [];
    if (primaryMessage) {
        parts.push(`<div class="mb-2"><strong>${escapeHtml(primaryMessage)}</strong></div>`);
    }
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
    if (warnLines.length) {
        parts.push(`<div class="mt-2 text-warning small">${warnLines.map((w) => escapeHtml(w)).join('<br>')}</div>`);
    }
    if (infoLines.length) {
        parts.push(
            '<details class="small mt-2 mb-0"><summary>Apply details</summary><ul class="mb-0 ps-3">' +
            `${infoLines.map((line) => `<li>${escapeHtml(line.replace(/^ℹ\s*/, ''))}</li>`).join('')}</ul></details>`
        );
    }
    if (correlationId) {
        parts.push(`<div class="small text-muted mt-2">Reference ID: <code>${escapeHtml(correlationId)}</code></div>`);
    }
    parts.push('<div class="mt-2 text-success"><i class="fa fa-pen"></i> Edit mode: send a new prompt to change this setup, then regenerate mapping and finalize again.</div>');
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

const doStartSession = async (importMode = '') => {
    gradebookMissingNoticeShown = false;
    const payload = {courseid: getCourseId()};
    const normalizedImportMode = normalizeImportMode(importMode);
    if (normalizedImportMode) {
        payload.import_mode = normalizedImportMode;
    }
    const raw = await callWs('block_ai_assistant_gradebook_start', payload);
    const parsed = parseResponse(raw);

    const status = Number(parsed && (parsed.status || parsed.code_status || parsed.http_status) || 0);
    if (status >= 400) {
        throw new Error(extractResponseErrorMessage(parsed, 'Could not start gradebook session.'));
    }

    const sessionId = parsed.session_id || parsed.sessionId || parsed.session || '';
    if (!String(sessionId || '').trim()) {
        throw new Error(extractResponseErrorMessage(parsed, 'Could not start gradebook session (missing session id).'));
    }

    setSessionId(sessionId);
    setPhase(parsed.phase || parsed.state || '-');
    clearStarterChatHistory();
    const activeImportMode = setImportMode(parsed.import_mode || normalizedImportMode);
    const baselineAvailable = parsed.baseline_available !== undefined
        ? Boolean(parsed.baseline_available)
        : Boolean((parsed.start_metadata && parsed.start_metadata.baseline_available));
    setBaselineAvailable(baselineAvailable);
    setRevertAvailable(false);
    setRevertButtonVisibility(activeImportMode, baselineAvailable, false);

    const autoMode = normalizeImportMode(importMode) === '';
    if (autoMode) {
        if (baselineAvailable) {
            const useBaseline = await askBaselineModeChoice();
            if (!useBaseline) {
                try {
                    await remoteDeleteSession(String(sessionId));
                } catch (e) {
                }
                setSessionId('');
                setPhase('—');
                setImportMode('fresh');
                return doStartSession('fresh');
            }
            setImportMode('baseline');
            appendSystemMessage(await getStringSafe(
                'gradebook_baseline_detected',
                'Using your existing gradebook as baseline.'
            ));
        } else {
            // Prefer backend initial_message (includes syllabus found / upload gate / Part B).
            // Only show a short note when backend did not already explain syllabus state.
            const backendInitial = String(parsed.initial_message || parsed.message || '');
            const backendLower = backendInitial.toLowerCase();
            const backendCoversSyllabus = backendLower.includes('syllabus')
                || backendLower.includes('upload')
                || backendLower.includes('add your course activities');
            if (!backendCoversSyllabus) {
                appendSystemMessage(await getStringSafe(
                    'gradebook_baseline_not_found',
                    'No existing gradebook baseline found. Starting from syllabus/context analysis.'
                ));
            }
        }
    }

    const initial = String(parsed.initial_message || parsed.message || 'Gradebook session started.');
    const phaseForInitial = parsed.phase || parsed.state || '-';
    const backendReplies = Array.isArray(parsed.quick_replies) ? parsed.quick_replies : [];
    const initialReplies = backendReplies.length > 0
        ? backendReplies
        : inferAnalysisQuickRepliesFromText(initial, phaseForInitial);

    if (initialReplies.length > 0) {
        const lowered = initial.toLowerCase();
        const isActivityPrep = lowered.includes('add your course activities')
            || lowered.includes('open course to add activities');
        const isSyllabusPrep = lowered.includes('upload your') && lowered.includes('syllabus');
        const normalizedInitial = (!isActivityPrep && !isSyllabusPrep && lowered.includes('show proposal'))
            ? "I've found syllabus-like content and started analysis. Do you want me to start generating your proposal now?"
            : initial;
        appendSystemMessageWithQuickReplies(normalizedInitial, initialReplies);
    } else {
        appendSystemMessage(initial);
    }

    if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
        applyProposalFromPayload(parsed);
    }

    serverSaveState();

    return sessionId;
};

const clearLocalGradebookState = async () => {
    gradebookMissingNoticeShown = false;
    removeKey(getStorageKey('session_id'));
    removeKey(getStorageKey('chat_history'));
    removeKey(getStorageKey('chat_history_backend'));
    clearStarterChatHistory();
    removeKey(getStorageKey('phase'));
    removeKey(getStorageKey('result'));
    lastStoredFinalizeResult = null;
    removeKey(getStorageKey('proposal_categories'));
    removeKey(getStorageKey('import_mode'));
    removeKey(getStorageKey('baseline_available'));
    removeKey(getStorageKey('revert_available'));
    removeKey(getStorageKey('baseline_modified'));
    proposalCategories = [];
    proposalCategoriesWithItems = [];
    proposalSubcategoriesByCategory = {};
    setRevertButtonVisibility('');
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

const remoteRevertSession = async (sessionId, revision = 0) => {
    if (!sessionId || sessionId === 'starting…') {
        return null;
    }
    const raw = await callWs('block_ai_assistant_gradebook_revert', {
        courseid: getCourseId(),
        session_id: sessionId,
        revision: Number(revision) || 0
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
            // Informational only (e.g., no AI categories were applied yet).
            messages.push(data.grade_setup_message);
        }
    }

    const finalMessage = messages.length > 0 ? messages.join(' ') : 'Session deleted.';
    appendSystemMessage(finalMessage);

    return doStartSession();
};

const revertSessionAndRestart = async () => {
    const currentSessionId = getSessionId();
    const revertResponse = await remoteRevertSession(currentSessionId, 0);
    const status = Number((revertResponse && revertResponse.status) || 0);

    if (!revertResponse || (status >= 400 && status !== 0)) {
        const fallbackMessage = (revertResponse && revertResponse.message)
            ? String(revertResponse.message)
            : 'Revert failed. Please check snapshot availability and try again.';
        appendSystemMessage(fallbackMessage);
        return null;
    }

    suspendAutoSave = true;
    if (pendingServerSave) {
        clearTimeout(pendingServerSave);
        pendingServerSave = null;
    }
    try { await saveInFlight; } catch (_) { }

    await clearLocalGradebookState();
    suspendAutoSave = false;

    const messages = [];
    if (revertResponse && revertResponse.message) {
        messages.push(String(revertResponse.message));
    }
    const restoredRevision = revertResponse && revertResponse.data
        ? Number(revertResponse.data.restored_revision || 0)
        : 0;
    if (restoredRevision > 0) {
        messages.push(`Restored snapshot revision #${restoredRevision}.`);
    }
    const finalMessage = messages.length > 0 ? messages.join(' ') : 'Gradebook reverted to the original baseline setup.';
    appendSystemMessage(finalMessage);

    return doStartSession('baseline');
};

const moodleConfirm = async ({title, message, yesLabel, noLabel, yesTitle, noTitle}) => {
    const applyButtonTitles = () => {
        const modal = document.querySelector('.modal.show');
        if (!modal) {
            return;
        }
        const footerButtons = modal.querySelectorAll('.modal-footer button');
        if (!footerButtons || footerButtons.length < 2) {
            return;
        }
        // Moodle's confirm footer order is cancel/no then continue/yes.
        const noButton = footerButtons[0];
        const yesButton = footerButtons[1];
        if (yesButton && yesTitle) {
            yesButton.setAttribute('title', String(yesTitle));
        }
        if (noButton && noTitle) {
            noButton.setAttribute('title', String(noTitle));
        }
    };

    return new Promise((resolve) => {
        notification.confirm(
            title,
            message,
            yesLabel,
            noLabel,
            () => resolve(true),
            () => resolve(false)
        );
        setTimeout(applyButtonTitles, 0);
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
    return doStartSession('');
};

const restoreOrStartSession = async () => {
    gradebookDebug('restoreOrStartSession:start');
    const serverState = await serverGetState();
    if (serverState) {
        hydrateFromServerState(serverState);
    }

    const candidateSession = (serverState && serverState.session_id) ||
        String(loadJson(getStorageKey('session_id'), '') || '').trim();

    if (candidateSession && candidateSession !== 'starting…') {
        gradebookDebug('restoreOrStartSession:candidate', {sessionId: candidateSession});
        try {
            const raw = await callWs('block_ai_assistant_gradebook_status', {
                courseid: getCourseId(),
                session_id: candidateSession
            });
            const parsed = parseResponse(raw);
            gradebookDebug('restoreOrStartSession:status-ok', {
                phase: parsed && (parsed.phase || parsed.state || (parsed.session && parsed.session.phase) || ''),
                chatLength: Array.isArray((parsed && parsed.chat_history) || (parsed && parsed.session && parsed.session.chat_history) || [])
                    ? (((parsed && parsed.chat_history) || (parsed && parsed.session && parsed.session.chat_history) || []).length)
                    : 0
            });
            if (isSessionNotFound(parsed)) {
                gradebookDebug('restoreOrStartSession:status-session-not-found');
                return softRestartSession('gradebook_session_expired');
            }
            setSessionId(candidateSession);
            hydrateFromStatusPayload(parsed, serverState);
            ensureChatRenderedFromAnySource(parsed, serverState);
            const statusPayload = (parsed && parsed.session && typeof parsed.session === 'object')
                ? parsed.session
                : parsed;
            reattachStickyGateUiFromExtraction(statusPayload, {forceRender: true});
            rehydrateBaselineDeleteWarningState(serverState);
            if (!serverState) {
                serverSaveState();
            }
            return candidateSession;
        } catch (e) {
            gradebookDebug('restoreOrStartSession:status-error', {
                sessionId: candidateSession,
                message: String((e && e.message) || e || '')
            });
            setSessionId(candidateSession);
            ensureChatRenderedFromAnySource(null, serverState);
            return candidateSession;
        }
    }

    gradebookDebug('restoreOrStartSession:no-candidate-starting-new');
    return doStartSession('');
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
    const previousSuspendAutoSave = suspendAutoSave;
    suspendAutoSave = true;
    setUiBusy(true);
    try {
        await waitForProposalPanelSync();
        await action();
        await waitForProposalPanelSync();
    } finally {
        suspendAutoSave = previousSuspendAutoSave;
        requestInFlight = false;
        clearBusyWaitIndicator();
        setUiBusy(false);
        if (!suspendAutoSave) {
            scheduleServerSave();
        }
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

const sendPreparedPrompt = async ({typed, prepared}) => {
    const input = el('block-ai-assistant-gradebook-input');
    const normalized = prepared.prompt;
    let shouldForcePostChatSave = false;

    await ensureSession();

    await withRequestLock(async () => {
        const chatContainer = getChatMessages();
        const continueLikePrompt = /^(continue|go on|next|ok|okay)$/i.test(String(normalized || '').trim());
        const hadSyllabusGateBeforeSend = Boolean(findLastSyllabusPrepBotMessage(chatContainer));
        // Avoid stacking a human "Continue" bubble when we may only refresh the gate in place.
        const suppressHumanContinue = continueLikePrompt && hadSyllabusGateBeforeSend;
        if (!suppressHumanContinue) {
            appendMessage(chatContainer, prepared.displayText, true, false);
        } else {
            // Mark prior Continue buttons spent without adding a jump-causing human row.
            document.querySelectorAll('.gradebook-quick-replies:not(.is-spent)').forEach((node) => {
                node.classList.add('is-spent');
                node.querySelectorAll('button').forEach((btn) => {
                    btn.disabled = true;
                });
            });
        }
        rememberPrompt(typed);
        input.value = '';
        if (prepared.usedFormulaInput) {
            clearExcelFormulaInput();
        }

        // Stale/invalid JSON typed in Advanced Options should not persist into normal chat turns.
        const mappingNode = el('gradebook-confirmed-mapping');
        if (mappingNode) {
            const rawMapping = String(mappingNode.value || '').trim();
            if (rawMapping) {
                const confirmedRows = getConfirmedMapping();
                if (!Array.isArray(confirmedRows) || confirmedRows.length < 1) {
                    mappingNode.value = '';
                    syncMappingUIFromJson();
                    clearMappingError();
                }
            }
        }
        focusPromptInput();

        let loadingText = 'Loading...';
        try {
            loadingText = await Str.get_string('gradebook_loading', 'block_ai_assistant');
        } catch (e) {
        }
        appendProgressiveLoader(chatContainer, 'gradebook-loading-indicator', loadingText);

        try {
            const previousPhase = String(loadJson(getStorageKey('phase'), '') || '').toUpperCase();
            const parsed = await callWithSessionRetry(async (sid) => {
                const raw = await callWsWithTimeout('block_ai_assistant_gradebook_chat', {
                    courseid: getCourseId(),
                    session_id: sid,
                    prompt: normalized
                });
                return parseResponse(raw);
            });
            const replyText = String(parsed.reply || parsed.message || 'Updated.');
            const replyUiPayload = (parsed.ui && typeof parsed.ui === 'object') ? parsed.ui : null;
            const effectiveQuickReplies = (() => {
                const direct = Array.isArray(parsed.quick_replies) ? parsed.quick_replies : [];
                if (direct.length > 0) {
                    return direct;
                }
                if (isSyllabusPrepGateText(replyText)) {
                    return [{label: 'Continue', prompt: 'continue'}];
                }
                return inferAnalysisQuickRepliesFromText(replyText, parsed.phase || parsed.state || '');
            })();
            const backendAlreadyHasReply = backendHistoryContainsReply(parsed.chat_history || [], replyText);
            ingestBackendChatHistory(parsed.chat_history || [], false);
            await runWithinProposalPanelSync(async () => {
                setPhase(parsed.phase || parsed.state || '-');
                const currentPhase = String(parsed.phase || parsed.state || '-').toUpperCase();
                const movedBackToProposalFlow = ['INTAKE', 'ANALYSIS', 'PROPOSAL', 'REFINEMENT'].includes(currentPhase);
                const proposalChanged = parsed.proposal_changed === true;
                const enteredEditFromFinalized =
                    (previousPhase === 'COMPLETED' || previousPhase === 'ACCEPTED') && currentPhase === 'REFINEMENT';

                shouldForcePostChatSave = proposalChanged || enteredEditFromFinalized;

                if (enteredEditFromFinalized) {
                    removeKey(getStorageKey('result'));
                    renderResultPanel(null);
                    refreshRevertButtonFromLocalState();
                    appendSystemMessageSafely('Edit mode enabled. Your previous finalization remains as baseline; regenerate mapping and click Finalize again to apply your updated override.');
                }

                if (extractContentMapping(parsed)) {
                    applyMappingPayload(parsed);
                } else if (proposalChanged && parsed.proposal) {
                    applyProposalFromPayload(parsed);
                    const existingRows = getConfirmedMapping();
                    if (existingRows.length > 0) {
                        const synced = syncNotGradedRowsFromProposal(parsed.proposal, existingRows);
                        const pruned = pruneStaleSubcategoriesFromMapping(synced);
                        persistMappingInputs(pruned);
                        syncMappingUIFromJson();
                    }
                }

                if (movedBackToProposalFlow) {
                    removeKey(getStorageKey('result'));
                    renderResultPanel(null);
                }

                const updatedGateInPlace = isSyllabusPrepGateText(replyText)
                    && updateSyllabusPrepGateInPlace(chatContainer, replyText, effectiveQuickReplies);
                if (!updatedGateInPlace) {
                    appendMessage(
                        chatContainer,
                        replyText,
                        false,
                        backendAlreadyHasReply,
                        effectiveQuickReplies,
                        replyUiPayload
                    );
                }
                if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
                    const cats = extractProposalCategories(parsed.proposal);
                    const catsWithItems = extractProposalCategoriesWithItems(parsed.proposal);
                    const subcategoriesByCategory = extractProposalSubcategoriesMap(parsed.proposal);
                    if (cats.length) {
                        setProposalCategories(cats, catsWithItems, subcategoriesByCategory);
                    }


                    applyProposalWeightGate(parsed.proposal);
                }
            });
            applyRevertFlagsFromPayload(parsed);
            if (payloadRevertAvailable(parsed) === null) {
                refreshRevertButtonFromLocalState();
            }
            void serverSaveState(shouldForcePostChatSave ? {force: true} : undefined).catch(() => {});
        } catch (error) {
            appendSystemMessageSafely('Chat request failed. Please try again.');
            notification.exception(error);
        } finally {
            const indicator = document.getElementById('gradebook-loading-indicator');
            if (indicator) {
                if (indicator.dataset.loaderIntervalId) {
                    clearInterval(Number(indicator.dataset.loaderIntervalId));
                }
                indicator.remove();
            }
            focusPromptInput();
        }
    });
};

const sendPrompt = async () => {
    const input = el('block-ai-assistant-gradebook-input');
    const typed = String(input.value || '').trim();
    const formula = getExcelFormulaInput();
    if (!typed && !formula) {
        return;
    }

    const prepared = buildPromptWithFormula(typed);
    const payload = {typed, prepared};

    if (requestInFlight) {
        await showBusyWaitIndicator();
        focusPromptInput();
        return;
    }

    await sendPreparedPrompt(payload);
};

const loadProposalPanel = async (announceLoaded = false) => {
    const parsed = await callWithSessionRetry(async (sid) => {
        const raw = await callWs('block_ai_assistant_gradebook_proposal', {
            courseid: getCourseId(),
            session_id: sid
        });
        return parseResponse(raw);
    });

    const proposal = parsed.proposal || null;
    setPhase(parsed.phase || parsed.state || '-');
    if (announceLoaded) {
        appendChatNotice('Proposal refreshed.');
    }

    if (proposal && Array.isArray(proposal.categories)) {
        const cats = extractProposalCategories(proposal);
        const catsWithItems = extractProposalCategoriesWithItems(proposal);
        const subcategoriesByCategory = extractProposalSubcategoriesMap(proposal);
        if (cats.length) {
            setProposalCategories(cats, catsWithItems, subcategoriesByCategory);
        }

        applyProposalWeightGate(proposal);
    } else {
        appendChatNotice('No proposal yet. Send a prompt to generate one.');
        setAcceptEnabled(false);
    }

    return parsed;
};

const fetchProposal = async () => {
    await withRequestLock(async () => {
        await ensureSession();
        await runWithinProposalPanelSync(async () => {
            await loadProposalPanel(true);
        });
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
        const catsWithItems = extractProposalCategoriesWithItems(parsed.proposal || null);
        const subcategoriesByCategory = extractProposalSubcategoriesMap(parsed.proposal || null);
        if (cats.length) {
            setProposalCategories(cats, catsWithItems, subcategoriesByCategory);
        }

        const mappedCount = applyMappingPayload(parsed);
        if (mappedCount > 0) {
            const confirmed = getConfirmedMapping();
            const drawer = el('gradebook-mapping-drawer');
            if (drawer && drawer.classList.contains('is-collapsed')) {
                drawer.classList.remove('is-collapsed');
                const toggle = el('gradebook-mapping-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
            const catHint = proposalCategories.length
                ? ` Each category (${proposalCategories.join(', ')}) needs at least one mapped row or manual grade item.`
                : '';
            const constrainedCount = confirmed.filter((row) => isReadOnlyMappingRow(row)).length;
            setMappingDrawerGuidance(
                `<strong>Mapping ready:</strong> ${mappedCount} activities. ` +
                'Pick a category for each row (or "Not graded"), then click <strong>Generate gradebook</strong>.' +
                `${catHint}`
            );
            if (constrainedCount > 0) {
                showMappingActionNote(
                    `Read-only constraints detected on ${constrainedCount} row${constrainedCount === 1 ? '' : 's'}; ` +
                    'these are auto-marked as Not graded and cannot be reassigned.'
                );
            }
            appendChatNotice(
                `Mapping is ready for ${mappedCount} activities. Review rows in the mapping panel below, then click Finalize.`
            );
        } else {
            syncMappingUIFromJson();
            appendChatNotice(
                'Accepted, but mapping is empty. Add a manual grade item, remove empty categories, or review course activities before finalizing.'
            );
        }

        applyRevertFlagsFromPayload(parsed);
        if (payloadRevertAvailable(parsed) === null) {
            refreshRevertButtonFromLocalState();
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
        await refreshMappingBeforeFinalize();

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
            const msg = getEmptyCategoryWarningMessage(emptyCats);
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

        const serializeMappingRow = (item) => ({
            grade_item_id: item.grade_item_id,
            grade_item_type: item.grade_item_type || item.itemtype || '',
            grade_item_name: item.grade_item_name || item.activity_name || '',
            category: item.category,
            subcategory: String(item.subcategory || '').trim(),
            activity_name: item.activity_name || '',
            moodle_cmid: item.moodle_cmid != null ? item.moodle_cmid : null,
            itemmodule: item.itemmodule || item.module || '',
            iteminstance: item.iteminstance != null ? item.iteminstance : null,
            itemtype: item.itemtype || item.grade_item_type || '',
            item_source: item.item_source || '',
            mapping_method: item.mapping_method || '',
            activity_key: item.activity_key || '',
            not_graded: isNotGraded(item.category) || Boolean(item.not_graded),
        });

        const gradedRows = confirmed
            .filter((item) => !isNotGraded(item.category))
            .map(serializeMappingRow)
            .filter((item) => {
                if (item.grade_item_id != null || item.moodle_cmid != null) {
                    return true;
                }
                const itemSource = String(item.item_source || '').trim().toLowerCase();
                const activityName = String(item.activity_name || item.grade_item_name || '').trim();
                return activityName.length > 0
                    && (itemSource === 'manual' || itemSource === 'proposal_manual' || itemSource === 'proposal');
            });
        const notGradedRows = confirmed
            .filter((item) => isNotGraded(item.category))
            .map(serializeMappingRow)
            .filter((item) => {
                if (item.grade_item_id != null || item.moodle_cmid != null) {
                    return true;
                }
                return String(item.activity_name || item.grade_item_name || '').trim().length > 0;
            });
        const finalizeRows = gradedRows.concat(notGradedRows);
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
                confirmed_mapping_json: JSON.stringify(finalizeRows)
            });
            return parseResponse(raw);
        });

        const finalizePhase = String(parsed.phase || parsed.state || '').toUpperCase();
        const finalizeData = (parsed && typeof parsed === 'object' && parsed.data && typeof parsed.data === 'object')
            ? parsed.data
            : {};
        const setupSkipped = Boolean(finalizeData.grade_setup_skipped || finalizeData.grade_setup_apply_rolled_back);
        const finalizeCompleted = ['COMPLETED', 'FINALIZED'].includes(finalizePhase) && !setupSkipped;

        await runWithinProposalPanelSync(async () => {
            setPhase(setupSkipped ? 'REFINEMENT' : (parsed.phase || parsed.state || 'COMPLETED'));
            if (finalizeCompleted) {
                const resultToStore = (parsed && typeof parsed === 'object') ? {...parsed} : parsed;
                if (isBaselineImportSession()) {
                    resultToStore._baseline_applied = true;
                    resultToStore._import_mode = 'baseline';
                    setBaselineModified(true);
                } else if (getImportMode() === 'fresh') {
                    resultToStore._import_mode = 'fresh';
                }
                lastStoredFinalizeResult = resultToStore;
                saveJson(getStorageKey('result'), resultToStore);
                renderResultPanel(resultToStore);
            } else {
                removeKey(getStorageKey('result'));
                renderResultPanel(null);
            }

            try {
                if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
                    const cats = extractProposalCategories(parsed.proposal);
                    const catsWithItems = extractProposalCategoriesWithItems(parsed.proposal);
                    const subcategoriesByCategory = extractProposalSubcategoriesMap(parsed.proposal);
                    if (cats.length) {
                        setProposalCategories(cats, catsWithItems, subcategoriesByCategory);
                    }
                    applyProposalWeightGate(parsed.proposal);
                }
            } catch (e) {
            }
        });

        appendChatNotice(buildFinalizePrimaryMessage(parsed));
        announceFinalizeDiagnostics(parsed);
        if (finalizeCompleted) {
            syncBaselineModifiedFromFinalizeResult(parsed);
            applyRevertFlagsFromPayload(parsed);
            if (payloadRevertAvailable(parsed) === null) {
                refreshRevertButtonFromLocalState();
            }
            await serverSaveState({result_json: JSON.stringify(parsed)});
        } else {
            appendChatNotice('Finalize is blocked. Fix the validation issues above (or remove the conflicting rule/formula), then click Generate mapping and Finalize again.');
            await serverSaveState({result_json: JSON.stringify(null)});
        }
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

    // Baseline + finalized apply only: warn that delete destroys backup + changes.
    if (shouldShowBaselineDeleteWarning()) {
        const secondaryTitle = await Str.get_string('gradebook_delete_session', 'block_ai_assistant');
        const secondaryMsg = await Str.get_string('gradebook_delete_baseline_warning', 'block_ai_assistant');
        const secondaryYes = await Str.get_string('gradebook_delete_baseline_confirm', 'block_ai_assistant');
        const secondaryConfirmed = await moodleConfirm({
            title: secondaryTitle,
            message: secondaryMsg,
            yesLabel: secondaryYes,
            noLabel: noLabel
        });
        if (!secondaryConfirmed) {
            return;
        }
    }

    await withRequestLock(async () => {
        await deleteSessionAndRestart();
    });
};

const revertSessionHandler = async () => {
    const title = await Str.get_string('gradebook_revert_session', 'block_ai_assistant');
    const confirmMsg = await Str.get_string('gradebook_revert_session_confirm', 'block_ai_assistant');
    const yesLabel = await Str.get_string('gradebook_revert_session', 'block_ai_assistant');
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
        await revertSessionAndRestart();
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

                if (parsed.phase) {
                    setPhase(parsed.phase);
                }

                const replyText = String(parsed.reply || '').trim();
                const autoAdvanced = parsed.auto_advanced === true;
                const uploadQuickReplies = (() => {
                    const direct = Array.isArray(parsed.quick_replies) ? parsed.quick_replies : [];
                    if (direct.length > 0) {
                        return direct;
                    }
                    return inferAnalysisQuickRepliesFromText(replyText, parsed.phase || '');
                })();

                // Auto-advance already embeds "Syllabus found" + next ask; skip duplicate analyzing line.
                if (!autoAdvanced || !replyText) {
                    appendSystemMessage(`✓ Uploaded "${file.name}". I'm analyzing the grading structure...`);
                }
                if (replyText) {
                    if (uploadQuickReplies.length > 0) {
                        appendSystemMessageWithQuickReplies(replyText, uploadQuickReplies);
                    } else {
                        appendSystemMessage(replyText);
                    }
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

    if (cachedResult && isFinalizeCompletedPhase(cachedPhase)) {
        rehydrateBaselineDeleteWarningState();
    } else if (cachedResult && isSuccessfulFinalizeResult(cachedResult)) {
        rehydrateBaselineDeleteWarningState();
    } else {
        renderResultPanel(null);
    }

    setUiBusy(true);
    ensureSession()
        .then(() => rehydrateBaselineDeleteWarningState())
        .catch((error) => {
            notification.exception(error);
        })
        .finally(() => {
            if (!requestInFlight) {
                setUiBusy(false);
            }
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
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigatePromptHistory('up');
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigatePromptHistory('down');
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
    attachListener('btn-gradebook-revert', 'click', guardedAction(revertSessionHandler));
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

    refreshRevertButtonFromLocalState();

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
    Str.get_string('gradebook_subcategory_none', 'block_ai_assistant').then((label) => {
        if (label) {
            noSubcategoryLabel = label;
            syncMappingUIFromJson();
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_subcategory_select_title', 'block_ai_assistant').then((label) => {
        if (label) {
            subcategorySelectTitle = label;
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
    Str.get_string('gradebook_mapping_empty_category', 'block_ai_assistant').then((label) => {
        if (label) {
            emptyCategoryErrorLabel = label;
            syncMappingUIFromJson();
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_add_manual_item', 'block_ai_assistant').then((label) => {
        if (label) {
            addManualItemLabel = label;
            syncMappingUIFromJson();
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_manual_item_label', 'block_ai_assistant').then((label) => {
        if (label) {
            manualItemLabel = label;
            syncMappingUIFromJson();
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_add_manual_item_prompt', 'block_ai_assistant').then((label) => {
        if (label) {
            addManualItemPromptLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_add_manual_item_default_name', 'block_ai_assistant').then((label) => {
        if (label) {
            addManualItemDefaultNameLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_add_manual_item_failed', 'block_ai_assistant').then((label) => {
        if (label) {
            addManualItemFailedLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_add_manual_item_created', 'block_ai_assistant').then((label) => {
        if (label) {
            addManualItemCreatedLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_missing_items_assessment', 'block_ai_assistant').then((label) => {
        if (label) {
            missingItemsAssessmentLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_missing_items_action', 'block_ai_assistant').then((label) => {
        if (label) {
            missingItemsActionLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_missing_items_activity', 'block_ai_assistant').then((label) => {
        if (label) {
            missingItemsActivityLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_missing_items_grade_item', 'block_ai_assistant').then((label) => {
        if (label) {
            missingItemsGradeItemLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_missing_items_skip', 'block_ai_assistant').then((label) => {
        if (label) {
            missingItemsSkipLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_missing_items_submit', 'block_ai_assistant').then((label) => {
        if (label) {
            missingItemsSubmitLabel = label;
        }
    }).catch(() => {
    });
    Str.get_string('gradebook_missing_items_partial_failed', 'block_ai_assistant').then((label) => {
        if (label) {
            missingItemsPartialFailedLabel = label;
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
