import {get_string as getString} from 'core/str';
import ajax from 'core/ajax';
import config from 'core/config';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';

// Delete a chat
const deleteChat = async (chatId) => {
    return new Promise((resolve, reject) => {
        try {
            const response = ajax.call([{
                methodname: 'block_ai_assistant_chat_delete',
                args: {
                    chatid: chatId
                },
            }]);

            response[0].done(() => {
                // Remove the chat item from the modal
                const chatItem = document.querySelector(`[data-chat-id="${chatId}"]`);
                if (chatItem) {
                    chatItem.remove();
                }
                resolve(); // Resolve the promise on successful deletion
            }).fail((error) => {
                alert('Failed to delete chat. Please try again.' + error);
                reject(error); // Reject the promise on failure
            });
        } catch (error) {
            alert('Error deleting chat. Please try again. ' + error);
            reject(error);
        }
    });
};

/**
 * Display saved chats in a modal
 * @param {Array} savedChats - Array of saved chat objects
 * @param {number} courseId - Course ID
 */
const showSavedChatsModal = async (savedChats, courseId) => {
    try {
        // Track whether any chats were deleted
        let chatsDeleted = false;

        // Get strings for modal
        const strings = await Promise.all([
            getString('saved_chats', 'block_ai_assistant'),
            getString('close', 'core')
        ]);

        // Render the saved chats content using the mustache template
        let modalContent = '';
        if (savedChats && savedChats.length > 0) {
            for (const chat of savedChats) {
                const chatHtml = await Templates.render('block_ai_assistant/saved_chats', {
                    ...chat,
                    courseid: courseId,
                    config: {wwwroot: config.wwwroot}
                });
                modalContent += chatHtml;
            }
        } else {
            modalContent = '<div class="no-saved-chats">No saved chats yet</div>';
        }

        // Create the modal
        const modal = await ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            title: strings[0] || 'Saved Chats',
            body: `<div class="saved-chats-modal-content">${modalContent}</div>`,
            large: true
        });

        // Show the modal
        modal.show();

        // Add event listeners for delete buttons within the modal
        modal.getRoot().on('click', '.block-ai-assistant-delete-chat', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling

            if (!confirm('Are you sure you want to delete this chat?')) {
                return;
            }

            const chatId = this.getAttribute('data-chatid');
            if (chatId) {
                // Call deleteChat and mark that a chat was deleted
                deleteChat(chatId).then(() => {
                    chatsDeleted = true;
                }).catch(() => {
                    // If deletion fails, don't mark as deleted
                });
            }
        });

        // Handle modal close events (both X button and other close methods)
        modal.getRoot().on(ModalEvents.hidden, () => {
            modal.destroy();
            // Only reload if chats were deleted
            if (chatsDeleted) {
                location.reload();
            }
        });

        modal.getRoot().on(ModalEvents.cancel, () => {
            modal.hide();
            // Only reload if chats were deleted
            if (chatsDeleted) {
                location.reload();
            }
        });

    } catch (error) {
        alert('Error displaying saved chats. Please try again.');
    }
};

/**
 * Initialize saved chats functionality
 * @param {Array} savedChats - Array of saved chat objects
 * @param {number} courseId - Course ID
 */
export const init = (savedChats, courseId) => {
    // Add event listeners for delete buttons in the main interface
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('block-ai-assistant-delete-chat') ||
            e.target.closest('.block-ai-assistant-delete-chat')) {
            e.preventDefault();
            const button = e.target.classList.contains('block-ai-assistant-delete-chat') ?
                e.target : e.target.closest('.block-ai-assistant-delete-chat');
            const chatId = button.getAttribute('data-chatid');
            if (chatId) {
                deleteChat(chatId, courseId);
            }
        }

        // Handle the "Manage Saved Chats" button click
        if (e.target.id === 'btn-ai-assistant-manage-saved-chats' ||
            e.target.closest('#btn-ai-assistant-manage-saved-chats')) {
            e.preventDefault();
            showSavedChatsModal(savedChats, courseId);
        }
    });

    // Make the showSavedChatsModal function globally available
    window.showSavedChatsModal = () => showSavedChatsModal(savedChats, courseId);
};

// Export functions for external use
export { deleteChat, showSavedChatsModal };
