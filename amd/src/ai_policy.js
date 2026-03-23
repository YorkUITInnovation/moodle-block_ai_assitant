import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';
import ajax from 'core/ajax';

export const init = async () => {
    // Get element with id ai-policy-status
    const policyStatus = document.getElementById('ai-policy-status');
    // Show modal pop-up for policy acceptance if policyStatus is empty
    if (policyStatus.value === '0') {
        // Get strings for modal
        const strings = await Promise.all([
            getString('aipolicyacceptance', 'block_ai_assistant'),
            getString('userpolicy', 'block_ai_assistant'),
            getString('acceptai', 'block_ai_assistant'),
            getString('declineaipolicy', 'block_ai_assistant')
        ]);

        const modal = await ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: strings[0],
            body: strings[1],
            large: true,
            buttons: {
                save: strings[2],
                cancel: strings[3]
            }
        });

        modal.show();

        modal.getRoot().on(ModalEvents.save, () => {
            // Get context id element
            const contextId = document.getElementById('block-ai-assistant-context-id');
            // Set policy status
            const setPolicyStatus = ajax.call([{
                methodname: 'block_ai_assistant_ai_policy',
                args: {
                    contextid: contextId.value,
                },
            }]);

            // Refresh the page
            setPolicyStatus[0].done(() => {
                window.location.reload();
            }).fail((error) => {
                alert('Error setting policy status:', error);
            });
        });

        modal.getRoot().on(ModalEvents.cancel, () => {
            modal.hide();
        });
    }
};
