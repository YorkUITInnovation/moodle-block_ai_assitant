import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    append_add_button();
    append_upload_button();
    append_execute_button();
    delete_autotest_question();
};

/**
 * Delete a content
 */
function delete_autotest_question() {
    const buttons = document.querySelectorAll('.block-ai-assistant-delete-auto-test');
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
                        methodname: 'block_ai_assistant_delete_autotest_question',
                        args: {
                            'id': id,
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

/**
 * Append href to div element with class form-inline
 *
 */
function append_add_button() {
    const div = document.querySelector('.form-inline');
    const button = document.createElement('a');
    // Get element value with name courseid
    const courseid = document.querySelector('input[name="courseid"]').value;

    button.setAttribute('href', 'edit_autotest_question.php?courseid=' + courseid);
    button.setAttribute('class', 'btn btn-primary ml-2');
    button.textContent = 'Add';
    div.appendChild(button);
}

/**
 * Append href to div element with class form-inline
 */
function append_upload_button() {
    const div = document.querySelector('.form-inline');
    const button = document.createElement('a');
    // Get element value with name courseid
    const courseid = document.querySelector('input[name="courseid"]').value;

    button.setAttribute('href', 'autotest_import.php?courseid=' + courseid);
    button.setAttribute('class', 'btn btn-primary ml-2');
    button.textContent = 'Upload questions';
    div.appendChild(button);
}

function append_execute_button() {
    const div = document.querySelector('.form-inline');
    const button = document.createElement('a');
    // Get element value with name courseid
    const courseid = document.querySelector('input[name="courseid"]').value;

    button.setAttribute('href', 'autotest_exec.php?courseid=' + courseid);
    button.setAttribute('id', 'block-ai-assistant-execute-auto-test');
    button.setAttribute('class', 'btn btn-primary ml-2');
    button.textContent = 'Run AutoTest';
    div.appendChild(button);
}