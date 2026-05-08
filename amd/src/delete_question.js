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
    // Check to see if element btn-ai-assistant-delete-question exists on the page
    if (!document.getElementById('btn-ai-assistant-delete-question')) {
        return;
    }
        var deleteButton = document.getElementById('btn-ai-assistant-delete-question');
        deleteButton.addEventListener('click', function() {
            // get data-courseid from current element
            var questionid = parseInt(this.getAttribute('data-questionid') || '0', 10);
            var courseid = this.getAttribute('data-courseid');

            // Pop up notificaiton to confirm delete
            notification.confirm(Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('delete_question_help', 'block_ai_assistant'),
                Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('cancel', 'core'), function () {
                    deleteButton.disabled = true;
                    //Delete the record
                    var delete_content = ajax.call([{
                        methodname: 'block_ai_assistant_delete_question_file',
                        args: {
                            'questionid': questionid || 0,
                            'courseid': courseid
                        }
                    }]);

                    delete_content[0].done(function () {
                        location.reload();
                    }).fail(function (error) {
                        deleteButton.disabled = false;
                        notification.exception(error);
                    });
                });
    });
}