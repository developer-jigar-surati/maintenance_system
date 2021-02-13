function validemail(email) {
    var regex = /^([a-zA-Z0-9-_\.])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    if (!regex.exec(email)) {
        return 0;
    }
    else {
        return 1;
    }
}
function isNumber(evt) {
    evt = (evt) ? evt : window.event;
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode > 32 && (charCode < 48 || charCode > 57) && charCode != 45) {
        return false;
    }
    return true;
}
function validate_form(id) {
    var error = 0;
    $('.invalid').removeClass('invalid');
    $("#" + id).find(".notnull").each(function () {
        if ($(this).val() == "") {
            error = 1;
            $(this).addClass('invalid');
        }
    });

    $("#" + id).find(".dropdown").each(function () {
        if ($(this).val() == "0") {
            error = 1;
            $(this).addClass('invalid');
        }
    });

    $("#" + id).find(".email").each(function () {
        if ($(this).val() != "") {
            var check = validemail($(this).val());
            if (check == 0) {
                error = 1;
                $(this).addClass('invalid');
            }
        }
    });
    if (error == 1)
        return false;
    else
        return true;
}