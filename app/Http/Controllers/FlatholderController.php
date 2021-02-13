<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Flatholder;
use Illuminate\Support\Facades\Log;

class FlatholderController extends Controller
{
    public function saveflatholder(Request $request)
    {
        try{
            Log::info("FlatholderController => saveflatholder Method ");
            $catobj = new Flatholder($request);
            $res = $catobj->saveflatholder();
            return $res;
        } catch (\Exception $e){
            Log::info("FlatholderController => saveflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getflatholders(Request $request)
    {
        try{
            Log::info("FlatholderController => getflatholders Method ");
            $catobj = new Flatholder($request);
            $res = $catobj->getflatholders();
            return $res;
        } catch (\Exception $e){
            Log::info("FlatholderController => getflatholders Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function changeflatholderstatus(Request $request)
    {
        try{
            Log::info("FlatholderController => changeflatholderstatus Method ");
            $catobj = new Flatholder($request);
            $res = $catobj->changeflatholderstatus();
            return $res;
        } catch (\Exception $e){
            Log::info("FlatholderController => changeflatholderstatus Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function deleteflatholder(Request $request)
    {
        try{
            Log::info("FlatholderController => deleteflatholder Method ");
            $catobj = new Flatholder($request);
            $res = $catobj->deleteflatholder();
            return $res;
        } catch (\Exception $e){
            Log::info("FlatholderController => deleteflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getflatholderbyid(Request $request)
    {
        try{
            Log::info("FlatholderController => getflatholderbyid Method ");
            $catobj = new Flatholder($request);
            $res = $catobj->getflatholderbyid();
            return $res;
        } catch (\Exception $e){
            Log::info("FlatholderController => getflatholderbyid Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function updateflatholder(Request $request)
    {
        try{
            Log::info("FlatholderController => updateflatholder Method ");
            $catobj = new Flatholder($request);
            $res = $catobj->updateflatholder();
            return $res;
        } catch (\Exception $e){
            Log::info("FlatholderController => updateflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getbuldingsforflatholder(Request $request)
    {
        try{
            Log::info("BuildingController => getbuldingsforflatholder Method ");
            $catobj = new Building($request);
            $res = $catobj->getbuldingsforflatholder();
            return $res;
        } catch (\Exception $e){
            Log::info("BuildingController => getbuldingsforflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function saveflatholderasadmin(Request $request)
    {
        try{
            Log::info("FlatholderController => saveflatholderasadmin Method ");
            $catobj = new Flatholder($request);
            $res = $catobj->saveflatholderasadmin();
            return $res;
        } catch (\Exception $e){
            Log::info("FlatholderController => saveflatholderasadmin Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }
}
