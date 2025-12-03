jQuery(document).ready(function ($) {
    $('.vfp-send-otp-btn').on('click', function (e) {
        e.preventDefault();

        var type = $(this).data('type');
        var target = $(this).siblings('input').val();
        var nonce = vfp_ajax.nonce; // <-- yaha vfp_ajax use
        var ajax_url = vfp_ajax.ajax_url;

        $.post(ajax_url, {
            action: 'vfp_send_otp',
            type: type,
            target: target,
            nonce: nonce
        }, function (response) {
            if (response.success) {
                alert('OTP sent successfully!');
            } else {
                alert(response.data.message);
            }
        });
    });
});
