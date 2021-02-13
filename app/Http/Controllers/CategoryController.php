<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function savecategory(Request $request)
    {
        try{
            Log::info("CategoryController => savecategory Method ");
            $catobj = new Category($request);
            $res = $catobj->addcategory();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => savecategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getcategory(Request $request)
    {
        try{
            Log::info("CategoryController => getcategory Method ");
            $catobj = new Category($request);
            $res = $catobj->getcategory();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => getcategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function changecategorystatus(Request $request)
    {
        try{
            Log::info("CategoryController => changecategorystatus Method ");
            $catobj = new Category($request);
            $res = $catobj->changecategorystatus();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => changecategorystatus Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function deletecategory(Request $request)
    {
        try{
            Log::info("CategoryController => deletecategory Method ");
            $catobj = new Category($request);
            $res = $catobj->deletecategory();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => deletecategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getcategorybyid(Request $request)
    {
        try{
            Log::info("CategoryController => getcategorybyid Method ");
            $catobj = new Category($request);
            $res = $catobj->getcategorybyid();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => getcategorybyid Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function updatecategory(Request $request)
    {
        try{
            Log::info("CategoryController => updatecategory Method ");
            $catobj = new Category($request);
            $res = $catobj->updatecategory();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => updatecategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getcategoryforledger(Request $request)
    {
        try{
            Log::info("CategoryController => getcategoryforledger Method ");
            $catobj = new Category($request);
            $res = $catobj->getcategoryforledger();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => getcategoryforledger Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getbuildingsforledger(Request $request)
    {
        try{
            Log::info("CategoryController => getbuildingsforledger Method ");
            $catobj = new Category($request);
            $res = $catobj->getbuildingsforledger();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => getbuildingsforledger Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function getflatholderforledger(Request $request)
    {
        try{
            Log::info("CategoryController => getflatholderforledger Method ");
            $catobj = new Category($request);
            $res = $catobj->getflatholderforledger();
            return $res;
        } catch (\Exception $e){
            Log::info("CategoryController => getflatholderforledger Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }
}
