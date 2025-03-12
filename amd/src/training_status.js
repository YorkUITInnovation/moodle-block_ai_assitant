import ajax from 'core/ajax';

export const init = () => {
    get_training_status();
    get_question_training_status();
};

/**
 * Delete a content
 */
function get_training_status() {
    // Get element value for element with id block-ai_assistant-training-status-id
    var statusElement = document.getElementById('block-ai_assistant-training-status-id');
    // Get cria_file_id from data atrribute in statusId element
    var courseId = statusElement.getAttribute('data-course_id');
    // Get statusId from element value
    var statusId = statusElement.value;

    // if statusId does not equal 1, repeat the ajax call every 10 seconds that checks the training staus
    // if statusId equals 1, stop the ajax call
    if (statusId !== "4" && statusId !== "1") {
        setInterval(function () {
            var get_status = ajax.call([{
                methodname: 'block_ai_assistant_get_training_status',
                args: {
                    'courseid': courseId
                }
            }]);

            get_status[0].done(function (data) {
                // Convert data from JSON to object
                data = JSON.parse(data);
                // Update the element value for element with id block-ai_assistant-training-status-id
                document.getElementById('block-ai_assistant-training-status-id').value = data.training_status_id;
                // Update the element value for element with id block-ai-assistant-training-status
                document.getElementById('block-ai-assistant-training-status').innerHTML = data.training_status;
                // Exit setInterval
                if (data.training_status_id === 1) {
                    clearInterval();
                }
            }).fail(function () {
                alert('An error has occurred. Could not update the training status');
            });
        }, 5000);
    }
}

/**
 * Delete a content
 */
function get_question_training_status() {
    // Get element value for element with id block-ai_assistant-training-status-id
    var statusElement = document.getElementById('block-ai_assistant-question-training-status-id');
    // Get cria_file_id from data atrribute in statusId element
    var questionId = statusElement.getAttribute('data-question_id');
    // Get statusId from element value
    var statusId = statusElement.value;

    // if statusId does not equal 1, repeat the ajax call every 10 seconds that checks the training staus
    // if statusId equals 1, stop the ajax call
    if (statusId !== "4" && statusId !== "1") {
        setInterval(function () {
            var get_status = ajax.call([{
                methodname: 'block_ai_assistant_get_question_training_status',
                args: {
                    'questionid': questionId
                }
            }]);

            get_status[0].done(function (data) {
                // Convert data from JSON to object
                data = JSON.parse(data);
                // Update the element value for element with id block-ai_assistant-training-status-id
                document.getElementById('block-ai_assistant-question-training-status-id').value = data.training_status_id;
                // Update the element value for element with id block-ai-assistant-training-status
                document.getElementById('block-ai-assistant-question-training-status').innerHTML = data.training_status;
                // Exit setInterval
                if (data.training_status_id === 1) {
                    clearInterval();
                }
            }).fail(function () {
                alert('An error has occurred. Could not update the training status');
            });
        }, 5000);
    }
}