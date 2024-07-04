define(['jquery', 'core/str'], function($) {
    return {
        init: function() {
            $('.delete-button').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                var confirmMessage = $(this).data('confirm');
 
                if (confirm(confirmMessage)) {
                    window.location.href = url;
                }
            });
        }
    };
});