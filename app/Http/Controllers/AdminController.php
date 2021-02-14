<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Admin;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function dologin(Request $request)
    {
        $adminobj = new Admin($request);
        $res = $adminobj->dologin();
        return $res;
    }

    public function resetpass(Request $request)
    {
        try {
            Log::info("AdminController => resetpass Method ");
            $adminobj = new Admin($request);
            $res = $adminobj->resetpass();
            return $res;
        } catch (\Exception $e) {
            Log::info("AdminController => resetpass Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success" => "false", "Message" => "Exception Caught", "data" => $e->getMessage()]);
        }
    }

    public function forgotpassword(Request $request)
    {
        try {
            Log::info("AdminController => forgotpassword Method ");
            $adminobj = new Admin($request);
            $res = $adminobj->forgotpassword();
            return $res;
        } catch (\Exception $e) {
            Log::info("AdminController => forgotpassword Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success" => "false", "Message" => "Exception Caught", "data" => $e->getMessage()]);
        }
    }

    public function forgotpasswordlink(Request $request)
    {
        try {
            Log::info("AdminController => forgotpasswordlink Method ");
            $link = url()->full();
            if (isset($link) && $link != '') {
                $admindata = \DB::connection()->table('admin_mst')
                    ->where([['is_active', '=', '1'], ['forgotlink', '=', $link], ["forgotlink_expire", ">=", date('Y-m-d H:i:s')]])
                    ->whereNull('deleted_on')->first();
                if ($admindata != null) {
                    return view('forgotpasswordlink', ['admindata' => $admindata]);
                } else {
                    return \Redirect::to(url('/a@dmin'));
                }
            } else {
                return \Redirect::to(url('/a@dmin'));
            }
        } catch (\Exception $e) {
            Log::info("AdminController => forgotpasswordlink Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success" => "false", "Message" => "Exception Caught", "data" => $e->getMessage()]);
        }
    }

    public function forgotpasslink(Request $request)
    {
        try {
            Log::info("AdminController => forgotpasslink Method ");
            $adminobj = new Admin($request);
            $res = $adminobj->forgotpasslink();
            return $res;
        } catch (\Exception $e) {
            Log::info("AdminController => forgotpasslink Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success" => "false", "Message" => "Exception Caught", "data" => $e->getMessage()]);
        }
    }

    public function dologout(Request $request)
    {
        $request->session()->flush();
        return redirect('/a@dmin');
    }

    public function showdashboard(Request $request)
    {
        try {
            Log::info("AdminController => showdashboard Method ");
            $drdataque = \DB::connection()->table('tbl_payment')
                ->where([['pay_type', '=', 1]]);
            if ($request->session()->get('building_id') !== null && $request->session()->get('building_id') !== '') {
                $drdataque->where('building_id', $request->session()->get('building_id'));
            }
            $drdataque->select(\DB::raw('SUM(pay_amount) as drtotal'))
                ->whereNull('deleted_on');
            $drdata = $drdataque->first();

            $crdataque = \DB::connection()->table('tbl_payment')
                ->where([['pay_type', '=', 2]]);
            if ($request->session()->get('building_id') !== null && $request->session()->get('building_id') !== '') {
                $crdataque->where('building_id', $request->session()->get('building_id'));
            }
            $crdataque->select(\DB::raw('SUM(pay_amount) as crtotal'))
                ->whereNull('deleted_on');
            $crdata = $crdataque->first();

            $categorywisedata = \DB::connection()->table('tbl_payment as tp')
                ->join('tbl_category as tc', 'tc.categoryid', 'tp.category_id');
                if ($request->session()->get('building_id') !== null && $request->session()->get('building_id') !== '') {
                    $categorywisedata->where('tp.building_id', $request->session()->get('building_id'));
                }
                $categorywisedata->select(\DB::raw('GROUP_CONCAT(tc.category_name) as category_name'), \DB::raw('SUM(tp.pay_amount) as totalamount'))
                                ->groupBy('tc.categoryid');
                $categorywisedatares = $categorywisedata->get();

            $temp = [];
            foreach ($categorywisedatares as $key => $value) {
                $temp['category_name'][] = explode(',', $value->category_name)[0];
                $temp['totalamount'][] = round($value->totalamount);
            }
            // dd($temp);
            return view('dashboard', ['drdata' => $drdata->drtotal, 'crdata' => $crdata->crtotal, 'categorydata' => json_encode($temp,true)]);
        } catch (\Exception $e) {
            Log::info("AdminController => showdashboard Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success" => "false", "Message" => "Exception Caught", "data" => $e->getMessage()]);
        }
    }
}
