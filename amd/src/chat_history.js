import $ from 'jquery';
import ajax from 'core/ajax';
import notification from 'core/notification';
import {get_strings as getStrings} from 'core/str';
import SaveCancelModal from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

const DEBUG = false; // Set to true to enable console logging for troubleshooting

const log = function(label, data) {
    if (DEBUG) {
        console.log(label, data);
    }
};

const COMPONENT = 'block_ai_assistant';

const STRING_KEYS = [
    {key: 'chat_history_selected_count', component: COMPONENT},
    {key: 'chat_history_bulk_confirm_title', component: COMPONENT},
    {key: 'chat_history_bulk_confirm_body', component: COMPONENT},
    {key: 'chat_history_delete_title', component: COMPONENT},
    {key: 'chat_delete_confirm_body', component: COMPONENT},
    {key: 'chat_delete_success', component: COMPONENT},
    {key: 'chat_history_bulk_deleted', component: COMPONENT},
    {key: 'chat_history_delete_error', component: COMPONENT},
    {key: 'chat_history_delete_selected', component: COMPONENT},
];

export const init = async function() {
    const strings = {};
    try {
        (await getStrings(STRING_KEYS)).forEach((value, i) => {
            strings[STRING_KEYS[i].key] = value;
        });
    } catch (e) {
        log('[chat_history] Failed to load lang strings', e);
    }

    const str = (key, param) => {
        const template = strings[key] || key;
        return param === undefined ? template : template.replace('{$a}', param);
    };

    const selectAllCheckbox = $('#select-all-chats');
    const chatCheckboxes = $('.chat-checkbox');
    const bulkDeleteBtn = $('#bulk-delete-btn');
    const selectionCount = $('#selection-count');
    const deleteSingleBtns = $('.delete-single-chat');
    const deleteSelectedLabel = '<i class="fa fa-trash"></i> ' + str('chat_history_delete_selected');

    // Select all functionality
    selectAllCheckbox.on('change', function() {
        var isChecked = this.checked;
        chatCheckboxes.prop('checked', isChecked).trigger('change');
    });

    // Individual checkbox change
    chatCheckboxes.on('change', function() {
        updateSelectionUI();
    });

    // Update UI based on selection
    function updateSelectionUI() {
        var selectedCount = chatCheckboxes.filter(':checked').length;
        var totalCount = chatCheckboxes.length;

        selectionCount.text(str('chat_history_selected_count', selectedCount));
        bulkDeleteBtn.prop('disabled', selectedCount === 0);
        selectAllCheckbox.prop('checked', selectedCount === totalCount && selectedCount > 0);

        // Highlight selected rows
        chatCheckboxes.each(function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
        });
    }

    // Show confirmation modal
    function showConfirm(title, message, onConfirm) {
        SaveCancelModal.create({
            title: title,
            body: message
        }).then(function(modal) {
            modal.getRoot().on(ModalEvents.save, function() {
                modal.hide();
                onConfirm();
            });
            modal.show();
            return modal;
        }).catch(function(error) {
            console.error('Modal error:', error);
        });
    }

    // Bulk delete
    bulkDeleteBtn.on('click', function() {
        var selectedIds = chatCheckboxes.filter(':checked').map(function() {
            return $(this).data('id');
        }).get();

        if (selectedIds.length === 0) {
            return;
        }

        showConfirm(
            str('chat_history_bulk_confirm_title'),
            str('chat_history_bulk_confirm_body', selectedIds.length),
            function() {
                bulkDeleteChats(selectedIds);
            }
        );
    });

    // Single delete
    deleteSingleBtns.on('click', function() {
        var id = $(this).data('id');
        showConfirm(
            str('chat_history_delete_title'),
            str('chat_delete_confirm_body'),
            function() {
                deleteChat(id);
            }
        );
    });

    // Delete single chat
    function deleteChat(id) {
        var chatid = $('tr[data-id="' + id + '"]').data('chatid');
        log('[chat_history] Deleting single chat:', chatid);

        ajax.call([{
            methodname: 'block_ai_assistant_delete_chat',
            args: {chatid: chatid},
            done: function() {
                log('[chat_history] Chat deleted successfully', null);
                notification.addNotification({
                    message: str('chat_delete_success'),
                    type: 'success'
                });
                $('tr[data-id="' + id + '"]').fadeOut(300, function() {
                    $(this).remove();
                });
                updateSelectionUI();
            },
            fail: function(error) {
                log('[chat_history] Delete failed:', error);
                notification.addNotification({
                    message: str('chat_history_delete_error'),
                    type: 'error'
                });
            }
        }]);
    }

    // Delete multiple chats
    function bulkDeleteChats(ids) {
        bulkDeleteBtn.prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin"></i> ' + str('chat_history_delete_selected'));
        log('[chat_history] Bulk deleting chats:', ids);

        var chatids = ids.map(function(id) {
            return $('tr[data-id="' + id + '"]').data('chatid');
        });
        log('[chat_history] Chat IDs:', chatids);

        ajax.call([{
            methodname: 'block_ai_assistant_bulk_delete_chats',
            args: {chatids: chatids},
            done: function(result) {
                log('[chat_history] Bulk delete successful:', result);
                notification.addNotification({
                    message: str('chat_history_bulk_deleted', result.deleted),
                    type: 'success'
                });

                ids.forEach(function(id) {
                    $('tr[data-id="' + id + '"]').fadeOut(300, function() {
                        $(this).remove();
                    });
                });

                selectAllCheckbox.prop('checked', false);
                updateSelectionUI();
                bulkDeleteBtn.html(deleteSelectedLabel);

                if ($('.chat-row').length === 0) {
                    location.reload();
                }
            },
            fail: function(error) {
                log('[chat_history] Bulk delete failed:', error);
                notification.addNotification({
                    message: str('chat_history_delete_error'),
                    type: 'error'
                });
                bulkDeleteBtn.prop('disabled', false).html(deleteSelectedLabel);
            }
        }]);
    }

    updateSelectionUI();
};
