@include('partials.header',['pagetitle'=>"Category"])
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

        @include('partials.breadcrumb',['active_breadcrumb'=>"Category",'pagename'=>"Category"])

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
                            <div class="col-md-3 addeditcategory">
                                <form id="categoryform" method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="category_name">Category name <span class="required"> *</span></label>
                                        <input type="text" class="form-control notnull" id="category_name" placeholder="Enter category name" name="category_name">
                                    </div>

                                    <div class="form-group fill">
                                        <label for="cat_type">Type <span class="required"> *</span></label>
                                        <select class="form-control dropdown" id="cat_type" name="cat_type">
                                            <option value="0">Please Select</option>
                                            <option value="1">Dr</option>
                                            <option value="2">Cr</option>
                                            <option value="3">Both</option>
                                        </select>
                                    </div>

                                    
                                    <div class="form-group">
                                        <label for="description"> Short Description <span class="required"> *</span> <small>( max length : 100 char )</small></label>
                                        <textarea id="description" maxlength="100" class="form-control notnull" name="description"></textarea>
                                    </div>

                                    <div class="clearfix"></div>
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
                                    <input type="hidden" id="category_id" name="category_id" value="0">
                                    <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" class="btn btn-primary" id="categorybtn">Submit</button>
                                </form>
                            </div>
                            <div class="col-md-9 categorylist">
                                <div class="table-border-style">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="datatable" style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Type</th>
                                                    <th>Created Datetime</th>
                                                    <th>Modified Datetime</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Type</th>
                                                    <th>Created Datetime</th>
                                                    <th>Modified Datetime</th>
                                                    <th>Action</th>
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
@include('partials.footer')
<script>
    $(document).ready(function() {
        refreshdata();
    });

    function refreshdata() {
        $("#category_name").val("");
        $("#cat_type").val(0);
        $("#description").val("");
        $("#category_id").val("0");
        
        $('#datatable').DataTable({
            "responsive": true,
            "lengthChange": true,
            "processing": false,
            "serverSide": true,
            "destroy": true,
            "order": [],
            "ajax": {
                "url": "getcategory",
                "type": "POST",
                "dataType": "json",
                "data": function(d) {
                    d.service = 'getcategory';
                    d._token = '{{ csrf_token() }}';
                }
            },
            "columns": [
                {
                    "data": "category_name",
                    "name": "category_name",
                    "searchable": true,
                    "sortable": true
                },
                {
                    "data": "cat_type",
                    "name": "cat_type",
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
                    "targets": 4,
                    "render": function(data, type, row) {
                        if (row['is_active'].toString().toLowerCase() == "active") {
                            return '<div>'+
                                        '<div title="Status" class="flex text-theme-9" style="cursor:pointer;;float:left;padding-left:5px;" onClick="changestatus(\'' + row.categoryid + '\',1)">'+
                                            '<i class="text-success feather icon-check fa-2x"></i>'+
                                        '</div>'+
                                        '<div title="Edit" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="editcategory(\'' + row.categoryid + '\')">'+
                                            '<i class="text-primary feather icon-edit-2 fa-2x"></i>'+
                                        '</div>' +
                                        '<div title="Delete" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="deletecategory(\'' + row.categoryid + '\')">'+
                                            '<i class="text-danger feather icon-trash-2 fa-2x"></i>'+
                                        '</div>' +
                                    '</div>';
                        } else if (row['is_active'].toString().toLowerCase() == "in active") {
                            return '<div>'+
                                        '<div title="Status" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="changestatus(\'' + row.categoryid + '\',0)">'+
                                            '<i class="text-danger feather icon-x fa-2x"></i>'+
                                        '</div>'+
                                        '<div title="Edit" class="flex" style="cursor:pointer;float:left;padding-left:5px;" onClick="editcategory(\'' + row.categoryid + '\')">'+
                                            '<i class="text-primary feather icon-edit-2 fa-2x"></i>'+
                                        '</div>' +
                                        '<div title="Delete" class="flex text-theme-6" style="cursor:pointer;float:left;padding-left:5px;" onClick="deletecategory(\'' + row.categoryid + '\')">'+
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

    $('form').submit(function(event) {
        event.preventDefault();
        if (validate_form('categoryform')) {
            $("#categorybtn").prop('disabled', true);

            if($("#category_id").val() == "0"){
                curl = "savecategory";
            } else {
                curl = "updatecategory";
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
                    $("#categorybtn").prop('disabled', false);
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
                    $("#categorybtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)
                }
            });
        } else {
            $("#categorybtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        }
    });

    function changestatus(cat_id,status) {
        $.ajax({
            type: 'post',
            url: 'changecategorystatus',
            data: {"is_active":status,"categoryid":cat_id,"_token": '{{ csrf_token() }}' },
            success: function (res) {
                if(res.Success == "true"){
                    refreshdata();
                    alertify.success(res.Message);
                } else {
                    alertify.error(res.Message);
                }
            },
            error: function (jqXHR,res,errorThrown) {
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
                alertify.error(res.Message)   
            }
        });
    }

    function deletecategory(cat_id) {
        $.ajax({
            type: 'post',
            url: 'deletecategory',
            data: {"categoryid":cat_id,"_token": '{{ csrf_token() }}' },
            success: function (res) {
                if(res.Success == "true"){
                    refreshdata();
                    alertify.success(res.Message);
                } else {
                    alertify.error(res.Message);
                }
            },
            error: function (jqXHR,res,errorThrown) {
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
                alertify.error(res.Message)   
            }
        });
    }

    function editcategory(cat_id) {
        $('.invalid').removeClass('invalid');
        
        $.ajax({
            type: 'post',
            url: 'getcategorybyid',
            data: {"categoryid":cat_id,"_token": '{{ csrf_token() }}' },
            success: function (res) {
                if(res.Success == "true"){
                    $("#category_name").val(res.data.category_name);
                    $("#cat_type").val(res.data.cat_type);
                    $("#description").val(res.data.description);
                    $("#category_id").val(res.data.categoryid);
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
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
                alertify.error(res.Message)   
            }
        });
    }
</script>