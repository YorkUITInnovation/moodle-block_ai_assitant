import ajax from 'core/ajax';
import notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import * as Str from 'core/str';
import config from 'core/config';

export const init = () => {
    initTutorialButtons();
};

/**
 * Initialize tutorial button click handlers
 */
function initTutorialButtons() {
    // Find all elements with class block-ai-assistant-tutorial-button
    const tutorialButtons = document.querySelectorAll('.block-ai-assistant-tutorial-button');

    tutorialButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            // Get course ID from data attribute
            const courseid = this.getAttribute('data-courseid');
            // Get tutorial id from data attribute
            const tutorialid = this.getAttribute('data-tutorialid');

            if (!courseid) {
                notification.exception(new Error('Course ID not found'));
                return;
            }

            // Make AJAX call to display student course modules
            const request = ajax.call([{
                methodname: 'block_ai_assistant_display_student_course_modules',
                args: {
                    'courseid': parseInt(courseid)
                }
            }]);

            request[0].done(function (response) {
                // Create and display modal with the response content
                displayModalWithContent(response, courseid, tutorialid);
            }).fail(function (error) {
                notification.exception(error);
            });
        });
    });
}

/**
 * Display content in a Moodle modal
 * @param {string} content - HTML content to display in modal
 * @param {string} courseid - Course ID for navigation
 * @param {string} tutorialid - Tutorial ID for navigation
 */
function displayModalWithContent(content, courseid, tutorialid) {
    // Get strings for modal title and close button
    const stringRequests = [
        {key: 'course_modules', component: 'block_ai_assistant'},
        {key: 'close', component: 'core'}
    ];

    Str.get_strings(stringRequests).then(function (strings) {
        const modalTitle = strings[0] || 'Course Modules';
        const closeLabel = strings[1] || 'Close';

        // Create modal factory
        return ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            title: modalTitle,
            body: content,
            footer: ''
        });
    }).then(function (modal) {
        // Show the modal
        modal.show();

        // Add event listener for close button
        modal.getRoot().on(ModalEvents.hidden, function () {
            modal.destroy();
        });

        // Add event listeners for student module buttons after modal is shown
        modal.getRoot().on('click', '.btn-block-ai-assistant-student-module', function (e) {
            e.preventDefault();

            // Get the cmid and name from the button's data attributes
            const cmid = this.getAttribute('data-cmid');
            const name = this.getAttribute('data-name');

            // Show loading modal before redirect
            showLoadingModal().then(function () {
                // Small delay to ensure modal is visible before redirect
                setTimeout(function () {
                    redirectToChatPage(courseid, tutorialid, cmid, name);
                }, 500);
            });
        });

        return modal;
    }).catch(function (error) {
        notification.exception(error);
    });
}

/**
 * Redirect to chat.php with proper query parameters
 * @param {string} courseid - Course ID
 * @param {string} tutorialid - Tutorial ID
 * @param {string} cmid - Course Module ID
 * @param {string} name - Module name (will be HTML encoded)
 */
function redirectToChatPage(courseid, tutorialid, cmid, name) {
    // Create URL with query parameters
    const baseUrl = config.wwwroot + '/blocks/ai_assistant/chat.php';
    const params = new URLSearchParams();

    params.append('courseid', courseid);
    if (tutorialid) {
        params.append('tutorialid', tutorialid);
    }
    params.append('cmid', cmid);
    params.append('name', name); // URLSearchParams automatically handles encoding

    const redirectUrl = baseUrl + '?' + params.toString();

    // Redirect to the chat page
    window.location.href = redirectUrl;
}

/**
 * Show a loading modal with the message "Preparing the tutorial"
 */
function showLoadingModal() {
    // Get strings for modal title and close button
    const stringRequests = [
        {key: 'preparing_tutorial', component: 'block_ai_assistant'},
    ];
    return Str.get_strings(stringRequests).then(function (strings) {
        const warning = strings[0] || 'Preparing the tutorial...';

        return ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            title: '',
            body: '<div class="text-center">' + warning + '</div>',
            footer: ''
        }).then(function (modal) {
            modal.show();
            return modal;
        });
    });
}
