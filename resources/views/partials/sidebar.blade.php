<!-- [ navigation menu ] start -->
<nav class="pcoded-navbar menu-light">
    <div class="loading" id="custom_loader" style="display: none;">Loading&#8230;</div>
    <div class="navbar-wrapper">
        <div class="navbar-content scroll-div">

            <div class="">
                <div class="main-menu-header">
                    <img class="img-radius" src="assets/images/user_default.png" alt="User-Profile-Image">
                    <div class="user-details">
                        <div id="more-details">{{ Session::get('name')}} <i class="fa fa-caret-down"></i></div>
                    </div>
                </div>
                <div class="collapse" id="nav-user-link">
                    <ul class="list-unstyled">
                        <li class="list-group-item"><a href="{{ url('/resetpass') }}"><i class="feather icon-lock m-r-5"></i>Reset Password</a></li>
                        <li class="list-group-item"><a href="{{ url('/logout') }}"><i class="feather icon-log-out m-r-5"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <ul class="nav pcoded-inner-navbar ">
                <li class="nav-item pcoded-menu-caption">
                    <!-- <label>Navigation</label> -->
                </li>
                <li class="nav-item {{ (Request::is('dashboard')) ? 'active' : '' }}">
                    <a href="{{ url('/dashboard') }}" class="nav-link"><span class="pcoded-micon"><i class="feather icon-activity"></i></span><span class="pcoded-mtext">Dashboard</span></a>
                </li>
                @if(Session::get('user_role') != 3)
                <li class="nav-item {{ (Request::is('buildingslist')) ? 'active' : '' }}">
                    <a href="{{ url('/buildingslist') }}" class="nav-link "><span class="pcoded-micon"><i class="feather icon-home"></i></span><span class="pcoded-mtext">Buildings</span></a>
                </li>
                @endif
                <li class="nav-item {{ (Request::is('flatholders')) ? 'active' : '' }}">
                    <a href="{{ url('/flatholders') }}" class="nav-link "><span class="pcoded-micon"><i class="feather icon-user"></i></span><span class="pcoded-mtext">Flat Holders</span></a>
                </li>
                @if(Session::get('user_role') != 3)
                <li class="nav-item {{ (Request::is('category')) ? 'active' : '' }}">
                    <a href="{{ url('/category') }}" class="nav-link "><span class="pcoded-micon"><i class="feather icon-align-justify"></i></span><span class="pcoded-mtext">Category</span></a>
                </li>
                @endif
                <li class="nav-item {{ (Request::is('ledger')) ? 'active' : '' }}">
                    <a href="{{ url('/ledger') }}" class="nav-link "><span class="pcoded-micon"><i class="feather icon-book"></i></span><span class="pcoded-mtext">Ledger</span></a>
                </li>
            </ul>



        </div>
    </div>
</nav>
<!-- [ navigation menu ] end -->