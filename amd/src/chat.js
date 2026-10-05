import {get_string as getString} from 'core/str';
import ajax from 'core/ajax';
import config from 'core/config';
import notification from 'core/notification';

const CHAT_LOADER_STAGES = [
    'Connecting to retrieval engines...',
    'Searching course knowledge base...',
    'Retrieving relevant document chunks...',
    'Synthesizing response draft...',
    'Polishing response quality...',
    'Finalizing answer... almost there.',
];
const CHAT_LOADER_LONG_WAIT_STAGES = [
    'Still working through the retrieved context...',
    'Checking the answer against course material...',
    'Preparing the final response...',
];
const CHAT_LOADER_STEP_MS = 1800;
const CHAT_LOADER_FALLBACK = 'Thinking...';

const normalizeChatResult = (result) => {
    if (typeof result === 'string') return result;
    if (result && typeof result === 'object') {
        if (typeof result.reply?.content?.content === 'string') return result.reply.content.content;
        if (typeof result.message === 'string') return result.message;
        if (typeof result.reply === 'string') return result.reply;
    }
    return String(result ?? '');
};

const normalizeAjaxError = (error) => {
    if (typeof error === 'string') return error;
    if (error && typeof error === 'object') {
        if (typeof error.message === 'string' && error.message) return error.message;
        if (typeof error.error === 'string' && error.error) return error.error;
        if (typeof error.exception === 'string' && error.exception) return error.exception;
    }
    return 'Unable to get a response from AI Assistant.';
};

const startProgressiveLoader = (container, id, initialText) => {
    const loader = document.createElement('div');
    loader.id = id;
    loader.className = 'chat-message bot-message';
    loader.innerHTML = `
        <div class="message-content cria-loader">
            <div class="cria-loader-header"><span class="cria-loader-ring" aria-hidden="true"></span><span class="cria-loader-title">Thinking</span></div>
            <div class="cria-loader-stage"></div>
            <div class="cria-loader-steps-wrap"><ul class="cria-loader-steps"></ul></div>
            <div class="cria-loader-skeleton" aria-hidden="true"><span class="line w100"></span><span class="line w84"></span><span class="line w66"></span></div>
        </div>`;
    container.appendChild(loader);

    const stageNode = loader.querySelector('.cria-loader-stage');
    const stepsNode = loader.querySelector('.cria-loader-steps');
    let stageIndex = 0;
    let waitIndex = 0;
    const completedStages = [];
    const showStage = (stage) => {
        stageNode.textContent = stage;
        const current = stepsNode.querySelector('li.current');
        if (current) {
            current.classList.remove('current');
            current.classList.add('done');
        }
        const item = document.createElement('li');
        item.className = 'current';
        item.textContent = stage;
        stepsNode.appendChild(item);
        if (stepsNode.children.length > 5) stepsNode.removeChild(stepsNode.children[0]);
        if (!completedStages.includes(stage)) completedStages.push(stage);
    };
    showStage(initialText || CHAT_LOADER_STAGES[0]);
    const intervalId = setInterval(() => {
        if (!loader.isConnected) {
            clearInterval(intervalId);
            return;
        }
        if (stageIndex < CHAT_LOADER_STAGES.length - 1) {
            stageIndex++;
            showStage(CHAT_LOADER_STAGES[stageIndex]);
        } else {
            showStage(CHAT_LOADER_LONG_WAIT_STAGES[waitIndex % CHAT_LOADER_LONG_WAIT_STAGES.length]);
            waitIndex++;
        }
        container.scrollTop = container.scrollHeight;
    }, CHAT_LOADER_STEP_MS);
    return {
        stop: () => {
            clearInterval(intervalId);
            if (loader.isConnected) loader.remove();
        },
        getStages: () => completedStages.slice(),
    };
};

const buildBotHtml = (message, stages, botName) => {
    const label = stages.length > 1 ? `${stages.length} steps · Answered by ${botName}` : `Answered by ${botName}`;
    const stepsHtml = stages.map(stage => `<span class="cria-step-item">${stage.replace(/\.\.\.$/, '').replace(/\.$/, '').trim()}</span>`).join('');
    return `<div class="chat-message bot-message"><div class="message-content">${message}<div class="cria-steps-bar"><button class="cria-steps-toggle" aria-expanded="false" type="button"><span class="cria-steps-label">${label}</span><span class="cria-steps-chevron" aria-hidden="true">⌄</span></button><div class="cria-steps-body" hidden>${stepsHtml}</div></div></div></div>`;
};

const appendErrorMessage = (container, message) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'chat-message bot-message';
    const content = document.createElement('div');
    content.className = 'message-content';
    content.textContent = `⚠️ ${message}`;
    wrapper.appendChild(content);
    container.appendChild(wrapper);
};

export const sendMessage = async () => {
    const input = document.getElementById('block-ai-assistant-chat-input');
    const prompt = input.value.trim();
    if (!prompt) return;

    const chatMessages = document.getElementById('chat-messages');
    const userMessage = document.createElement('div');
    userMessage.className = 'chat-message human-message';
    const userContent = document.createElement('div');
    userContent.className = 'message-content';
    userContent.textContent = prompt;
    userMessage.appendChild(userContent);
    chatMessages.appendChild(userMessage);

    const courseId = document.getElementById('block-ai-assistant-courseid').value;
    const chatId = document.getElementById('block-ai-assistant-chatid').value;
    const botName = document.getElementById('block-ai-assistant-botname').value;
    const tutorialId = document.getElementById('block-ai-assistant-tutorialid').value;
    const tutorialChatId = document.getElementById('block-ai-assistant-tutorialchatid').value;
    input.value = '';

    let loadingText = CHAT_LOADER_FALLBACK;
    try {
        loadingText = await getString('gradebook_loading', 'block_ai_assistant');
    } catch (e) {
        // Keep the local fallback when the language string is unavailable.
    }
    const stopLoader = startProgressiveLoader(chatMessages, 'block-ai-assistant-delete-me', loadingText);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    try {
        const response = await ajax.call([{
            methodname: 'block_ai_assistant_chat',
            args: {courseid: courseId, tutorialid: tutorialId, tutorialchatid: tutorialChatId, botname: botName, prompt, chatid: chatId},
        }]);
        response[0].done((result) => {
            const stages = stopLoader.getStages();
            stopLoader.stop();
            chatMessages.innerHTML += buildBotHtml(normalizeChatResult(result), stages, botName);
            input.focus();
        }).fail((error) => {
            stopLoader.stop();
            appendErrorMessage(chatMessages, normalizeAjaxError(error));
            input.focus();
        });
    } catch (error) {
        stopLoader.stop();
        appendErrorMessage(chatMessages, normalizeAjaxError(error));
        input.focus();
    }
};

document.getElementById('block-ai-assistant-chat-send-btn').addEventListener('click', sendMessage);
document.getElementById('block-ai-assistant-chat-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
});
document.getElementById('chat-messages').addEventListener('click', (e) => {
    const btn = e.target.closest('.cria-steps-toggle');
    if (!btn) return;
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    const body = btn.closest('.cria-steps-bar').querySelector('.cria-steps-body');
    btn.setAttribute('aria-expanded', String(!expanded));
    body.hidden = expanded;
});

export const initChatMenu = () => {
    const menuToggle = document.getElementById('chat-menu-toggle');
    const dropdown = document.getElementById('saved-chats-dropdown');
    const courseId = document.getElementById('block-ai-assistant-courseid').value;
    if (menuToggle && dropdown) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (!menuToggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.remove('show');
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') dropdown.classList.remove('show');
        });
    }
    document.addEventListener('click', (e) => {
        const chatElement = e.target.closest('.block-ai-assistant-delete-chat');
        if (chatElement) deleteChat(chatElement.closest('[data-chat-id]').getAttribute('data-chat-id'), courseId);
    });
};

const deleteChat = async (chatId, courseId) => {
    const [confirmTitle, confirmBody, deleteLabel, cancelLabel, successMessage, failedMessage] = await Promise.all([
        getString('chat_delete_confirm_title', 'block_ai_assistant'),
        getString('chat_delete_confirm_body', 'block_ai_assistant'),
        getString('delete', 'block_ai_assistant'),
        getString('cancel', 'core'),
        getString('chat_delete_success', 'block_ai_assistant'),
        getString('chat_delete_failed', 'block_ai_assistant'),
    ]);
    notification.confirm(confirmTitle, confirmBody, deleteLabel, cancelLabel, () => {
        const response = ajax.call([{methodname: 'block_ai_assistant_delete_chat', args: {chatid: chatId}}]);
        response[0].done(() => {
            sessionStorage.setItem('block_ai_assistant_notice', JSON.stringify({type: 'success', message: successMessage}));
            window.location.href = config.wwwroot + `/course/view.php?id=${courseId}`;
        }).fail(() => notification.addNotification({message: failedMessage, type: 'error'}));
    });
};