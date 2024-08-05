import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    delete_question();
};

/**
 * Delete a content
 */
function delete_question() {
    const buttons = document.querySelectorAll('.block-ai-assistant-delete-question');
    buttons.forEach(button => {
        button.addEventListener('click', function () {
            const row = this.closest('tr');
            const id = this.getAttribute('data-id');
            var courseid = this.getAttribute('data-courseid');
            // Pop up notificaiton to confirm delete
            notification.confirm(Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('delete_question_help', 'block_ai_assistant'),
                Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('cancel', 'block_ai_assistant'), function () {
                    //Delete the record
                    var delete_content = ajax.call([{
                        methodname: 'block_ai_assistant_delete_question',
                        args: {
                            'questionid': id,
                            'courseid': courseid
                        }
                    }]);

                    delete_content[0].done(function () {
                        row.parentNode.removeChild(row);
                    }).fail(function () {
                        alert('An error has occurred. The record was not deleted');
                    });
                });
        });
    });
}