/**
 *
 * Created by hhj on 4/24/15.
 */
$(document).ready(function () {
    jqueryInit();
});

var jqueryInit = function () {
    $('#showAllBtn').click(function() {
        var checked = $('#showAllBtn').prop('checked');
        if (!checked) {
            $('.message-read').css("display", "block");
            $('#showAllBtn').prop('checked', true);
            $('#showAllBtn').html('Show unread');
        } else {
            $('.message-read').css("display", "none");
            $('#showAllBtn').prop('checked', false);
            $('#showAllBtn').html('Show all');
        }
    });

    $('#reloadBtn').click(function() {
        location.reload();
    });
}