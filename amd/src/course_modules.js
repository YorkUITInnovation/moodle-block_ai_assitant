import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';
import jQuery from 'jquery';

export const init = () => {
    // display_modules();
};

function display_modules() {
    document.getElementById('btn-ai-assistant-train-modules').addEventListener('click', function () {
        // get data-courseid from current element
        var courseid = this.getAttribute('data-courseid');

        var display_modules = ajax.call([{
            methodname: 'block_ai_assistant_display_course_modules',
            args: {
                'courseid': courseid
            }
        }]);

        display_modules[0].done(function (results) {
            // Pop up notificaiton to confirm delete
            notification.confirm(Str.get_string('train_course_assistant', 'block_ai_assistant'),
                results,
                Str.get_string('save', 'block_ai_assistant'),
                Str.get_string('cancel', 'block_ai_assistant'), function () {
                    // Get all checkboxes with class courseModuleCheckbox and store the attribute data-filename, data-content, datacourseid for each checked checkbox
                    var checkboxes = document.querySelectorAll('.courseModuleCheckbox');
                    var selected_modules = [];
                    checkboxes.forEach(function (checkbox) {
                        if (checkbox.checked) {
                            selected_modules.push({
                                'filename': checkbox.getAttribute('data-filename'),
                                'content': checkbox.getAttribute('data-content'),
                                'courseid': checkbox.getAttribute('data-courseid'),
                                'cmid': checkbox.getAttribute('data-cmid'),
                                'modname': checkbox.getAttribute('data-modname'),
                            });
                            //make a new ajax call to a new webservice that calls insert from course_module class
                            //block_ai_assistant_insert_course_modules
                            var insert_modules = ajax.call([{
                                methodname: 'block_ai_assistant_insert_course_modules',
                                args: {
                                    'courseid': courseid,
                                    'selected_modules':selected_modules
                                }

                            }]);
                            insert_modules[0].done(function(response){
                                    console.log("response", response);
                                    alert("Successfully added, record id is: " + response);
                                    // You can now use the data variable for further processing
                            }).fail(function(error){
                                alert("error in ajax call of insert modules" + error);
                            });
                        }
                    });
                    console.log("selected_module structure",selected_modules);





                    // var save_content = ajax.call([{
                    //     methodname: 'block_ai_assistant_some_new_method',
                    //     args: {
                    //         'courseid': courseid
                    //     }
                    // }]);
                    //
                    // save_content[0].done(function () {
                    //
                    // }).fail(function () {
                    //     alert('An error has occurred. The record was not deleted');
                    // });
                });
        }).fail(function () {
            alert('An error has occurred. Cannot display data');
        });

    });
}


