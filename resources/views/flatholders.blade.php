@include('partials.header',['pagetitle'=>"Flat Holder"])
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

        @include('partials.breadcrumb',['active_breadcrumb'=>"Flat Holder",'pagename'=>"Flat Holder"])

        <!-- [ Main Content ] start -->
        <div class="row">

            <!-- [ form-element ] start -->
            <div class="col-sm-12">
                <div class="card">
                    @if(Session::get('user_role') != 3)
                    <div class="card-header addeditbtndiv">
                        <button type="button" class="btn btn-success btn-sm btn-round has-ripple" id="addeditbtn" onclick="addeditflatholderfun()" style="float: right;">
                            <i class="feather icon-plus"></i> Add Flat Holder
                        </button>
                    </div>
                    @endif
                    <div class="card-body">
                        <!-- <h5>Form controls</h5> -->
                        <!-- <hr> -->
                        <div class="row">
                            <div class="col-md-12 addeditflatholder" style="display: none;">
                                <form id="flatholderform" method="post" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-group fill">
                                                <label for="building">Building <span class="required"> *</span></label>
                                                <select class="form-control dropdown" id="building" name="building">
                                                    <option value="0">Please Select</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="flat_no">Flat No <span class="required"> *</span></label>
                                                <input type="text" class="form-control notnull" id="flat_no" maxlength="50" placeholder="Enter Flat No" name="flat_no">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="name">Name <span class="required"> *</span></label>
                                                <input type="text" class="form-control notnull" id="name" maxlength="50" placeholder="Enter Name" name="name">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="mobile_no">Mobile No <span class="required"> *</span></label>
                                                <input type="text" class="form-control notnull" id="mobile_no" placeholder="Enter Mobile No" onkeypress="return isNumber(event)" name="mobile_no">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="email_id">Email Id <span class="required"> *</span></label>
                                                <input type="text" class="form-control notnull" id="email_id" placeholder="Enter Email Id" name="email_id">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group fill">
                                                <label for="flat_type">Type <span class="required"> *</span></label>
                                                <select class="form-control dropdown" onchange="getrentfieldfun(this.value,'add','')" id="flat_type" name="flat_type">
                                                    <option value="0">Please Select</option>
                                                    <option value="1">Owner</option>
                                                    <option value="2">Rent</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-6 ifrentdiv">
                                            <div class="form-group">
                                                <label for="owner_name">Owner Name <span class="required"> *</span></label>
                                                <input type="text" class="form-control" id="owner_name" maxlength="50" placeholder="Enter Owner Name" name="owner_name">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 ifrentdiv">
                                            <div class="form-group">
                                                <label for="owner_mobile_no">Owner Mobile No <span class="required"> *</span></label>
                                                <input type="text" class="form-control" id="owner_mobile_no" placeholder="Enter Owner Mobile No" onkeypress="return isNumber(event)" name="owner_mobile_no">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 ifrentdiv">
                                            <div class="form-group">
                                                <label for="owner_email_id">Owner Email Id <span class="required"> *</span></label>
                                                <input type="text" class="form-control" id="owner_email_id" placeholder="Enter Owner Email Id" name="owner_email_id">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 ifrentdiv">
                                            <div class="form-group">
                                                <label for="owner_address">Owner Address <span class="required"> *</span></label>
                                                <textarea id="owner_address" class="form-control" name="owner_address"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-sm-4 ifrentdiv">
                                            <div class="form-group fill">
                                                <label for="is_aggrement">Rent Aggrement <span class="required"> *</span></label>
                                                <select class="form-control" onchange="getaggrementfieldfun(this.value,'add','')" id="is_aggrement" name="is_aggrement">
                                                    <option value="0">Please Select</option>
                                                    <option value="1">Yes</option>
                                                    <option value="2">No</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-4 ifrentaggrement">
                                            <div class="form-group">
                                                <label for="start_date">Start Date </label>
                                                <input type="date" class="form-control" id="start_date" placeholder="Select Date" name="start_date">
                                            </div>
                                        </div>
                                        <div class="col-sm-4 ifrentaggrement">
                                            <div class="form-group">
                                                <label for="end_date">End Date </label>
                                                <input type="date" class="form-control" id="end_date" placeholder="Select Date" name="end_date">
                                            </div>
                                        </div>
                                        <div class="col-sm-4 ifrentaggrement">
                                            <div class="form-group">
                                                <label for="rent_document">Rent Document <small>(<strong>Note:</strong> Allow only jpeg, jpg, png, pdf)</small></label>
                                                <input type="file" class="form-control" onChange="handleRentDocument(event)" id="rent_document" name="rent_document">
                                            </div>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group fill">
                                                <label for="is_president">Is President <span class="required"> *</span></label>
                                                <select class="form-control" id="is_president" name="is_president">
                                                    <option value="1">Yes</option>
                                                    <option value="2" selected>No</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-12">
                                            <div class="form-group">
                                                <label for="status">Status <span class="required"> *</span></label>
                                                <div class="custom-control custom-radio custom-control-inline">
                                                    <input type="radio" id="active" name="is_active" value="1" class="custom-control-input" checked>
                                                    <label class="custom-control-label" for="active">Active</label>
                                                </div>
                                                <div class="custom-control custom-radio custom-control-inline">
                                                    <input type="radio" id="inactive" name="is_active" value="0" class="custom-control-input">
                                                    <label class="custom-control-label" for="inactive">Inactive</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12" style="margin-top: 10px;">
                                            <input type="hidden" id="fholder_id" name="fholder_id" value="0">
                                            <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                                            <button type="submit" class="btn btn-primary" id="flatholderbtn">Submit</button>
                                            &nbsp;&nbsp;&nbsp;
                                            <button type="button" class="btn btn-primary" onclick="refreshdata()" id="backbtn">Back</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-12 flatholderlist" style="display: block;">
                                <div class="table-border-style">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="datatable" style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>Building</th>
                                                    <th>Flat No</th>
                                                    <th>Name</th>
                                                    <th>Mobile No</th>
                                                    <th>Email Id</th>
                                                    <th>Type</th>
                                                    @if(Session::get('user_role') != 3)
                                                    <th>Created Datetime</th>
                                                    <th>Modified Datetime</th>
                                                    <th>Action</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>

                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th>Building</th>
                                                    <th>Flat No</th>
                                                    <th>Name</th>
                                                    <th>Mobile No</th>
                                                    <th>Email Id</th>
                                                    <th>Type</th>
                                                    @if(Session::get('user_role') != 3)
                                                    <th>Created Datetime</th>
                                                    <th>Modified Datetime</th>
                                                    <th>Action</th>
                                                    @endif
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>


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

<!-- show details modal -->
<div id="rentDetailsModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="d-flex modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Float Holder Details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" onclick="handleRentalModalClose()" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="col-md-12">
                    <div class="table-border-style">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="datatable" style="width: 100%;">
                                <tbody id="rentDetailstable">
                                    
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="handleRentalModalClose()" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- show details modal -->

@include('partials.footer')

<script>
    $(document).ready(function() {
        getbuldings();
        refreshdata();
    });

    function refreshdata() {

        $(".ifrentdiv").hide();
        $("#owner_name").removeClass('notnull');
        $("#owner_mobile_no").removeClass('notnull');
        $("#owner_email_id").removeClass('notnull');
        $("#owner_address").removeClass('notnull');
        $("#is_aggrement").removeClass('dropdown');

        $("#email_id").prop('readonly',false);

        $(".ifrentaggrement").hide();
        $("#start_date").removeClass('notnull');
        $("#end_date").removeClass('notnull');

        $(".flatholderlist").show();
        $(".addeditflatholder").hide();
        $("#addeditbtn").show();
        $("#fholder_id").val("0");


        $("#flatholderform").trigger("reset");
        var user_role = "{{ Session::get('user_role') }}";

        if (user_role == 3) {
            $('#datatable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "processing": true,
                "serverSide": true,
                "destroy": true,
                "order": [],
                "ajax": {
                    "url": "getflatholders",
                    "type": "POST",
                    "dataType": "json",
                    "data": function(d) {
                        d.service = 'getflatholders';
                        d._token = '{{ csrf_token() }}';
                    }
                },
                "columns": [{
                        "data": "building_name",
                        "name": "building_name",
                        "searchable": true,
                        "sortable": false
                    }, {
                        "data": "flat_no",
                        "name": "flat_no",
                        "searchable": true,
                        "sortable": true
                    },
                    {
                        "data": "name",
                        "name": "name",
                        "searchable": true,
                        "sortable": true,
                        "render": function(data, type, row) {
                            var html = '<div class="d-flex"><div>'+row.name+'</div>';
                            if (row.is_president == 1) {
                                html += '<div class="ml-1" title="President"><i class="feather icon-award"></i></div></div>';
                            }
                            return html;
                        }
                    },
                    {
                        "data": "mobile_no",
                        "name": "mobile_no",
                        "searchable": true,
                        "sortable": false
                    },
                    {
                        "data": "email_id",
                        "name": "email_id",
                        "searchable": true,
                        "sortable": false
                    },
                    {
                        "data": "flat_type",
                        "name": "flat_type",
                        "searchable": false,
                        "sortable": false
                    },
                    // {
                    //     "data": "createddatetime",
                    //     "name": "createddatetime",
                    //     "searchable": false,
                    //     "sortable": false
                    // },
                    // {
                    //     "data": "modifireddatetime",
                    //     "name": "modifireddatetime",
                    //     "searchable": false,
                    //     "sortable": false
                    // }
                ],
                "columnDefs": [{
                    "targets": 3,
                    "render": function(data, type, row) {
                        return '<div>' +
                            '<a style="text-decoration:none" href="tel://' + row.mobile_no + '">' + row.mobile_no + '</a>' +
                            '</div>';

                    }
                }, {
                    "targets": 4,
                    "render": function(data, type, row) {
                        return '<div>' +
                            '<a style="text-decoration:none" href="mailto://' + row.email_id + '">' + row.email_id + '</a>' +
                            '</div>';

                    }
                }],
                "pageLength": 10
            });
        } else {
            $('#datatable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "processing": true,
                "serverSide": true,
                "destroy": true,
                "order": [],
                "ajax": {
                    "url": "getflatholders",
                    "type": "POST",
                    "dataType": "json",
                    "data": function(d) {
                        d.service = 'getflatholders';
                        d._token = '{{ csrf_token() }}';
                    }
                },
                "columns": [{
                        "data": "building_name",
                        "name": "building_name",
                        "searchable": true,
                        "sortable": false
                    }, {
                        "data": "flat_no",
                        "name": "flat_no",
                        "searchable": true,
                        "sortable": true
                    },
                    {
                        "data": "name",
                        "name": "name",
                        "searchable": true,
                        "sortable": true,
                        "render": function(data, type, row) {
                            var html = '<div class="d-flex"><div>'+row.name+'</div>';
                            if (row.is_president == 1) {
                                html += '<div class="ml-1" title="President"><i class="feather icon-award"></i></div></div>';
                            }
                            return html;
                        }
                    },
                    {
                        "data": "mobile_no",
                        "name": "mobile_no",
                        "searchable": true,
                        "sortable": false
                    },
                    {
                        "data": "email_id",
                        "name": "email_id",
                        "searchable": true,
                        "sortable": false
                    },
                    {
                        "data": "flat_type",
                        "name": "flat_type",
                        "searchable": false,
                        "sortable": false,
                        "render": function(data, type, row) {
                            var html = '<div class="d-flex"><div>'+row.flat_type+'</div>';
                            if (row.flat_type == 'Rent') {
                                html += '<div class="ml-1" title="Details" onclick="showRentDetails(\'' + row.fholder_id + '\')" style="cursor:pointer"><i class="feather icon-info"></i></div></div>';
                            }
                            return html;
                        }
                    },
                    {
                        "data": "createddatetime",
                        "name": "createddatetime",
                        "searchable": false,
                        "sortable": false
                    },
                    {
                        "data": "modifireddatetime",
                        "name": "modifireddatetime",
                        "searchable": false,
                        "sortable": false
                    },
                    {
                        "data": "is_active",
                        "name": "is_active",
                        "searchable": false,
                        "sortable": false
                    }
                ],
                "columnDefs": [{
                    "targets": 3,
                    "render": function(data, type, row) {
                        return '<div>' +
                            '<a style="text-decoration:none" href="tel://' + row.mobile_no + '">' + row.mobile_no + '</a>' +
                            '</div>';

                    }
                }, {
                    "targets": 4,
                    "render": function(data, type, row) {
                        return '<div>' +
                            '<a style="text-decoration:none" href="mailto://' + row.email_id + '">' + row.email_id + '</a>' +
                            '</div>';

                    }
                }, {
                    "targets": 8,
                    "render": function(data, type, row) {
                        if (row['is_active'].toString().toLowerCase() == "active") {
                            return '<div>' +
                                '<div title="Status" class="flex text-theme-9" style="cursor:pointer;;float:left;padding-left:5px;" onClick="changestatus(\'' + row.fholder_id + '\',1)">' +
                                '<i class="text-success feather icon-check fa-2x"></i>' +
                                '</div>' +
                                '<div title="Edit" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="editflatholderbyid(\'' + row.fholder_id + '\')">' +
                                '<i class="text-primary feather icon-edit-2 fa-2x"></i>' +
                                '</div>' +
                                '<div title="Delete" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="deleteflatholder(\'' + row.fholder_id + '\')">' +
                                '<i class="text-danger feather icon-trash-2 fa-2x"></i>' +
                                '</div>' +
                                '<div title="' + ((row.is_credentials_send == 1) ? 'Login credentials already send' : 'Send login credentials') + '" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="sendlogincredentialsfun(\'' + row.fholder_id + '\',' + row.is_credentials_send + ')">' +
                                '<i class="' + ((row.is_credentials_send == 1) ? 'text-muted' : 'text-info') + ' feather icon-mail fa-2x"></i>' +
                                '</div>' +
                                '</div>';
                        } else if (row['is_active'].toString().toLowerCase() == "in active") {
                            return '<div>' +
                                '<div title="Status" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="changestatus(\'' + row.fholder_id + '\',0)">' +
                                '<i class="text-danger feather icon-x fa-2x"></i>' +
                                '</div>' +
                                '<div title="Edit" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="editflatholderbyid(\'' + row.fholder_id + '\')">' +
                                '<i class="text-primary feather icon-edit-2 fa-2x"></i>' +
                                '</div>' +
                                '<div title="Delete" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="deleteflatholder(\'' + row.fholder_id + '\')">' +
                                '<i class="text-danger feather icon-trash-2 fa-2x"></i>' +
                                '</div>' +
                                '<div title="' + ((row.is_credentials_send == 1) ? 'Login credentials already send' : 'Send login credentials') + '" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="sendlogincredentialsfun(\'' + row.fholder_id + '\',' + row.is_credentials_send + ')">' +
                                '<i class="' + ((row.is_credentials_send == 1) ? 'text-muted' : 'text-info') + ' feather icon-mail fa-2x"></i>' +

                                '</div>' +
                                '</div>';
                        }
                    }
                }, ],
                "pageLength": 10
            });
        }
    }

    function getbuldings() {
        $.ajax({
            type: 'post',
            url: 'getbuldingsforflatholder',
            data: {
                "_token": '{{ csrf_token() }}'
            },
            method: 'POST',
            success: function(res) {
                if (res.Success == "true") {
                    if (res.data.length > 0) {
                        $("#building").html('');
                        if (res.data.length == 1) {
                            html = '<option value="' + res.data['0']['building_id'] + '">' + res.data['0']['building_name'] + '</option>';
                        } else {
                            html = '<option value="0">Please Select</option>';
                            $.each(res.data, function(k, v) {
                                html += '<option value="' + v['building_id'] + '">' + v['building_name'] + '</option>';
                            });
                        }
                        $("#building").html(html);
                    }
                }
            },
            error: function(jqXHR, res, errorThrown) {
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
            }
        });
    }

    function addeditflatholderfun() {
        $(".flatholderlist").hide();
        $(".addeditflatholder").show();
        $("#addeditbtn").hide();
    }

    $('form').submit(function(event) {
        event.preventDefault();
        $(".invalid").removeClass("invalid");
        if (!validate_form('flatholderform')) {
            $("#flatholderbtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        } else {
            $("#flatholderbtn").prop('disabled', true);
            showloader();
            if ($("#fholder_id").val() == "0") {
                url = "addflatholder";
            } else {
                url = "updateflatholder";
            }
            var frmdata = new FormData($(this)[0]);
            $.ajax({
                type: 'post',
                url: url,
                data: frmdata,
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                success: function(res) {
                    hideloader();
                    $("#flatholderbtn").prop('disabled', false);
                    if (res.Success == "true") {
                        alertify.success(res.Message);
                        refreshdata();
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function(jqXHR, res, errorThrown) {
                    hideloader();
                    $("#flatholderbtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        }
    });

    function changestatus(fholder_id, status) {
        alertify.confirm('Change Status', 'Are you sure you want to change status?',
        function() {
            showloader();
            $.ajax({
                type: 'post',
                url: 'changeflatholderstatus',
                data: {
                    "is_active": status,
                    "fholder_id": fholder_id,
                    "_token": '{{ csrf_token() }}'
                },
                success: function(res) {
                    hideloader();
                    if (res.Success == "true") {
                        refreshdata();
                        alertify.success(res.Message);
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function(jqXHR, res, errorThrown) {
                    hideloader();
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        },
        function() {
            console.log('Confirm status close.'); 
            // alertify.error('Cancel')
        });
    }

    function deleteflatholder(fholder_id) {
        alertify.confirm('Delete Flat holder', 'Are you sure you want to delete?',
        function() {
            showloader();
            $.ajax({
                type: 'post',
                url: 'deleteflatholder',
                data: {
                    "fholder_id": fholder_id,
                    "_token": '{{ csrf_token() }}'
                },
                success: function(res) {
                    hideloader();
                    if (res.Success == "true") {
                        refreshdata();
                        alertify.success(res.Message);
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function(jqXHR, res, errorThrown) {
                    hideloader();
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        },
        function() {
            console.log('Confirm delete close.'); 
            // alertify.error('Cancel')
        });
    }

    function editflatholderbyid(fholder_id) {
        $('.invalid').removeClass('invalid');
        showloader();
        $.ajax({
            type: 'post',
            url: 'getflatholderbyid',
            data: {
                "fholder_id": fholder_id,
                "_token": '{{ csrf_token() }}'
            },
            success: function(res) {
                hideloader();
                console.log(res);
                if (res.Success == "true") {

                    $(".flatholderlist").hide();
                    $(".addeditflatholder").show();
                    $("#addeditbtn").hide();

                    $("#building").val(res.data.building_id);
                    $("#flat_no").val(res.data.flat_no);
                    $("#name").val(res.data.name);
                    $("#mobile_no").val(res.data.mobile_no);
                    $("#email_id").val(res.data.email_id);
                    $("#flat_type").val(res.data.flat_type);
                    $("#fholder_id").val(res.data.fholder_id);
                    $("#is_president").val(res.data.is_president);
                    $("#email_id").prop('readonly',true);

                    getrentfieldfun(res.data.flat_type, 'edit', res.data);

                    if (res.data.is_active == 1) {
                        $('#active').prop('checked', true);
                    } else {
                        $('#inactive').prop('checked', true);
                    }
                } else {
                    alertify.error(res.Message);
                }
            },
            error: function(jqXHR, res, errorThrown) {
                hideloader();
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
                alertify.error(res.Message)
            }
        });
    }

    function getrentfieldfun(currentval, type, data) {
        if (currentval == 2) {
            $(".ifrentdiv").show();
            $("#owner_name").addClass('notnull');
            $("#owner_mobile_no").addClass('notnull');
            $("#owner_email_id").addClass('notnull');
            $("#owner_address").addClass('notnull');
            $("#is_aggrement").addClass('dropdown');

            if (type == 'edit') {
                $("#owner_name").val(data.owner_name);
                $("#owner_mobile_no").val(data.owner_mobile_no);
                $("#owner_email_id").val(data.owner_email);
                $("#owner_address").val(data.owner_address);
                $("#is_aggrement").val(data.rent_aggrement);

                getaggrementfieldfun(data.rent_aggrement, type, data);
            }

        } else {
            $(".ifrentdiv").hide();
            $("#owner_name").removeClass('notnull');
            $("#owner_mobile_no").removeClass('notnull');
            $("#owner_email_id").removeClass('notnull');
            $("#owner_address").removeClass('notnull');
            $("#is_aggrement").removeClass('dropdown');
        }
    }

    function getaggrementfieldfun(currentval, type, data) {
        if (currentval == 1) {
            $(".ifrentaggrement").show();
            $("#start_date").addClass('notnull');
            $("#end_date").addClass('notnull');

            if (type == 'edit') {
                $("#start_date").val(data.aggrement_start_date);
                $("#end_date").val(data.aggrement_end_date);
            }

        } else {
            $(".ifrentaggrement").hide();
            $("#start_date").removeClass('notnull');
            $("#end_date").removeClass('notnull');
        }
    }

    function sendlogincredentialsfun(fholder_id, is_credentials_send) {
        if (is_credentials_send == '1') {
            alertify.alert("Send Credentials", "Credentials Already Sent!");
            return false;
        }
        var generated_pass = generatePassword(8);
        alertify.prompt("Enter Credentials", 'Please Enter Password', generated_pass, function(evt, value) {
            showloader();
            $.ajax({
                type: 'post',
                url: 'saveflatholderasadmin',
                data: {
                    "fholder_id": fholder_id,
                    "is_credentials_send": is_credentials_send,
                    "password": value,
                    "_token": '{{ csrf_token() }}'
                },
                success: function(res) {
                    hideloader();
                    if (res.Success == "true") {
                        refreshdata();
                        alertify.success(res.Message);
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function(jqXHR, res, errorThrown) {
                    hideloader();
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        }, function() {
            alertify.error('You click Cancel')
        });
    }

    function generatePassword(length) {
        var charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",
            retVal = "";
        for (var i = 0, n = charset.length; i < length; ++i) {
            retVal += charset.charAt(Math.floor(Math.random() * n));
        }
        return retVal;
    }

    function showRentDetails(fholder_id) {
        console.log('fholder_id', fholder_id);
        showloader();
        $.ajax({
            type: 'post',
            url: 'getRentDetails',
            data: {
                "fholder_id": fholder_id,
                "_token": '{{ csrf_token() }}'
            },
            success: function(res) {
                hideloader();
                if (res.Success == "true") {
                    $("#rentDetailsModal").modal('show');
                    const resData = res.data;
                    console.log('resData', resData);
                    let html = '';
                    Object.entries(resData).map((val) => {
                        console.log('val', val);
                        html += `
                            <tr>
                                <th>${val[0]}</th>
                                <td>${val[1]}</td>
                            </tr>
                        `;
                    });
                    $("#rentDetailstable").html(html);
                } else {
                    alertify.error(res.Message);
                }
            },
            error: function(jqXHR, res, errorThrown) {
                hideloader();
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
                alertify.error(res.Message)
            }
        });
    }
    
    function handleRentalModalClose() {
        $("#rentDetailsModal").modal('hide');
    }

    function handleRentDocument(event) {
        var input = event.target;
        var fileExtension = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if(typeof input.files[0] != 'undefined'  && typeof input.files[0].type != 'undefined' && $.inArray(input.files[0].type, fileExtension) !== -1){
            if(input.files[0].size > 10485760) {
                $("#rent_document").val('');
                alertify.error("Not allow file more than 10MB.");
                return false;
            }
        } else {
            $("#rent_document").val('');
            alertify.error("Invalid File Type");
        }
    }
</script>