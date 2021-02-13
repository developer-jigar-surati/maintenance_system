@include('partials.header',['pagetitle'=>"Ledger"])
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

        @include('partials.breadcrumb',['active_breadcrumb'=>"Ledger",'pagename'=>"Ledger"])

        <!-- [ Main Content ] start -->
        <div class="row">

            <!-- [ form-element ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header addeditledgerdiv">
                        @if(Session::get('user_role') != 3)
                        <button type="button" class="btn btn-success btn-sm btn-round has-ripple" id="addeditbtn" onclick="addeditledgerfun()" style="float: right;">
                            <i class="feather icon-plus"></i> Add Payment
                        </button>
                        @endif

                        @if(Session::get('user_role') == '1')
                        <div class="col-sm-3">
                            <div class="form-group">
                                <select class="form-control dropdown" id="list_building" name="list_building" onchange="refreshdata(this.value)">
                                    <option value="">Please Select</option>
                                </select>
                            </div>
                        </div>
                        @endif
                        <div class="col-sm-12 totaltbl mt-5" >
                            <table class="table table-striped table-bordered" id="datatable" style="width: 100%;">
                                <tr>
                                    <th style="color:white;background-color:#6865cc !important;">Cr</th>
                                    <th class="bg-danger" style="color:white">Dr</th>
                                    <th class="bg-info" style="color:white">Total</th>
                                    
                                </tr>
                                <tr>
                                    <td style="color:#6865cc !important;"><span id="total_cr"></span></td>
                                    <td class="text-danger"><span id="total_dr"></span></td>
                                    <td class="text-info"><span id="total_bal"></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- <h5>Form controls</h5> -->
                        <!-- <hr> -->
                        <div class="row">
                            <div class="col-md-12 addeditledger" style="display: none;">
                                <form id="ledgerform" method="post" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="building">Building <span class="required"> *</span></label>
                                                <select class="form-control notnull" id="building" name="building" onchange="getflatholderfun(this.value)">
                                                    <option value="0">Please Select</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group fill">
                                                <label for="payment_type">Type <span class="required"> *</span></label>
                                                <select class="form-control dropdown" id="payment_type" onchange="getcrdatafun(this.value)" name="payment_type">
                                                    <option value="0">Please Select</option>
                                                    <option value="1">Dr</option>
                                                    <option value="2">Cr</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group fill">
                                                <label for="category">Category <span class="required"> *</span></label>
                                                <select class="form-control dropdown" id="category" name="category">
                                                    <option value="0">Please Select</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-6 ifcr">
                                            <div class="form-group fill">
                                                <label for="flat_holder">Flat Holder <span class="required"> *</span></label>
                                                <select class="form-control" id="flat_holder" name="flat_holder">
                                                    <option value="0">Please Select</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group fill">
                                                <label for="pay_amount">Amount <span class="required"> *</span></label>
                                                <input type="number" class="form-control notnull" id="pay_amount" placeholder="Enter Amount" name="pay_amount">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group fill">
                                                <label for="pay_date">Date </label>
                                                <input type="date" class="form-control notnull" id="pay_date" placeholder="Select Date" name="pay_date">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="short_desc">Remark <span class="required"> *</span></label>
                                                <textarea id="short_desc" class="form-control notnull" name="short_desc"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label for="pay_document">Document </label>
                                                <input type="file" class="form-control" id="pay_document" onChange="imagechange(event)" name="pay_document">
                                                <img src="" id="appenddocument" style="height: 100px;width: 100px;display:none;float:left">
                                                <i onClick="crossimgclick()" id="crossimg" style="display:none;float:left" class="feather icon-x fa-2x"></i>
                                            </div>
                                        </div>

                                        <div class="col-sm-12" style="margin-top: 10px;">
                                            <input type="hidden" id="hiddendocument_img" name="hiddendocument_img" value="">
                                            <input type="hidden" id="payment_id" name="payment_id" value="0">
                                            <input type="hidden" id="_token" name="_token" value="{{ csrf_token() }}">
                                            <button type="submit" class="btn btn-primary" id="ledgerbtn">Submit</button>
                                            &nbsp;&nbsp;&nbsp;
                                            <button type="button" class="btn btn-primary" onclick="refreshdata('{{Session::get('building_id')}}')" id="backbtn">Back</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-12 ledgerlist" style="display: block;">
                                <div class="table-border-style">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="datatable" style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Details</th>
                                                    <th>CR</th>
                                                    <th>DR</th>
                                                    <th>Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ledgerdatatable">
                                                
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Details</th>
                                                    <th>CR</th>
                                                    <th>DR</th>
                                                    <th>Balance</th>
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
    $(document).ready(function(){
        var buildingid = "{{ Session::get('building_id')}}";
        refreshdata(buildingid);
        getbuildings();
        $(".ifcr").hide();
    });

    function addeditledgerfun(){
        $(".ledgerlist").hide();
        $(".addeditledger").show();
        $("#addeditbtn").hide();
        $("#list_building").hide();
        $(".totaltbl").hide();
    }

    function refreshdata(buildingid) {
    
        $(".invalid").removeClass("invalid");
        $(".ledgerlist").show();
        $(".addeditledger").hide();
        $("#addeditbtn").show();
        $("#list_building").show();
        $(".totaltbl").show(); 
        $("#list_building").val(buildingid);

        $(".ifcr").hide();
        $("#flat_holder").removeClass('dropdown');

        $("#payment_id").val("0");
        $("#ledgerform").trigger("reset");

        $.ajax({
            type: 'post',
            url: 'getledgerdata',
            data: { "_token" : '{{ csrf_token() }}','buildingid':buildingid },
            method: 'POST',
            success: function (res) {
                
                if(res.Success=="true"){
                    
                    var html = '';
                    var balance = 0;
                    var total_bal = 0;
                    var total_cr = 0;
                    var total_dr = 0;
                    if(res.data.length > 0){
                        $.each(res.data,function (k,v) {
                            html += '<tr>';
                            html += '<td>'+v['pay_date']+'</td>';
                            html += '<td><div>'+
                                            '<span><strong>Building: </strong></span><span>'+v['building_name']+'</span></br>'+
                                            '<span><strong>Category: </strong></span><span>'+v['category_name']+'</span></br>';
                                            if(v['pay_type'] == 2){
                                                html += '<span><strong>Flat Details: </strong></span><span>'+v['name']+' ('+v['flat_no']+')</span></br>';
                                            }
                                            if(v['pay_document']!=''){
                                                html += '<span><strong>Document: </strong></span><span><a href="'+v['pay_document']+'" target="_blank">Clieck Here</a></span></br>';
                                            }
                                            html += '<span><strong>Remark: </strong></span><span>'+v['short_desc']+'</span></br>';
                                            
                                html += '</div>'+
                                    '</td>';
                            if(v['pay_type'] == 2){
                                html += '<td>'+v['pay_amount']+'</td>';
                                html += '<td>-</td>';
                                balance = parseFloat(balance) + parseFloat(v['pay_amount']);
                                total_cr += parseFloat(v['pay_amount']);
                            } else if(v['pay_type'] == 1){
                                html += '<td>-</td>';
                                html += '<td>'+v['pay_amount']+'</td>';
                                balance = parseFloat(balance) - parseFloat(v['pay_amount']);
                                total_dr += parseFloat(v['pay_amount']);
                            } else {
                                html += '<td>-</td>';
                                html += '<td>-</td>';
                            }
                            total_bal = parseFloat(total_cr) - parseFloat(total_dr);
                            html += '<td>'+parseFloat(balance).toFixed(2)+'</td>';
                            html += '</tr>';
                        });
                        
                    } else {
                        html += "<tr><td colspan='5'>No Record's Found</td></tr>"
                    }
                    
                    $("#ledgerdatatable").html(html);
                    $("#total_cr").html(parseFloat(total_cr).toFixed(2));
                    $("#total_dr").html(parseFloat(total_dr).toFixed(2));
                    $("#total_bal").html(parseFloat(total_bal).toFixed(2));
                }  
            },
            error: function (jqXHR,res,errorThrown) {
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
            }
        });
    }

    function getcrdatafun(currentval){
        if(currentval == 2){
            $(".ifcr").show();
            $("#flat_holder").addClass('dropdown');
        } else {
            $("#flat_holder").removeClass('dropdown');
            $(".ifcr").hide();
        }
        getcategory(currentval);
    }

    function getcategory(currentval){
        $.ajax({
            type: 'post',
            url: 'getcategoryforledger',
            data: { "_token" : '{{ csrf_token() }}',"currentval" : currentval },
            method: 'POST',
            success: function (res) {
                if(res.Success=="true"){
                    if(res.data.length > 0){
                        $("#category").html('');
                        html = '<option value="0">Please Select</option>';
                        $.each(res.data,function (k,v) {
                            html += '<option value="'+v['categoryid']+'">'+v['category_name']+'</option>';
                        });
                        $("#category").html(html);
                    }
                }  
            },
            error: function (jqXHR,res,errorThrown) {
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
            }
        });
    }

    function getbuildings(){
        $.ajax({
            type: 'post',
            url: 'getbuildingsforledger',
            data: { "_token" : '{{ csrf_token() }}'},
            method: 'POST',
            success: function (res) {
                if (res.Success == "true") {
                    if (res.data.length > 0) {
                        $("#building").html('');
                        $("#list_building").html('');
                        if (res.data.length == 1) {
                            html = '<option value="'+res.data['0']['building_id']+'">'+res.data['0']['building_name']+'</option>';
                        } else {
                            html = '<option value="">Please Select</option>';
                            $.each(res.data, function(k, v) {
                                html += '<option value="' + v['building_id'] + '">' + v['building_name'] + '</option>';
                            });
                        }
                        $("#building").html(html);
                        if (res.data.length == 1) {
                            $("#building").trigger('change');
                        }
                        $("#list_building").html(html);
                    }
                }
            },
            error: function (jqXHR,res,errorThrown) {
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
            }
        });
    }

    function getflatholderfun(currentval){
        $.ajax({
            type: 'post',
            url: 'getflatholderforledger',
            data: { "_token" : '{{ csrf_token() }}',"currentval" : currentval },
            method: 'POST',
            success: function (res) {
                if(res.Success=="true"){
                    $("#flat_holder").html('');
                    html = '<option value="0">Please Select</option>';
                    if(res.data.length > 0){
                        $.each(res.data,function (k,v) {
                            html += '<option value="'+v['fholder_id']+'">'+v['name']+' ('+v['flat_no']+')</option>';
                        });
                    }
                    $("#flat_holder").html(html);
                }  
            },
            error: function (jqXHR,res,errorThrown) {
                console.log("error");
                console.log(jqXHR);
                console.log(res);
                console.log(errorThrown);
            }
        });
    }

    $('form').submit(function(event) {
        event.preventDefault();
        $(".invalid").removeClass("invalid");
        if (!validate_form('ledgerform')) {
            $("#ledgerbtn").prop('disabled', false);
            alertify.error('Please Fill Neccesary Information!');
        } else {
            $("#ledgerbtn").prop('disabled', true);
            if($("#payment_id").val() == "0"){
                url = "addledgerpayment";
            } else {
                url = "updateledgerpayment";
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
                success: function (res) {
                    $("#ledgerbtn").prop('disabled', false);
                    if(res.Success=="true"){
                        alertify.success(res.Message);
                        var buildingid = "{{ Session::get('building_id')}}";
                        refreshdata(buildingid);
                    } else{ 
                        alertify.error(res.Message);
                    }  
                },
                error: function (jqXHR,res,errorThrown) {
                    $("#ledgerbtn").prop('disabled', false);
                    console.log("error");
                    console.log(jqXHR);
                    console.log(res);
                    console.log(errorThrown);
                    alertify.error(res.Message)   
                }
            });
        }
    });

    var imagechange = function(event) {
        var input = event.target;
        var fileExtension = ['image/jpeg', 'image/jpg', 'image/png'];
        if(typeof input.files[0] != 'undefined'  && typeof input.files[0].type != 'undefined' && $.inArray(input.files[0].type, fileExtension) !== -1){
            var reader = new FileReader();
            reader.onload = function() {
                var dataURL = reader.result;
                var extarr = dataURL.split("/");
                var output = document.getElementById('appenddocument');
                output.src = dataURL;
            };
            
            console.log(input.files[0]);
            if (input.files[0] != 'undefined') {
                reader.readAsDataURL(input.files[0]);
                $('#appenddocument').show();
                $('#crossimg').show();
            } else {
                $('#pay_document').val('');
                $('#appenddocument').attr('src', '');
                $('#appenddocument').hide();
                $('#crossimg').hide();
            }
        } else {
            $('#appenddocument').attr('src', '');
            $('#appenddocument').hide();
            $('#crossimg').hide();
            console.log('document not an image');
        }
    };

    function crossimgclick() {
        $('#pay_document').val('');
        $('#appenddocument').attr('src', '');
        $('#appenddocument').hide();
        $('#crossimg').hide();
        $('#hiddendocument_img').val("");
    }

</script>