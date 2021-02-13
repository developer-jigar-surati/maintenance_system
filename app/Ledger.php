<?php

namespace App;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Common;

class Ledger extends Model
{
    const main_tbl = 'tbl_payment';
    const category_tbl = 'tbl_category';
    const flat_holders_tbl = 'tbl_flat_holders';
    const buildings_tbl = 'tbl_buildings';
    
    public function __construct($request){
        $this->request = $request;
    }

    public function addledgerpayment()
    {
        try {
            Log::info("Ledger => addledgerpayment Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'building' => 'required',
                'payment_type' => 'required',
                'category' => 'required',
                'pay_amount' => 'required',
                'pay_date' => 'required',
                'short_desc' => 'required',
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }

            if($input_arr['payment_type'] == 2){
                $validator = Validator::make($input_arr, [
                    'flat_holder' => 'required',
                ]);
               
                if ($validator->fails()) {
                    return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
                }
            }
            
            $image = '';
            if($files=$this->request->file('pay_document')){
                $name=$files->getClientOriginalName();
                if($input_arr['payment_type'] == "1"){
                    $files->move(public_path().'/uploads/documents/cr/', date('YmdHis').$name);
                    $image = url('/uploads/documents/cr/'. date('YmdHis').$name);
                } else {
                    $files->move(public_path().'/uploads/documents/dr/', date('YmdHis').$name);
                    $image = url('/uploads/documents/dr/'. date('YmdHis').$name);
                }
            }
            
            $insert_arr = [
                "payment_id" => md5($input_arr['category'].date('Y-m-d H:i:s')),
                "pay_type" => $input_arr['payment_type'],
                "category_id" => $input_arr['category'],
                "building_id" => $input_arr['building'],
                "flat_holder_id" => $input_arr['flat_holder'],
                "pay_amount" => $input_arr['pay_amount'],
                "pay_date" => $input_arr['pay_date'],
                "short_desc" => $input_arr['short_desc'],
                "pay_document" => $image,
                "added_on" => date('Y-m-d H:i:s'),
                "added_by" => $this->request->session()->get('z_adminid_pk')
            ];
            
            DB::connection()->table(self::main_tbl)->insert($insert_arr);
            return response()->json(["Success"=> "true","Message" => "Payment Added Successfully","data"=>""]);
            

        } catch (\Exception $e){
            Log::info("Ledger => addledgerpayment Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function getledgerdata()
    {
        try {
            Log::info("Ledger => getledgerdata Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);

            $ledgerdata = DB::connection()->table(self::main_tbl.' as tp')
                        ->leftJoin(self::category_tbl.' as ct','ct.categoryid','tp.category_id')
                        ->leftJoin(self::flat_holders_tbl.' as fh','fh.fholder_id','tp.flat_holder_id')
                        ->leftJoin(self::buildings_tbl.' as bl','bl.building_id','tp.building_id')
                        ->select('tp.payment_id','tp.pay_type','ct.category_name','bl.building_name','fh.flat_no','fh.name','tp.pay_amount','tp.pay_date','tp.short_desc','tp.pay_document')
                        ->whereNull('tp.deleted_on');
            
            // if($this->request->session()->get('building_id') !== null && $this->request->session()->get('building_id') !== ''){
            //     $ledgerdata->where('tp.building_id',$this->request->session()->get('building_id'));
            // } else {
                if($input_arr['buildingid'] != '0' && $input_arr['buildingid'] != null && $input_arr['buildingid'] != ''){
                    $ledgerdata->where('tp.building_id','=',$input_arr['buildingid']);
                }
            // }
            $ledgerdata->orderBy('pay_date','asc');
            $res = $ledgerdata->get();
            return response()->json(["Success"=> "true","Message" => "Get ledger data","data"=>$res]);
        } catch (\Exception $e){
            Log::info("Ledger => getledgerdata Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }
}
