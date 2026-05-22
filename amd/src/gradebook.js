import ajax from 'core/ajax';
import notification from 'core/notification';
import * as Str from 'core/str';

const STORAGE_PREFIX = 'block_ai_assistant_gradebook';
const NOT_GRADED = '__not_graded__';
const DEFAULT_CATEGORIES = ['Assignments', 'Quizzes', 'Labs', 'Exams', 'Projects', 'Participation'];
const MAX_LOCAL_CHAT_MESSAGES = 300;
let sessionInitPromise = null;
let requestInFlight = false;
let proposalCategories = [];
let proposalPanelSyncPromise = null;
let queuedSystemMessages = [];
let latestProposalWeightCheck = {known: false, total: null, valid: true};
let lastKnownStateTimemodified = 0;
let pendingServerSave = null;
let saveInFlight = Promise.resolve();
let suspendAutoSave = false;
let chatStorageTrimWarned = false;
let notGradedLabel = '— Not graded —';
let selectCategoryLabel = 'Select a category…';
let missingCategoryErrorLabel = 'Please pick a category for every row (or set it to Not graded).';

let currentCourseId = 0;
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

const convertMarkdownToHtml = (text) => {
    if (!text) {
        return '';
    }
    
    // Helper to escape HTML special characters
    const escapeHtml = (str) => {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>"']/g, (char) => map[char]);
    };
    
    // Convert Markdown links [text](url) to HTML <a> tags
    // and preserve newlines as <br>
    let html = escapeHtml(text);
    
    // Convert Markdown links: [text](url) -> <a href="url" target="_blank" rel="noopener">text</a>
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, text, url) => {
        const escapedUrl = escapeHtml(url);
        const escapedText = escapeHtml(text);
        return `<a href="${escapedUrl}" target="_blank" rel="noopener">${escapedText}</a>`;
    });
    
    // Convert newlines to <br> tags
    html = html.replace(/\n/g, '<br>');
    
    return html;
};

const appendMessage = (container, text, isHuman, skipPersist) => {
    if (!container) {
        return;
    }

    const div = document.createElement('div');
    div.className = `chat-message ${isHuman ? 'human-message' : 'bot-message'}`;

    const content = document.createElement('div');
    content.className = 'message-content';
    content.innerHTML = convertMarkdownToHtml(text || '');

    div.appendChild(content);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;

    if (!skipPersist) {
        const history = loadJson(getStorageKey('chat_history'), []);
        history.push({role: isHuman ? 'human' : 'bot', text: text || ''});
        persistChatHistoryLocal(history);
        scheduleServerSave();
    }
};

const appendSystemMessage = (text) => {
    appendMessage(getChatMessages(), text, false, false);
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
    if (proposalPanelSyncPromise) {
        queuedSystemMessages.push(String(text || ''));
        return;
    }
    appendSystemMessage(text);
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
        appendMessage(chatMessages, String(item.text || ''), item.role === 'human', true);
    });
    return true;
};

const normalizeChatHistoryEntries = (raw) => {
    if (!Array.isArray(raw)) {
        return [];
    }
    return raw
        .filter((item) => item && typeof item === 'object')
        .map((item) => ({
            role: item.role === 'human' ? 'human' : (item.role === 'bot' ? 'bot' : ''),
            text: String(item.text || ''),
        }))
        .filter((item) => item.role && item.text !== '');
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

    saveJson(getStorageKey('chat_history_backend'), normalizedBackend);

    const normalizedLocal = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    const shouldReplaceLocal = normalizedLocal.length < 1
        || normalizedBackend.length > normalizedLocal.length
        || !chatHistoryTailMatches(normalizedLocal, normalizedBackend);

    if (!shouldReplaceLocal) {
        return false;
    }

    persistChatHistoryLocal(normalizedBackend);
    if (renderIfFresh) {
        renderHistory(normalizedBackend);
    }
    return true;
};

const restoreChatHistory = () => {
    const localHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history'), []));
    const backendHistory = normalizeChatHistoryEntries(loadJson(getStorageKey('chat_history_backend'), []));

    if (backendHistory.length > localHistory.length || !chatHistoryTailMatches(localHistory, backendHistory)) {
        if (backendHistory.length > 0) {
            persistChatHistoryLocal(backendHistory);
            return renderHistory(backendHistory);
        }
    }

    return renderHistory(localHistory);
};

const isNotGraded = (category) => String(category || '').trim() === NOT_GRADED;

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
            return {
                ...item,
                category: normalizeMappedCategory(item.category)
            };
        });
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

const clearBusyWaitIndicator = () => {
    const existing = document.getElementById('gradebook-busy-wait-indicator');
    if (existing) {
        existing.remove();
    }
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
    const waitDiv = document.createElement('div');
    waitDiv.className = 'chat-message bot-message';
    waitDiv.id = 'gradebook-busy-wait-indicator';
    waitDiv.innerHTML = `
        <div class="message-content">
            <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
            <span>${loadingText}</span>
        </div>`;
    chat.appendChild(waitDiv);
    chat.scrollTop = chat.scrollHeight;
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
        const normalizedCategory = normalizeMappedCategory(confirmed || suggested);
        const moodleCmid = item.moodle_cmid != null
            ? item.moodle_cmid
            : (item.cmid != null ? item.cmid : null);
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
            category: normalizedCategory,
            suggested_category: suggested
        };
    }).filter((item) => item.grade_item_id != null);
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
    const phaseUpper = String(statusPayload.phase || parsed.phase || parsed.state || '-').toUpperCase();

    ingestBackendChatHistory(statusPayload.chat_history || parsed.chat_history || [], true);

    const statusProposal = statusPayload.proposal || null;
    const cats = extractProposalCategories(statusProposal);
    if (cats.length > 0) {
        setProposalCategories(cats);
    }
    if (statusProposal && Array.isArray(statusProposal.categories)) {
        applyProposalWeightGate(statusProposal);
    }

    if (!isFinalizeCompletedPhase(phaseUpper)) {
        removeKey(getStorageKey('result'));
        renderResultPanel(null);
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
    const preferredHistory = (backendHistory.length > localHistory.length || !chatHistoryTailMatches(localHistory, backendHistory))
        ? backendHistory
        : localHistory;

    return {
        session_id: (sid && sid !== 'starting…') ? sid : null,
        phase: (phaseText && phaseText !== '—') ? phaseText : null,
        chat_history_json: JSON.stringify(preferredHistory),
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
            persistChatHistoryLocal(history);
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
        const result = state.result_json ? JSON.parse(state.result_json) : null;
        if (result && isFinalizeCompletedPhase(restoredPhase)) {
            saveJson(getStorageKey('result'), result);
            renderResultPanel(result);
            hydrated = true;
        } else if (result) {
            removeKey(getStorageKey('result'));
            renderResultPanel(null);
            scheduleServerSave();
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
    removeKey(getStorageKey('chat_history_backend'));
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

    await withRequestLock(async () => {
        appendMessage(getChatMessages(), prepared.displayText, true, false);
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
            const previousPhase = String(loadJson(getStorageKey('phase'), '') || '').toUpperCase();
            const parsed = await callWithSessionRetry(async (sid) => {
                const raw = await callWs('block_ai_assistant_gradebook_chat', {
                    courseid: getCourseId(),
                    session_id: sid,
                    prompt: normalized
                });
                return parseResponse(raw);
            });
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
                    const mappingNode = el('gradebook-confirmed-mapping');
                    if (mappingNode) {
                        mappingNode.value = '';
                    }
                    syncMappingUIFromJson();
                    removeKey(getStorageKey('result'));
                    renderResultPanel(null);
                    appendSystemMessageSafely('Edit mode enabled. Your previous finalization remains as baseline; regenerate mapping and click Finalize again to apply your updated override.');
                }

                if (movedBackToProposalFlow) {
                    removeKey(getStorageKey('result'));
                    renderResultPanel(null);
                }

                appendSystemMessageSafely(parsed.reply || parsed.message || 'Updated.');
                if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
                    const cats = extractProposalCategories(parsed.proposal);
                    if (cats.length) {
                        setProposalCategories(cats);
                    }

                    const shouldRenderProposalSummary = movedBackToProposalFlow && (proposalChanged || enteredEditFromFinalized);
                    if (shouldRenderProposalSummary) {
                        const lines = summarizeProposalCategories(parsed.proposal);
                        appendSystemMessageSafely(`Updated proposal: ${lines}`);

                        const effects = extractProposalEffects(parsed.proposal);
                        if (effects.length) {
                            appendSystemMessageSafely(`Effects (newest first): ${effects.slice(0, 6).join(' | ')}`);
                        }
                    }

                    applyProposalWeightGate(parsed.proposal);
                }
            });
            await serverSaveState(shouldForcePostChatSave ? {force: true} : undefined);
        } finally {
            const indicator = document.getElementById('gradebook-loading-indicator');
            if (indicator) {
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
        appendSystemMessageSafely('Proposal loaded.');
    }

    if (proposal && Array.isArray(proposal.categories)) {
        const cats = extractProposalCategories(proposal);
        if (cats.length) {
            setProposalCategories(cats);
        }

        const lines = summarizeProposalCategories(proposal);
        appendSystemMessageSafely(`Current proposal: ${lines}`);

        const effects = extractProposalEffects(proposal);
        if (effects.length) {
            const recent = effects.slice(0, 6).join(' | ');
            appendSystemMessageSafely(`Effects (newest first): ${recent}`);
        }

        const checks = extractProposalChecks(proposal);
        if (checks.length) {
            const checkText = checks.slice(-3).join(' | ');
            appendSystemMessageSafely(`Checks: ${checkText}`);
        }

        applyProposalWeightGate(proposal);
    } else {
        appendSystemMessageSafely('No proposal yet. Send a prompt to generate one.');
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

        const gradedRows = confirmed
            .filter((item) => !isNotGraded(item.category))
            .map((item) => ({
                grade_item_id: item.grade_item_id,
                category: item.category,
                activity_name: item.activity_name || '',
                moodle_cmid: item.moodle_cmid != null ? item.moodle_cmid : null,
            }))
            .filter((item) => item.grade_item_id != null);
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

        const finalizePhase = String(parsed.phase || parsed.state || '').toUpperCase();
        const finalizeCompleted = ['COMPLETED', 'FINALIZED'].includes(finalizePhase);

        await runWithinProposalPanelSync(async () => {
            setPhase(parsed.phase || parsed.state || 'COMPLETED');
            if (finalizeCompleted) {
                saveJson(getStorageKey('result'), parsed);
                renderResultPanel(parsed);
            } else {
                removeKey(getStorageKey('result'));
                renderResultPanel(null);
            }

            try {
                await loadProposalPanel(false);
            } catch (e) {
                if (parsed.proposal && Array.isArray(parsed.proposal.categories)) {
                    const cats = extractProposalCategories(parsed.proposal);
                    if (cats.length) {
                        setProposalCategories(cats);
                    }
                    applyProposalWeightGate(parsed.proposal);
                }
            }
        });

        appendSystemMessageSafely(parsed.message || 'Gradebook finalized.');
        if (finalizeCompleted) {
            appendSystemMessageSafely('You can continue editing. If you change anything, regenerate mapping and finalize again to override the current setup.');
            await serverSaveState({result_json: JSON.stringify(parsed)});
        } else {
            appendSystemMessageSafely('Finalize is blocked. Fix the validation issues above (or remove the conflicting rule/formula), then click Generate mapping and Finalize again.');
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

    if (cachedResult && isFinalizeCompletedPhase(cachedPhase)) {
        renderResultPanel(cachedResult);
    } else {
        renderResultPanel(null);
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
