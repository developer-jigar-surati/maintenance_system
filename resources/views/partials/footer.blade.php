<!-- Required Js -->
<!-- <script src="{{ url('assets/js/jquery-3.5.1.min.js') }}" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="{{ url('assets/js/vendor-all.min.js') }}"></script>
<script src="{{ url('assets/js/plugins/bootstrap.min.js') }}"></script>
<script src="{{ url('assets/js/ripple.js') }}"></script>
<script src="{{ url('assets/js/pcoded.min.js') }}"></script>
<script src="{{ url('assets/js/custom.js') }}"></script>

<!-- datatable Js -->
<script src="{{ url('assets/js/plugins/jquery.dataTables.min.js') }}"></script>
<script src="{{ url('assets/js/plugins/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ url('assets/js/pages/data-basic-custom.js') }}"></script>

<!-- ckeditor -->
<!-- <script src="https://cdn.ckeditor.com/4.14.1/standard/ckeditor.js"></script> -->
<script src="{{ url('assets/ckeditor-full/ckeditor.js') }}"></script>
<script src="{{ url('assets/ckeditor-full/adapters/jquery.js') }}"></script>

<!-- chart -->
<script src="{{ url('assets/js/plugins/apexcharts.min.js') }}"></script>
<!-- <script src="{{ url('assets/js/pages/chart-apex.js') }}"></script> -->

<!-- datepicker -->

<script>
    function showloader(){
        $("#custom_loader").show();
    }

    function hideloader(){
        $("#custom_loader").hide();
    }

    $('.cancelbtn').click(function () {
        $(this).addClass('d-none');
    });
</script>

</body>

</html>