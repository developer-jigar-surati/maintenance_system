<?php

namespace App;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Common;

class Category extends Model
{
    const main_tbl = 'tbl_category';
    const building_tbl = 'tbl_buildings';
    const flat_holders_tbl = 'tbl_flat_holders';
    
    public function __construct($request){
        $this->request = $request;
    }

    public function addcategory()
    {
        try {
            Log::info("Category => addcategory Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'category_name' => 'required',
                'cat_type' => 'required',
                'description' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['category_name'] = $input_arr['category_name'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                return response()->json(["Success"=> "false","Message" => "Category Already Exist","data"=>""]);
            } else {

                $insert_arr = [
                    "categoryid" => md5($input_arr['category_name'].date('Y-m-d H:i:s')),
                    "category_name" => $input_arr['category_name'],
                    "cat_type" => $input_arr['cat_type'],
                    "description" => $input_arr['description'],
                    "is_active" => $input_arr['is_active'],
                    "added_on" => date('Y-m-d H:i:s'),
                    "added_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->insert($insert_arr);
                return response()->json(["Success"=> "true","Message" => "Category Added Successfully","data"=>""]);
            }

        } catch (\Exception $e){
            Log::info("Category => addcategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getcategory()
    {
        try {
            Log::info("Category => getcategory Method Called ");
            $input_arr = $this->request->input();
            
            $totalEmployees = 0;
            $filteredEmployees = 0;
            $areRecordsFiltered = false;

            $searchValue = addslashes($input_arr['search']['value']);
            $orderColumn = isset($input_arr['order'][0]['column'])?$input_arr['order'][0]['column']:'';
            $orderBy = isset($input_arr['order'][0]['dir'])?$input_arr['order'][0]['dir']:'';
            $start = $input_arr['start'];
            $length = $input_arr['length'];

            $query = DB::connection()->table(self::main_tbl)->whereNull('deleted_on');            
            if(isset($searchValue) && trim($searchValue) != '')
            {
                $areRecordsFiltered = true;
                $query->where('category_name', 'RLIKE', $searchValue);
            }

            $totalEmployees = DB::connection()->table(self::main_tbl)->whereNull('deleted_on')->count();
            if((isset($orderColumn) && trim($orderColumn) != '') && (isset($orderBy) && trim($orderBy) != '')) {
                //$colindex = (intval($orderColumn) + 1);
                $fieldname = $input_arr['columns'][$orderColumn]['data'];
                $query->orderBy($fieldname, $orderBy);
            } else {
                $query->orderBy('added_on', 'desc');   
            }
            if(isset($length) && $length != -1) {
                $query->skip($start)->take($length);
            }
            $results = $query->get();
            
            $rows = array();
            if($totalEmployees > 0){
                foreach($results as $key => $value){
                    if($value->cat_type == 1){
                        $cat_type = 'Dr';
                    } else if($value->cat_type == 2){
                        $cat_type = 'Cr';
                    } else if($value->cat_type == 3){
                        $cat_type = 'Both';
                    } else {
                        $cat_type = '';
                    }
                    $tempRow = array();
                    $tempRow['category_name'] = $value->category_name;
                    $tempRow['cat_type'] = $cat_type;
                    $tempRow['createddatetime'] = date('d-m-Y H:i:s',strtotime($value->added_on));
                    $tempRow['modifireddatetime'] = ($value->modified_on!=NULL)?date('d-m-Y H:i:s',strtotime($value->modified_on)):"";
                    $tempRow['createdtime'] = date('H:i:s',strtotime($value->added_on));
                    $tempRow['modifiredtime'] = ($value->modified_on!=NULL)?date('H:i:s',strtotime($value->modified_on)):"";
                    $tempRow['is_active'] = ($value->is_active==1)?'Active':'In active';
                    $tempRow['deleted_on'] = 'Delete';
                    $tempRow['categoryid'] = $value->categoryid;
                    $rows[] = $tempRow;
                }
            }

            $filteredEmployees = $totalEmployees;
            if($areRecordsFiltered)
            {
                $filteredEmployees = count($rows);
            }
            $output = array();
            $output['draw'] = $input_arr['draw'];
            $output['recordsTotal'] = $totalEmployees;
            $output['recordsFiltered'] = $filteredEmployees;
            $output['data'] = $rows;
            $output['query'] = '';

            return response()->json($output);
        } catch (\Exception $e){
            Log::info("Category => getcategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function changecategorystatus()
    {
        try {
            Log::info("Category => changecategorystatus Method Called ");
            $input_arr = $this->request->input();
            
            $validator = Validator::make($input_arr, [
                'is_active' => 'required',
                'categoryid' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['categoryid'] = $input_arr['categoryid'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                $update_arr = [
                    "is_active" => ($input_arr['is_active']==1)?0:1,
                    "modified_on" => date('Y-m-d H:i:s'),
                    "modified_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('categoryid',$input_arr['categoryid'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Status Update Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Category Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Category => changecategorystatus Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function deletecategory()
    {
        try {
            Log::info("Category => deletecategory Method Called ");
            $input_arr = $this->request->input();
            
            $validator = Validator::make($input_arr, [
                'categoryid' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['categoryid'] = $input_arr['categoryid'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                $update_arr = [
                    "deleted_on" => date('Y-m-d H:i:s'),
                    "deleted_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('categoryid',$input_arr['categoryid'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Category Delete Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Category Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Category => deletecategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }        
    }

    public function getcategorybyid()
    {
        try {
            Log::info("Category => getcategorybyid Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $category = DB::connection()->table(self::main_tbl)
                        ->where('categoryid',$input_arr['categoryid'])
                        ->whereNull('deleted_on')
                        ->first();
            return response()->json(["Success"=> "true","Message" => "Get Category","data"=>$category]);
        } catch (\Exception $e){
            Log::info("Category => getcategorybyid Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function updatecategory()
    {
        try {
            Log::info("Category => updatecategory Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'category_name' => 'required',
                'category_id' => 'required',
                'cat_type' => 'required',
                'description' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['categoryid'] = $input_arr['category_id'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                
                $checkname = DB::connection()->table(self::main_tbl)
                            ->where([['category_name','=',$input_arr['category_name']],['categoryid','!=',$input_arr['category_id']]])
                            ->whereNull('deleted_on')->count();
                if($checkname > 0){
                    return response()->json(["Success"=> "false","Message" => "Category Already Exist","data"=>""]);
                }

                $update_arr = [
                    "category_name" => $input_arr['category_name'],
                    "cat_type" => $input_arr['cat_type'],
                    "description" => $input_arr['description'],
                    "is_active" => $input_arr['is_active'],
                    "modified_on" => date('Y-m-d H:i:s'),
                    "modified_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('categoryid',$input_arr['category_id'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Category Update Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Category Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Category => updatecategory Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getcategoryforledger()
    {
        try {
            Log::info("Category => getcategoryforledger Method Called ");
            $input_arr = $this->request->input();
            $category = DB::connection()->table(self::main_tbl)
                        ->where('is_active','=',1)
                        ->whereIn('cat_type',[3,$input_arr['currentval']])
                        ->whereNull('deleted_on')
                        ->select('categoryid','category_name','cat_type')
                        ->orderBy('category_name','asc')
                        ->get();
            return response()->json(["Success"=> "true","Message" => "Get Category","data"=>$category]);
        } catch (\Exception $e){
            Log::info("Category => getcategoryforledger Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getbuildingsforledger()
    {
        try {
            Log::info("Category => getbuildingsforledger Method Called ");
            $input_arr = $this->request->input();
            $buildings = DB::connection()->table(self::building_tbl)
                        ->where('is_active','=',1)
                        ->whereNull('deleted_on')
                        ->select('building_id','building_name')
                        ->orderBy('building_name','asc')
                        ->get();
            return response()->json(["Success"=> "true","Message" => "Get Building","data"=>$buildings]);
        } catch (\Exception $e){
            Log::info("Category => getbuildingsforledger Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getflatholderforledger()
    {
        try {
            Log::info("Category => getflatholderforledger Method Called ");
            $input_arr = $this->request->input();
            $category = DB::connection()->table(self::flat_holders_tbl)
                        ->where([['is_active','=',1],['building_id','=',$input_arr['currentval']]])
                        ->whereNull('deleted_on')
                        ->select('fholder_id','name','flat_no')
                        ->orderBy('name','asc')
                        ->get();
            return response()->json(["Success"=> "true","Message" => "Get Flat Holders","data"=>$category]);
        } catch (\Exception $e){
            Log::info("Category => getflatholderforledger Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }
}
