<?php

namespace App;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Common;

class Building extends Model
{
    const main_tbl = 'tbl_buildings';
    public function __construct($request){
        $this->request = $request;
    }

    public function addbuilding()
    {
        try {
            Log::info("Building => addbuilding Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'building_name' => 'required',
                'total_flats' => 'required',
                'maintenance' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['building_name'] = $input_arr['building_name'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                return response()->json(["Success"=> "false","Message" => "Building Already Exist","data"=>""]);
            } else {
                $insert_arr = [
                    "building_id" => md5($input_arr['building_name'].date('Y-m-d H:i:s')),
                    "building_name" => $input_arr['building_name'],
                    "description" => $input_arr['description'],
                    "total_flats" => $input_arr['total_flats'],
                    "maintenance" => $input_arr['maintenance'],
                    "is_active" => $input_arr['is_active'],
                    "added_on" => date('Y-m-d H:i:s'),
                    "added_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->insert($insert_arr);
                return response()->json(["Success"=> "true","Message" => "Building Added Successfully","data"=>""]);
            }

        } catch (\Exception $e){
            Log::info("Building => addbuilding Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getbuildings()
    {
        try {
            Log::info("Building => getbuildings Method Called ");
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
            if($this->request->session()->get('building_id') !== null && $this->request->session()->get('building_id') !== ''){
                $query->where('building_id',$this->request->session()->get('building_id'));
            }
           
            if(isset($searchValue) && trim($searchValue) != '')
            {
                $areRecordsFiltered = true;
                $query->where('building_name', 'RLIKE', $searchValue);
                $query->orwhere('total_flats', 'RLIKE', $searchValue);
            }

            $totalEmployeesquery = DB::connection()->table(self::main_tbl)->whereNull('deleted_on');
            if($this->request->session()->get('building_id') !== null && $this->request->session()->get('building_id') !== ''){
                $totalEmployeesquery->where('building_id',$this->request->session()->get('building_id'));
            }
            $totalEmployees = $totalEmployeesquery->count();
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
                    $tempRow = array();
                    $tempRow['building_name'] = $value->building_name;
                    $tempRow['total_flats'] = $value->total_flats;
                    $tempRow['maintenance'] = $value->maintenance;
                    $tempRow['createddatetime'] = date('d-m-Y H:i:s',strtotime($value->added_on));
                    $tempRow['modifireddatetime'] = ($value->modified_on!=NULL)?date('d-m-Y H:i:s',strtotime($value->modified_on)):"";
                    $tempRow['createdtime'] = date('H:i:s',strtotime($value->added_on));
                    $tempRow['modifiredtime'] = ($value->modified_on!=NULL)?date('H:i:s',strtotime($value->modified_on)):"";
                    $tempRow['is_active'] = ($value->is_active==1)?'Active':'In active';
                    $tempRow['deleted_on'] = 'Delete';
                    $tempRow['building_id'] = $value->building_id;
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
            Log::info("Building => getbuildings Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function changebuildingstatus()
    {
        try {
            Log::info("Building => changebuildingstatus Method Called ");
            $input_arr = $this->request->input();
            
            $validator = Validator::make($input_arr, [
                'is_active' => 'required',
                'building_id' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['building_id'] = $input_arr['building_id'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                $update_arr = [
                    "is_active" => ($input_arr['is_active']==1)?0:1,
                    "modified_on" => date('Y-m-d H:i:s'),
                    "modified_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('building_id',$input_arr['building_id'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Status Update Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Building Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Building => changebuildingstatus Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function deletebuilding()
    {
        try {
            Log::info("Building => deletebuilding Method Called ");
            $input_arr = $this->request->input();
            
            $validator = Validator::make($input_arr, [
                'building_id' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['building_id'] = $input_arr['building_id'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                $update_arr = [
                    "deleted_on" => date('Y-m-d H:i:s'),
                    "deleted_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('building_id',$input_arr['building_id'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Building Delete Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Building Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Building => deletebuilding Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }        
    }

    public function getbuildingbyid()
    {
        try {
            Log::info("Building => getbuildingbyid Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $category = DB::connection()->table(self::main_tbl)
                        ->where('building_id',$input_arr['building_id'])
                        ->whereNull('deleted_on')
                        ->first();
            return response()->json(["Success"=> "true","Message" => "Get Building","data"=>$category]);
        } catch (\Exception $e){
            Log::info("Building => getbuildingbyid Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function updatebuilding()
    {
        try {
            Log::info("Building => updatebuilding Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'building_name' => 'required',
                'building_id' => 'required',
                'total_flats' => 'required',
                'maintenance' => 'required',
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['building_id'] = $input_arr['building_id'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();
            
            if($category){
                $checkname = DB::connection()->table(self::main_tbl)
                            ->where([['building_name','=',$input_arr['building_name']],['building_id','!=',$input_arr['building_id']]])
                            ->whereNull('deleted_on')->count();
                if($checkname > 0){
                    return response()->json(["Success"=> "false","Message" => "Building Already Exist","data"=>""]);
                }
                $update_arr = [
                    "building_name" => $input_arr['building_name'],
                    "description" => $input_arr['description'],
                    "total_flats" => $input_arr['total_flats'],
                    "maintenance" => $input_arr['maintenance'],
                    "is_active" => $input_arr['is_active'],
                    "modified_on" => date('Y-m-d H:i:s'),
                    "modified_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('building_id',$input_arr['building_id'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Building Update Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Building Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Building => updatebuilding Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getbuldingsforflatholder()
    {
        try {
            Log::info("Building => getbuldingsforflatholder Method Called ");
            
            $building = DB::connection()->table(self::main_tbl)
                        ->where('is_active','=',1)
                        ->whereNull('deleted_on')
                        ->select('building_id','building_name')
                        ->orderBy('building_name','asc');
            if($this->request->session()->get('building_id') !== null && $this->request->session()->get('building_id') !== ''){
                $building->where('building_id',$this->request->session()->get('building_id'));
            }
            $buildingres =$building->get();
            return response()->json(["Success"=> "true","Message" => "Get buldings","data"=>$buildingres]);
        } catch (\Exception $e){
            Log::info("Building => getbuldingsforflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }
}
