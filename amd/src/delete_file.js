import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    delete_syllabus();
};

/**
 * Delete a content
 */
function delete_syllabus() {
    // Check to see if element btn-ai-assistant-delete-syllabus exists on the page
    if (!document.getElementById('btn-ai-assistant-delete-syllabus')) {
        return;
    }
    document.getElementById('btn-ai-assistant-delete-syllabus').addEventListener('click', function () {
        // get data-courseid from current element
        var courseid = this.getAttribute('data-courseid');
        // Pop up notificaiton to confirm delete
        notification.confirm(Str.get_string('delete', 'block_ai_assistant'),
            Str.get_string('delete_syllabus_help', 'block_ai_assistant'),
            Str.get_string('delete', 'block_ai_assistant'),
            Str.get_string('cancel', 'block_ai_assistant'), function () {
                //Delete the record
                var delete_content = ajax.call([{
                    methodname: 'block_ai_assistant_delete_syllabus',
                    args: {
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