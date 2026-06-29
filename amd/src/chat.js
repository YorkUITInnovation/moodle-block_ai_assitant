import {get_string as getString} from 'core/str';
import ajax from 'core/ajax';
import config from 'core/config';
import notification from 'core/notification';

const CHAT_LOADER_STAGES = [
    'Connecting to retrieval engines...',
    'Searching course knowledge base...',
    'Retrieving relevant document chunks...',
    'Synthesizing response draft...',
    'Finalizing answer... almost there.',
];
const CHAT_LOADER_STEP_MS = 2200;
const CHAT_LOADER_FALLBACK = 'Thinking...';

const normalizeChatResult = (result) => {
    if (typeof result === 'string') {
        return result;
    }

    if (result && typeof result === 'object') {
        if (result.reply && result.reply.content && typeof result.reply.content.content === 'string') {
            return result.reply.content.content;
        }
        if (result.response && result.response.data && result.response.data.reply &&
            result.response.data.reply.content && typeof result.response.data.reply.content.content === 'string') {
            return result.response.data.reply.content.content;
        }
        if (typeof result.message === 'string') {
            return result.message;
        }
        if (typeof result.reply === 'string') {
            return result.reply;
        }
        if (result.reply && typeof result.reply.message === 'string') {
            return result.reply.message;
        }
        if (result.response && typeof result.response === 'object') {
            if (result.response.data && typeof result.response.data.answer === 'string') {
                return result.response.data.answer;
            }
            if (typeof result.response.message === 'string') {
                return result.response.message;
            }
        }
        if (typeof result.response === 'string') {
            return result.response;
        }
    }

    return String(result ?? '');
};

const normalizeAjaxError = (error) => {
    if (typeof error === 'string') {
        return error;
    }

    if (error && typeof error === 'object') {
        if (typeof error.message === 'string' && error.message) {
            return error.message;
        }
        if (error.error && typeof error.error === 'string') {
            return error.error;
        }
        if (error.exception && typeof error.exception === 'string') {
            return error.exception;
        }
    }

    return 'Unable to get a response from AI Assistant.';
};

const startProgressiveLoader = (container, id, initialText) => {
    const loader = document.createElement('div');
    loader.id = id;
    loader.className = 'chat-message bot-message';
    loader.innerHTML = `
        <div class="message-content cria-loader">
            <div class="cria-loader-header">
                <span class="cria-loader-ring" aria-hidden="true"></span>
                <span class="cria-loader-title">Thinking</span>
            </div>
            <div class="cria-loader-stage">${initialText}</div>
            <div class="cria-loader-steps-wrap">
                <ul class="cria-loader-steps">
                    <li class="current">${CHAT_LOADER_STAGES[0]}</li>
                </ul>
            </div>
            <div class="cria-loader-skeleton" aria-hidden="true">
                <span class="line w100"></span>
                <span class="line w84"></span>
                <span class="line w66"></span>
            </div>
        </div>`;
    container.appendChild(loader);
    container.scrollTop = container.scrollHeight;

    const stageNode = loader.querySelector('.cria-loader-stage');
    const stepsNode = loader.querySelector('.cria-loader-steps');
    let stageIndex = 0;
    // Track which stages were shown for the details panel.
    const completedStages = [CHAT_LOADER_STAGES[0]];

    const intervalId = setInterval(() => {
        if (!loader.isConnected || !stageNode || !stepsNode) {
            clearInterval(intervalId);
            return;
        }
        const currentLi = stepsNode.querySelector('li.current');
        if (currentLi) {
            currentLi.classList.remove('current');
            currentLi.classList.add('done');
        }
        stageIndex = Math.min(stageIndex + 1, CHAT_LOADER_STAGES.length - 1);
        const currentStage = CHAT_LOADER_STAGES[stageIndex];
        stageNode.textContent = currentStage;
        const li = document.createElement('li');
        li.className = 'current';
        li.textContent = currentStage;
        stepsNode.appendChild(li);
        completedStages.push(currentStage);
        if (stepsNode.children.length > 5) {
            stepsNode.removeChild(stepsNode.children[0]);
        }
        container.scrollTop = container.scrollHeight;
    }, CHAT_LOADER_STEP_MS);

    return {
        stop: () => {
            clearInterval(intervalId);
            if (loader.isConnected) {
                loader.remove();
            }
        },
        getStages: () => [...new Set(completedStages)],
    };
};

export const sendMessage = async () => {
    const input = document.getElementById('block-ai-assistant-chat-input');
    const prompt = input.value;
    if (prompt) {
        const chatMessages = document.getElementById('chat-messages');
        let loadingText = CHAT_LOADER_FALLBACK;
        try {
            loadingText = await getString('gradebook_loading', 'block_ai_assistant');
        } catch (e) {
        }
        const loaderId = 'block-ai-assistant-delete-me';

        const html = `<div class="chat-message human-message">
                        <div class="message-content">${prompt}</div>
                     </div>`;
        // Scroll down to the top of new message
        chatMessages.innerHTML += html;
        const stopLoader = startProgressiveLoader(chatMessages, loaderId, loadingText);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        input.value = '';

        const courseId = document.getElementById('block-ai-assistant-courseid').value;
        const chatId = document.getElementById('block-ai-assistant-chatid').value;
        const botName = document.getElementById('block-ai-assistant-botname').value;
        const tutorialChatId = document.getElementById('block-ai-assistant-tutorialchatid').value;

        const buildBotHtml = (message, stages) => {
            // Claude-style inline step summary: "3 steps · Answered by BotName ▾"
            const label = stages.length > 1
                ? `${stages.length} steps · Answered by ${botName}`
                : `Answered by ${botName}`;
            const stepsHtml = stages
                .map(s => `<span class="cria-step-item">${s.replace(/\.\.\.$/, '').replace(/\.$/, '').trim()}</span>`)
                .join('');
            return `<div class="chat-message bot-message">
                <div class="message-content">
                    ${message}
                    <div class="cria-steps-bar">
                        <button class="cria-steps-toggle" aria-expanded="false" type="button">
                            <span class="cria-steps-label">${label}</span>
                            <span class="cria-steps-chevron" aria-hidden="true">
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14.128 7.165a.502.502 0 0 1 .744.67l-4.5 5-.078.07a.5.5 0 0 1-.666-.07l-4.5-5-.06-.082a.501.501 0 0 1 .729-.656l.075.068L10 11.752z"/>
                                </svg>
                            </span>
                        </button>
                        <div class="cria-steps-body" hidden>${stepsHtml}</div>
                    </div>
                </div>
            </div>`;
        };

        if (prompt) {
            const response = await ajax.call([{
                methodname: 'block_ai_assistant_chat',
                args: {
                    courseid: courseId,
                    tutorialchatid: tutorialChatId,
                    botname: botName,
                    prompt: prompt,
                    chatid: chatId
                },
            }]);
            response[0].done((result) => {
                const stages = stopLoader.getStages();
                stopLoader.stop();
                const message = normalizeChatResult(result);
                chatMessages.innerHTML += buildBotHtml(message, stages);
                input.focus();
            }).fail((error) => {
                stopLoader.stop();
                const message = normalizeAjaxError(error);
                const botHtml = `<div class="chat-message bot-message"><div class="message-content">${message}</div></div>`;
                chatMessages.innerHTML += botHtml;
                input.focus();
            });
        }
    }
};

document.getElementById('block-ai-assistant-chat-send-btn').addEventListener('click', sendMessage);

document.getElementById('block-ai-assistant-chat-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
});

// Delegated toggle for step bars injected after each bot reply.
document.getElementById('chat-messages').addEventListener('click', (e) => {
    const btn = e.target.closest('.cria-steps-toggle');
    if (!btn) return;
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    const body = btn.closest('.cria-steps-bar').querySelector('.cria-steps-body');
    btn.setAttribute('aria-expanded', String(!expanded));
    body.hidden = expanded;
});

// Hamburger menu functionality
export const initChatMenu = () => {
    const menuToggle = document.getElementById('chat-menu-toggle');
    const dropdown = document.getElementById('saved-chats-dropdown');
    const courseId = document.getElementById('block-ai-assistant-courseid').value;

    if (menuToggle && dropdown) {
        // Toggle dropdown visibility
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!menuToggle.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Close dropdown when pressing Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                dropdown.classList.remove('show');
            }
        });
    }

    // Handle delete chat button clicks
    document.addEventListener('click', (e) => {
        if (e.target.closest('.block-ai-assistant-delete-chat')) {
            const chatElement = e.target.closest('[data-chat-id]');
            if (chatElement) {
                const chatId = chatElement.getAttribute('data-chat-id');
                deleteChat(chatId, courseId);
            }
        }
    });
};


// Delete a chat
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
        const response = ajax.call([{
            methodname: 'block_ai_assistant_chat_delete',
            args: {
                chatid: chatId
            },
        }]);

        response[0].done(() => {
            sessionStorage.setItem('block_ai_assistant_notice', JSON.stringify({
                type: 'success',
                message: successMessage
            }));
            window.location.href = config.wwwroot + `/course/view.php?id=${courseId}`;
        }).fail(() => {
            notification.addNotification({
                message: failedMessage,
                type: 'error'
            });
        });
    });
};
