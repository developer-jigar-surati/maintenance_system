@include('partials.header',['pagetitle'=>"Building"])
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

        @include('partials.breadcrumb',['active_breadcrumb'=>"Building",'pagename'=>"Building"])

        <!-- [ Main Content ] start -->
        <div class="row">

            <!-- [ form-element ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <!-- <div class="card-header">
                        <h5>Building</h5>
                    </div> -->
                    <div class="card-body">
                        <!-- <h5>Form controls</h5> -->
                        <!-- <hr> -->
                        <div class="row">
                            @if(Session::get('user_role') != 3)
                            <div class="col-md-3 addeditbuilding">
                                <form id="buildingform" method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="building_name">Building name <span class="required"> *</span></label>
                                        <input type="text" class="form-control notnull" id="building_name" placeholder="Enter Building name" name="building_name">
                                    </div>

                                    <div class="form-group">
                                        <label for="total_flats">Total Flats <span class="required"> *</span></label>
                                        <input type="number" class="form-control notnull" id="total_flats" placeholder="Enter Total Flats" name="total_flats">
                                    </div>

                                    <div class="form-group">
                                        <label for="maintenance">Maintenance <small>(Per Flat wise)</small><span class="required"> *</span></label>
                                        <input type="number" class="form-control notnull" id="maintenance" placeholder="Enter maintenance" name="maintenance">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description"> Short Description </label>
                                        <textarea id="description" maxlength="100" class="form-control" name="description"></textarea>
                                    </div>

                                    <!-- <div class="form-group">
                                        <label for="is_active">Status <span class="required"> *</span></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="is_active" id="active" value="1" checked="checked">
                                            <label class="form-check-label" for="gridRadios1">Active</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="is_active" id="inactive" value="0">
                                            <label class="form-check-label" for="gridRadios1">Inactive</label>
                                        </div>
                                    </div> -->
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
                                    <div class="clearfix"></div>
                                    <input type="hidden" id="building_id" name="building_id" value="0">
                                    <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" class="btn btn-primary" id="buildingbtn">Submit</button>
                                    <button type="reset" class="btn btn-secondary d-none cancelbtn">Cancel</button>
                                </form>
                            </div>
                            <div class="col-md-9 buildinglist">
                            @else
                            <div class="col-md-12 buildinglist">
                            @endif
                            
                                <div class="table-border-style">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="datatable" style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Total Flats</th>
                                                    <th>Maintenance</th>
                                                    <th>Created Datetime</th>
                                                    <th>Modified Datetime</th>
                                                    @if(Session::get('user_role') != 3)
                                                    <th>Action</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Total Flats</th>
                                                    <th>Maintenance</th>
                                                    <th>Created Datetime</th>
                                                    <th>Modified Datetime</th>
                                                    @if(Session::get('user_role') != 3)
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
<div id="buidingDetailsModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="d-flex modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Buiding Update Logs</h5>
                <button type="button" class="close" data-bs-dismiss="modal" onclick="handlebuidingDetailsModalClose()" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="col-md-12" id="buildingDetailstable">
                    
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="handlebuidingDetailsModalClose()" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- show details modal -->

@include('partials.footer')
<script>
    $(document).ready(function() {
        refreshdata();
    });

    function resetFromData() {
        $("#building_name").val("");
        $("#description").val("");
        $('#total_flats').val("");
        $('#maintenance').val("");
        $("#building_id").val("0");
        $('#active').prop('checked', true);
    }

    function refreshdata() {
        resetFromData();
                       
        var user_role = "{{ Session::get('user_role') }}";
        if(user_role == 3) {
            $('#datatable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "processing": true,
                "serverSide": true,
                "destroy": true,
                "order": [],
                "ajax": {
                    "url": "getbuildings",
                    "type": "POST",
                    "dataType": "json",
                    "data": function(d) {
                        d.service = 'getbuildings';
                        d._token = '{{ csrf_token() }}';
                    }
                },
                "columns": [
                    {
                        "data": "building_name",
                        "name": "building_name",
                        "searchable": true,
                        "sortable": true
                    },
                    {
                        "data": "total_flats",
                        "name": "total_flats",
                        "searchable": true,
                        "sortable": true
                    },
                    {
                        "data": "maintenance",
                        "name": "maintenance",
                        "searchable": false,
                        "sortable": false
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
                    }
                ],
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
                    "url": "getbuildings",
                    "type": "POST",
                    "dataType": "json",
                    "data": function(d) {
                        d.service = 'getbuildings';
                        d._token = '{{ csrf_token() }}';
                    }
                },
                "columns": [
                    {
                        "data": "building_name",
                        "name": "building_name",
                        "searchable": true,
                        "sortable": true
                    },
                    {
                        "data": "total_flats",
                        "name": "total_flats",
                        "searchable": true,
                        "sortable": true
                    },
                    {
                        "data": "maintenance",
                        "name": "maintenance",
                        "searchable": false,
                        "sortable": false
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
                "columnDefs": [
                    {
                        "targets": 5,
                        "render": function(data, type, row) {
                            if (row['is_active'].toString().toLowerCase() == "active") {
                                let html = '';
                                if (row.last_data_log == 1) {
                                    html += '<div title="change logs" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="showbuildingLogs(\'' + row.building_id + '\')">'+
                                                '<i class="text-secondary feather icon-info fa-2x"></i>'+
                                            '</div>';
                                }
                                return '<div>'+ html +
                                            '<div title="Edit" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="editbuilding(\'' + row.building_id + '\')">'+
                                                '<i class="text-primary feather icon-edit-2 fa-2x"></i>'+
                                            '</div>' +
                                            '<div title="Status" class="flex text-theme-9" style="cursor:pointer;;float:left;padding-left:5px;" onClick="changestatus(\'' + row.building_id + '\',1)">'+
                                                '<i class="text-success feather icon-check fa-2x"></i>'+
                                            '</div>'+
                                            '<div title="Delete" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="deletebuilding(\'' + row.building_id + '\')">'+
                                                '<i class="text-danger feather icon-trash-2 fa-2x"></i>'+
                                            '</div>' +
                                        '</div>';
                            } else if (row['is_active'].toString().toLowerCase() == "in active") {
                                let html = '';
                                if (row.last_data_log == 1) {
                                    html += '<div title="change logs" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="showbuildingLogs(\'' + row.building_id + '\')">'+
                                                '<i class="text-secondary feather icon-info fa-2x"></i>'+
                                            '</div>';
                                }
                                return '<div>'+ html +
                                            '<div title="Edit" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="editbuilding(\'' + row.building_id + '\')">'+
                                                '<i class="text-primary feather icon-edit-2 fa-2x"></i>'+
                                            '</div>' +
                                            '<div title="Status" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="changestatus(\'' + row.building_id + '\',0)">'+
                                                '<i class="text-danger feather icon-x fa-2x"></i>'+
                                            '</div>'+
                                            '<div title="Delete" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="deletebuilding(\'' + row.building_id + '\')">'+
                                                '<i class="text-danger feather icon-trash-2 fa-2x"></i>'+
                                            '</div>' +
                                        '</div>';
                            }
                        }
                    },
                ],
                "pageLength": 10
            });
        } 
    }

    $('form').submit(function(event) {
        event.preventDefault();
        if (validate_form('buildingform')) {
            $("#buildingbtn").prop('disabled', true);
            showloader();
            if($("#building_id").val() == "0"){
                curl = "savebuilding";
            } else {
                curl = "updatebuilding";
            }

            var frmdata = new FormData($(this)[0]);
            $.ajax({
                type: 'post',
                url: curl,
                data: frmdata,
                cache: false,
                contentType: false,
                processData: false,
                success: function(res) {
                    hideloader();
                    $("#buildingbtn").prop('disabled', false);
                    if (res.Success == "true") {
                        alertify.success(res.Message);
                        refreshdata();
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
                    $("#buildingbtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        } else {
            $("#buildingbtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        }
    });

    function changestatus(building_id,status) {
        alertify.confirm('Change Status', 'Are you sure you want to change status?', 
            function() {
                showloader();
                $.ajax({
                    type: 'post',
                    url: 'changebuildingstatus',
                    data: {"is_active":status,"building_id":building_id,"_token": '{{ csrf_token() }}' },
                    success: function (res) {
                        hideloader();
                        if(res.Success == "true"){
                            refreshdata();
                            alertify.success(res.Message);
                        } else {
                            alertify.error(res.Message);
                        }
                    },
                    error: function (jqXHR,res,errorThrown) {
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

    function deletebuilding(building_id) {
        alertify.confirm('Delete Building', 'Are you sure you want to delete?',
        function() {
            showloader();
            $.ajax({
                type: 'post',
                url: 'deletebuilding',
                data: {"building_id":building_id,"_token": '{{ csrf_token() }}' },
                success: function (res) {
                    hideloader();
                    if(res.Success == "true"){
                        refreshdata();
                        alertify.success(res.Message);
                    } else {
                        alertify.error(res.Message);
                    }
                },
                error: function (jqXHR,res,errorThrown) {
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

    function editbuilding(building_id) {
        $('.invalid').removeClass('invalid');
        $('.cancelbtn').removeClass('d-none');
        
        showloader();
        $.ajax({
            type: 'post',
            url: 'getbuildingbyid',
            data: {"building_id":building_id,"_token": '{{ csrf_token() }}' },
            success: function (res) {
                hideloader();
                if(res.Success == "true"){
                    $("#building_name").val(res.data.building_name);
                    $("#description").val(res.data.description);
                    $("#total_flats").val(res.data.total_flats);
                    $("#maintenance").val(res.data.maintenance);
                    $("#building_id").val(res.data.building_id);
                    
                    if(res.data.is_active == 1){
                        $('#active').prop('checked', true);
                    } else {
                        $('#inactive').prop('checked', true);
                    }
                    
                    
                } else {
                    alertify.error(res.Message);
                }
            },
            error: function (jqXHR,res,errorThrown) {
                hideloader();
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
                alertify.error(res.Message)   
            }
        });
    }

    function showbuildingLogs(building_id) {
        showloader();
        $.ajax({
            type: 'post',
            url: 'getbuildingLogs',
            data: {
                "building_id": building_id,
                "_token": '{{ csrf_token() }}'
            },
            success: function(res) {
                hideloader();
                if (res.Success == "true") {
                    $("#buidingDetailsModal").modal('show');
                    const resData = res.data;
                    console.log('resData', resData);
                    let html = '';
                    resData.map((val) => {
                        console.log('val', val);
                        html += `
                            <tr>
                                <th>${val}</th>
                                <td>${val}</td>
                            </tr>
                        `;
                    });
                    $("#buildingDetailstable").html(html);
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

    function handlebuidingDetailsModalClose() {
        $("#buidingDetailsModal").modal('hide');
    }
</script>