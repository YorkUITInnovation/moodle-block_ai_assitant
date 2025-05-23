import ajax from 'core/ajax';

export const init = () => {
    enable_ai_assitant();
};

/**
 * Enable AI assistant
 */
function enable_ai_assitant() {
    //Check to see if the button exists
    if (!document.getElementById('btn-enable-ai-assistant')) {
        return;
    }
    document.getElementById('btn-enable-ai-assistant').addEventListener('click', function () {
        // get data-courseid from current element
        var courseid = this.getAttribute('data-courseid');
        // Pop up notificaiton to confirm delete

        //Publish the record
        var enable_content = ajax.call([{
            methodname: 'block_ai_assistant_publish',
            args: {
                'courseid': courseid
            }
        }]);
        enable_content[0].done(function () {
            location.reload();
        }).fail(function () {
            alert('An error has occurred. The record was not published');
        });
    });
}