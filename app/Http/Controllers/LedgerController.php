<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ledger;
use Illuminate\Support\Facades\Log;

class LedgerController extends Controller
{
    public function addledgerpayment(Request $request)
    {
        try{
            Log::info("LedgerController => addledgerpayment Method ");
            $catobj = new Ledger($request);
            $res = $catobj->addledgerpayment();
            return $res;
        } catch (\Exception $e){
            Log::info("LedgerController => addledgerpayment Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }
    
    public function getledgerdata(Request $request)
    {
        try{
            Log::info("LedgerController => getledgerdata Method ");
            $catobj = new Ledger($request);
            $res = $catobj->getledgerdata();
            return $res;
        } catch (\Exception $e){
            Log::info("LedgerController => getledgerdata Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }
}
