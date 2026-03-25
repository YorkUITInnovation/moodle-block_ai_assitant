import {get_string as getString} from 'core/str';
import ajax from 'core/ajax';
import config from 'core/config';

export const sendMessage = async () => {
    const input = document.getElementById('block-ai-assistant-chat-input');
    const prompt = input.value;
    if (prompt) {
        const chatMessages = document.getElementById('chat-messages');
        const loadingText = await getString('loading', 'block_learningassist');

        const html = `<div class="chat-message human-message">
                        <div class="message-content">${prompt}</div>
                     </div>
                    <div id="block-ai-assistant-delete-me" class="chat-message bot-message">
                        <div class="message-content">
                        <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
                        <span class="visually-hidden">${loadingText}</span></div>
                     </div>`;
        // Scroll down to the top of new message
        chatMessages.innerHTML += html;
        chatMessages.scrollTop = chatMessages.scrollHeight;
        input.value = '';

        const courseId = document.getElementById('block-ai-assistant-courseid').value;
        const chatId = document.getElementById('block-ai-assistant-chatid').value;
        const botName = document.getElementById('block-ai-assistant-botname').value;
        const tutorialChatId = document.getElementById('block-ai-assistant-tutorialchatid').value;

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
                const deleteMe = document.getElementById('block-ai-assistant-delete-me');
                if (deleteMe) {
                    deleteMe.remove();
                }
                const bot_html = `<div class="chat-message bot-message">
                            <div class="message-content">${result}</div>
                         </div>`;
                chatMessages.innerHTML += bot_html;
                input.focus();
            }).fail((error) => {
                alert(error);
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
    if (!confirm('Are you sure you want to delete this chat?')) {
        return;
    }

    try {
        const response = await ajax.call([{
            methodname: 'block_ai_assistant_chat_delete',
            args: {
                chatid: chatId
            },
        }]);

        response[0].done(() => {
               // Redirect to course page after deletion
                window.location.href = config.wwwroot + `/course/view.php?id=${courseId}`;

        }).fail((error) => {
            alert('Failed to delete chat. Please try again.' + error);
        });
    } catch (error) {
        alert('Error deleting chat. Please try again. ' + error);
    }
};
