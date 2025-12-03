jQuery(document).ready(function ($) {
    console.log("VerifyForm Pro JS Loaded!");
    console.log(vfp_ajax.url);

    let allowedForms = window.vfp_allowed_forms || [];

    // Form submit handler
    $(document).on("submit", "form.vfp-form", function (e) {
        e.preventDefault();

        let form = $(this);
        let formID = form.find('input[name="form_id"]').val();

        if (!allowedForms.includes(formID)) {
            this.submit();
            return;
        }

        // CLEAR OLD ERRORS
        form.find('.vfp-error').removeClass('show').text('');

        // FRONTEND VALIDATION
        let valid = true;
        let requiredFields = [];

        // ---------------------------
        // FRONTEND VALIDATION
        // ---------------------------
        form.find("input[required]").each(function () {
            let field = $(this);
            let name = field.attr("name");
            let val = field.val();

            requiredFields.push(name);

            if (field.attr("type") === "file") {
                if (this.files.length === 0) {
                    form.find(".vfp-error-" + name)
                        .text("This file is required")
                        .addClass("show");
                    valid = false;
                }
            } else {
                if (!val || val.trim() === "") {
                    form.find(".vfp-error-" + name)
                        .text("This field is required")
                        .addClass("show");
                    valid = false;
                }
            }
        });

        if (!valid) return;

        // SEND AJAX REQUEST
        let formData = new FormData(this);
        formData.append("action", "vfp_submit");

        // Send list of required fields to PHP
        formData.append("required_fields", JSON.stringify(requiredFields));

        $.ajax({
            url: vfp_ajax.url,
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,

            success: function (response) {

                console.log(response);

                // SERVER-SIDE VALIDATION FAIL
                if (response.success === false && response.errors) {
                    Object.keys(response.errors).forEach(function (key) {
                        form.find(".vfp-error-" + key).text(response.errors[key]).addClass("show");
                    });
                    return;
                }

                // SUCCESS + REDIRECT
                if (response.success && response.redirect) {
                    window.location.href = response.redirect;
                    return;
                }

                // SUCCESS (no redirect)
                if (response.success) {
                    form.find('.vfp-success-msg')
                        .text(response.message)
                        .show();

                    form[0].reset();
                }

            },

            error: function () {
                alert("Something went wrong.");
            }
        });

    });

});
