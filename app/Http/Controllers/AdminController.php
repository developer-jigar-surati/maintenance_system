<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Admin;

class AdminController extends Controller
{
    public function dologin(Request $request)
    {
        $adminobj = new Admin($request);
        $res = $adminobj->dologin();
        return $res;
    }
    
    public function dologout(Request $request){
        $request->session()->flush();
        return redirect('/a@dmin');
    }
}

