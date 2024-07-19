import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    delete_syllabus();
    enable_ai_assitant();
};

/**
 * Delete a content
 */
function delete_syllabus() {
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
                    methodname: 'ai_assistant_delete_syllabus',
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

function enable_ai_assitant() {
    document.getElementById('btn-enable-ai-assistant').addEventListener('click', function () {
        // get data-courseid from current element
        var courseid = this.getAttribute('data-courseid');
        // Pop up notificaiton to confirm delete

        //Delete the record
        var enable_content = ajax.call([{
            methodname: 'ai_assistant_publish',
            args: {
                'courseid': courseid
            }
        }]);
        delete_content[0].done(function () {
            location.reload();
        }).fail(function () {
            alert('An error has occurred. The record was not published');
        });
    });
}