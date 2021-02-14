@include('partials.header',['pagetitle'=>"Reset Password"])
<!-- [ Pre-loader ] start -->
<div class="loader-bg">
    <div class="loader-track">
        <div class="loader-fill"></div>
    </div>
</div>
<!-- [ Pre-loader ] End -->
@include('partials.sidebar')

@include('partials.top')

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-content">

        @include('partials.breadcrumb',['active_breadcrumb'=>"Reset Password",'pagename'=>"Reset Password"])

        <!-- [ Main Content ] start -->
        <div class="row">

            <!-- [ form-element ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <!-- <div class="card-header">
                        <h5>Category</h5>
                    </div> -->
                    <div class="card-body">
                        <!-- <h5>Form controls</h5> -->
                        <!-- <hr> -->
                        <div class="row">
                            <div class="col-md-12">
                                <form id="resetpasswordform" method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="old_password">Current Password <span class="required"> *</span></label>
                                        <input type="password" class="form-control notnull" id="old_password" placeholder="Enter Old Password" name="old_password">
                                    </div>
                                    <div class="form-group">
                                        <label for="new_password">New Password <span class="required"> *</span></label>
                                        <input type="password" class="form-control notnull" id="new_password" placeholder="Enter New Password" name="new_password">
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm Password <span class="required"> *</span></label>
                                        <input type="password" class="form-control notnull" id="confirm_password" placeholder="Enter Confirm Password" name="confirm_password">
                                    </div>

                                    <div class="clearfix"></div>
                                    <input type="hidden" id="admin_id" name="admin_id" value="{{ Session::get('admin_id') }}">
                                    <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" class="btn btn-primary" id="resetpassbtn">Submit</button>
                                    <button type="reset" class="btn btn-default">Reset</button>
                                </form>
                            </div>                            
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ form-element ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
@include('partials.footer')
<script>

    $('form').submit(function(event) {
        event.preventDefault();
        if (validate_form('resetpasswordform')) {
            $("#resetpassbtn").prop('disabled', true);
            showloader();
            var frmdata = new FormData($(this)[0]);
            $.ajax({
                type: 'post',
                url: "resetpassword",
                data: frmdata,
                cache: false,
                contentType: false,
                processData: false,
                success: function(res) {
                    hideloader();
                    $("#resetpassbtn").prop('disabled', false);
                    if (res.Success == "true") {
                        alertify.success(res.Message);
                        $("#old_password").val("");
                        $("#new_password").val("");
                        $("#confirm_password").val("");
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
                    $("#resetpassbtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        } else {
            $("#resetpassbtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        }
    });
</script>