// filepath: y:\home\thibaud\earlyalerts\html\blocks\ai_assistant\amd\src\tutorials.js
import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    delete_tutorial();
};

/**
 * Delete a tutorial via AJAX
 */
function delete_tutorial() {
    const buttons = document.querySelectorAll('.block-ai-assistant-delete-tutorial');
    if (!buttons.length) {
        return;
    }
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const tutorialid = this.getAttribute('data-id');
            const courseId = this.getAttribute('data-courseid');

            notification.confirm(
                Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('delete_tutorial_help', 'block_ai_assistant'),
                Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('cancel', 'block_ai_assistant'),
                () => {
                    const request = ajax.call([{
                        methodname: 'block_ai_assistant_delete_tutorial',
                        args: {
                            id: tutorialid,
                            courseid: courseId
                        }
                    }]);

                    request[0].done(() => {
                        location.reload();
                    }).fail(() => {
                        alert('An error has occurred. The record was not deleted');
                    });
                }
            );
        });
    });
}

