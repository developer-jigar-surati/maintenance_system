<!-- [ Header ] start -->
<header class="navbar pcoded-header navbar-expand-lg navbar-light header-blue">
    <div class="m-header">
        <a class="mobile-menu" id="mobile-collapse" href="javascript:void(0);"><span></span></a>
        <a href="javascript:void(0);" class="b-brand">
            <!-- ========   change your logo hear   ============ -->
            <img src="{{ url('assets/images/logo.png') }}" style="width: 68px;height:28px;" alt="" class="logo">
            <img src="{{ url('assets/images/logo-dark.png') }}" alt="" class="logo-thumb">
        </a>
        <a href="javascript:void(0);" class="mob-toggler">
            <i class="feather icon-more-vertical"></i>
        </a>
    </div>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav ml-auto">
            <li>
                <div class="dropdown drp-user">
                    <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="feather icon-user"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right profile-notification">
                        <div class="pro-head">
                            <img src="assets/images/user_default.png" class="img-radius" alt="User-Profile-Image">
                            <span>Welcome {{ Session::get('name')}}</span>
                            <a href="{{ url('/logout') }}" class="dud-logout" title="Logout">
                                <i class="feather icon-log-out"></i>
                            </a>
                        </div>
                        <ul class="pro-body">
                            
                            <li><a href="{{ url('/resetpass') }}" class="dropdown-item"><i class="feather icon-lock m-r-5"></i>Reset Password</a></li>
                            <li><a href="{{ url('/logout') }}" class="dropdown-item"><i class="feather icon-log-out m-r-5"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</header>
<!-- [ Header ] end -->
