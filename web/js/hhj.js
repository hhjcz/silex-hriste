/**
 *
 * Created by hhj on 4/24/15.
 */
$(document).ready(function () {
    jqueryInit();
});

var jqueryInit = function () {
    console.log('Jo!');
    $('#showAllBtn').click(function() {
        var checked = $('#showAllBtn').is(':checked');
        if (checked) {
            $('.message-read').css("display", "block");
        } else {
            $('.message-read').css("display", "none");
        }
        //$('#showAllLbl').html('Show unread');
    });
}