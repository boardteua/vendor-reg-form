jQuery(document).ready(function ($) {

    // form validate

    let valid = false;

    $('#vendor__login_form').validate({

        rules: {
            vendor__user_name: {
                required: true,
                minlength: 3
            },

            vendor__user_email: {
                required: true,
                email: true
            },

            vendor__user_pass: {
                required: true,
                minlength: 6
            },

            vendor__user_pass_check: {
                required: true,
                minlength: 6,
                equalTo: "#vendor__user_pass"
            }
        },

        messages: {
            vendor__user_name: "Please fill the required field",
            vendor__user_email: "Please enter a valid email address.",
            vendor__user_pass: "Please fill the required field",
            vendor__user_pass_check: "Password didn't match"
        },

        errorElement: "div",
        errorPlacement: function (error, element) {
            element.after(error);
        },
        submitHandler: function (form) {
            valid = true;
        }

    });

    // form send

    $('#vendor__login_form').on('submit', function (e) {

        e.preventDefault();
        e.stopPropagation();

        let data = new FormData();
        data.append('vendor__user_name', $('#vendor__user_name').val().trim());
        data.append('vendor__user_email', $('#vendor__user_email').val().trim());
        data.append('vendor__user_pass', $('#vendor__user_pass').val().trim());
        data.append('vendor__user_pass_check', $('#vendor__user_pass_check').val().trim());
        data.append('nonce', $('#vendor__register_nonce').val().trim());

        data.append('action', 'create_user');
        if (valid === true) {
            $(this).find('.loading').show();
            $.ajax({
                type: 'POST',
                url: form.ajaxurl,
                data: data,
                contentType: false,
                processData: false,
                success: function (resp) {
                    if (resp.success === true) {
                        $('.form-response').html('<p class="text-success">To verify the data provided we have sent you a confirmation email, please check your email box</p>')
                        //location.href = resp.data;

                        $('.loading').hide();
                        $('.form-response').nextAll().hide();

                    } else {
                        $(this).find('.loading').hide();
                        $('.form-response').html('<p class="text-danger">' + resp.data.join('<br />') + '</p>')
                    }
                }
            });
        }
    });

});