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
                        <span class="sr-only">${loadingText}</span></div>
                     </div>`;
        // Scroll down to the top of new message
        chatMessages.innerHTML += html;
        chatMessages.scrollTop = chatMessages.scrollHeight;
        input.value = '';

        const courseId = document.getElementById('block-ai-assistant-courseid').value;
        const chatId = document.getElementById('block-ai-assistant-chatid').value;
        const botName = document.getElementById('block-ai-assistant-botname').value;

        if (prompt) {
            const response = await ajax.call([{
                methodname: 'block_ai_assistant_chat',
                args: {
                    courseid: courseId,
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
                console.log(error);
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


// Create HTML for a chat item
const createChatItemHTML = (chat) => {
    const date = new Date(chat.timemodified * 1000).toLocaleDateString();
    const title = chat.title || `Chat ${chat.id}`;

    return `
    <div class="saved-chat-item" data-chat-id="${chat.id}">
      <div>
        <div class="chat-title" title="${title}">${title}</div>
        <div class="chat-date">${date}</div>
      </div>
      <div class="chat-actions">
        <button class="chat-action-btn" data-action="rename" title="Rename chat">
          <i class="fa fa-edit"></i>
        </button>
        <button class="chat-action-btn delete" data-action="delete" title="Delete chat">
          <i class="fa fa-trash"></i>
        </button>
      </div>
    </div>
  `;
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

        response[0].done((result) => {
               // Redirect to course page after deletion
                window.location.href = config.wwwroot + `/course/view.php?id=${courseId}`;

        }).fail((error) => {
            alert('Failed to delete chat. Please try again.');
        });
    } catch (error) {
        alert('Error deleting chat. Please try again.');
    }
};

// Rename a chat
export const renameChat = async (chatId) => {
    const newTitle = prompt('Enter new chat title:');
    if (!newTitle || newTitle.trim() === '') {
        return;
    }

    try {
        const response = await ajax.call([{
            methodname: 'block_ai_assistant_rename_chat',
            args: {
                chatid: chatId,
                title: newTitle.trim()
            },
        }]);

        response[0].done((result) => {
            if (result.success) {
                // Reload the saved chats list to show the new title
                loadSavedChats();
            }
        }).fail((error) => {
            console.error('Failed to rename chat:', error);
            alert('Failed to rename chat. Please try again.');
        });
    } catch (error) {
        console.error('Error renaming chat:', error);
        alert('Error renaming chat. Please try again.');
    }
};

// Initialize the chat menu when the page loads
document.addEventListener('DOMContentLoaded', () => {
    initChatMenu();
});
