<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Building;
use Illuminate\Support\Facades\Log;

class BuildingController extends Controller
{
    public function savebuilding(Request $request)
    {
        try{
            Log::info("BuildingController => savebuilding Method ");
            $catobj = new Building($request);
            $res = $catobj->addbuilding();
            return $res;
        } catch (\Exception $e){
            Log::info("BuildingController => savebuilding Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getbuildings(Request $request)
    {
        try{
            Log::info("BuildingController => getbuildings Method ");
            $catobj = new Building($request);
            $res = $catobj->getbuildings();
            return $res;
        } catch (\Exception $e){
            Log::info("BuildingController => getbuildings Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function changebuildingstatus(Request $request)
    {
        try{
            Log::info("BuildingController => changebuildingstatus Method ");
            $catobj = new Building($request);
            $res = $catobj->changebuildingstatus();
            return $res;
        } catch (\Exception $e){
            Log::info("BuildingController => changebuildingstatus Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function deletebuilding(Request $request)
    {
        try{
            Log::info("BuildingController => deletebuilding Method ");
            $catobj = new Building($request);
            $res = $catobj->deletebuilding();
            return $res;
        } catch (\Exception $e){
            Log::info("BuildingController => deletebuilding Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getbuildingbyid(Request $request)
    {
        try{
            Log::info("BuildingController => getbuildingbyid Method ");
            $catobj = new Building($request);
            $res = $catobj->getbuildingbyid();
            return $res;
        } catch (\Exception $e){
            Log::info("BuildingController => getbuildingbyid Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function updatebuilding(Request $request)
    {
        try{
            Log::info("BuildingController => updatebuilding Method ");
            $catobj = new Building($request);
            $res = $catobj->updatebuilding();
            return $res;
        } catch (\Exception $e){
            Log::info("BuildingController => updatebuilding Method Exception Caught => ");
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
}
