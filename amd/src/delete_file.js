define(['core/str'], function() {
    return {
        init: function() {
            var deleteButtons = document.querySelectorAll('.delete-button');
            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var url = button.getAttribute('href');
                    var confirmMessage = button.getAttribute('data-confirm');
                    if (confirm(confirmMessage)) {
                        window.location.href = url;
                    }
                });
            });
        }
    };
});