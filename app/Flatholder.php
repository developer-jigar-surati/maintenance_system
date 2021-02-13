<?php

namespace App;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Common;

class Flatholder extends Model
{
    const main_tbl = 'tbl_flat_holders';
    const building_tbl = 'tbl_buildings';
    const admin_tbl = 'admin_mst';
    public function __construct($request){
        $this->request = $request;
    }

    public function saveflatholder()
    {
        try {
            Log::info("Flatholder => saveflatholder Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'building' => 'required',
                'flat_no' => 'required',
                'name' => 'required',
                'mobile_no' => 'required',
                'email_id' => 'required|email',
                'flat_type' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }

            if($input_arr['flat_type'] == 2){
                $validator1 = Validator::make($input_arr, [
                    'owner_name' => 'required',
                    'owner_mobile_no' => 'required',
                    'owner_email_id' => 'required|email',
                    'owner_address' => 'required'
                ]);
                if ($validator1->fails()) {
                    return response()->json(["Success"=> "false","Message" => "Flat owner details is required!","data"=>$validator1->errors()]);
                } 

                if($input_arr['is_aggrement'] == 1){
                    $validator2 = Validator::make($input_arr, [
                        'start_date' => 'required',
                        'end_date' => 'required',
                    ]);
                    if ($validator2->fails()) {
                        return response()->json(["Success"=> "false","Message" => "Aggrement dates is required!","data"=>$validator2->errors()]);
                    } 
                }
            }

            $conditions_email = [];
            $conditions_email['email_id'] = $input_arr['email_id'];
            $flatholder_email_check = DB::connection()->table(self::main_tbl)->where($conditions_email)->whereNull('deleted_on')->count();
            if($flatholder_email_check > 0){
                return response()->json(["Success"=> "false","Message" => "Email Already Exist","data"=>""]);    
            }
            $conditions = [];
            $conditions['mobile_no'] = $input_arr['mobile_no'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                return response()->json(["Success"=> "false","Message" => "Mobile No Already Exist","data"=>""]);
            } else {
                $insert_arr = [
                    "fholder_id" => md5($input_arr['mobile_no'].date('Y-m-d H:i:s')),
                    "building_id" => $input_arr['building'],
                    "flat_no" => $input_arr['flat_no'],
                    "name" => $input_arr['name'],
                    "mobile_no" => $input_arr['mobile_no'],
                    "email_id" => $input_arr['email_id'],
                    "flat_type" => $input_arr['flat_type'],
                    "owner_name" => $input_arr['owner_name'],
                    "owner_mobile_no" => $input_arr['owner_mobile_no'],
                    "owner_email" => $input_arr['owner_email_id'],
                    "owner_address" => $input_arr['owner_address'],
                    "rent_aggrement" => $input_arr['is_aggrement'],
                    "is_president" => $input_arr['is_president'],
                    "aggrement_start_date" => ($input_arr['start_date'] != "") ? $input_arr['start_date'] : null,
                    "aggrement_end_date" => ($input_arr['end_date'] != "") ? $input_arr['end_date'] : null,
                    "is_active" => $input_arr['is_active'],
                    "added_on" => date('Y-m-d H:i:s'),
                    "added_by" => $this->request->session()->get('z_adminid_pk')
                ];
                
                DB::connection()->table(self::main_tbl)->insert($insert_arr);
                return response()->json(["Success"=> "true","Message" => "Flat Holder Added Successfully","data"=>""]);
            }

        } catch (\Exception $e){
            Log::info("Flatholder => saveflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getflatholders()
    {
        try {
            Log::info("Flatholder => getflatholders Method Called ");
            $input_arr = $this->request->input();
            
            $totalEmployees = 0;
            $filteredEmployees = 0;
            $areRecordsFiltered = false;

            $searchValue = addslashes($input_arr['search']['value']);
            $orderColumn = isset($input_arr['order'][0]['column'])?$input_arr['order'][0]['column']:'';
            $orderBy = isset($input_arr['order'][0]['dir'])?$input_arr['order'][0]['dir']:'';
            $start = $input_arr['start'];
            $length = $input_arr['length'];

            $query = DB::connection()->table(self::main_tbl.' as mt')
                    ->join(self::building_tbl.' as bt','bt.building_id','=','mt.building_id')
                    ->select('mt.*','bt.building_name')
                    ->whereNull('mt.deleted_on'); 
            if($this->request->session()->get('building_id') !== null && $this->request->session()->get('building_id') !== ''){
                $query->where('mt.building_id',$this->request->session()->get('building_id'));
            }            
            if(isset($searchValue) && trim($searchValue) != '')
            {
                $areRecordsFiltered = true;
                $query->where('bt.building_name', 'RLIKE', $searchValue);
                $query->orwhere('mt.flat_no', 'RLIKE', $searchValue);
                $query->orwhere('mt.name', 'RLIKE', $searchValue);
                $query->orwhere('mt.mobile_no', 'RLIKE', $searchValue);
                $query->orwhere('mt.email_id', 'RLIKE', $searchValue);
            }

            $totalEmployeesquery = DB::connection()->table(self::main_tbl.' as mt')
                                ->join(self::building_tbl.' as bt','bt.building_id','=','mt.building_id')
                                ->whereNull('mt.deleted_on');
            if($this->request->session()->get('building_id') !== null && $this->request->session()->get('building_id') !== ''){
                $totalEmployeesquery->where('mt.building_id',$this->request->session()->get('building_id'));
            }
            $totalEmployees = $totalEmployeesquery->count();

            if((isset($orderColumn) && trim($orderColumn) != '') && (isset($orderBy) && trim($orderBy) != '')) {
                //$colindex = (intval($orderColumn) + 1);
                $fieldname = $input_arr['columns'][$orderColumn]['data'];
                $query->orderBy('mt.'.$fieldname, $orderBy);
            } else {
                $query->orderBy('mt.added_on', 'desc');   
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
                    $tempRow['flat_no'] = $value->flat_no;
                    $tempRow['name'] = $value->name;
                    $tempRow['mobile_no'] = $value->mobile_no;
                    $tempRow['email_id'] = $value->email_id;
                    $tempRow['flat_type'] = ($value->flat_type==2)?'Rent':'Owner';
                    $tempRow['createddatetime'] = date('d-m-Y H:i:s',strtotime($value->added_on));
                    $tempRow['modifireddatetime'] = ($value->modified_on!=NULL)?date('d-m-Y H:i:s',strtotime($value->modified_on)):"";
                    $tempRow['createdtime'] = date('H:i:s',strtotime($value->added_on));
                    $tempRow['modifiredtime'] = ($value->modified_on!=NULL)?date('H:i:s',strtotime($value->modified_on)):"";
                    $tempRow['is_active'] = ($value->is_active==1)?'Active':'In active';
                    $tempRow['deleted_on'] = 'Delete';
                    $tempRow['is_credentials_send'] = $value->is_credentials_send;
                    $tempRow['fholder_id'] = $value->fholder_id;
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
            Log::info("Flatholder => getflatholders Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function changeflatholderstatus()
    {
        try {
            Log::info("Flatholder => changeflatholderstatus Method Called ");
            $input_arr = $this->request->input();
            
            $validator = Validator::make($input_arr, [
                'is_active' => 'required',
                'fholder_id' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['fholder_id'] = $input_arr['fholder_id'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                $update_arr = [
                    "is_active" => ($input_arr['is_active']==1)?0:1,
                    "modified_on" => date('Y-m-d H:i:s'),
                    "modified_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('fholder_id',$input_arr['fholder_id'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Status Update Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Flat Holder Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Flatholder => changeflatholderstatus Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
    }

    public function deleteflatholder()
    {
        try {
            Log::info("Flatholder => deleteflatholder Method Called ");
            $input_arr = $this->request->input();
            
            $validator = Validator::make($input_arr, [
                'fholder_id' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }
            
            $conditions = [];
            $conditions['fholder_id'] = $input_arr['fholder_id'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($category){
                $update_arr = [
                    "deleted_on" => date('Y-m-d H:i:s'),
                    "deleted_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('fholder_id',$input_arr['fholder_id'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Flatholder Delete Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Flatholder Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Flatholder => deleteflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }        
    }

    public function getflatholderbyid()
    {
        try {
            Log::info("Flatholder => getflatholderbyid Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $category = DB::connection()->table(self::main_tbl)
                        ->where('fholder_id',$input_arr['fholder_id'])
                        ->whereNull('deleted_on')
                        ->first();
            return response()->json(["Success"=> "true","Message" => "Get Flatholder","data"=>$category]);
        } catch (\Exception $e){
            Log::info("Flatholder => getflatholderbyid Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function updateflatholder()
    {
        try {
            Log::info("Flatholder => updateflatholder Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'building' => 'required',
                'flat_no' => 'required',
                'name' => 'required',
                'mobile_no' => 'required',
                'email_id' => 'required|email',
                'flat_type' => 'required',
                'fholder_id' => 'required',
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }

            if($input_arr['flat_type'] == 2){
                $validator1 = Validator::make($input_arr, [
                    'owner_name' => 'required',
                    'owner_mobile_no' => 'required',
                    'owner_email_id' => 'required|email',
                    'owner_address' => 'required'
                ]);
                if ($validator1->fails()) {
                    return response()->json(["Success"=> "false","Message" => "Flat owner details is required!","data"=>$validator1->errors()]);
                } 

                if($input_arr['is_aggrement'] == 1){
                    $validator2 = Validator::make($input_arr, [
                        'start_date' => 'required',
                        'end_date' => 'required',
                    ]);
                    if ($validator2->fails()) {
                        return response()->json(["Success"=> "false","Message" => "Aggrement dates is required!","data"=>$validator2->errors()]);
                    } 
                }
            }
            
            $conditions = [];
            $conditions['fholder_id'] = $input_arr['fholder_id'];
            $category = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();
            
            if($category){
                $flatholder_email_check = DB::connection()->table(self::main_tbl)
                                        ->where([['email_id','=',$input_arr['email_id']],['fholder_id','!=',$input_arr['fholder_id']]])
                                        ->whereNull('deleted_on')->count();
                if($flatholder_email_check > 0){
                    return response()->json(["Success"=> "false","Message" => "Email Already Exist","data"=>""]);    
                }

                $checkname = DB::connection()->table(self::main_tbl)
                            ->where([['mobile_no','=',$input_arr['mobile_no']],['fholder_id','!=',$input_arr['fholder_id']]])
                            ->whereNull('deleted_on')->count();
                if($checkname > 0){
                    return response()->json(["Success"=> "false","Message" => "Mobile No Already Exist","data"=>""]);
                }
                $update_arr = [
                    "building_id" => $input_arr['building'],
                    "flat_no" => $input_arr['flat_no'],
                    "name" => $input_arr['name'],
                    "mobile_no" => $input_arr['mobile_no'],
                    "email_id" => $input_arr['email_id'],
                    "flat_type" => $input_arr['flat_type'],
                    "owner_name" => $input_arr['owner_name'],
                    "owner_mobile_no" => $input_arr['owner_mobile_no'],
                    "owner_email" => $input_arr['owner_email_id'],
                    "owner_address" => $input_arr['owner_address'],
                    "rent_aggrement" => $input_arr['is_aggrement'],
                    "is_president" => $input_arr['is_president'],
                    "aggrement_start_date" => ($input_arr['start_date'] != "") ? $input_arr['start_date'] : null,
                    "aggrement_end_date" => ($input_arr['end_date'] != "") ? $input_arr['end_date'] : null,
                    "is_active" => $input_arr['is_active'],
                    "modified_on" => date('Y-m-d H:i:s'),
                    "modified_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('fholder_id',$input_arr['fholder_id'])->update($update_arr);
                return response()->json(["Success"=> "true","Message" => "Flat Holder Update Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Flat Holder Not Found","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Flatholder => updateflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getbuldingsforflatholder()
    {
        try {
            Log::info("Flatholder => getbuldingsforflatholder Method Called ");
            
            $category = DB::connection()->table(self::main_tbl)
                        ->where('is_active','=',1)
                        ->whereNull('deleted_on')
                        ->select('building_id','building_name')
                        ->orderBy('building_name','asc')
                        ->get();
            return response()->json(["Success"=> "true","Message" => "Get buildings","data"=>$category]);
        } catch (\Exception $e){
            Log::info("Flatholder => getbuldingsforflatholder Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function saveflatholderasadmin()
    {
        try {
            Log::info("Flatholder => saveflatholderasadmin Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            if($input_arr['is_credentials_send'] == "0"){
                $conditions = [];
                $conditions['fholder_id'] = $input_arr['fholder_id'];
                $flatholder_details = DB::connection()->table(self::main_tbl)->where($conditions)->first();

                $update_arr = [
                    "is_credentials_send" => 1,
                ];
                DB::connection()->table(self::main_tbl)->where('fholder_id',$input_arr['fholder_id'])->update($update_arr);
                
                $pass = $input_arr['password'];//$this->randomPassword();

                $insert_arr = [
                    "admin_id" => md5($flatholder_details->email_id . date('Y-m-d H:i:s')),
                    "name" => $flatholder_details->name,
                    "email_id" => $flatholder_details->email_id,
                    "mobile_no" => $flatholder_details->mobile_no,
                    "building_id" => $flatholder_details->building_id,
                    "user_role"=> ($flatholder_details->is_president == 1) ?'2':'3',
                    "password" => md5($pass),
                    "added_on" => date('Y-m-d H:i:s'),
                    "added_by" => $this->request->session()->get('z_adminid_pk')
                ];
                $admin = DB::connection()->table(self::admin_tbl)->insert($insert_arr);

                if($admin){
                    $fromemail = str_replace('_',' ',env('MAIL_FROM_ADDRESS'));
                    $data = array(  'username' => $flatholder_details->name,
                                    'product_name' => env('APP_NAME'),
                                    'useremail' => $flatholder_details->email_id,
                                    'password' => $pass,
                                    'login_username' => $this->request->session()->get('name')
                                );
                    $mail_data = $data;
                    \Mail::send('mailtemplete.welcome', $data, function($message) use ($mail_data,$fromemail) {
                        $message->to($mail_data['useremail'], $mail_data['username'])->subject
                            ('Welcome to '.$mail_data['product_name']);
                        $message->from($fromemail,$mail_data['product_name']);
                    });
                    
                    Log::info("Flatholder => Credentials mail send successfully.");
                    return response()->json(["Success"=> "true","Message" => "Credentials send successfully.","data"=>""]);
                } else {
                    Log::info("Flatholder => Admin not save successfully");
                    return response()->json(["Success"=> "false","Message" => "Admin not save successfully!","data"=>""]);
                }
            } else {
                return response()->json(["Success"=> "true","Message" => "Credentials Already Send!","data"=>""]);
            }
            
            
        } catch (\Exception $e){
            Log::info("Flatholder => saveflatholderasadmin Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
}
