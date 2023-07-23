@include('partials.header',['pagetitle'=>"Forgot Password"])
<!-- [ auth-signin ] start -->
<div class="auth-wrapper">
    <div class="auth-content">
        <div class="card">
            <div class="row align-items-center text-center">
                <div class="col-md-12">
                    <div class="card-body">
                        <form id="forgotpassform">
                            <img src="{{ url('assets/images/new-logo.png') }}" alt="" class="img-fluid mb-4">
                            <h4 class="mb-3 f-w-400">Forgot Password</h4>
                            <div class="form-group mb-3">
                                <label class="floating-label" for="Email">Email address</label>
                                <input type="text" name="email_id" class="form-control notnull email" value="{{ $admindata->email_id }}" id="email_id" placeholder="" readonly>
                            </div>
                            <div class="form-group mb-4">
                                <label class="floating-label" for="new_password">Password</label>
                                <input type="password" name="new_password" class="form-control notnull" id="new_password" placeholder="">
                            </div>
                            <div class="form-group mb-4">
                                <label class="floating-label" for="confirm_password">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control notnull" id="confirm_password" placeholder="">
                            </div>
                            <input type="hidden" id="forgotlink" name="forgotlink" value="{{ $admindata->forgotlink }}">
                            <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-block btn-primary mb-4" type="button" id="forgotpassbtn" onclick="forgotclick()">Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- [ auth-signin ] end -->
@include('partials.footer')

<script>
    function forgotclick() {
        if (validate_form('forgotpassform')) {
            $("#forgotpassbtn").prop('disabled', true);
            $.ajax({
                type: 'post',
                url: '{{ url("forgotpasslink") }}',
                data: $('#forgotpassform').serialize(),
                success: function(res) {
                    $("#forgotpassbtn").prop('disabled', false);
                    if (res.Success == "true") {
                        alertify.success(res.Message);
                        window.location.href = "{{ url('/a@dmin') }}";
                    } else if (res.Success == "false" && typeof res.data != 'undefined' && res.data != null && res.data != '') {
                        $.each(res.data, function(key, value) {
                            alertify.error(value[0]);
                        });
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function(jqXHR, res, errorThrown) {
                    $("#forgotpassbtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        } else {
            $("#forgotpassbtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        }
    }
</script>
