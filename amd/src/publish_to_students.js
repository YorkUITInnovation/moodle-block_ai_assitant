import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    enable_ai_assitant();
};

/**
 * Enable AI assistant
 */

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