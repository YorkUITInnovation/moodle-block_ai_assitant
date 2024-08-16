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
    document.getElementById('btn-ai-assistant-delete-question').addEventListener('click', function() {
            // get data-courseid from current element
            var questionid = this.getAttribute('data-questionid');
            var courseid = this.getAttribute('data-courseid');
            console.log(questionid);
            // Pop up notificaiton to confirm delete
            notification.confirm(Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('delete_question_help', 'block_ai_assistant'),
                Str.get_string('delete', 'block_ai_assistant'),
                Str.get_string('cancel', 'block_ai_assistant'), function () {
                    //Delete the record
                    var delete_content = ajax.call([{
                        methodname: 'block_ai_assistant_delete_question_file',
                        args: {
                            'questionid': questionid,
                            'courseid': courseid
                        }
                    }]);

                    delete_content[0].done(function () {
                        location.reload();
                    }).fail(function () {
                        alert('An error has occurred. The record was not deleted');
                    });
                });
    });
}