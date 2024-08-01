import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    enableSelectAll();
};

/**
 * Enable Select All functionality
 */
function enableSelectAll() {
    document.addEventListener('DOMContentLoaded', function() {
        // Get all "Select All" checkboxes
        const selectAllCheckboxes = document.querySelectorAll('.select-all-checkbox');

        selectAllCheckboxes.forEach(selectAllCheckbox => {
            selectAllCheckbox.addEventListener('change', function() {
                const sectionId = this.id.replace('selectAll-', '');
                const checkboxes = document.querySelectorAll(`.module-checkbox-${sectionId}`);
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        });
    });
}