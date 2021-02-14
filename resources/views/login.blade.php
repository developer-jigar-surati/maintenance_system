@if (Session::get('is_login') != 1)
@include('partials.header',['pagetitle'=>"Login"])
<!-- [ auth-signin ] start -->
<div class="auth-wrapper">
    <div class="auth-content">
        <div class="card">
            <div class="row align-items-center text-center">
                <div class="col-md-12">
                    <div class="card-body">
                        <form id="loginform">
                            <img src="{{ url('assets/images/logo-dark.png') }}" alt="" class="img-fluid mb-4">
                            <h4 class="mb-3 f-w-400">Signin</h4>
                            <div class="form-group mb-3">
                                <label class="floating-label" for="Email">Email address</label>
                                <input type="text" name="email_id" class="form-control notnull email" id="email_id" placeholder="">
                            </div>
                            <div class="form-group mb-4">
                                <label class="floating-label" for="Password">Password</label>
                                <input type="password" name="password" class="form-control notnull" id="Password" placeholder="">
                            </div>
                            <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-block btn-primary mb-4" type="button" id="loginbtn" onclick="loginclick()">Signin</button>
                            <p class="mb-2 text-muted">Forgot password? <a href="javascript:void(0);" onclick="forgotpassclick()" class="f-w-400">Reset</a></p>
                        </form>

                        <form id="forgotpasswordform" style="display: none;">
                            <img src="{{ url('assets/images/logo-dark.png') }}" alt="" class="img-fluid mb-4">
                            <h4 class="mb-3 f-w-400">Forgot Password</h4>
                            
                            <div class="form-group mb-3">
                                <label class="floating-label" for="Email">Email address</label>
                                <input type="text" name="forgotpasswordemail_id" class="form-control notnull email" id="forgotpasswordemail_id" placeholder="">
                            </div>
                            <div class="text-success mb-3 success_msg" style="display: none;"><strong>Forgot Password Mail Sent Successfully Plase Check Mail</strong></div>
                            <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-block btn-primary mb-4" type="button" id="forgotpasswordbtn" onclick="forgotpasswordclick()">Submit</button>
                            <p class="mb-2 text-muted">Know password? <a href="javascript:void(0);" onclick="backtologinclick()" class="f-w-400">Signin</a></p>
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
    function loginclick() {
        if (validate_form('loginform')) {
            $("#loginbtn").prop('disabled', true);
            $.ajax({
                type: 'post',
                url: 'admin_login',
                data: $('#loginform').serialize(),
                success: function(res) {
                    $("#loginbtn").prop('disabled', false);
                    if (res.Success == "true") {
                        $("#email_id").val("");
                        $("#password").val("");
                        alertify.success(res.Message);
                        window.location.href = "{{ url('/dashboard') }}";
                    } else if (res.Success == "false" && typeof res.data != 'undefined' && res.data != null && res.data != '') {
                        $.each(res.data, function(key, value) {
                            alertify.error(value[0]);
                        });
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function(jqXHR, res, errorThrown) {
                    $("#loginbtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        } else {
            $("#loginbtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        }
    }

    function forgotpassclick() {
        $(".success_msg").hide();
        $("#loginform").hide();
        $("#forgotpasswordform").show();
    }
    function backtologinclick() {
        $(".success_msg").hide();
        $("#loginform").show();
        $("#forgotpasswordform").hide();
    }
    
    function forgotpasswordclick() {
        if (validate_form('forgotpasswordform')) {
            $("#forgotpasswordbtn").prop('disabled', true);
            showloader();
            $.ajax({
                type: 'post',
                url: 'admin_forgotpassword',
                data: $('#forgotpasswordform').serialize(),
                success: function(res) {
                    hideloader();
                    $("#forgotpasswordbtn").prop('disabled', false);
                    if (res.Success == "true") {
                        $("#forgotpasswordemail_id").val("");
                        $(".success_msg").show();
                        alertify.success(res.Message);
                    } else if (res.Success == "false" && typeof res.data != 'undefined' && res.data != null && res.data != '') {
                        $.each(res.data, function(key, value) {
                            alertify.error(value[0]);
                        });
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function(jqXHR, res, errorThrown) {
                    hideloader();
                    $("#forgotpasswordbtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        } else {
            $("#loginbtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        }
    }
</script>
@else 
    <script>window.location = "/dashboard";</script>
@endif