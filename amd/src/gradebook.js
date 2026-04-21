import ajax from 'core/ajax';
import notification from 'core/notification';
import * as Str from 'core/str';

const STORAGE_PREFIX = 'block_ai_assistant_gradebook';
// Sentinel value for "not graded / exclude from gradebook". Stored in the row's `category`
// field; rows carrying this value are filtered out before `finalize` is sent to Criabot.
const NOT_GRADED = '__not_graded__';
// Fallback list of categories if we have not yet accepted a proposal.
const DEFAULT_CATEGORIES = ['Assignments', 'Quizzes', 'Labs', 'Exams', 'Projects', 'Participation'];
let sessionInitPromise = null;
let requestInFlight = false;
// Category names from the accepted proposal, used to populate the mapping select dropdowns.
let proposalCategories = [];
// In-memory cache of the latest server-side state timestamp we've seen, so we never overwrite newer data.
let lastKnownStateTimemodified = 0;
// Debounced server-save state.
let pendingServerSave = null;
// Localized labels (populated from lang strings once available; literal fallbacks used until then).
let notGradedLabel = '— Not graded —';
let selectCategoryLabel = 'Select a category…';
let missingCategoryErrorLabel = 'Please pick a category for every row (or set it to Not graded).';

const el = (id) => document.getElementById(id);

const getChatMessages = () => el('gradebook-chat-messages');

const getCourseId = () => String(el('block-ai-assistant-gradebook-courseid').value || '').trim();

const getStorageKey = (kind) => `${STORAGE_PREFIX}_${kind}_${getCourseId()}`;

const loadJson = (key, fallback) => {
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
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
        // Ignore quota/storage exceptions.
    }
};

const removeKey = (key) => {
    try {
        localStorage.removeItem(key);
    } catch (e) {
        // Ignore.
    }
};

const setText = (id, value) => {
    const node = el(id);
    if (node) {
        node.textContent = value;
    }
};

const setSessionId = (sessionId) => {
    el('block-ai-assistant-gradebook-sessionid').value = sessionId || '';
    setText('gradebook-session-badge', sessionId || '-');
    if (sessionId) {
        saveJson(getStorageKey('session_id'), sessionId);
    } else {
        removeKey(getStorageKey('session_id'));
    }
};

const getSessionId = () => String(el('block-ai-assistant-gradebook-sessionid').value || '').trim();

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
        // Some Moodle/PHP paths can prepend warnings/notices before JSON.
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
                // Fall through to explicit error below.
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

// Heuristic: does a parsed Criabot response indicate the backend lost/expired our session?
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

// A row has a real category if it has any non-empty string in `category` (Not-graded counts).
const rowHasCategory = (item) => item && String(item.category || '').trim().length > 0;

const getConfirmedMapping = () => {
    try {
        return JSON.parse(el('gradebook-confirmed-mapping').value || '[]');
    } catch (e) {
        return [];
    }
};

// Find row indexes that are missing a category (used for the click-time validation + red borders).
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

const clearMissingHighlights = () => {
    document.querySelectorAll('#gradebook-mapping-rows tr.gradebook-row-missing').forEach((tr) => {
        tr.classList.remove('gradebook-row-missing');
    });
};

const setFinalizeEnabled = (enabled) => {
    // The finalize + generate buttons are kept clickable whenever the UI isn't busy;
    // we validate on click instead of silently disabling, so the user always knows why.
    ['btn-gradebook-finalize', 'btn-gradebook-generate'].forEach((id) => {
        const btn = el(id);
        if (btn) {
            btn.disabled = !enabled;
        }
    });
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

    // After a request finishes, re-enable finalize/generate unconditionally — validation happens on click.
    if (!isBusy) {
        setFinalizeEnabled(true);
    }
};

// Build the list of options to show in the category dropdown.
// Always includes the "Not graded" sentinel + either proposalCategories or DEFAULT_CATEGORIES.
const buildCategoryOptions = (currentCat) => {
    const base = proposalCategories.length > 0 ? proposalCategories.slice() : DEFAULT_CATEGORIES.slice();
    // If the current value is a custom one (neither in the base list nor the sentinel), include it.
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
    // Finalize/Generate remain clickable; we validate only on click.
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

        // Placeholder option — always present, selected when category is empty.
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

        // Separator + Not graded.
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
            // Once the user picks anything, clear the "missing" red-border state on this row.
            tr.classList.remove('gradebook-row-missing');
            if (val) {
                sel.classList.remove('gradebook-select-empty');
            } else {
                sel.classList.add('gradebook-select-empty');
            }
            persistMappingInputs(confirmed);
        });

        category.appendChild(sel);
        tr.appendChild(category);

        rows.appendChild(tr);
    });

    updateMappingSummary(confirmed);
    // Finalize/Generate stay clickable; validation runs on click.
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
        // Prefer a category the backend explicitly confirmed, then suggested, then a generic one.
        // If still missing, leave it blank — the user will pick from the dropdown.
        const provided = item.confirmed_category || item.suggested_category || item.category || '';
        const category = provided && String(provided).trim().length > 0 ? provided : '';
        return {
            moodle_cmid: item.moodle_cmid != null ? item.moodle_cmid : (item.cmid != null ? item.cmid : item.id),
            activity_name: name,
            module: module,
            category: category
        };
    }).filter((item) => item.moodle_cmid != null);
};

const normalizePrompt = (prompt) => {
    let p = String(prompt || '').trim();
    p = p.replace(/\blap\b/gi, 'Labs').replace(/\badjuest\b/gi, 'adjust');

    const lower = p.toLowerCase();
    if ((lower.indexOf('change') !== -1 || lower.indexOf('adjust') !== -1 || lower.indexOf('set') !== -1) &&
        lower.indexOf('labs') !== -1 && lower.indexOf('final') !== -1) {
        const labsMatch = p.match(/labs?\s*(?:to|=)?\s*(\d+(?:\.\d+)?)/i);
        const finalMatch = p.match(/final(?:\s*exam)?\s*(?:to|=)?\s*(\d+(?:\.\d+)?)/i);
        if (labsMatch && finalMatch) {
            return `Update existing categories only: set Labs to ${labsMatch[1]}%, set Final Exam to ${finalMatch[1]}%. Keep all other categories unchanged and keep total exactly 100%.`;
        }
    }

    if (lower.indexOf('split assignments into') !== -1) {
        p += ' Treat Homework/Projects as subcategories inside Assignments and do not add extra top-level categories.';
    }

    return p;
};

const hydrateFromStatusPayload = (parsed) => {
    const statusPayload = parsed && parsed.session ? parsed.session : null;
    if (!statusPayload || typeof statusPayload !== 'object') {
        return;
    }

    setPhase(statusPayload.phase || parsed.phase || parsed.state || '-');

    const mapping = extractContentMapping(statusPayload);
    const confirmed = mappingToConfirmedRows(mapping);
    if (confirmed.length > 0) {
        el('gradebook-confirmed-mapping').value = JSON.stringify(confirmed, null, 2);
        syncMappingUIFromJson();
    }
};

// --- Server-side persistence (authoritative across refresh / browsers) ---

const collectStateSnapshot = () => {
    return {
        session_id: getSessionId() || null,
        phase: el('gradebook-phase-badge') ? el('gradebook-phase-badge').textContent.trim() : null,
        chat_history_json: JSON.stringify(loadJson(getStorageKey('chat_history'), [])),
        confirmed_mapping_json: el('gradebook-confirmed-mapping')
            ? String(el('gradebook-confirmed-mapping').value || '')
            : null,
        result_json: JSON.stringify(loadJson(getStorageKey('result'), null))
    };
};

const serverSaveState = async (overrides = {}) => {
    const snap = Object.assign({}, collectStateSnapshot(), overrides);
    try {
        const payload = {
            courseid: Number(getCourseId()),
            session_id: snap.session_id,
            phase: snap.phase,
            chat_history_json: snap.chat_history_json,
            confirmed_mapping_json: snap.confirmed_mapping_json,
            result_json: snap.result_json
        };
        const response = await callWs('block_ai_assistant_gradebook_save_state', payload);
        if (response && response.timemodified) {
            lastKnownStateTimemodified = Number(response.timemodified) || lastKnownStateTimemodified;
        }
    } catch (e) {
        // Best-effort only; a save failure should never block the UI.
    }
};

const scheduleServerSave = () => {
    if (pendingServerSave) {
        clearTimeout(pendingServerSave);
    }
    pendingServerSave = setTimeout(() => {
        pendingServerSave = null;
        serverSaveState();
    }, 400);
};

const serverClearState = async () => {
    try {
        await callWs('block_ai_assistant_gradebook_save_state', {
            courseid: Number(getCourseId()),
            clear: true
        });
    } catch (e) {
        // Ignore.
    }
    lastKnownStateTimemodified = 0;
};

const serverGetState = async () => {
    try {
        const response = await callWs('block_ai_assistant_gradebook_get_state', {
            courseid: Number(getCourseId())
        });
        if (response && response.timemodified) {
            lastKnownStateTimemodified = Number(response.timemodified) || 0;
        }
        return response && response.found ? response : null;
    } catch (e) {
        return null;
    }
};

// Hydrate UI (chat history, mapping, result panel) from a persisted server-side state record.
const hydrateFromServerState = (state) => {
    if (!state) {
        return false;
    }

    let hydrated = false;

    // Chat history.
    try {
        const history = JSON.parse(state.chat_history_json || '[]');
        if (Array.isArray(history) && history.length > 0) {
            saveJson(getStorageKey('chat_history'), history);
            renderHistory(history);
            hydrated = true;
        }
    } catch (e) {
        // Ignore malformed history.
    }

    // Session id + phase.
    if (state.session_id) {
        setSessionId(state.session_id);
        hydrated = true;
    }
    if (state.phase) {
        setPhase(state.phase);
    }

    // Mapping JSON.
    if (state.confirmed_mapping_json && el('gradebook-confirmed-mapping')) {
        el('gradebook-confirmed-mapping').value = state.confirmed_mapping_json;
        syncMappingUIFromJson();
    }

    // Finalize result.
    try {
        const result = state.result_json ? JSON.parse(state.result_json) : null;
        if (result) {
            saveJson(getStorageKey('result'), result);
            renderResultPanel(result);
            hydrated = true;
        }
    } catch (e) {
        // Ignore malformed result.
    }

    return hydrated;
};

// --- Finalize result panel ---

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

const renderResultPanel = (result) => {
    const panel = el('gradebook-result-panel');
    const summaryEl = el('gradebook-result-summary');
    if (!panel || !summaryEl) {
        return;
    }
    if (!result) {
        panel.classList.add('d-none');
        summaryEl.textContent = '';
        return;
    }
    summaryEl.textContent = buildSummaryText(result);
    panel.classList.remove('d-none');
};

const triggerJsonDownload = (filename, data) => {
    try {
        const payload = JSON.stringify(data, null, 2);
        const blob = new Blob([payload], {type: 'application/json'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        notification.exception(e);
    }
};

const downloadFinalizeResult = () => {
    const result = loadJson(getStorageKey('result'), null);
    if (!result) {
        return;
    }
    const courseid = getCourseId();
    const sessionId = result.session_id || getSessionId() || 'session';
    triggerJsonDownload(`gradebook-${courseid}-${sessionId}.json`, result);
};

const copyFinalizeSummary = async () => {
    const result = loadJson(getStorageKey('result'), null);
    const text = buildSummaryText(result);
    if (!text) {
        return;
    }
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        const notice = await Str.get_string('gradebook_copied', 'block_ai_assistant');
        appendSystemMessage(notice);
    } catch (e) {
        notification.exception(e);
    }
};

// Minimal HTML-escape for embedding into the Word/PDF export documents.
const escapeHtml = (value) => String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

// Build a printable HTML document from the finalize result. Shared by Word + PDF.
const buildPrintableHtml = (result) => {
    if (!result || typeof result !== 'object') {
        return '';
    }
    const summary = result.summary || result.details || {};
    const mapping = extractContentMapping(result) || {};
    const categories = mapping.categories || (result.proposal && result.proposal.categories) || [];
    const activities = mapping.graded_activities || mapping.activities || mapping.mapped_activities || [];

    const metaRows = [];
    if (summary.course_name) {
        metaRows.push(`<tr><th>Course</th><td>${escapeHtml(summary.course_name)}</td></tr>`);
    }
    if (summary.total_items != null) {
        metaRows.push(`<tr><th>Total items</th><td>${escapeHtml(summary.total_items)}</td></tr>`);
    }
    if (summary.created_categories != null) {
        metaRows.push(`<tr><th>Created categories</th><td>${escapeHtml(summary.created_categories)}</td></tr>`);
    }
    if (summary.total_weight != null) {
        metaRows.push(`<tr><th>Total weight</th><td>${escapeHtml(summary.total_weight)}%</td></tr>`);
    }
    const generatedAt = new Date().toLocaleString();
    metaRows.push(`<tr><th>Generated</th><td>${escapeHtml(generatedAt)}</td></tr>`);

    const categoriesHtml = Array.isArray(categories) && categories.length
        ? `<h2>Grade categories</h2>
           <table>
             <thead><tr><th>Category</th><th style="width:100px">Weight</th></tr></thead>
             <tbody>
               ${categories.map((c) => `<tr>
                 <td>${escapeHtml(c.name || '')}</td>
                 <td>${c.weight != null ? escapeHtml(c.weight) + '%' : ''}</td>
               </tr>`).join('')}
             </tbody>
           </table>`
        : '';

    const activitiesHtml = Array.isArray(activities) && activities.length
        ? `<h2>Activity mapping</h2>
           <table>
             <thead><tr><th>Activity</th><th>Category</th><th>CMID</th></tr></thead>
             <tbody>
               ${activities.map((a) => `<tr>
                 <td>${escapeHtml(a.activity_name || a.name || '')}</td>
                 <td>${escapeHtml(a.confirmed_category || a.suggested_category || a.category || '')}</td>
                 <td>${escapeHtml(a.moodle_cmid != null ? a.moodle_cmid : (a.cmid != null ? a.cmid : ''))}</td>
               </tr>`).join('')}
             </tbody>
           </table>`
        : '';

    return `<!DOCTYPE html>
<html><head>
<meta charset="utf-8">
<title>Gradebook export</title>
<style>
  body { font-family: Calibri, Arial, sans-serif; color: #222; margin: 24px; }
  h1 { font-size: 22px; margin: 0 0 8px 0; }
  h2 { font-size: 16px; margin: 24px 0 8px 0; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 12px; font-size: 12pt; }
  th, td { border: 1px solid #999; padding: 6px 10px; text-align: left; vertical-align: top; }
  th { background: #f2f2f2; }
  .meta th { width: 160px; }
  .muted { color: #666; font-size: 10pt; }
</style>
</head><body>
<h1>Gradebook — ${escapeHtml(summary.course_name || 'Course ' + getCourseId())}</h1>
<p class="muted">Generated by Cria AI Assistant.</p>
<table class="meta"><tbody>${metaRows.join('')}</tbody></table>
${categoriesHtml}
${activitiesHtml}
</body></html>`;
};

const downloadBlob = (filename, content, mime) => {
    try {
        const blob = new Blob([content], {type: mime});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        notification.exception(e);
    }
};

const downloadFinalizeResultAsWord = () => {
    const result = loadJson(getStorageKey('result'), null);
    if (!result) {
        return;
    }
    const html = buildPrintableHtml(result);
    if (!html) {
        return;
    }
    const courseid = getCourseId();
    const sessionId = result.session_id || getSessionId() || 'session';
    // .doc extension + application/msword MIME is opened natively by MS Word and LibreOffice.
    downloadBlob(`gradebook-${courseid}-${sessionId}.doc`, html, 'application/msword');
};

const downloadFinalizeResultAsPdf = () => {
    const result = loadJson(getStorageKey('result'), null);
    if (!result) {
        return;
    }
    const html = buildPrintableHtml(result);
    if (!html) {
        return;
    }
    // Open a new window and trigger the native print dialog — the user picks "Save as PDF".
    const win = window.open('', '_blank');
    if (!win) {
        // Popup blocked — fall back to downloading an HTML file.
        const courseid = getCourseId();
        const sessionId = result.session_id || getSessionId() || 'session';
        downloadBlob(`gradebook-${courseid}-${sessionId}.html`, html, 'text/html');
        return;
    }
    win.document.open();
    win.document.write(html);
    win.document.close();
    const fire = () => {
        try {
            win.focus();
            win.print();
        } catch (e) {
            // Ignore — the document is still open for the user to print manually.
        }
    };
    // Give the browser a tick to lay out the styles before invoking print.
    if (win.document.readyState === 'complete') {
        setTimeout(fire, 150);
    } else {
        win.addEventListener('load', () => setTimeout(fire, 150));
    }
};

// --- Session lifecycle ---

const doStartSession = async () => {
    const raw = await callWs('block_ai_assistant_gradebook_start', {courseid: getCourseId()});
    const parsed = parseResponse(raw);
    const sessionId = parsed.session_id || parsed.sessionId || parsed.session || '';
    setSessionId(sessionId);
    setPhase(parsed.phase || parsed.state || '-');

    const initial = parsed.initial_message || parsed.message || 'Gradebook session started.';
    appendSystemMessage(initial);

    // Persist the fresh session id + phase immediately (do not wait for debounce).
    serverSaveState();

    return sessionId;
};

// Full reset: clear local + server state, start a fresh session.
const resetSession = async (systemMessageKey) => {
    removeKey(getStorageKey('session_id'));
    removeKey(getStorageKey('chat_history'));
    removeKey(getStorageKey('result'));
    setSessionId('');
    if (el('gradebook-confirmed-mapping')) {
        el('gradebook-confirmed-mapping').value = '';
    }
    clearChatUI();
    renderResultPanel(null);
    syncMappingUIFromJson();
    await serverClearState();

    if (systemMessageKey) {
        try {
            const msg = await Str.get_string(systemMessageKey, 'block_ai_assistant');
            appendSystemMessage(msg);
        } catch (e) {
            // Ignore missing string.
        }
    }

    return doStartSession();
};

// Soft-restart: keep chat history, mapping, and result visible, but mint a brand new backend
// session because the Criabot session has expired/been dropped. Used when the backend says
// "session not found" but the user's local work is still valid.
const softRestartSession = async (systemMessageKey) => {
    setSessionId('');
    if (systemMessageKey) {
        try {
            const msg = await Str.get_string(systemMessageKey, 'block_ai_assistant');
            appendSystemMessage(msg);
        } catch (e) {
            // Ignore.
        }
    }
    return doStartSession();
};

// Restore from server-side state if available, else localStorage, else start fresh.
// If the Criabot status call shows the session is gone, keep the chat history visible and
// transparently start a new backend session (soft restart).
const restoreOrStartSession = async () => {
    const existing = getSessionId();
    if (existing) {
        return existing;
    }

    // 1) Authoritative source: server-side state.
    const serverState = await serverGetState();
    if (serverState) {
        hydrateFromServerState(serverState);
    }

    const candidateSession = (serverState && serverState.session_id) ||
        String(loadJson(getStorageKey('session_id'), '') || '').trim();

    if (candidateSession) {
        try {
            const raw = await callWs('block_ai_assistant_gradebook_status', {
                courseid: getCourseId(),
                session_id: candidateSession
            });
            const parsed = parseResponse(raw);
            if (isSessionNotFound(parsed)) {
                // Backend session is gone, but keep whatever UI state the user had visible.
                return softRestartSession('gradebook_session_expired');
            }
            setSessionId(candidateSession);
            hydrateFromStatusPayload(parsed);
            if (!serverState) {
                // Promote the localStorage-only session to the server store.
                serverSaveState();
            }
            return candidateSession;
        } catch (e) {
            // Transient failure — keep the locally-restored state visible; the user can retry.
            setSessionId(candidateSession);
            return candidateSession;
        }
    }

    return doStartSession();
};

const ensureSession = async () => {
    if (getSessionId()) {
        return getSessionId();
    }
    if (!sessionInitPromise) {
        sessionInitPromise = restoreOrStartSession().finally(() => {
            sessionInitPromise = null;
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

// Wrap a call that expects an active session: if the backend says the session is gone,
// keep the chat history visible (soft restart) and retry once with the fresh session id.
const callWithSessionRetry = async (fn) => {
    try {
        const parsed = await fn(getSessionId());
        if (isSessionNotFound(parsed)) {
            const newId = await softRestartSession('gradebook_session_expired');
            return fn(newId);
        }
        return parsed;
    } catch (e) {
        throw e;
    }
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
            // Fallback to literal if the string is not available.
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
            // Persist immediately so history is never lost to a sudden refresh.
            await serverSaveState();
        } finally {
            const indicator = document.getElementById('gradebook-loading-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
    });
};

const extractProposalCategories = (proposal) => {
    if (proposal && Array.isArray(proposal.categories)) {
        return proposal.categories.map((c) => c.name).filter(Boolean);
    }
    return [];
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
                proposalCategories = cats;
            }
            const lines = proposal.categories.map((c) => `${c.name}: ${c.weight}%`).join(' | ');
            appendSystemMessage(`Current proposal: ${lines}`);
        } else {
            appendSystemMessage('No proposal yet. Send a prompt to generate one.');
        }
        await serverSaveState();
    });
};

const acceptProposal = async () => {
    await withRequestLock(async () => {
        await ensureSession();

        const parsed = await callWithSessionRetry(async (sid) => {
            const raw = await callWs('block_ai_assistant_gradebook_accept', {
                courseid: getCourseId(),
                session_id: sid
            });
            return parseResponse(raw);
        });

        setPhase(parsed.phase || parsed.state || '-');

        const cats = extractProposalCategories(parsed.proposal || null);
        if (cats.length) {
            proposalCategories = cats;
        }

        const confirmed = mappingToConfirmedRows(extractContentMapping(parsed));
        if (confirmed.length > 0) {
            el('gradebook-confirmed-mapping').value = JSON.stringify(confirmed, null, 2);
            syncMappingUIFromJson();
            // Auto-open the mapping drawer now that there is something to review.
            const drawer = el('gradebook-mapping-drawer');
            if (drawer && drawer.classList.contains('is-collapsed')) {
                drawer.classList.remove('is-collapsed');
                const toggle = el('gradebook-mapping-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
            appendSystemMessage(
                `Mapping ready: ${confirmed.length} activities. ` +
                `Pick a category for each row (or "Not graded"), then click "Generate gradebook".`
            );
        } else {
            syncMappingUIFromJson();
            appendSystemMessage('Accepted, but mapping is empty. Check course activities and try Proposal then Generate mapping again.');
        }

        await serverSaveState();
    });
};

// Apply red-border highlighting to the rows at the given indexes and scroll to the first one.
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
    // Make sure the mapping drawer is open so the user can see the red rows.
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

        const confirmed = getConfirmedMapping();

        if (!Array.isArray(confirmed) || confirmed.length < 1) {
            appendSystemMessage('There is nothing to finalize yet. Click "Generate mapping" first.');
            return;
        }

        // 1) Every row must have a category picked (Not graded counts).
        const missing = findMissingCategoryIndexes(confirmed);
        if (missing.length > 0) {
            highlightMissingRows(missing);
            const template = String(missingCategoryErrorLabel || '');
            const msg = template.indexOf('{$a}') !== -1
                ? template.replace('{$a}', String(missing.length))
                : `${template} (${missing.length} row${missing.length === 1 ? '' : 's'} missing)`;
            appendSystemMessage(msg);
            return;
        }

        // 2) At least one row must be actually graded (otherwise there's nothing to grade).
        const gradedRows = confirmed.filter((item) => !isNotGraded(item.category))
            .map((item) => ({moodle_cmid: item.moodle_cmid, category: item.category}));
        if (gradedRows.length < 1) {
            appendSystemMessage('Every row is set to "Not graded" — nothing would be added to the gradebook. ' +
                'Pick a real category for at least one activity.');
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

        // Persist the full Criabot response so the user can download/copy later.
        saveJson(getStorageKey('result'), parsed);
        renderResultPanel(parsed);
        await serverSaveState({result_json: JSON.stringify(parsed)});
    });
};

const resetSessionHandler = async () => {
    const confirmMsg = await Str.get_string('gradebook_reset_session_confirm', 'block_ai_assistant');
    // eslint-disable-next-line no-alert
    if (!window.confirm(confirmMsg)) {
        return;
    }
    await withRequestLock(async () => {
        await resetSession(null);
    });
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

export const init = () => {
    if (!el('block-ai-assistant-gradebook-courseid')) {
        return;
    }

    // Fast, optimistic hydration from localStorage so the UI feels instant.
    restoreChatHistory();
    syncMappingUIFromJson();
    setFinalizeEnabled(true);
    const cachedResult = loadJson(getStorageKey('result'), null);
    if (cachedResult) {
        renderResultPanel(cachedResult);
    }

    // Authoritative hydration + Criabot status check runs via ensureSession().
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

    attachListener('btn-gradebook-proposal', 'click', guardedAction(fetchProposal));
    attachListener('btn-gradebook-accept', 'click', guardedAction(acceptProposal));
    attachListener('btn-gradebook-finalize', 'click', guardedAction(finalizeGradebook));
    attachListener('btn-gradebook-generate', 'click', guardedAction(finalizeGradebook));
    attachListener('btn-gradebook-reset', 'click', guardedAction(resetSessionHandler));
    attachListener('btn-gradebook-download-result', 'click', () => downloadFinalizeResult());
    attachListener('btn-gradebook-copy-summary', 'click', guardedAction(copyFinalizeSummary));
    attachListener('btn-gradebook-download-word', 'click', () => downloadFinalizeResultAsWord());
    attachListener('btn-gradebook-download-pdf', 'click', () => downloadFinalizeResultAsPdf());

    attachListener('gradebook-confirmed-mapping', 'input', () => {
        syncMappingUIFromJson();
        scheduleServerSave();
    });

    // Mapping drawer collapse/expand toggle (also keyboard accessible).
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

    // Auto-expand the mapping drawer the first time a mapping is present.
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

    // Pre-load the localized "Not graded" label so the first render uses it.
    Str.get_string('gradebook_not_graded', 'block_ai_assistant').then((label) => {
        if (label) {
            notGradedLabel = label;
            // Re-render in case the mapping was already painted before the lang string arrived.
            syncMappingUIFromJson();
        }
    }).catch(() => {
        // Keep the literal fallback defined at module scope.
    });
    Str.get_string('gradebook_select_category', 'block_ai_assistant').then((label) => {
        if (label) {
            selectCategoryLabel = label;
            syncMappingUIFromJson();
        }
    }).catch(() => {
        // Keep literal fallback.
    });
    Str.get_string('gradebook_mapping_missing', 'block_ai_assistant').then((label) => {
        if (label) {
            missingCategoryErrorLabel = label;
        }
    }).catch(() => {
        // Keep literal fallback.
    });

    // Save-on-unload as a last-ditch guarantee. We also fire a keep-alive beacon so the request
    // survives even if the Moodle AJAX Promise can't complete during unload.
    window.addEventListener('beforeunload', () => {
        if (pendingServerSave) {
            clearTimeout(pendingServerSave);
            pendingServerSave = null;
        }
        // Fire-and-forget async save (may not complete).
        serverSaveState();
    });
};
